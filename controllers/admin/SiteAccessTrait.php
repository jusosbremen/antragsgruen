<?php

namespace app\controllers\admin;

use app\components\{UrlHelper, mail\Tools as MailTools};
use app\models\db\{ConsultationUserGroup, EMailLog, Site, Consultation, User};
use app\models\exceptions\{AlreadyExists, MailNotSent};
use app\models\policies\IPolicy;
use app\models\settings\AntragsgruenApp;
use yii\base\ExitException;
use yii\web\Response;

/**
 * @property Site $site
 * @property Consultation $consultation
 * @method render(string $view, array $options = [])
 * @method isPostSet(string $name)
 * @method AntragsgruenApp getParams()
 */
trait SiteAccessTrait
{
    /**
     * @param string $email
     * @throws \Yii\base\Exception
     */
    private function addAdminEmail($email)
    {
        $newUser = User::findByAuthTypeAndName(\app\models\settings\Site::LOGIN_STD, $email);
        if (!$newUser) {
            $newPassword              = User::createPassword();
            $newUser                  = new User();
            $newUser->auth            = 'email:' . $email;
            $newUser->status          = User::STATUS_CONFIRMED;
            $newUser->email           = $email;
            $newUser->emailConfirmed  = 1;
            $newUser->pwdEnc          = password_hash($newPassword, PASSWORD_DEFAULT);
            $newUser->name            = '';
            $newUser->organizationIds = '';
            $newUser->save();

            $authText = \Yii::t('admin', 'siteacc_mail_yourdata');
            $authText = str_replace(['%EMAIL%', '%PASSWORD%'], [$email, $newPassword], $authText);
        } else {
            $authText = \Yii::t('admin', 'siteacc_mail_youracc');
            $authText = str_replace('%EMAIL%', $email, $authText);
        }

        $subject = \Yii::t('admin', 'sitacc_admmail_subj');
        $link    = UrlHelper::createUrl('consultation/index');
        $link    = UrlHelper::absolutizeLink($link);
        $text    = str_replace(['%LINK%', '%ACCOUNT%'], [$link, $authText], \Yii::t('admin', 'sitacc_admmail_body'));
        try {
            $consultation = $this->consultation;
            MailTools::sendWithLog(EMailLog::TYPE_SITE_ADMIN, $consultation, $email, $newUser->id, $subject, $text);
        } catch (MailNotSent $e) {
            $errMsg = \Yii::t('base', 'err_email_not_sent') . ': ' . $e->getMessage();
            \Yii::$app->session->setFlash('error', $errMsg);
        }
    }

    /**
     * @throws AlreadyExists
     */
    private function addUserBySamlWw(string $username, ConsultationUserGroup $initGroup): User
    {
        $auth = 'openid:https://service.gruene.de/openid/' . $username;

        /** @var User $user */
        $user = User::find()->where(['auth' => $auth])->andWhere('status != ' . User::STATUS_DELETED)->one();
        if ($user) {
            // If the user already exist AND is already in the group, we will abort
            foreach ($user->userGroups as $userGroup) {
                if ($userGroup->id === $initGroup->id) {
                    throw new AlreadyExists();
                }
            }
        } else {
            $user                  = new User();
            $user->auth            = $auth;
            $user->email           = '';
            $user->name            = '';
            $user->emailConfirmed  = 0;
            $user->pwdEnc          = null;
            $user->status          = User::STATUS_CONFIRMED;
            $user->organizationIds = '';
            $user->save();
        }

        foreach ($this->consultation->getAllAvailableUserGroups() as $userGroup) {
            if ($userGroup->id === $initGroup->id) {
                $user->link('userGroups', $userGroup);
            }
        }

        return $user;
    }

    private function addUsersBySamlWw(): void
    {
        $usernames = explode("\n", \Yii::$app->request->post('samlWW', ''));

        $errors         = [];
        $alreadyExisted = [];
        $created        = 0;

        for ($i = 0; $i < count($usernames); $i++) {
            if (trim($usernames[$i]) === '') {
                continue;
            }
            try {
                $initGroup = $this->getDefaultUserGroup();
                $this->addUserBySamlWw($usernames[$i], $initGroup);
                $created++;
            } catch (AlreadyExists $e) {
                $alreadyExisted[] = $usernames[$i];
            } catch (\Exception $e) {
                $errors[] = $usernames[$i] . ': ' . $e->getMessage();
            }
        }
        if ($created === 0) {
            $errors[] = \Yii::t('admin', 'siteacc_user_added_0');
        }
        if (count($errors) > 0) {
            $errMsg = \Yii::t('admin', 'siteacc_err_occ') . ': ' . implode("\n", $errors);
            \Yii::$app->session->setFlash('error', $errMsg);
        }
        if (count($alreadyExisted) > 0) {
            \Yii::$app->session->setFlash('info', \Yii::t('admin', 'siteacc_user_had') . ': ' . implode(', ', $alreadyExisted));
        }
        if ($created > 0) {
            if ($created == 1) {
                $msg = str_replace('%NUM%', $created, \Yii::t('admin', 'siteacc_user_added_x'));
            } else {
                $msg = str_replace('%NUM%', $created, \Yii::t('admin', 'siteacc_user_added_x'));
            }
            \Yii::$app->session->setFlash('success', $msg);
        }
    }

