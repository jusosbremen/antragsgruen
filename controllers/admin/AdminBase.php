<?php

namespace app\controllers\admin;

use app\components\RequestContext;
use app\components\UrlHelper;
use app\controllers\Base;
use app\models\db\{ConsultationUserGroup, User};

class AdminBase extends Base
{
    public static $REQUIRED_PRIVILEGES = [
        ConsultationUserGroup::PRIVILEGE_ANY,
    ];

    /**
     * @param \yii\base\Action $action
     *
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (YII_ENV === 'test' && in_array($action->id, [
                'excellist', 'odslist', 'openslides', 'openslidesusers',
                'motion-excellist', 'motion-odslist', 'motion-openslides', 'motion-yopenslidesusers',
            ])) {
            // Downloading files is done by curl, not by chrome/firefox.
            // Therefore, the session is lost when downloading in the test environment
            return true;
        }

        if (RequestContext::getUser()->isGuest) {
            $this->redirect(UrlHelper::createUrl(['user/login', 'backUrl' => $_SERVER['REQUEST_URI']]));
            return false;
        }

        if (!User::haveOneOfPrivileges($this->consultation, static::$REQUIRED_PRIVILEGES)) {
            $this->showErrorpage(403, \Yii::t('admin', 'no_access'));
            return false;
        }
        return true;
    }

    protected function activateFunctions(): void
    {
        if (!User::havePrivilege($this->consultation, ConsultationUserGroup::PRIVILEGE_CONSULTATION_SETTINGS)) {
            return;
        }

        if ($this->getHttpRequest()->get('activate') === 'procedure') {
            foreach ($this->consultation->motionTypes as $motionType) {
                $settings                       = $motionType->getSettingsObj();
                $settings->hasProposedProcedure = true;
                $motionType->setSettingsObj($settings);
                $motionType->save();
            }

            $this->getHttpSession()->setFlash('success', \Yii::t('admin', 'list_functions_activated_t'));
        }
        if ($this->getHttpRequest()->get('activate') === 'responsibilities') {
            foreach ($this->consultation->motionTypes as $motionType) {
                $settings                      = $motionType->getSettingsObj();
                $settings->hasResponsibilities = true;
                $motionType->setSettingsObj($settings);
                $motionType->save();
            }

            $this->getHttpSession()->setFlash('success', \Yii::t('admin', 'list_functions_activated_t'));
        }
        if ($this->getHttpRequest()->get('activate') === 'openslides') {
            $settings = $this->consultation->getSettings();
            $settings->openslidesExportEnabled = true;
            $this->consultation->setSettings($settings);
            $this->consultation->save();

            $this->getHttpSession()->setFlash('success', \Yii::t('admin', 'list_functions_activated_c'));
        }
    }
}
