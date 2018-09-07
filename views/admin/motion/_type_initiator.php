<?php

use app\components\HTMLTools;
use app\models\db\ConsultationMotionType;
use app\models\settings\InitiatorForm;
use app\models\supportTypes\CollectBeforePublish;
use app\models\supportTypes\SupportBase;
use yii\helpers\Html;

/**
 * @var ConsultationMotionType $motionType
 */

$settings = $motionType->getMotionSupportTypeClass()->getSettingsObj();

?>
<h3><?= \Yii::t('admin', 'motion_type_initiator') ?></h3>

<div class="form-group">
    <label class="col-md-4 control-label" for="typeSupportType">
        <?= \Yii::t('admin', 'motion_type_supp_form') ?>
    </label>
    <div class="col-md-8">
        <?php
        $options = [];
        foreach (SupportBase::getImplementations() as $formId => $formClass) {
            $supporters = ($formClass::hasInitiatorGivenSupporters() || $formClass === CollectBeforePublish::class);
            $options[]  = [
                'title'      => $formClass::getTitle(),
                'attributes' => ['data-has-supporters' => ($supporters ? '1' : '0')],
            ];
        }
        echo HTMLTools::fueluxSelectbox(
            'type[supportType]',
            $options,
            $motionType->supportType,
            ['id' => 'typeSupportType'],
            true
        );
        ?>
    </div>
</div>

<div class="form-group">
    <label class="col-md-4" style="text-align: right;">
        <?= \Yii::t('admin', 'motion_type_contact_name') ?>
    </label>
    <div class="col-md-8 contactDetails contactName">
        <input type="hidden" name="initiatorSettingFields[]" value="contactName">
        <?php
        echo Html::radioList(
            'initiatorSettings[contactName]',
            $settings->contactName,
            [
                InitiatorForm::CONTACT_NONE     => \Yii::t('admin', 'motion_type_skip'),
                InitiatorForm::CONTACT_OPTIONAL => \Yii::t('admin', 'motion_type_optional'),
                InitiatorForm::CONTACT_REQUIRED => \Yii::t('admin', 'motion_type_required'),
            ],
            ['class' => 'form-control']
        );
        ?>
    </div>
</div>

<div class="form-group">
    <label class="col-md-4" style="text-align: right;">
        <?= \Yii::t('admin', 'motion_type_email') ?>
    </label>
    <div class="col-md-8 contactDetails contactEMail">
        <input type="hidden" name="initiatorSettingFields[]" value="contactEmail">
        <?php
        echo Html::radioList(
            'initiatorSettings[contactEmail]',
            $settings->contactEmail,
            [
                InitiatorForm::CONTACT_NONE     => \Yii::t('admin', 'motion_type_skip'),
                InitiatorForm::CONTACT_OPTIONAL => \Yii::t('admin', 'motion_type_optional'),
                InitiatorForm::CONTACT_REQUIRED => \Yii::t('admin', 'motion_type_required'),
            ],
            ['class' => 'form-control']
        );
        ?>
    </div>
</div>

<div class="form-group">
    <label class="col-md-4" style="text-align: right;">
        <?= \Yii::t('admin', 'motion_type_phone') ?>
    </label>
    <div class="col-md-8 contactDetails contactPhone">
        <input type="hidden" name="initiatorSettingFields[]" value="contactPhone">
        <?php
        echo Html::radioList(
            'initiatorSettings[contactPhone]',
            $settings->contactPhone,
            [
                InitiatorForm::CONTACT_NONE     => \Yii::t('admin', 'motion_type_skip'),
                InitiatorForm::CONTACT_OPTIONAL => \Yii::t('admin', 'motion_type_optional'),
                InitiatorForm::CONTACT_REQUIRED => \Yii::t('admin', 'motion_type_required'),
            ],
            ['class' => 'form-control']
        );
        ?>
    </div>
</div>

<div class="form-group">
    <label class="col-md-4" style="text-align: right;">
        <?= \Yii::t('admin', 'motion_type_orga_resolution') ?>
    </label>
    <div class="col-md-8 contactDetails contactResolutionDate">
        <input type="hidden" name="initiatorSettingFields[]" value="hasResolutionDate">
        <?php
        echo Html::radioList(
            'initiatorSettings[hasResolutionDate]',
            $settings->hasResolutionDate,
            [
                InitiatorForm::CONTACT_NONE     => \Yii::t('admin', 'motion_type_skip'),
                InitiatorForm::CONTACT_OPTIONAL => \Yii::t('admin', 'motion_type_optional'),
                InitiatorForm::CONTACT_REQUIRED => \Yii::t('admin', 'motion_type_required'),
            ],
            ['class' => 'form-control']
        );
        ?>
    </div>
</div>

<div class="form-group">
    <label class="col-md-4" style="text-align: right;">
        <?= \Yii::t('admin', 'motion_type_gender') ?>
    </label>
    <div class="col-md-8 contactDetails contactGender">
        <input type="hidden" name="initiatorSettingFields[]" value="contactGender">
        <?php
        echo Html::radioList(
            'initiatorSettings[contactGender]',
            $settings->contactGender,
            [
                InitiatorForm::CONTACT_NONE     => \Yii::t('admin', 'motion_type_skip'),
                InitiatorForm::CONTACT_OPTIONAL => \Yii::t('admin', 'motion_type_optional'),
                InitiatorForm::CONTACT_REQUIRED => \Yii::t('admin', 'motion_type_required'),
            ],
            ['class' => 'form-control']
        );
        ?>
    </div>
</div>

<div class="form-group" id="typeMinSupportersRow">
    <label class="col-md-4 control-label" for="typeMinSupporters">
        <?= \Yii::t('admin', 'motion_type_supp_min') ?>
    </label>
    <div class="col-md-2">
        <input type="hidden" name="initiatorSettingFields[]" value="minSupporters">
        <input type="number" name="initiatorSettings[minSupporters]" class="form-control" id="typeMinSupporters"
               value="<?= Html::encode($settings->minSupporters) ?>">
    </div>
</div>

<div class="form-group" id="typeAllowMoreSupporters">
    <div class="checkbox col-md-8 col-md-offset-4">
        <input type="hidden" name="initiatorSettingFields[]" value="allowMoreSupporters">
        <?php
        echo HTMLTools::fueluxCheckbox(
            'initiatorSettings[allowMoreSupporters]',
            \Yii::t('admin', 'motion_type_allow_more_supp'),
            $settings->allowMoreSupporters
        );
        ?>
    </div>
</div>

<div class="form-group" id="typeHasOrgaRow">
    <div class="checkbox col-md-8 col-md-offset-4">
        <input type="hidden" name="initiatorSettingFields[]" value="hasOrganizations">
        <?php
        echo HTMLTools::fueluxCheckbox(
            'initiatorSettings[hasOrganizations]',
            \Yii::t('admin', 'motion_type_ask_orga'),
            $settings->hasOrganizations
        );
        ?>
    </div>
</div>