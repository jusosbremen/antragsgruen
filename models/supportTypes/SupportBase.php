<?php

namespace app\models\supportTypes;

use app\components\RequestContext;
use app\components\Tools;
use app\controllers\Base;
use app\models\db\{Amendment, AmendmentSupporter, ConsultationMotionType, ConsultationUserGroup, ISupporter, Motion, MotionSupporter, User};
use app\models\exceptions\{FormError, Internal};
use app\models\forms\{AmendmentEditForm, MotionEditForm};
use app\models\settings\InitiatorForm;
use yii\web\View;

abstract class SupportBase
{
    // Also defined in Typescript
    const ONLY_INITIATOR        = 0;
    const GIVEN_BY_INITIATOR    = 1;
    const COLLECTING_SUPPORTERS = 2;
    const NO_INITIATOR          = 3;

    const LIKEDISLIKE_LIKE    = 1;
    const LIKEDISLIKE_DISLIKE = 2;
    const LIKEDISLIKE_SUPPORT = 4;

    /** @var bool */
    protected $adminMode = false;

    /** @var InitiatorForm */
    protected $settingsObject;

    /** @var ConsultationMotionType $motionType */
    protected $motionType;

    /**
     * @return SupportBase[]|string[]
     */
    public static function getImplementations(): array
    {
        return [
            static::ONLY_INITIATOR        => OnlyInitiator::class,
            static::GIVEN_BY_INITIATOR    => GivenByInitiator::class,
            static::COLLECTING_SUPPORTERS => CollectBeforePublish::class,
            static::NO_INITIATOR          => NoInitiator::class,
        ];
    }

    /**
     * @throws Internal
     */
    public static function getImplementation(InitiatorForm $settings, ConsultationMotionType $motionType): SupportBase
    {
        switch ($settings->type) {
            case static::ONLY_INITIATOR:
                return new OnlyInitiator($motionType, $settings);
            case static::GIVEN_BY_INITIATOR:
                return new GivenByInitiator($motionType, $settings);
            case static::COLLECTING_SUPPORTERS:
                return new CollectBeforePublish($motionType, $settings);
            case static::NO_INITIATOR:
                return new NoInitiator($motionType, $settings);
            default:
                throw new Internal('Supporter form type not found');
        }
    }

    /**
     * @return string[]
     */
    public static function getGenderSelection(): array
    {
        return [
            'female'  => \Yii::t('structure', 'gender_female'),
            'male'    => \Yii::t('structure', 'gender_male'),
            'diverse' => \Yii::t('structure', 'gender_diverse'),
            'na'      => \Yii::t('structure', 'gender_na'),
        ];
    }

    public function __construct(ConsultationMotionType $motionType, InitiatorForm $settings)
    {
        $this->motionType = $motionType;
        $this->settingsObject = $settings;
        $this->fixSettings();
    }

    public function getSettingsObj(): InitiatorForm
    {
        return $this->settingsObject;
    }

    public function setSettingsObj(InitiatorForm $settings): void
    {
        $this->settingsObject = $settings;
        $this->fixSettings();
    }

    protected function fixSettings()
    {
    }

    public static function getTitle(): string
    {
        return '';
    }

    public function setAdminMode(bool $set): void
    {
        $this->adminMode = $set;
    }

    public static function collectSupportersBeforePublication(): bool
    {
        return false;
    }

    public function isValidName(string $name): bool
    {
        return (trim($name) !== '');
    }

    public static function hasInitiatorGivenSupporters(): bool
    {
        return false;
    }

    public function hasFullTextSupporterField(): bool
    {
        return false;
    }

    /**
     * @return ISupporter[]
     */
    protected function parseSupporters(ISupporter $model): array
    {
        $ret  = [];
        $post = \Yii::$app->request->post();
        if (isset($post['supporters']) && is_array($post['supporters']['name'])) {
            foreach ($post['supporters']['name'] as $i => $name) {
                if (!$this->isValidName($name)) {
                    continue;
                }
                $sup             = clone $model;
                $sup->name       = trim($name);
                $sup->role       = ISupporter::ROLE_SUPPORTER;
                $sup->userId     = null;
                $sup->personType = ISupporter::PERSON_NATURAL;
                $sup->position   = $i;
                if (isset($post['supporters']['organization']) && isset($post['supporters']['organization'][$i])) {
                    $sup->organization = trim($post['supporters']['organization'][$i]);
                }
                $ret[] = $sup;
            }
        }
        return $ret;
    }


