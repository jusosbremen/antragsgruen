<?php

namespace app\models\db;

use yii\db\{ActiveQuery, ActiveRecord};

/**
 * @property int|null $id
 * @property int $userId
 * @property int $status
 * @property string $dateCreation
 * @property string $text
 *
 * @property User $user
 */
abstract class IAdminComment extends ActiveRecord
{
    const PROPOSED_PROCEDURE   = 1;
    const PROCEDURE_OVERVIEW   = 2;

    const SORT_DESC = 'desc';
    const SORT_ASC = 'asc';

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'userId'])
            ->andWhere(User::tableName() . '.status != ' . User::STATUS_DELETED);
    }


    public function getMyUser(): ?User
    {
        if ($this->userId) {
            return User::getCachedUser($this->userId);
        } else {
            return null;
        }
    }
}
