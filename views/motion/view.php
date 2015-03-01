<?php

use app\components\AntiXSS;
use app\components\Tools;
use app\components\UrlHelper;
use app\models\db\Amendment;
use app\models\db\ISupporter;
use app\models\db\Motion;
use app\models\db\MotionComment;
use app\models\db\MotionSupporter;
use app\models\policies\IPolicy;
use yii\helpers\Html;

/**
 * @var \yii\web\View $this
 * @var Motion $motion
 * @var Amendment[] $amendments
 * @var bool $editLink
 * @var int[] $openedComments
 * @var string|null $adminEdit
 * @var string|null $commentDelLink
 * @var array $hiddens
 * @var bool $jsProtection
 * @var null|int $supportStatus
 */

/** @var \app\controllers\Base $controller */
$controller = $this->context;
$layout     = $controller->layoutParams;
$wording    = $consultation->getWording();


$layout->breadcrumbs[] = $motion->getTypeName();
$layout->addJS('/js/socialshareprivacy/jquery.socialshareprivacy.js');

$this->title = $motion->getNameWithPrefix() . " (" . $motion->consultation->title . ", Antragsgrün)";

$rows = 4;
if ($motion->dateResolution != "") {
    $rows++;
}
// if (count($antrag->antraege) > 0) $rows++; // @TODO

$html = '<ul class="motionActions">';

$policy = $motion->consultation->getAmendmentPolicy();
if ($policy->checkCurUserHeuristically()) {
    $html .= '<li class="amendmentCreate">';
    $amendCreateUrl = UrlHelper::createUrl("amendment/create", ['motionId' => $motion->id]);
    $html .= Html::a($wording->get("Änderungsantrag stellen"), $amendCreateUrl) . '</li>';
} else {
    $msg = $policy->getPermissionDeniedMsg($wording);
    if ($msg != "") {
        $html .= '<li class="amendmentCreate"><span><span style="font-style: italic;">';
        $html .= Html::encode($wording->get("Änderungsantrag stellen"));
        $html .= '</span><br><span style="font-size: 13px; color: #dbdbdb; text-transform: none;">';
        $html .= Html::encode($policy->getPermissionDeniedMsg($wording)) . '</span></span></li>';
    }
}

if ($motion->consultation->getSettings()->hasPDF && $motion->isVisible()) {
    $pdfLink = UrlHelper::createUrl("motion/pdf", ['motionId' => $motion->id]);
    $html .= '<li class="download">' . Html::a($wording->get("PDF-Version herunterladen"), $pdfLink) . '</li>';
}

if ($editLink) {
    $mergeLink = UrlHelper::createUrl('motion/mergeamendments', ['motionId' => $motion->id]);
    $html .= '<li class="edit">' . Html::a($wording->get("Änderungsanträge einpflegen"), $mergeLink) . '</li>';
    $amendLink = UrlHelper::createUrl('amendment/create', ['motionId' => $motion->id]);
    $html .= '<li class="edit">' . Html::a($wording->get("Antrag bearbeiten"), $amendLink) . '</li>';
}

if ($adminEdit) {
    $html .= '<li class="adminEdit">' . Html::a("Admin: bearbeiten", $adminEdit) . '</li>';
} else {
    $backUrl = UrlHelper::createUrl('consultation/index');
    $html .= '<li class="back">' . Html::a("Zurück zur Übersicht", $backUrl) . '</li>';
}
$html .= '</ul>';
$layout->menusHtml[] = $html;

$minimalisticUi = $motion->consultation->getSettings()->minimalisticUI;
$minHeight      = ($minimalisticUi && \Yii::$app->user->isGuest ? 60 : 114);

echo '<h1>' . Html::encode($motion->getNameWithPrefix()) . '</h1>';

echo '<div class="motionData" style="min-height: ' . $minHeight . 'px;">
    <div id="socialshareprivacy"></div>';