    /**
     * @throws FormError
     */
    public function validateMotion(): void
    {
        $post = \Yii::$app->request->post();
        if (!isset($post['Initiator'])) {
            throw new FormError('No Initiator data given');
        }

        $initiator = $post['Initiator'];
        $settings  = $this->getSettingsObj();

        $errors = [];

        if (!isset($initiator['primaryName']) || !$this->isValidName($initiator['primaryName'])) {
            $errors[] = \Yii::t('motion', 'err_invalid_name');
        }

        $emailSet   = (isset($initiator['contactEmail']) && trim($initiator['contactEmail']) !== '');
        $checkEmail = ($settings->contactEmail === InitiatorForm::CONTACT_REQUIRED || $emailSet);
        if ($checkEmail && !filter_var(trim($initiator['contactEmail']), FILTER_VALIDATE_EMAIL)) {
            $errors[] = \Yii::t('motion', 'err_invalid_email');
        }

        $phoneSet   = (isset($initiator['contactPhone']) && trim($initiator['contactPhone']) !== '');
        $checkPhone = ($settings->contactPhone === InitiatorForm::CONTACT_REQUIRED || $phoneSet);
        if ($checkPhone && empty($initiator['contactPhone'])) {
            $errors[] = \Yii::t('motion', 'err_invalid_phone');
        }

        $personType = IntVal($initiator['personType']);
        if ($personType === ISupporter::PERSON_NATURAL && !$settings->initiatorCanBePerson) {
            $errors[] = 'Invalid person type.';
        }
        if ($personType === ISupporter::PERSON_ORGANIZATION && !$settings->initiatorCanBeOrganization) {
            $errors[] = 'Invalid person type.';
        }
        if ($personType === ISupporter::PERSON_ORGANIZATION &&
            $settings->hasResolutionDate === InitiatorForm::CONTACT_REQUIRED &&
            empty($initiator['resolutionDate'])) {
            $errors[] = 'No resolution date entered.';
        }
        if ($personType === ISupporter::PERSON_NATURAL) {
            $validGenderValues = array_keys(static::getGenderSelection());
            if ($settings->contactGender === InitiatorForm::CONTACT_REQUIRED) {
                if (!isset($initiator['gender']) || !in_array($initiator['gender'], $validGenderValues)) {
                    $errors[] = 'Please enter a valid value in the field Gender';
                }
            }
            if ($settings->contactGender === InitiatorForm::CONTACT_OPTIONAL) {
                $validGenderValues[] = '';
                if (isset($initiator['gender']) && !in_array($initiator['gender'], $validGenderValues)) {
                    $errors[] = 'Please enter a valid value in the field Gender';
                }
            }
        }

        if ($this->hasInitiatorGivenSupporters()) {
            $supporters = $this->parseSupporters(new MotionSupporter());
            $num        = count($supporters);
            if ($personType !== ISupporter::PERSON_ORGANIZATION) {
                if ($num < $settings->minSupporters) {
                    $errors[] = 'Not enough supporters.';
                }
                if (!$settings->allowMoreSupporters && $num > $settings->minSupporters) {
                    $errors[] = 'Too many supporters.';
                }
            }
        }

        if (count($errors) > 0) {
            throw new FormError($errors);
        }
    }

    /**
     * @throws FormError
     */
    public function validateAmendment(): void
    {
        $this->validateMotion();
    }

    /**
     * @throws \Throwable
     */
    public function submitMotion(Motion $motion): void
    {
        $affectedRoles = [MotionSupporter::ROLE_INITIATOR];
        if ($this->hasInitiatorGivenSupporters() && !$this->adminMode) {
            $affectedRoles[] = MotionSupporter::ROLE_SUPPORTER;
        }

        foreach ($motion->motionSupporters as $supp) {
            if (in_array($supp->role, $affectedRoles)) {
                $supp->delete();
            }
        }

        $supporters = $this->getMotionSupporters($motion);
        foreach ($supporters as $sup) {
            if (in_array($sup->role, $affectedRoles)) {
                $sup->motionId = $motion->id;
                $sup->save();
            }
        }
    }


