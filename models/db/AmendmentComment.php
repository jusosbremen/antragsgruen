<?php

namespace app\models\db;

use app\models\settings\AntragsgruenApp;
use app\components\{RSSExporter, Tools, UrlHelper};
use yii\db\ActiveQuery;

/**
 * @property int|null $id
 * @property int $userId
 * @property int $amendmentId
 * @property int $parentCommentId
 * @property string $text
 * @property string $name
 * @property string $contactEmail
 * @property string $dateCreation
 * @property int $status
 * @property int $replyNotification
 *
 * @property User $user
 * @property Amendment $amendment
 * @property AmendmentComment $parentComment
 * @property AmendmentComment[] $replies
 */
class AmendmentComment extends IComment
{
    public function init(): void
    {
        parent::init();

        $this->on(static::EVENT_PUBLISHED, [$this, 'logToConsultationLog'], null, false);
    }

    public static function tableName(): string
    {
        return AntragsgruenApp::getInstance()->tablePrefix . 'amendmentComment';
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'userId'])
            ->andWhere(User::tableName() . '.status != ' . User::STATUS_DELETED);
    }

    public function getAmendment(): ActiveQuery
    {
        return $this->hasOne(Amendment::class, ['id' => 'amendmentId'])
            ->andWhere(Amendment::tableName() . '.status != ' . Amendment::STATUS_DELETED);
    }

    private ?Amendment $imotion = null;

    public function getIMotion(): ?Amendment
    {
        if (!$this->imotion) {
            $current = Consultation::getCurrent();
            if ($current) {
                $amendment = $current->getAmendment($this->amendmentId);
                if ($amendment) {
                    $this->imotion = $amendment;
                } else {
                    $this->imotion = Amendment::findOne($this->amendmentId);
                }
            } else {
                $this->imotion = Amendment::findOne($this->amendmentId);
            }
        }
        return $this->imotion;
    }

    public function getParentComment(): ActiveQuery
    {
        return $this->hasOne(AmendmentComment::class, ['id' => 'parentCommentId'])
            ->andWhere(AmendmentComment::tableName() . '.status != ' . AmendmentComment::STATUS_DELETED)
            ->andWhere(AmendmentComment::tableName() . '.status != ' . AmendmentComment::STATUS_PRIVATE);
    }

    public function getReplies(): ActiveQuery
    {
        return $this->hasMany(AmendmentComment::class, ['parentCommentId' => 'id'])
            ->andWhere(AmendmentComment::tableName() . '.status != ' . AmendmentComment::STATUS_DELETED)
            ->andWhere(AmendmentComment::tableName() . '.status != ' . AmendmentComment::STATUS_PRIVATE);
    }

    public function getConsultation(): ?Consultation
    {
        $amendment = $this->getIMotion();
        return $amendment->getMyConsultation();
    }

    public function rules(): array
    {
        return [
            [['amendmentId', 'paragraph', 'status', 'dateCreation'], 'required'],
            ['name', 'required', 'message' => \Yii::t('comment', 'err_no_name')],
            ['text', 'required', 'message' => \Yii::t('comment', 'err_no_text')],
            [['id', 'amendmentId', 'paragraph', 'status', 'parentCommentId'], 'number'],
            [['text', 'paragraph'], 'safe'],
        ];
    }

    /**
     * @return AmendmentComment[]
     */
    public static function getNewestByConsultation(Consultation $consultation, int $limit = 5): array
    {
        $invisibleStatuses = array_map('intval', $consultation->getStatuses()->getInvisibleMotionStatuses());

        /** @var AmendmentComment[] $comments */
        $comments = static::find()->joinWith('amendment', true)->joinWith('amendment.motionJoin', true)
            ->where('amendmentComment.status = ' . intval(static::STATUS_VISIBLE))
            ->andWhere('amendment.status NOT IN (' . implode(', ', $invisibleStatuses) . ')')
            ->andWhere('motion.status NOT IN (' . implode(', ', $invisibleStatuses) . ')')
            ->andWhere('motion.consultationId = ' . intval($consultation->id))
            ->orderBy('amendmentComment.dateCreation DESC')
            ->offset(0)->limit($limit)->all();

        return array_values(array_filter($comments, function (AmendmentComment $comment): bool {
            return $comment->getIMotion()->getMyMotionType()->maySeeIComments();
        }));
    }

    public function getMotionTitle(): string
    {
        $amendment = $this->getIMotion();
        return $amendment->titlePrefix . ' ' . \Yii::t('amend', 'amend_for_motion') . ' ' . $amendment->getMyMotion()->getTitleWithPrefix();
    }

    public function addToFeed(RSSExporter $feed): void
    {
        if ($this->status === static::STATUS_PRIVATE) {
            return;
        }
        $feed->addEntry(
            UrlHelper::createAmendmentCommentUrl($this),
            \Yii::t('motion', 'comment_for') . ': ' . $this->getMotionTitle(),
            $this->name,
            $this->text,
            Tools::dateSql2timestamp($this->dateCreation)
        );
    }

    public function getDate(): string
    {
        return $this->dateCreation;
    }

    public function getLink(): string
    {
        return UrlHelper::createAmendmentCommentUrl($this);
    }

    /**
     * @return AmendmentComment[]
     */
    public static function getScreeningComments(Consultation $consultation): array
    {
        $query = AmendmentComment::find();
        $query->where('amendmentComment.status = ' . intval(static::STATUS_SCREENING));
        $query->joinWith(
            [
                'amendment' => function ($query) use ($consultation) {
                    $invisibleStatuses = array_map('intval', $consultation->getStatuses()->getInvisibleAmendmentStatuses());
                    /** @var ActiveQuery $query */
                    $query->andWhere('amendment.status NOT IN (' . implode(', ', $invisibleStatuses) . ')');
                    $query->andWhere('amendment.motionId = motion.id');

                    $query->joinWith(
                        [
                            'motionJoin' => function ($query) use ($consultation) {
                                $invisibleStatuses = array_map('intval', $consultation->getStatuses()->getInvisibleMotionStatuses());
                                /** @var ActiveQuery $query */
                                $query->andWhere('motion.status NOT IN (' . implode(', ', $invisibleStatuses) . ')');
                                $query->andWhere('motion.consultationId = ' . intval($consultation->id));
                            }
                        ]
                    );
                }
            ]
        );
        $query->orderBy('dateCreation DESC');

        return $query->all();
    }

    public function logToConsultationLog(): void
    {
        if ($this->status === static::STATUS_PRIVATE) {
            return;
        }
        ConsultationLog::logCurrUser($this->getConsultation(), ConsultationLog::AMENDMENT_COMMENT, $this->id);
    }

    public function getUserdataExportObject(): array
    {
        $amendment = $this->getIMotion();
        return [
            'amendment_title' => $amendment->getTitle(),
            'amendment_link'  => $amendment->getLink(true),
            'text'            => $this->text,
            'name'            => $this->name,
            'email'           => $this->contactEmail,
            'date_creation'   => $this->dateCreation,
            'status'          => $this->status,
        ];
    }
}