if (!$minimalisticUi) {
    echo '<div class="content">';
    /*
    if (count($antrag->antraege) > 0) { ?>
                <div class="alert alert-error" style="margin-top: 10px; margin-bottom: 25px;">
                    <?php if (count($antrag->antraege) == 1) {
                        echo 'Achtung: dies ist eine alte Fassung; die aktuelle Fassung gibt es hier:<br>';
                        $a = $antrag->antraege[0];
                        echo CHtml::link($a->revision_name . " - " . $a->name, $this->createUrl("antrag/anzeige",
    array("antrag_id" => $a->id)));
                    } else {
                        echo 'Achtung: dies ist eine alte Fassung. Aktuellere Fassungen gibt es hier:<br>';
                        foreach ($antrag->antraege as $a) {
                            echo "- " . CHtml::link($a->revision_name . " - " . $a->name, $this->createUrl(
    "antrag/anzeige", array("antrag_id" => $a->id))) . "<br>";
                        }
                    } ?>
                </div>
            <?php } */

    echo '<table style="width: 100%;" class="motionDataTable">
                <tr>
                    <th>' . $wording->get("Veranstaltung") . ':</th>
                    <td>' .
        Html::a($motion->consultation->title, UrlHelper::createUrl('consultation/index')) . '</td>
                </tr>
                <tr>
                    <th>' . $wording->get("AntragsstellerIn"), ':</th>
                    <td>';


    $x = array();
    foreach ($motion->supporters as $supp) {
        if ($supp->role == ISupporter::ROLE_INITIATOR) {
            $name = $supp->getNameWithResolutionDate(true);
            if ($motion->consultation->isAdminCurUser() && ($supp->contactEmail != "" || $supp->contactPhone != "")) {
                $name .= " <small>(Kontaktdaten, nur als Admin sichtbar: ";
                if ($supp->contactEmail != "") {
                    $name .= "E-Mail: " . Html::encode($supp->contactEmail);
                }
                if ($supp->contactEmail != "" && $supp->contactPhone != "") {
                    $name .= ", ";
                }
                if ($supp->contactPhone != "") {
                    $name .= "Telefon: " . Html::encode($supp->contactPhone);
                }
                $name .= ")</small>";
            }
            $x[] = $name;
        }
    }
    echo implode(", ", $x);

    echo '</td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td>';

    $screeningMotionsShown = $motion->consultation->getSettings()->screeningMotionsShown;
    $statiNames            = Motion::getStati();
    if ($motion->status == Motion::STATUS_SUBMITTED_UNSCREENED) {
        echo '<span class="unscreened">' . Html::encode($statiNames[$motion->status]) . '</span>';
    } elseif ($motion->status == Motion::STATUS_SUBMITTED_SCREENED && $screeningMotionsShown) {
        echo '<span class="screened">Von der Programmkommission geprüft</span>';
    } else {
        echo Html::encode($statiNames[$motion->status]);
    }
    if (trim($motion->statusString) != "") {
        echo " <small>(" . Html::encode($motion->statusString) . ")</string>";
    }
    echo '</td>
                </tr>';

    if ($motion->dateResolution != "") {
        echo '<tr>
       <th>Entschieden am:</th>
       <td>' . Tools::formatMysqlDate($motion->dateResolution) . '</td>
     </tr>';
    }
    echo '<tr>
       <th>Eingereicht:</th>
       <td>' . Tools::formatMysqlDateTime($motion->dateCreation) . '</td>
                </tr>';

    if ($motion->consultation->isAdminCurUser() && count($motion->consultation->tags) > 0) {
        echo '<tr>
                        <th>Themenbereiche:</th>
                        <td>';

        $tags         = array();
        $used_tag_ids = array();
        foreach ($motion->tags as $tag) {
            $used_tag_ids[] = $tag->id;
            $delParams      = ['motionId' => $motion->id, AntiXSS::createToken("del_tag") => $tag->id];
            $dellink        = UrlHelper::createUrl("motion/view", $delParams);
            $str            = Html::encode($tag->title);
            $str .= ' <a href="' . Html::encode($dellink) . '" class="dellink">del</a>';
            $tags[] = $str;
        }
        echo implode(", ", $tags);

        echo '&nbsp; &nbsp; <a href="#" class="tag_adder_holder" style="color: green;">Neu</a>
    <form method="POST" style="display: none;" id="tag_adder_form">
      <select name="tag_id" size="1" title="Schlagwort aussuchen">
        <option>-</option>';

        foreach ($motion->consultation->tags as $tag) {
            if (!in_array($tag->id, $used_tag_ids)) {
                echo '<option value="' . IntVal($tag->id) . '">' . Html::encode($tag->name) . '</option>';
            }
        }
        echo '</select>
    <button class="btn btn-primary" type="submit" name="' . Html::encode(AntiXSS::createToken("add_tag")) . '"
                                        style="margin: 0; margin-top: -10px;">Hinzufügen
                                </button>
                            </form>
                            <script>
                                $(function () {

                                })
                            </script>
                        </td>
                    </tr>
                            ';
        $layout->addOnLoadJS(
            '$(".tag_adder_holder").click(function (ev) {
         ev.preventDefault();
         $(this).hide();
         $("#tag_adder_form").show();
       });'
        );

    } elseif (count($motion->tags) > 0) {
        echo '<tr>
       <th>' . (count($motion->tags) > 1 ? "Themenbereiche" : "Themenbereich") . '</th>
       <td>';

        $tags = array();
        foreach ($motion->tags as $tag) {
            $tags[] = $tag->title;
        }
        echo Html::encode(implode(", ", $tags));

        echo '</td></tr>';

    }
    /*
    if ($motion->abgeleitetVon) {
                    ?>
                    <tr>
                        <th>Ersetzt diesen Antrag:</th>
                        <td><?php echo CHtml::link($antrag->abgeleitetVon->revision_name . " - " .
    $antrag->abgeleitetVon->name, $this->createUrl("antrag/anzeige", array("antrag_id" => $antrag->abgeleitetVon->id)));
    ?> </td>
                    </tr>
                <?php }
    */


    echo '</table>

            <div class="hidden-desktop">
                <div style="width: 49%; display: inline-block; text-align: center; padding-top: 25px;">
                    <a href="' . Html::a(UrlHelper::createUrl("motion/pdf", ['motionId' => $motion->id])) . '"
                       class="btn" style="color: black;"><i class="icon-pdf"></i> PDF-Version</a>
                </div>';

    $policy = $motion->consultation->getAmendmentPolicy();
    if ($policy->checkCurUserHeuristically()) {
        echo '<div style="width: 49%; display: inline-block; text-align: center; padding-top: 25px;">
            <a href="' . Html::encode(UrlHelper::createUrl("amendment/neu", ['motionId' => $motion->id])) . '"
               class="btn btn-danger" style="color: white;"><i class="icon-aender-stellen"></i> ' .
            Html::encode($wording->get("Änderungsantrag stellen")) . '</a>
        </div>';
    }
    echo '</div></div>';
}