    /**
     * @throws \Throwable
     */
    public function submitAmendment(Amendment $amendment): void
    {
        $affectedRoles = [MotionSupporter::ROLE_INITIATOR];
        if ($this->hasInitiatorGivenSupporters() && !$this->adminMode) {
            $affectedRoles[] = MotionSupporter::ROLE_SUPPORTER;
        }

        foreach ($amendment->amendmentSupporters as $supp) {
            if (in_array($supp->role, $affectedRoles)) {
                $supp->delete();
            }
        }

        $supportersAndInitiators = $this->getAmendmentSupporters($amendment);
        $initiators = [];
        foreach ($supportersAndInitiators as $sup) {
            if (in_array($sup->role, $affectedRoles)) {
                $sup->amendmentId = $amendment->id;
                $sup->save();
            }
            if ($sup->role === ISupporter::ROLE_INITIATOR) {
                $initiators[] = $sup;
            }
        }
        $amendment->refresh();

        $initiatorsFormattedPre = $amendment->getInitiatorsStr();
        $initiatorsFormattedPost = $amendment->getInitiatorsStrFromArray($initiators);
        if ($initiatorsFormattedPre !== $initiatorsFormattedPost) {
            $amendment->getMyMotion()->flushViewCache();
        }
    }


    /**
     * @throws \Exception
     */
    public function getMotionForm(ConsultationMotionType $motionType, MotionEditForm $editForm, Base $controller): string
    {
        $view           = new View();
        $initiator      = null;
        $moreInitiators = [];
        $supporters     = [];
        foreach ($editForm->supporters as $supporter) {
            if ($supporter->role == MotionSupporter::ROLE_INITIATOR) {
                if ($supporter->position == 0) {
                    $initiator = $supporter;
                } else {
                    $moreInitiators[] = $supporter;
                }
            }
            if ($supporter->role == MotionSupporter::ROLE_SUPPORTER) {
                $supporters[] = $supporter;
            }
        }
        if (!$initiator) {
            $initiator               = new MotionSupporter();
            $initiator->dateCreation = date('Y-m-d H:i:s');
            $initiator->role         = MotionSupporter::ROLE_INITIATOR;
        }
        $othersPrivilege = User::havePrivilege($motionType->getConsultation(), ConsultationUserGroup::PRIVILEGE_CREATE_MOTIONS_FOR_OTHERS);
        $isForOther      = false;
        if ($othersPrivilege) {
            $isForOther = (!User::getCurrentUser() || !$initiator || User::getCurrentUser()->id != $initiator->userId);
        }
        return $view->render(
            '@app/views/motion/_create_initiator',
            [
                'initiator'         => $initiator,
                'moreInitiators'    => $moreInitiators,
                'supporters'        => $supporters,
                'allowOther'        => $othersPrivilege,
                'isForOther'        => $isForOther,
                'settings'          => $this->getSettingsObj(),
                'hasSupporters'     => $this->hasInitiatorGivenSupporters(),
                'supporterFulltext' => $this->hasFullTextSupporterField(),
                'adminMode'         => $this->adminMode,
            ],
            $controller
        );
    }

    /**
     * @throws \Exception
     */
    public function getAmendmentForm(ConsultationMotionType $motionType, AmendmentEditForm $editForm, Base $controller): string
    {
        $view           = new View();
        $initiator      = null;
        $supporters     = [];
        $moreInitiators = [];
        foreach ($editForm->supporters as $supporter) {
            if ($supporter->role == AmendmentSupporter::ROLE_INITIATOR) {
                if ($supporter->position == 0) {
                    $initiator = $supporter;
                } else {
                    $moreInitiators[] = $supporter;
                }
            }
            if ($supporter->role == AmendmentSupporter::ROLE_SUPPORTER) {
                $supporters[] = $supporter;
            }
        }
        $screeningPrivilege = User::havePrivilege($motionType->getConsultation(), ConsultationUserGroup::PRIVILEGE_SCREENING);
        $isForOther         = false;
        if ($screeningPrivilege) {
            $isForOther = (!User::getCurrentUser() || !$initiator || User::getCurrentUser()->id != $initiator->userId);
        }
        return $view->render(
            '@app/views/motion/_create_initiator',
            [
                'initiator'         => $initiator,
                'moreInitiators'    => $moreInitiators,
                'supporters'        => $supporters,
                'allowOther'        => $screeningPrivilege,
                'isForOther'        => $isForOther,
                'settings'          => $this->getSettingsObj(),
                'hasSupporters'     => $this->hasInitiatorGivenSupporters(),
                'supporterFulltext' => $this->hasFullTextSupporterField(),
                'adminMode'         => $this->adminMode,
            ],
            $controller
        );
    }