    /**
     * @throws AlreadyExists
     */
    private function addUserByEmail(string $email, string $name, ?string $setPassword, ConsultationUserGroup $initGroup, string $emailText): User
    {
        $email = mb_strtolower($email);
        $auth  = 'email:' . $email;

        /** @var User $user */
        $user = User::find()->where(['auth' => $auth])->andWhere('status != ' . User::STATUS_DELETED)->one();
        if ($user) {
            // If the user already exist AND is already in the group, we will abort
            foreach ($user->userGroups as $userGroup) {
                if ($userGroup->id === $initGroup->id) {
                    throw new AlreadyExists();
                }
            }
            $accountText = '';
        } else {
            if ($setPassword) {
                $password = $setPassword;
            } else {
                $password = User::createPassword();
            }

            $user = new User();
            $user->auth = $auth;
            $user->email = $email;
            $user->name = $name;
            $user->pwdEnc = password_hash($password, PASSWORD_DEFAULT);
            $user->status = User::STATUS_CONFIRMED;
            $user->emailConfirmed = 1;
            $user->organizationIds = '';
            $user->save();

            $accountText = str_replace(
                ['%EMAIL%', '%PASSWORD%'],
                [$email, $password],
                \Yii::t('user', 'acc_grant_email_userdata')
            );
        }

        foreach ($this->consultation->getAllAvailableUserGroups() as $userGroup) {
            if ($userGroup->id === $initGroup->id) {
                $user->link('userGroups', $userGroup);
            }
        }

        $consUrl   = UrlHelper::absolutizeLink(UrlHelper::homeUrl());
        $emailText = str_replace('%LINK%', $consUrl, $emailText);

        try {
            MailTools::sendWithLog(
                EMailLog::TYPE_ACCESS_GRANTED,
                $this->consultation,
                $email,
                $user->id,
                \Yii::t('user', 'acc_grant_email_title'),
                $emailText,
                '',
                ['%ACCOUNT%' => $accountText]
            );
        } catch (MailNotSent $e) {
            \yii::$app->session->setFlash('error', \Yii::t('base', 'err_email_not_sent') . ': ' . $e->getMessage());
        }

        return $user;
    }

    private function addUsersByEmail()
    {
        $params   = $this->getParams();
        $post     = \Yii::$app->request->post();
        $hasEmail = ($params->mailService['transport'] !== 'none');

        $emails    = explode("\n", $post['emailAddresses']);
        $names     = explode("\n", $post['names']);
        $passwords = ($hasEmail ? null : explode("\n", $post['passwords']));

        if (count($emails) !== count($names)) {
            \Yii::$app->session->setFlash('error', \Yii::t('admin', 'siteacc_err_linenumber'));
        } elseif (!$hasEmail && count($emails) !== count($passwords)) {
            \Yii::$app->session->setFlash('error', \Yii::t('admin', 'siteacc_err_linenumber'));
        } else {
            $errors         = [];
            $alreadyExisted = [];
            $created        = 0;

            for ($i = 0; $i < count($emails); $i++) {
                if ($emails[$i] === '') {
                    continue;
                }
                try {
                    $this->addUserByEmail(
                        trim($emails[$i]),
                        trim($names[$i]),
                        ($hasEmail ? null : $passwords[$i]),
                        $this->getDefaultUserGroup(),
                        ($hasEmail ? $post['emailText'] : '')
                    );
                    $created++;
                } catch (AlreadyExists $e) {
                    $alreadyExisted[] = $emails[$i];
                } catch (\Exception $e) {
                    $errors[] = $emails[$i] . ': ' . $e->getMessage();
                }
            }
            if (count($errors) > 0) {
                $errMsg = \Yii::t('admin', 'siteacc_err_occ') . ': ' . implode(', ', $errors);
                \Yii::$app->session->setFlash('error', $errMsg);
            }
            if (count($alreadyExisted) > 0) {
                \Yii::$app->session->setFlash('info', \Yii::t('admin', 'siteacc_user_had') . ': ' .
                    implode(', ', $alreadyExisted));
            }
            if ($created > 0) {
                if ($created === 1) {
                    $msg = str_replace('%NUM%', $created, \Yii::t('admin', 'siteacc_user_added_x'));
                } else {
                    $msg = str_replace('%NUM%', $created, \Yii::t('admin', 'siteacc_user_added_x'));
                }
                \Yii::$app->session->setFlash('success', $msg);
            } else {
                \Yii::$app->session->setFlash('error', \Yii::t('admin', 'siteacc_user_added_0'));
            }
        }
    }

