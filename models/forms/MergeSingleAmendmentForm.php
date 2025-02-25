<?php

namespace app\models\forms;

use app\models\db\{Amendment, ISupporter, Motion, MotionSection, MotionSupporter};
use app\models\events\MotionEvent;
use app\models\exceptions\DB;
use app\models\sectionTypes\ISectionType;
use yii\base\Model;

class MergeSingleAmendmentForm extends Model
{
    public Motion $oldMotion;
    public ?Motion $newMotion = null;
    public string $newTitlePrefix;
    public string $newVersion;
    public Amendment $mergeAmendment;
    public int $mergeAmendStatus;
    public array $otherAmendStatuses;
    public array $otherAmendOverrides;
    public array $paragraphs;

    public function __construct(
        Amendment $amendment,
        string $newTitlePrefix,
        string $newVersion,
        int $newStatus,
        array $paragraphs,
        array $otherAmendOverrides,
        array $otherAmendStatuses
    )
    {
        parent::__construct();
        $this->newTitlePrefix = $newTitlePrefix;
        $this->newVersion = $newVersion;
        $this->oldMotion = $amendment->getMyMotion();
        $this->mergeAmendment = $amendment;
        $this->mergeAmendStatus = $newStatus;
        $this->paragraphs = $paragraphs;
        $this->otherAmendStatuses = $otherAmendStatuses;
        $this->otherAmendOverrides = $otherAmendOverrides;
    }

    private function getNewHtmlParas(): array
    {
        $newSections = [];
        foreach ($this->mergeAmendment->getActiveSections(ISectionType::TYPE_TEXT_SIMPLE) as $section) {
            $amendmentParas = $section->getParagraphsRelativeToOriginal();
            if (isset($this->paragraphs[$section->sectionId])) {
                foreach ($this->paragraphs[$section->sectionId] as $paraNo => $para) {
                    $amendmentParas[$paraNo] = $para;
                }
            }
            $newSections[$section->sectionId] = implode("\n", $amendmentParas);
        }
        return $newSections;
    }

