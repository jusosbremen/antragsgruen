<?php

use app\components\UrlHelper;
use app\models\db\ConsultationMotionType;
use yii\helpers\Html;

/**
 * @var Yii\web\View $this
 * @var ConsultationMotionType $motionType
 */

/** @var \app\controllers\Base $controller */
$controller = $this->context;
$layout = $controller->layoutParams;

$this->title = $motionType->titleSingular;
$layout->robotsNoindex = true;
$layout->addBreadcrumb($motionType->titleSingular);

?>
<h1><?= Html::encode($this->title) ?></h1>

<div class="content createSelectStatutes">

    <div class="alert alert-info">
        <p>
            <?= Yii::t('amend', 'create_select_statutes') ?>
        </p>
    </div>

    <br>

    <?php
    $statutes = $motionType->getAmendableOnlyMotions(true, true);
    foreach ($statutes as $statute) {
        echo '<div class="statute statute' . $statute->id . '">';
        $url = UrlHelper::createUrl(['/amendment/create', 'motionSlug' => $statute->getMotionSlug()]);
        $title = '<span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span> ' . Html::encode($statute->getTitleWithPrefix());
        echo Html::a($title, $url);
        echo '</div>';
    }

    ?>
</div>