    /**
     * Hint: later it will be possible to select a group when inviting the user. Until then, it's a hard-coded group.
     */
    private function getDefaultUserGroup(): ?ConsultationUserGroup
    {
        foreach ($this->consultation->getAllAvailableUserGroups() as $userGroup) {
            if ($userGroup->templateId === ConsultationUserGroup::TEMPLATE_PARTICIPANT) {
                return $userGroup;
            }
        }
        return null;
    }

    private function saveUsers()
    {
        $postAccess = \Yii::$app->request->post('access');
        foreach ($this->consultation->userPrivileges as $privilege) {
            if (isset($postAccess[$privilege->userId])) {
                $access                     = $postAccess[$privilege->userId];
                $privilege->privilegeView   = (in_array('view', $access) ? 1 : 0);
                $privilege->privilegeCreate = (in_array('create', $access) ? 1 : 0);
            } else {
                $privilege->privilegeView   = 0;
                $privilege->privilegeCreate = 0;
            }
            $privilege->save();
        }
    }

    private function restrictToUsers()
    {
        $allowed = [IPolicy::POLICY_NOBODY, IPolicy::POLICY_LOGGED_IN, IPolicy::POLICY_LOGGED_IN];
        foreach ($this->consultation->motionTypes as $type) {
            if (!in_array($type->policyMotions, $allowed)) {
                $type->policyMotions = IPolicy::POLICY_LOGGED_IN;
            }
            if (!in_array($type->policyAmendments, $allowed)) {
                $type->policyAmendments = IPolicy::POLICY_LOGGED_IN;
            }
            if (!in_array($type->policyComments, $allowed)) {
                $type->policyComments = IPolicy::POLICY_LOGGED_IN;
            }
            if (!in_array($type->policySupportMotions, $allowed)) {
                $type->policySupportMotions = IPolicy::POLICY_LOGGED_IN;
            }
            if (!in_array($type->policySupportAmendments, $allowed)) {
                $type->policySupportAmendments = IPolicy::POLICY_LOGGED_IN;
            }
            $type->save();
        }
    }

    /*
     * This checks if there are regular users manually registered for this consultation,
     * but no restriction like "only registered users may create motions" or "force login to view the page" is set up.
     * If so, a warning should be shown.
     */
    private function needsPolicyWarning(): bool
    {
        $policyWarning = false;

        $siteAdminIds = array_map(function(User $user): int {
            return $user->id;
        }, $this->consultation->site->admins);

        $usersWithReadWriteAccess = false;
        foreach ($this->consultation->userPrivileges as $privilege) {
            // Users that have regular privilges, not consultation/site-admins
            if (($privilege->privilegeCreate || $privilege->privilegeView) && !(
                $privilege->adminContentEdit || $privilege->adminProposals || $privilege->adminScreen || $privilege->adminSuper ||
                in_array($privilege->userId, $siteAdminIds)
            )) {
                $usersWithReadWriteAccess = true;
            }
        }

        if (!$this->consultation->getSettings()->forceLogin && $usersWithReadWriteAccess) {
            $allowed = [IPolicy::POLICY_NOBODY, IPolicy::POLICY_LOGGED_IN, IPolicy::POLICY_LOGGED_IN];
            foreach ($this->consultation->motionTypes as $type) {
                if (!in_array($type->policyMotions, $allowed)) {
                    $policyWarning = true;
                }
                if (!in_array($type->policyAmendments, $allowed)) {
                    $policyWarning = true;
                }
                if (!in_array($type->policyComments, $allowed)) {
                    $policyWarning = true;
                }
                if (!in_array($type->policySupportMotions, $allowed)) {
                    $policyWarning = true;
                }
                if (!in_array($type->policySupportAmendments, $allowed)) {
                    $policyWarning = true;
                }
            }
        }
        return $policyWarning;
    }

    private function getConsultationAndCheckAdminPermission(): Consultation
    {
        $consultation = $this->consultation;

        if (!User::havePrivilege($consultation, ConsultationUserGroup::PRIVILEGE_CONSULTATION_SETTINGS)) {
            $this->showErrorpage(403, \Yii::t('admin', 'no_access'));
            throw new ExitException();
        }

        return $consultation;
    }