    public function checkConsistency(): bool
    {
        $newSections = $this->getNewHtmlParas();
        $overrides   = $this->otherAmendOverrides;

        foreach ($this->oldMotion->getAmendmentsRelevantForCollisionDetection([$this->mergeAmendment]) as $amendment) {
            if (!isset($this->otherAmendStatuses[$amendment->id])) {
                continue;
            }
            if (in_array($this->otherAmendStatuses[$amendment->id], $amendment->getMyConsultation()->getStatuses()->getStatusesMarkAsDoneOnRewriting())) {
                continue;
            }
            foreach ($amendment->getActiveSections(ISectionType::TYPE_TEXT_SIMPLE) as $section) {
                if (isset($overrides[$amendment->id]) && isset($overrides[$amendment->id][$section->sectionId])) {
                    $sectionOverrides = $overrides[$amendment->id][$section->sectionId];
                } else {
                    $sectionOverrides = [];
                }
                if (!$section->canRewrite($newSections[$section->sectionId], $sectionOverrides)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @throws DB
     */
    private function createNewMotion(?string $previousSlug): void
    {
        $this->newMotion = new Motion();
        $this->newMotion->consultationId = $this->oldMotion->consultationId;
        $this->newMotion->motionTypeId = $this->oldMotion->motionTypeId;
        $this->newMotion->parentMotionId = $this->oldMotion->id;
        $this->newMotion->agendaItemId = $this->oldMotion->agendaItemId;
        $this->newMotion->title = $this->oldMotion->title;
        $this->newMotion->titlePrefix = $this->newTitlePrefix;
        $this->newMotion->version = $this->newVersion;
        $this->newMotion->dateCreation = date('Y-m-d H:i:s');
        $this->newMotion->datePublication = date('Y-m-d H:i:s');
        $this->newMotion->dateContentModification = date('Y-m-d H:i:s');
        $this->newMotion->dateResolution = $this->oldMotion->dateResolution;
        $this->newMotion->statusString = $this->oldMotion->statusString;
        $this->newMotion->status = $this->oldMotion->status;
        $this->newMotion->noteInternal = $this->oldMotion->noteInternal;
        $this->newMotion->textFixed = $this->oldMotion->textFixed;
        $this->newMotion->slug = $previousSlug;
        $this->newMotion->cache = '';
        if (!$this->newMotion->save()) {
            throw new DB($this->newMotion->getErrors());
        }

        foreach ($this->oldMotion->motionSupporters as $supporter) {
            $newSupporter = new MotionSupporter();
            $newSupporter->setAttributes($supporter->getAttributes(), false);
            $newSupporter->dateCreation = date('Y-m-d H:i:s');
            $newSupporter->id = null;
            $newSupporter->motionId = $this->newMotion->id;
            if ($supporter->isNonPublic()) {
                $newSupporter->setExtraDataEntry(ISupporter::EXTRA_DATA_FIELD_NON_PUBLIC, true);
            }
            if (!$newSupporter->save()) {
                throw new DB($this->newMotion->getErrors());
            }
        }
    }

    /**
     * @throws DB
     */
    private function createNewMotionSections(): void
    {
        $newSections = $this->getNewHtmlParas();

        foreach ($this->oldMotion->sections as $section) {
            $newSection = new MotionSection();
            $newSection->setAttributes($section->getAttributes(), false);
            $newSection->motionId = $this->newMotion->id;
            $newSection->cache    = '';
            if ($section->getSettings()->type === ISectionType::TYPE_TEXT_SIMPLE) {
                if (isset($newSections[$section->sectionId])) {
                    $newSection->setData($newSections[$section->sectionId]);
                    $newSection->dataRaw = '';
                }
            }
            if (!$newSection->save()) {
                throw new DB($newSection->getErrors());
            }
        }
    }

    /**
     * @throws DB
     */
    private function rewriteOtherAmendments(): void
    {
        $newSections = $this->getNewHtmlParas();
        $overrides   = $this->otherAmendOverrides;

        foreach ($this->oldMotion->getAmendmentsRelevantForCollisionDetection([$this->mergeAmendment]) as $amendment) {
            if (!isset($this->otherAmendStatuses[$amendment->id])) {
                continue;
            }
            if (in_array($this->otherAmendStatuses[$amendment->id], $amendment->getMyConsultation()->getStatuses()->getStatusesMarkAsDoneOnRewriting())) {
                continue;
            }
            foreach ($amendment->getActiveSections(ISectionType::TYPE_TEXT_SIMPLE) as $section) {
                if (isset($overrides[$amendment->id]) && isset($overrides[$amendment->id][$section->sectionId])) {
                    $sectionOverrides = $overrides[$amendment->id][$section->sectionId];
                } else {
                    $sectionOverrides = [];
                }
                $section->performRewrite($newSections[$section->sectionId], $sectionOverrides);
                $section->dataRaw = '';
                $section->cache   = '';
                if (!$section->save()) {
                    throw new DB($section->getErrors());
                }
            }
            $amendment->motionId = $this->newMotion->id;
            $amendment->cache    = '';
            $amendment->status   = $this->otherAmendStatuses[$amendment->id];
            if (!$amendment->save()) {
                throw new DB($amendment->getErrors());
            }
        }
    }

    /**
     * @throws DB
     */
    private function setDoneAmendmentsStatuses(): void
    {
        foreach ($this->oldMotion->getAmendmentsRelevantForCollisionDetection([$this->mergeAmendment]) as $amendment) {
            if (!isset($this->otherAmendStatuses[$amendment->id])) {
                continue;
            }
            if (!in_array($this->otherAmendStatuses[$amendment->id], $amendment->getMyConsultation()->getStatuses()->getStatusesMarkAsDoneOnRewriting())) {
                continue;
            }
            $amendment->status = $this->otherAmendStatuses[$amendment->id];
            if (!$amendment->save()) {
                throw new DB($amendment->getErrors());
            }
        }
    }

    /**
     * @return Motion
     * @throws DB
     */
    public function performRewrite(): Motion
    {
        $previousSlug          = $this->oldMotion->slug;
        $this->oldMotion->slug = null;
        $this->oldMotion->save();

        $this->createNewMotion($previousSlug);
        $this->createNewMotionSections();
        $this->rewriteOtherAmendments();
        $this->setDoneAmendmentsStatuses();

        $this->mergeAmendment->status = $this->mergeAmendStatus;
        $this->mergeAmendment->save();

        $this->oldMotion->status = Motion::STATUS_MODIFIED;
        $this->oldMotion->save();

        $consultation = $this->oldMotion->getMyConsultation();
        $conSettings = $consultation->getSettings();
        if ($conSettings->forceMotion === $this->oldMotion->id) {
            $conSettings->forceMotion = $this->newMotion->id;
            $consultation->setSettings($conSettings);
            $consultation->save();
        }

        $this->newMotion->trigger(Motion::EVENT_MERGED, new MotionEvent($this->newMotion));

        return $this->newMotion;
    }
}