    /**
     * @return MotionSupporter[]
     */
    public function getMotionSupporters(Motion $motion): array
    {
        /** @var MotionSupporter[] $return */
        $return = [];

        $post            = \Yii::$app->request->post();
        $othersPrivilege = User::havePrivilege($this->motionType->getConsultation(), ConsultationUserGroup::PRIVILEGE_CREATE_MOTIONS_FOR_OTHERS);
        $otherInitiator  = (isset($post['otherInitiator']) && $othersPrivilege);

        if (RequestContext::getUser()->isGuest) {
            $init               = new MotionSupporter();
            $init->dateCreation = date('Y-m-d H:i:s');
            $init->userId       = null;
            $user               = null;
        } else {
            if ($otherInitiator) {
                $user   = null;
                $userId = null;
                foreach ($motion->motionSupporters as $supporter) {
                    if ($supporter->role === MotionSupporter::ROLE_INITIATOR && $supporter->userId > 0) {
                        $user   = $supporter->user;
                        $userId = $supporter->userId;
                    }
                }
            } else {
                $user   = User::getCurrentUser();
                $userId = $user->id;
            }

            $init = MotionSupporter::findOne(
                [
                    'motionId' => $motion->id,
                    'role'     => MotionSupporter::ROLE_INITIATOR,
                    'userId'   => $userId,
                ]
            );
            if (!$init) {
                $init               = new MotionSupporter();
                $init->dateCreation = date('Y-m-d H:i:s');
                $init->userId       = $userId;
                if ($otherInitiator) {
                    $init->setExtraDataEntry(ISupporter::EXTRA_DATA_FIELD_CREATED_BY_ADMIN, true);
                }
            }
        }

        $posCount = 0;

        $init->setAttributes($post['Initiator']);
        $init->motionId = $motion->id;
        $init->role     = MotionSupporter::ROLE_INITIATOR;
        $init->position = $posCount++;
        if ($init->personType === ISupporter::PERSON_NATURAL) {
            if ($user && $user->fixedData && !$otherInitiator) {
                $init->name         = trim($user->name);
                $init->organization = $user->organization;
            } else {
                $init->name = trim($post['Initiator']['primaryName']);
                if (isset($post['Initiator']['organization'])) {
                    $init->organization = $post['Initiator']['organization'];
                } else {
                    $init->organization = '';
                }
            }
            $init->contactName = $post['Initiator']['contactName'] ?? '';
        } else {
            $init->organization = $post['Initiator']['primaryName'];
            $init->contactName  = $post['Initiator']['contactName'];
        }


        $init->resolutionDate = Tools::dateBootstrapdate2sql($init->resolutionDate);
        $return[]             = $init;

        if (isset($post['moreInitiators']) && isset($post['moreInitiators']['name'])) {
            foreach ($post['moreInitiators']['name'] as $i => $name) {
                $init               = new MotionSupporter();
                $init->dateCreation = date('Y-m-d H:i:s');
                $init->motionId     = $motion->id;
                $init->role         = MotionSupporter::ROLE_INITIATOR;
                $init->position     = $posCount++;
                $init->personType   = MotionSupporter::PERSON_NATURAL;
                $init->name         = $name;
                if (isset($post['moreInitiators']['organization'])) {
                    $init->organization = $post['moreInitiators']['organization'][$i];
                }
                $return[] = $init;
            }
        }

        if ($this->hasInitiatorGivenSupporters()) {
            $supporters = $this->parseSupporters(new MotionSupporter());
            foreach ($supporters as $sup) {
                /** @var MotionSupporter $sup */
                $sup->motionId     = $motion->id;
                $sup->dateCreation = date('Y-m-d H:i:s');
                $return[]          = $sup;
            }
        }

        return $return;
    }

