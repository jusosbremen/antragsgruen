<?php

/**
 * @var \yii\web\View $this
 */

use app\components\UrlHelper;

/** @var \app\controllers\Base $controller */
$controller   = $this->context;
$layout       = $controller->layoutParams;
$consultation = $controller->consultation;

$this->title = \Yii::t('admin', 'list_head_title');
$layout->addBreadcrumb(\Yii::t('admin', 'bread_list'));
$layout->addJS('js/colResizable-1.6.min.js');
$layout->addJS('vue/build.js');
$layout->addCSS('css/backend.css');
$layout->loadFuelux();
$layout->fullWidth  = true;
$layout->fullScreen = true;

echo '<h1>' . \Yii::t('admin', 'list_head_title') . '</h1>';

echo $this->render('@app/views/admin/motion-list/_list_all_export');


$initData = json_encode([
    'motions'    => \app\async\models\Motion::getCollection($consultation),
    'amendments' => \app\async\models\Amendment::getCollection($consultation),
]);

$linkTemplates = json_encode([
    'motion/view'               => UrlHelper::createUrl(['/motion/view', 'motionSlug' => '_SLUG_']),
    'motion/odt'                => UrlHelper::createUrl(['/motion/odt', 'motionSlug' => '_SLUG_']),
    'motion/plainhtml'          => UrlHelper::createUrl(['/motion/plainhtml', 'motionSlug' => '_SLUG_']),
    'motion/pdf'                => UrlHelper::createUrl(['/motion/pdf', 'motionSlug' => '_SLUG_']),
    'motion/pdfamendcollection' => UrlHelper::createUrl(['/motion/pdfamendcollection', 'motionSlug' => '_SLUG_']),
    'motion/clone'              => UrlHelper::createUrl(['/motion/create', 'cloneFrom' => '_SLUG_']),

    'amendment/view'  => UrlHelper::createUrl(['/amendment/view', 'motionSlug' => '_MOTION_SLUG_', 'amendmentId' => '0123456789']),
    'amendment/odt'   => UrlHelper::createUrl(['/amendment/odt', 'motionSlug' => '_MOTION_SLUG_', 'amendmentId' => '0123456789']),
    'amendment/pdf'   => UrlHelper::createUrl(['/amendment/pdf', 'motionSlug' => '_MOTION_SLUG_', 'amendmentId' => '0123456789']),
    'amendment/clone' => UrlHelper::createUrl(['/amendment/create', 'motionSlug' => '_MOTION_SLUG_', 'cloneFrom' => '0123456789']),

    'admin/motion/update'    => UrlHelper::createUrl(['/admin/motion/update', 'motionId' => '0123456789']),
    'admin/amendment/update' => UrlHelper::createUrl(['/admin/amendment/update', 'amendmentId' => '0123456789']),
]);

$params = [
    'ajax-backend'     => UrlHelper::createUrl('/admin/motion-list/ajax'),
    'link-templates'   => $linkTemplates,
    'init-collections' => $initData,
];

?>
<!--
<div class="content">
    <?= \app\components\HTMLTools::getAngularComponent('admin-index', $params, ['structure', 'admin', 'tags']) ?>
</div>
-->

<div id="app"></div>
<script>
    $(function () {
        new Vue({
            el: "#app",
            template: `<admin-list :name="'Test'" :initialEnthusiasm="23" :subdomain="subdomain" :path="path" :cookie="cookie" :wsPort="wsPort" />`,
            components: {
                AdminList
            },
            data: {
                subdomain: 'stdparteitag',
                path: 'std-parteitag',
                cookie: <?= json_encode($_COOKIE['PHPSESSID']) ?>,
                wsPort: <?= IntVal(\Yii::$app->params->asyncConfig['port-external']) ?>
            }
        });
    })
</script>