echo '</div>';


echo '<div class="antrags_text_holder';
if ($motion->consultation->getSettings()->lineLength > 80) {
    echo " smallFont";
}
echo '"><h3>';


$currUserId = (\Yii::$app->user->isGuest ? 0 : \Yii::$app->user->id);
$supporters = $motion->supporters;
$likes      = $motion->getLikes();
$dislikes   = $motion->getDislikes();
$enries     = (count($likes) > 0 || count($dislikes) > 0);

//$supportPolicy = $motion->consultation->getSupportPolicy() // @TODO
//$kann_unterstuetzen           = $unterstuetzen_policy->checkCurUserHeuristically();
//$kann_nicht_unterstuetzen_msg = $unterstuetzen_policy->getPermissionDeniedMsg();
$canSupport     = false;
$cantSupportMsg = "";
foreach ($motion->supporters as $supp) {
    if ($supp->role == MotionSupporter::ROLE_INITIATOR && $supp->userId == $currUserId) {
        $canSupport = false;
    }
}

if (count($supporters) > 0) {
    echo '<h2>UnterstützerInnen</h2>
    <div class="content">';

    echo "<strong>UnterstützerInnen:</strong><br>";
    if (count($supporters) > 0) {
        echo '<ul>';
        foreach ($supporters as $p) {
            echo '<li>';
            if ($p->id == $currUserId) {
                echo '<span class="label label-info">Du!</span> ';
            }
            echo Html::encode($supp->getNameWithOrga());
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<em>keine</em><br>';
    }
    echo "<br>";
    echo '</div>';
}

if ($enries || $canSupport || $cantSupportMsg != "") {
    echo '<h2>Zustimmung</h2>
    <div class="content">';

    if (count($likes) > 0) {
        echo "<strong>Zustimmung von:</strong><br>";
        echo '<ul>';
        foreach ($likes as $supp) {
            echo '<li>';
            if ($p->id == $currUserId) {
                echo '<span class="label label-info">Du!</span> ';
            }
            echo Html::encode($supp->name);
            echo '</li>';
        }
        echo '</ul>';
        echo "<br>";
    }

    if (count($dislikes) > 0) {
        echo "<strong>Abgelehnt von:</strong><br>";
        echo '<ul>';
        foreach ($dislikes as $p) {
            echo '<li>';
            if ($p->id == $currUserId) {
                echo '<span class="label label-info">Du!</span> ';
            }
            echo Html::encode($supp->name);
            echo '</li>';
        }
        echo '</ul>';
        echo "<br>";
    }
    echo '</div>';

    if ($canSupport) {
        echo Html::beginForm();

        echo "<div style='text-align: center; margin-bottom: 20px;'>";
        switch ($supportStatus) {
            case MotionSupporter::ROLE_INITIATOR:
                break;
            case MotionSupporter::ROLE_LIKE:
                //$this->widget('bootstrap.widgets.TbButton', array('buttonType' => 'submit',
                //'label' => 'Zurückziehen', 'icon' => 'icon-remove',
                //'htmlOptions' => array('name' => AntiXSS::createToken('dochnicht'))));
                break;
            case MotionSupporter::ROLE_DISLIKE:
                //$this->widget('bootstrap.widgets.TbButton', array('buttonType' => 'submit',
                //'label' => 'Zurückziehen', 'icon' => 'icon-remove',
                //'htmlOptions' => array('name' => AntiXSS::createToken('dochnicht'))));
                break;
            default:
                echo '<div style="display: inline-block; width: 49%; text-align: center;">';
                //$this->widget('bootstrap.widgets.TbButton', array('buttonType' => 'submit',
                //'type' => 'success', 'label' => 'Zustimmen', 'icon' => 'icon-thumbs-up',
                //'htmlOptions' => array('name' => AntiXSS::createToken('mag'))));
                echo '</div>';
        }
        echo "</div>";
        echo Html::endForm();
    } else {
        /*
        Yii::app()->user->setFlash('warning', 'Um diesen Antrag unterstützen zu können, musst du '
        . CHtml::link("dich einzuloggen", $this->createUrl("veranstaltung/login")) . '.');
        $this->widget('bootstrap.widgets.TbAlert', array(
            'block' => true,
            'fade'  => true,
        ));
        */
        if ($cantSupportMsg != "") {
            echo '<div class="alert alert-danger" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                <span class="sr-only">Error:</span>
                ' . Html::encode($cantSupportMsg) . '
            </div>';
        }
    }
}

if (count($amendments) > 0 || $motion->consultation->getAmendmentPolicy()->getPolicyID() != IPolicy::POLICY_NOBODY) {
    echo '<h2>' . $wording->get("Änderungsanträge") . '</h2>
    <div class="content">';

    if (count($amendments) > 0) {
        echo '<ul class="amendments">';
        foreach ($amendments as $amend) {
            echo '<li>';
            $aename = $amend->statusString;
            if ($aename == "") {
                $aename = $amend->id;
            }
            $amendLink  = UrlHelper::createUrl(
                'amendment/view',
                [
                    'motionId'    => $motion->id,
                    'amendmentId' => $amend->id
                ]
            );
            $amendStati = Amendment::getStati();
            echo Html::a($aename, $amendLink);
            echo " (" . Html::encode($amendStati[$amend->status]) . ")";
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<em>keine</em>';
    }

    echo '</div>';
}


if ($motion->consultation->getSettings()->commentWholeMotions) {
    echo '<h2>Kommentare</h2>';

    $comments = array();
    foreach ($motion->comments as $comm) {
        if ($comm->paragraph == -1 && $comm->status != MotionComment::STATUS_DELETED) {
            $comments[] = $comm;
        }
    }

    echo $this->render(
        'showComments',
        [
            'motion'       => $motion,
            'paragraphNo'  => -1,
            'commDelLink'  => $commentDelLink,
            'jsProtection' => $jsProtection,
            'hiddens'      => $hiddens,
            'comments'     => $comments,
        ]
    );

}

if (!$motion->consultation->site->getBehaviorClass()->isLoginForced()) {
    $layout->addOnLoadJS(
        '$("#socialshareprivacy").socialSharePrivacy({
        css_path: "/socialshareprivacy/socialshareprivacy.css"
    });'
    );
}
$layout->addOnLoadJS(
    '$(".absatz_text.orig .text .zeilennummer").each(function () {
        $(this).attr("data-zeilennummer", $(this).text());
    });
    $(".row-absatz").each(function () {
        var $absatz = $(this);
        $absatz.find("ul.lesezeichen li.aenderungsantrag").each(function () {
            var $aenderungsantrag = $(this),
                marker_offset = $aenderungsantrag.offset().top,
                first_line = $aenderungsantrag.data("first-line"),
                $lineel = $absatz.find(".zeilennummer[data-zeilennummer=" + first_line + "]");
            if ($lineel.length == 0) {
                // Ergänzung am Ende des Absatzes
                $lineel = $absatz.find(".zeilennummer").last();
            }
            var lineel_offset = $lineel.offset().top;
            if ((marker_offset + 10) < lineel_offset) {
                $aenderungsantrag.css("margin-top", (lineel_offset - (marker_offset + 10)) + "px");
            }
        });
    });'
);
