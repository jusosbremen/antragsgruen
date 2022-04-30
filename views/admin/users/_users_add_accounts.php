<?php

use app\components\UrlHelper;
use app\models\db\Consultation;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var Consultation $consultation
 */

/** @var \app\controllers\Base $controller */
$controller = $this->context;

$preEmails = '';
$preNames = '';
$prePasswords = '';
$preSamlWW = '';
$preText = Yii::t('admin', 'siteacc_email_text_pre');
$hasEmail = ($controller->getParams()->mailService['transport'] !== 'none');
$hasSaml = $controller->getParams()->isSamlActive();

$authTypes = [
    'email' => Yii::t('admin', 'siteacc_add_email'),
];
if ($controller->getParams()->isSamlActive()) {
    $authTypes['gruenesnetz'] = Yii::t('admin', 'siteacc_add_ww');
}

?>
<section id="accountsCreateForm" class="adminForm form-horizontal"
         data-antragsgruen-widget="backend/UserAdminCreate"
         aria-labelledby="newUserAdderTitle">
    <h2 class="green" id="newUserAdderTitle"><?= Yii::t('admin', 'siteacc_new_users') ?></h2>
    <div class="content">
        <form action="<?= Html::encode(UrlHelper::createUrl('/admin/users/add-single-init')) ?>"
              class="row addSingleInit">
            <div class="col-md-3 adminType">
                <?= Html::dropDownList('authType', 'email', $authTypes, ['class' => 'stdDropdown adminTypeSelect']) ?>
            </div>
            <div class="col-md-4">
                <input type="email" name="addEmail" value="" class="form-control inputEmail"
                       title="<?= Html::encode(Yii::t('admin', 'siteacc_add_name_title')) ?>"
                       placeholder="<?= Html::encode(Yii::t('admin', 'siteacc_add_email_place')) ?>" required>
                <input type="text" name="addUsername" value="" class="form-control inputUsername hidden"
                       title="<?= Html::encode(Yii::t('admin', 'siteacc_add_name_title')) ?>"
                       placeholder="<?= Html::encode(Yii::t('admin', 'siteacc_add_username_place')) ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-default addUsersOpener singleuser" type="submit" data-type="singleuser">
                    <?= Yii::t('admin', 'siteacc_add_std_btn') ?>
                </button>
            </div>
        </form>

        <div class="alert alert-danger alreadyMember hidden">
            <p>This user was already added to this consultation.</p>
        </div>
        <?php

        echo Html::beginForm(UrlHelper::createUrl('/admin/users/add-single'), 'post', ['class' => 'addUsersByLogin singleuser hidden']);
        ?>
        <div class="stdTwoCols showIfNew">
            <label for="addSingleNameGiven" class="leftColumn">Given name:</label>
            <div class="rightColumn">
                <input type="text" name="nameGiven" class="form-control" id="addSingleNameGiven">
            </div>
        </div>
        <div class="stdTwoCols showIfNew">
            <label for="addSingleNameFamily" class="leftColumn">Family name:</label>
            <div class="rightColumn">
                <input type="text" name="nameGiven" class="form-control" id="addSingleNameFamily">
            </div>
        </div>
        <div class="stdTwoCols showIfNew">
            <label for="addSingleOrganization" class="leftColumn">Organization:</label>
            <div class="rightColumn">
                <input type="text" name="organization" class="form-control" id="addSingleOrganization">
            </div>
        </div>
        <div class="stdTwoCols showIfNew">
            <label for="addUserPassword" class="leftColumn">Password:</label>
            <div class="rightColumn">
                <label>
                    <input type="checkbox" name="generatePassword" checked id="addSingleGeneratePassword">
                    Auto-generate password
                </label>
                <input type="password" name="password" class="form-control" id="addUserPassword">
            </div>
        </div>

        <div class="alert alert-info showIfExists">
            <p>This user already has an account. You can grant him/her permissions.</p>
        </div>

        <div class="stdTwoCols">
            <label for="addSingleOrganization" class="leftColumn">User groups:</label>
            <div class="rightColumn">
                <?php
                foreach ($controller->consultation->userGroups as $userGroup) {
                    echo '<label><input type="checkbox" name="userGroups[]" value="' . Html::encode($userGroup->id) . '"';
                    if ($userGroup->templateId === \app\models\db\ConsultationUserGroup::TEMPLATE_PARTICIPANT) {
                        echo ' checked';
                    }
                    echo '> ' . Html::encode($userGroup->title) . '</label><br>';
                }
                ?>
            </div>
        </div>

        <?php if ($hasEmail) { ?>
        <div class="stdTwoCols welcomeEmail">
            <div class="leftColumn">Welcome e-mail:</div>
            <div class="rightColumn">
                <textarea id="emailText" name="emailText" rows="15" cols="80"
                          title="<?= Yii::t('admin', 'siteacc_new_text') ?>"><?= Html::encode($preText) ?></textarea>
            </div>
        </div>
        <?php } ?>

        <div class="saveholder">
            <button type="submit" name="addUsers" class="btn btn-primary">
                <?= Yii::t('admin', 'siteacc_new_do') ?>
            </button>
        </div>
        <?php
        echo Html::endForm();

        ?>

        <div class="addMultiple">
            <?= Yii::t('admin', 'siteacc_add_multiple') ?>:
            <button class="btn btn-link addUsersOpener email" type="button" data-type="email">
                <?= Yii::t('admin', 'siteacc_add_email_btn') ?>
            </button>
            <?php
            if ($hasSaml) {
                ?>
                <button class="btn btn-link addUsersOpener samlWW" type="button" data-type="samlWW">
                    <?= Yii::t('admin', 'siteacc_add_ww_btn') ?>
                </button>
                <?php
            }
            ?>
        </div>

        <?php

        if ($hasSaml) {
            echo Html::beginForm(UrlHelper::createUrl('/admin/users/add-multiple-ww'), 'post', [
                'class' => 'addUsersByLogin multiuser samlWW hidden',
            ]);
            ?>
            <div class="row">
                <label class="col-md-4 col-md-offset-4">
                    <?= Yii::t('admin', 'siteacc_new_saml_ww') ?>
                    <textarea id="samlWW" name="samlWW" rows="15"><?= Html::encode($preSamlWW) ?></textarea>
                </label>
            </div>

            <br><br>
            <div class="saveholder">
                <button type="submit" name="addUsers" class="btn btn-primary">
                    <?= Yii::t('admin', 'siteacc_new_do') ?>
                </button>
            </div>
            <?php
            echo Html::endForm();
        }


        if ($hasEmail) {
            echo Html::beginForm(UrlHelper::createUrl('/admin/users/add-multiple-email'), 'post', [
                'class' => 'addUsersByLogin multiuser email hidden',
            ]);
            ?>
            <div class="accountEditExplanation alert alert-info">
                <?= Yii::t('admin', 'siteacc_acc_expl_mail') ?>
            </div>
            <div class="row">
                <label class="col-md-6">
                    <?= Yii::t('admin', 'siteacc_new_emails') ?>
                    <textarea id="emailAddresses" name="emailAddresses"
                              rows="15"><?= Html::encode($preEmails) ?></textarea>
                </label>

                <label class="col-md-6">
                    <?= Yii::t('admin', 'siteacc_new_names') ?>
                    <textarea id="names" name="names" rows="15"><?= Html::encode($preNames) ?></textarea>
                </label>
            </div>

            <label for="emailText"><?= Yii::t('admin', 'siteacc_new_text') ?>:</label>
            <textarea id="emailText" name="emailText" rows="15" cols="80"><?= Html::encode($preText) ?></textarea>

            <br><br>
            <div class="saveholder">
                <button type="submit" name="addUsers" class="btn btn-primary">
                    <?= Yii::t('admin', 'siteacc_new_do') ?>
                </button>
            </div>
            <?php
            echo Html::endForm();
        } else {
            echo Html::beginForm(UrlHelper::createUrl('/admin/users/multiple-email'), 'post', [
                'class' => 'addUsersByLogin email hidden',
            ]);
            ?>
            <div class="accountEditExplanation alert alert-info">
                <?= Yii::t('admin', 'siteacc_acc_expl_nomail') ?>
            </div>
            <div class="row">
                <label class="col-md-4">
                    <?= Yii::t('admin', 'siteacc_new_emails') ?>
                    <textarea id="emailAddresses" name="emailAddresses"
                              rows="15"><?= Html::encode($preEmails) ?></textarea>
                </label>

                <label class="col-md-4">
                    <?= Yii::t('admin', 'siteacc_new_pass') ?>
                    <textarea id="passwords" name="passwords" rows="15"><?= Html::encode($prePasswords) ?></textarea>
                </label>

                <label class="col-md-4"><?= Yii::t('admin', 'siteacc_new_names') ?>
                    <textarea id="names" name="names" rows="15"><?= Html::encode($preNames) ?></textarea>
                </label>
            </div>
            <br><br>
            <div class="saveholder">
                <button type="submit" name="addUsers" class="btn btn-primary">
                    <?= Yii::t('admin', 'siteacc_new_do') ?>
                </button>
            </div>
            <?php
            echo Html::endForm();
        }

        ?>
    </div>
</section>