    /**
     * @return AmendmentSupporter[]
     */
    public function getAmendmentSupporters(Amendment $amendment): array
    {
        /** @var AmendmentSupporter[] $return */
        $return = [];
        $post   = \Yii::$app->request->post();

        $othersPrivilege = User::havePrivilege($this->motionType->getConsultation(), ConsultationUserGroup::PRIVILEGE_CREATE_MOTIONS_FOR_OTHERS);
        $otherInitiator  = (isset($post['otherInitiator']) && $othersPrivilege);

        if (RequestContext::getUser()->isGuest) {
            $init               = new AmendmentSupporter();
            $init->dateCreation = date('Y-m-d H:i:s');
            $init->userId       = null;
            $user               = null;
        } else {
            if ($otherInitiator) {
                $userId = null;
                $user   = null;
                foreach ($amendment->amendmentSupporters as $supporter) {
                    if ($supporter->role == AmendmentSupporter::ROLE_INITIATOR && $supporter->userId > 0) {
                        $userId = $supporter->userId;
                        $user   = $supporter->user;
                    }
                }
            } else {
                $user   = User::getCurrentUser();
                $userId = $user->id;
            }

            $init = AmendmentSupporter::findOne(
                [
                    'amendmentId' => $amendment->id,
                    'role'        => AmendmentSupporter::ROLE_INITIATOR,
                    'userId'      => $userId,
                ]
            );
            if (!$init) {
                $init               = new AmendmentSupporter();
                $init->dateCreation = date('Y-m-d H:i:s');
                $init->userId       = $userId;
                if ($otherInitiator) {
                    $init->setExtraDataEntry(ISupporter::EXTRA_DATA_FIELD_CREATED_BY_ADMIN, true);
                }
            }
        }

        $posCount = 0;

        $init->setAttributes($post['Initiator']);
        $init->amendmentId = $amendment->id;
        $init->role        = AmendmentSupporter::ROLE_INITIATOR;
        $init->position    = $posCount++;
        if ($init->personType == ISupporter::PERSON_NATURAL) {
            if ($user && $user->fixedData && !$otherInitiator) {
                $init->name         = trim($user->name);
                $init->organization = $user->organization;
            } else {
                $init->name = trim($post['Initiator']['primaryName']);
                if (isset($post['Initiator']['organization'])) {
                    $init->organization = $post['Initiator']['organization'];
                } else {
                    $init->organization = '';
                }
            }
            $init->contactName = $post['Initiator']['contactName'] ?? '';
        } else {
            $init->organization = $post['Initiator']['primaryName'];
            $init->contactName  = $post['Initiator']['contactName'];
        }

        $init->resolutionDate = Tools::dateBootstrapdate2sql($init->resolutionDate);
        $return[]             = $init;

        if (isset($post['moreInitiators']) && isset($post['moreInitiators']['name'])) {
            foreach ($post['moreInitiators']['name'] as $i => $name) {
                $init               = new AmendmentSupporter();
                $init->amendmentId  = $amendment->id;
                $init->role         = AmendmentSupporter::ROLE_INITIATOR;
                $init->position     = $posCount++;
                $init->personType   = MotionSupporter::PERSON_NATURAL;
                $init->name         = $name;
                $init->dateCreation = date('Y-m-d H:i:s');
                if (isset($post['moreInitiators']['organization'])) {
                    $init->organization = $post['moreInitiators']['organization'][$i];
                }
                $return[] = $init;
            }
        }

        if ($this->hasInitiatorGivenSupporters()) {
            $supporters = $this->parseSupporters(new AmendmentSupporter());
            foreach ($supporters as $sup) {
                /** @var AmendmentSupporter $sup */
                $sup->amendmentId  = $amendment->id;
                $sup->dateCreation = date('Y-m-d H:i:s');
                $return[]          = $sup;
            }
        }

        return $return;
    }
}