    private function getUsersWidgetData(Consultation $consultation): array
    {
        $usersArr = array_map(function (User $user): array {
            return $user->getUserAdminApiObject();
        }, $consultation->getUsersInAnyGroup());
        $groupsArr = array_map(function (ConsultationUserGroup $group): array {
            return $group->getUserAdminApiObject();
        }, $consultation->getAllAvailableUserGroups());

        return [
            'users' => $usersArr,
            'groups' => $groupsArr,
        ];
    }

    private function setUserGroups(Consultation $consultation, int $userId, array $groupIds): void
    {
        $user = User::findOne(['id' => $userId]);
        $userHasGroups = [];

        // Remove all groups belonging to this consultation that are not in the sent array
        foreach ($user->userGroups as $userGroup) {
            $userHasGroups[] = $userGroup->id;

            if (!$userGroup->isRelevantForConsultation($consultation)) {
                continue;
            }
            if (!in_array($userGroup->id, $groupIds)) {
                $user->unlink('userGroups', $userGroup, true);
            }
        }

        foreach ($consultation->getAllAvailableUserGroups() as $userGroup) {
            if (in_array($userGroup->id, $groupIds) && !in_array($userGroup->id, $userHasGroups)) {
                $user->link('userGroups', $userGroup);
            }
        }

        $consultation->refresh();
    }

    public function removeUser(Consultation $consultation, int $userId): void
    {
        $user = User::findOne(['id' => $userId]);
        foreach ($user->getUserGroupsForConsultation($consultation) as $userGroup) {
            $user->unlink('userGroups', $userGroup, true);
        }

        $consultation->refresh();
    }

    public function actionUsers(): string
    {
        $consultation = $this->getConsultationAndCheckAdminPermission();

        if ($this->isPostSet('addUsers')) {
            if (trim(\Yii::$app->request->post('emailAddresses', '')) !== '') {
                $this->addUsersByEmail();
            }
            if (trim(\Yii::$app->request->post('samlWW', '')) !== '' && $this->getParams()->isSamlActive()) {
                $this->addUsersBySamlWw();
            }
        }

        if ($this->isPostSet('grantAccess')) {
            $userIds = array_map('intval', \Yii::$app->request->post('userId', []));
            $defaultGroup = $this->getDefaultUserGroup();
            foreach ($this->consultation->screeningUsers as $screeningUser) {
                if (!in_array($screeningUser->userId, $userIds)) {
                    continue;
                }
                $user = $screeningUser->user;
                $user->link('userGroups', $defaultGroup);
                $screeningUser->delete();

                $consUrl = UrlHelper::createUrl('consultation/index');
                $consUrl = UrlHelper::absolutizeLink($consUrl);
                $emailText = str_replace('%LINK%', $consUrl, \Yii::t('user', 'access_granted_email'));

                MailTools::sendWithLog(
                    EMailLog::TYPE_ACCESS_GRANTED,
                    $this->consultation,
                    $user->email,
                    $user->id,
                    \Yii::t('user', 'acc_grant_email_title'),
                    $emailText
                );
            }
            $this->consultation->refresh();
        }

        if ($this->isPostSet('noAccess')) {
            $userIds = array_map('intval', \Yii::$app->request->post('userId', []));
            foreach ($this->consultation->screeningUsers as $screeningUser) {
                if (in_array($screeningUser->userId, $userIds)) {
                    $screeningUser->delete();
                }
            }
            $this->consultation->refresh();
        }

        return $this->render('users', [
            'widgetData' => $this->getUsersWidgetData($consultation),
            'screening' => $consultation->screeningUsers,
        ]);
    }

    public function actionUsersSave(): string
    {
        $consultation = $this->getConsultationAndCheckAdminPermission();

        $this->handleRestHeaders(['POST'], true);

        \Yii::$app->response->format = Response::FORMAT_RAW;
        \Yii::$app->response->headers->add('Content-Type', 'application/json');

        switch (\Yii::$app->request->post('op')) {
            case 'save-user-groups':
                $this->setUserGroups(
                    $consultation,
                    intval(\Yii::$app->request->post('userId')),
                    array_map('intval', \Yii::$app->request->post('groups', []))
                );
                break;
            case 'remove-user':
                $this->removeUser($consultation, intval(\Yii::$app->request->post('userId')));
                break;
        }

        $responseData = $this->getUsersWidgetData($consultation);
        return $this->returnRestResponse(200, json_encode($responseData));
    }

    public function actionUsersPoll(): string
    {
        $consultation = $this->getConsultationAndCheckAdminPermission();

        $this->handleRestHeaders(['GET'], true);

        \Yii::$app->response->format = Response::FORMAT_RAW;
        \Yii::$app->response->headers->add('Content-Type', 'application/json');

        $responseData = $this->getUsersWidgetData($consultation);
        return $this->returnRestResponse(200, json_encode($responseData));
    }
}
