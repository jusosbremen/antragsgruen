<?php

namespace app\models\sectionTypes;

use app\components\diff\AmendmentDiffMerger;
use app\components\diff\AmendmentSectionFormatter;
use app\components\diff\Diff;
use app\components\diff\Engine;
use app\components\HTMLTools;
use app\components\latex\Exporter;
use app\components\LineSplitter;
use app\components\opendocument\Text;
use app\components\UrlHelper;
use app\controllers\Base;
use app\models\db\Amendment;
use app\models\db\AmendmentSection;
use app\models\db\MotionSection;
use app\models\exceptions\FormError;
use app\models\forms\CommentForm;
use app\views\pdfLayouts\IPDFLayout;
use yii\helpers\Html;
use yii\web\View;

class TextSimple extends ISectionType
{

    /**
     * @return string
     */
    public function getMotionFormField()
    {
        return $this->getTextMotionFormField(false);
    }

    /**
     * @return string
     */
    public function getAmendmentFormField()
    {
        $this->section->consultationSetting->maxLen = 0; // @TODO Dirty Hack
        if ($this->section->consultationSetting->motionType->amendmentMultipleParagraphs) {
            return $this->getTextAmendmentFormField(false, $this->section->dataRaw);
        } else {
            return $this->getTextAmendmentFormFieldSingleParagraph();
        }
    }

    /**
     * @return string
     */
    public function getTextAmendmentFormFieldSingleParagraph()
    {
        /** @var AmendmentSection $amSection */
        $amSection = $this->section;
        $moSection = $amSection->getOriginalMotionSection();
        $moParas = HTMLTools::sectionSimpleHTML($moSection->data, false);

        if ($amSection->isNewRecord) {
            $amParas = $moParas;
        } else {
            $amParas = $amSection->diffToOrigParagraphs($moParas);
        }

        $type = $this->section->consultationSetting;
        $str  = '<div class="label">' . Html::encode($type->title) . '</div>';
        $str .= '<div class="texteditorBox">';
        foreach ($moParas as $paraNo => $moPara) {
            $nameBase = 'sections[' . $type->id . '][' . $paraNo . ']';
            $htmlId   = 'sections_' . $type->id . '_' . $paraNo;
            $holderId = 'section_holder_' . $type->id . '_' . $paraNo;

            $str .= '<div class="form-group wysiwyg-textarea single-paragraph" id="' . $holderId . '"';
            $str .= ' data-maxLen="' . $type->maxLen . '" data-fullHtml="0"';
            $str .= '><label for="' . $htmlId . '" class="hidden">' . Html::encode($type->title) . '</label>';

            $str .= '<textarea name="' . $nameBase . '[raw]" class="raw" id="' . $htmlId . '" ' .
                'title="' . Html::encode($type->title) . '"></textarea>';
            $str .= '<textarea name="' . $nameBase . '[consolidated]" class="consolidated" ' .
                'title="' . Html::encode($type->title) . '"></textarea>';
            $str .= '<div class="texteditor" data-track-changed="1" id="' . $htmlId . '_wysiwyg" ' .
                'title="' . Html::encode($type->title) . '">';
            $str .= $moPara;
            $str .= '</div>';

            $str .= '</div>';
        }
        $str .= '</div>';

        return $str;
    }

    /**
     * @param $data
     * @throws FormError
     */
    public function setMotionData($data)
    {
        $this->section->dataRaw = $data;
        $this->section->data    = HTMLTools::cleanSimpleHtml($data);
    }

    /**
     * @param string $data
     * @throws FormError
     */
    public function setAmendmentData($data)
    {
        /** @var AmendmentSection $section */
        $section          = $this->section;
        $section->data    = HTMLTools::cleanSimpleHtml($data['consolidated']);
        $section->dataRaw = $data['raw'];
    }

    /**
     * @return string
     */
    public function getSimple()
    {
        $sections = HTMLTools::sectionSimpleHTML($this->section->data);
        $str      = '';
        foreach ($sections as $section) {
            $str .= '<div class="paragraph"><div class="text">' . $section . '</div></div>';
        }
        return $str;
    }


    /**
     * @param IPDFLayout $pdfLayout
     * @param \TCPDF $pdf
     * @throws \app\models\exceptions\Internal
     */
    public function printMotionToPDF(IPDFLayout $pdfLayout, \TCPDF $pdf)
    {
        /** @var MotionSection $section */
        $section = $this->section;

        if (!$pdfLayout->isSkippingSectionTitles($this->section)) {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->writeHTML('<h3>' . $this->section->consultationSetting->title . '</h3>');
        }

        $lineLength = $section->consultationSetting->motionType->consultation->getSettings()->lineLength;
        $linenr     = $section->getFirstLineNumber();
        $textSize   = ($lineLength > 70 ? 10 : 11);
        if ($section->consultationSetting->fixedWidth) {
            $pdf->SetFont('Courier', '', $textSize);
        } else {
            $pdf->SetFont('helvetica', '', $textSize);
        }
        $pdf->Ln(7);

        $hasLineNumbers = $section->consultationSetting->lineNumbers;
        if ($section->consultationSetting->fixedWidth || $hasLineNumbers) {
            $paragraphs = $section->getTextParagraphObjects($hasLineNumbers);
            foreach ($paragraphs as $paragraph) {
                $linesArr = [];
                foreach ($paragraph->lines as $line) {
                    $linesArr[] = str_replace('###LINENUMBER###', '', $line);
                }

                if ($hasLineNumbers) {
                    $lineNos = [];
                    for ($i = 0; $i < count($paragraph->lines); $i++) {
                        $lineNos[] = $linenr++;
                    }
                    $text2 = implode('<br>', $lineNos);
                } else {
                    $text2 = '';
                }

                $y = $pdf->getY();
                $pdf->writeHTMLCell(12, '', 12, $y, $text2, 0, 0, 0, true, '', true);
                $pdf->writeHTMLCell(173, '', 24, '', implode('<br>', $linesArr), 0, 1, 0, true, '', true);

                $pdf->Ln(7);
            }
        } else {
            $paras = $section->getTextParagraphs();
            foreach ($paras as $para) {
                $y = $pdf->getY();
                $pdf->writeHTMLCell(12, '', 12, $y, '', 0, 0, 0, true, '', true);
                $pdf->writeHTMLCell(173, '', 24, '', $para, 0, 1, 0, true, '', true);

                $pdf->Ln(7);
            }
        }
    }

    /**
     * @param IPDFLayout $pdfLayout
     * @param \TCPDF $pdf
     */
    public function printAmendmentToPDF(IPDFLayout $pdfLayout, \TCPDF $pdf)
    {
        /** @var AmendmentSection $section */
        $section = $this->section;

        if (!$pdfLayout->isSkippingSectionTitles($this->section)) {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->writeHTML('<h3>' . $this->section->consultationSetting->title . '</h3>');
        }

        $formatter  = new AmendmentSectionFormatter($section, Diff::FORMATTING_INLINE);
        $diffGroups = $formatter->getGroupedDiffLinesWithNumbers();
        if (count($diffGroups) > 0) {
            $html = static::formatDiffGroup($diffGroups);
            $pdf->writeHTMLCell(170, '', 27, '', $html, 0, 1, 0, true, '', true);
            $pdf->Ln(7);
        }
    }

    /**
     * @param Base $controller
     * @param CommentForm $commentForm
     * @param int[] $openedComments
     * @param bool $consolidatedAmendments
     * @return string
     */
    public function showMotionView(Base $controller, $commentForm, $openedComments, $consolidatedAmendments)
    {
        $view   = new View();
        $script = ($consolidatedAmendments ? 'showSimpleTextSectionInline' : 'showSimpleTextSection');
        return $view->render(
            '@app/views/motion/' . $script,
            [
                'section'        => $this->section,
                'openedComments' => $openedComments,
                'commentForm'    => $commentForm,
            ],
            $controller
        );
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return ($this->section->data == '');
    }

    /**
     * @param array $diffGroups
     * @param string $wrapStart
     * @param string $wrapEnd
     * @param int $firstLineOfSection
     * @return string
     */
    public static function formatDiffGroup($diffGroups, $wrapStart = '', $wrapEnd = '', $firstLineOfSection = -1)
    {
        $out = '';
        foreach ($diffGroups as $diff) {
            $text      = $diff['text'];
            $hasInsert = (mb_strpos($text, '<ins>') !== false || mb_strpos($text, 'class="inserted"') !== false);
            $hasDelete = (mb_strpos($text, '<del>') !== false || mb_strpos($text, 'class="deleted"') !== false);
            $out .= $wrapStart;
            $out .= '<h4 class="lineSummary">';
            if ($diff['newLine']) {
                if (($hasInsert && $hasDelete) || (!$hasInsert && !$hasDelete)) {
                    $out .= 'Nach Zeile #LINETO#:';
                } elseif ($hasDelete) {
                    $out .= 'Nach Zeile #LINETO# löschen:';
                } elseif ($hasInsert) {
                    if ($diff['lineTo'] < $firstLineOfSection) {
                        $out .= str_replace('#LINE#', ($diff['lineTo'] + 1), 'Vor Zeile #LINE# einfügen:');
                    } else {
                        $out .= 'Nach Zeile #LINETO# einfügen:';
                    }
                }
            } elseif ($diff['lineFrom'] == $diff['lineTo']) {
                if (($hasInsert && $hasDelete) || (!$hasInsert && !$hasDelete)) {
                    $out .= 'In Zeile #LINETO#:';
                } elseif ($hasDelete) {
                    $out .= 'In Zeile #LINETO# löschen:';
                } elseif ($hasInsert) {
                    $out .= 'In Zeile #LINETO# einfügen:';
                }
            } else {
                if (($hasInsert && $hasDelete) || (!$hasInsert && !$hasDelete)) {
                    $out .= 'Von Zeile #LINEFROM# bis #LINETO#:';
                } elseif ($hasDelete) {
                    $out .= 'Von Zeile #LINEFROM# bis #LINETO# löschen:';
                } elseif ($hasInsert) {
                    $out .= 'Von Zeile #LINEFROM# bis #LINETO# einfügen:';
                }
            }
            $out = str_replace(['#LINETO#', '#LINEFROM#'], [$diff['lineTo'], $diff['lineFrom']], $out) . '</h4>';
            if ($diff['text'][0] != '<') {
                $out .= '<p>' . $diff['text'] . '</p>';
            } else {
                $out .= $diff['text'];
            }
            $out .= $wrapEnd;
        }
        return $out;
    }

    /**
     * @return string
     */
    public function getMotionPlainText()
    {
        return HTMLTools::toPlainText($this->section->data);
    }

    /**
     * @return string
     */
    public function getAmendmentPlainText()
    {
        return HTMLTools::toPlainText($this->section->data);
    }

    /**
     * @param string[] $lines
     * @return string
     */
    public static function getMotionLinesToTeX($lines)
    {
        $str = implode('###LINEBREAK###', $lines);
        $str = str_replace('###FORCELINEBREAK######LINEBREAK###', '###FORCELINEBREAK###', $str);
        $str = Exporter::encodeHTMLString($str);
        $str = str_replace('###LINENUMBER###', '', $str);
        $str = str_replace('###LINEBREAK###', "\\linebreak\n", $str);
        $str = str_replace('###FORCELINEBREAK###\linebreak', '\newline', $str);
        return $str;
    }

    /**
     * @return string
     */
    public function getMotionTeX()
    {
        $tex = '';
        if ($this->isEmpty()) {
            return $tex;
        }

        /** @var MotionSection $section */
        $section = $this->section;

        $hasLineNumbers = $section->consultationSetting->lineNumbers;

        $title = Exporter::encodePlainString($section->consultationSetting->title);
        if ($title == \Yii::t('motion', 'Antragstext') && $section->motion->agendaItem) {
            $title = $section->motion->title;
        }
        $tex .= '\subsection*{\AntragsgruenSection ' . $title . '}' . "\n";

        if ($section->consultationSetting->fixedWidth || $hasLineNumbers) {
            if ($hasLineNumbers) {
                $tex .= "\\linenumbers\n";
                $tex .= "\\resetlinenumber[" . $section->getFirstLineNumber() . "]\n";
            }

            $paragraphs = $section->getTextParagraphObjects($hasLineNumbers);
            foreach ($paragraphs as $paragraph) {
                $tex .= static::getMotionLinesToTeX($paragraph->lines) . "\n";
            }

            if ($hasLineNumbers) {
                $tex .= "\n\\nolinenumbers\n";
            }
        } else {
            $paras = $section->getTextParagraphs();
            foreach ($paras as $para) {
                $lines = LineSplitter::motionPara2lines($para, false, PHP_INT_MAX);
                $tex .= static::getMotionLinesToTeX($lines) . "\n";
            }
        }

        return $tex;
    }

    /**
     * @return string
     */
    public function getAmendmentTeX()
    {
        $tex = '';

        /** @var AmendmentSection $section */
        $section = $this->section;

        $formatter  = new AmendmentSectionFormatter($section, Diff::FORMATTING_CLASSES);
        $diffGroups = $formatter->getGroupedDiffLinesWithNumbers();

        if (count($diffGroups) > 0) {
            $title = Exporter::encodePlainString($section->consultationSetting->title);
            if ($title == \Yii::t('motion', 'Antragstext')) {
                $titPattern = 'Änderungsantrag zu #MOTION#';
                $title      = str_replace('#MOTION#', $section->amendment->motion->titlePrefix, $titPattern);
            }

            $tex .= '\subsection*{\AntragsgruenSection ' . $title . '}' . "\n";
            $html = TextSimple::formatDiffGroup($diffGroups, '', '<br><br>');
            for ($i = 0; $i < 100; $i++) {
                $tex .= Exporter::encodeHTMLString($html);
            }
        }

        return $tex;
    }

    /**
     * @return string
     */
    public function getMotionODS()
    {
        return $this->section->data;
    }

    /**
     * @return string
     */
    public function getAmendmentODS()
    {
        /** @var AmendmentSection $section */
        $section    = $this->section;
        $formatter  = new AmendmentSectionFormatter($section, Diff::FORMATTING_CLASSES);
        $diffGroups = $formatter->getGroupedDiffLinesWithNumbers();
        $diff       = static::formatDiffGroup($diffGroups);
        $diff       = str_replace('<h4', '<br><h4', $diff);
        $diff       = str_replace('</h4>', '</h4><br>', $diff);
        $diff       = str_replace('###FORCELINEBREAK###', '<br>', $diff);
        if (mb_substr($diff, 0, 4) == '<br>') {
            $diff = mb_substr($diff, 4);
        }
        return $diff;
    }

    /**
     * @param Text $odt
     * @return mixed
     */
    public function printMotionToODT(Text $odt)
    {
        $section = $this->section;
        /** @var MotionSection $section */
        $odt->addHtmlTextBlock('<h2>' . Html::encode($section->consultationSetting->title) . '</h2>', false);
        if ($section->consultationSetting->lineNumbers) {
            $paragraphs = $section->getTextParagraphObjects(true, false, false);
            foreach ($paragraphs as $paragraph) {
                $html = implode('<br>', $paragraph->lines);
                $html = str_replace('###LINENUMBER###', '', $html);
                $html = str_replace('###FORCELINEBREAK###', '', $html);
                if (mb_substr($html, 0, 1) != '<') {
                    $html = '<p>' . $html . '</p>';
                }

                $odt->addHtmlTextBlock($html, true);
            }
        } else {
            $paras = $section->getTextParagraphs();
            foreach ($paras as $para) {
                $lines = LineSplitter::motionPara2lines($para, false, PHP_INT_MAX);
                $odt->addHtmlTextBlock(implode('<br>', $lines), false);
            }
        }
    }

    /**
     * @param Text $odt
     * @return mixed
     */
    public function printAmendmentToODT(Text $odt)
    {
        $odt->addHtmlTextBlock('<h2>' . Html::encode($this->section->consultationSetting->title) . '</h2>', false);
        $odt->addHtmlTextBlock('[ÄNDERUNG]', false); // @TODO
    }

    /**
     * @param $text
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function matchesFulltextSearch($text)
    {
        $data = strip_tags($this->section->data);
        return (mb_stripos($data, $text) !== false);
    }


    private static $CHANGESET_COUNTER = 0;

    /**
     * @param array $changeset
     * @return string
     */
    public function getMotionTextWithInlineAmendments(&$changeset)
    {
        /** @var MotionSection $section */
        $section = $this->section;
        $merger  = new AmendmentDiffMerger();
        $merger->initByMotionSection($section);
        $merger->addAmendingSections($section->amendingSections);
        $merger->mergeParagraphs();
        $paragraphs = $section->getTextParagraphObjects(false, false, false);

        /** @var Amendment[] $amendmentsById */
        $amendmentsById = [];
        foreach ($section->amendingSections as $sect) {
            $amendmentsById[$sect->amendmentId] = $sect->amendment;
        }

        $out = '';
        foreach (array_keys($paragraphs) as $paragraphNo) {
            $groupedParaData = $merger->getGroupedParagraphData($paragraphNo);
            foreach ($groupedParaData as $part) {
                $text = $part['text'];

                if ($part['amendment'] > 0) {
                    $amendment = $amendmentsById[$part['amendment']];
                    $cid       = static::$CHANGESET_COUNTER++;
                    if (!isset($changeset[$amendment->id])) {
                        $changeset[$amendment->id] = [];
                    }
                    $changeset[$amendment->id][] = $cid;
                    $changeData                  = $amendment->getLiteChangeData($cid);

                    $text = str_replace('<ins>', '<ins class="ice-ins ice-cts appendHint"' . $changeData . '">', $text);
                    $text = str_replace('<del>', '<del class="ice-del ice-cts appendHint"' . $changeData . '">', $text);
                }

                $out .= $text;
            }

            $colliding = $merger->getCollidingParagraphGroups($paragraphNo);
            foreach ($colliding as $amendmentId => $paraText) {
                $amendment = $amendmentsById[$amendmentId];
                $text      = '<p><strong>' . 'Kollidierender Änderungsantrag: ';
                $text .= Html::a($amendment->getTitle(), UrlHelper::createAmendmentUrl($amendment));
                $text .= '</strong></p>';

                foreach ($paraText as $group) {
                    if ($group[1] == Engine::UNMODIFIED) {
                        $text .= $group[0];
                    } elseif ($group[1] == Engine::INSERTED) {
                        $cid                         = static::$CHANGESET_COUNTER++;
                        $changeset[$amendment->id][] = $cid;
                        $changeData                  = $amendment->getLiteChangeData($cid);
                        $text .= '<ins class="ice-ins ice-cts appendHint"' . $changeData . '">';
                        $text .= $group[0] . '</ins>';
                    } elseif ($group[1] == Engine::DELETED) {
                        $cid                         = static::$CHANGESET_COUNTER++;
                        $changeset[$amendment->id][] = $cid;
                        $changeData                  = $amendment->getLiteChangeData($cid);
                        $text .= '<del class="ice-del ice-cts appendHint"' . $changeData . '">';
                        $text .= $group[0] . '</del>';
                    }
                }
                $out .= $text;
            }
        }

        $out = str_replace('</ul><ul>', '', $out);
        /*
        $out = preg_replace_callback('/<li>(.*)<\/li>/siuU', function ($matches) {
            $inner  = $matches[1];
            $last6  = mb_substr($inner, mb_strlen($inner) - 6);
            $numIns = mb_substr_count($inner, '<ins');
            $numDel = mb_substr_count($inner, '<del');
            if (mb_stripos($inner, '<ins') === 0 && $last6 == '</ins>' && $numIns == 1 && $numDel == 0) {
                $ret = str_replace('</ins>', '', $matches[0]);
                $ret = str_replace('<li><ins', '<li', $ret);
                return $ret;
            } elseif (mb_stripos($inner, '<del') === 0 && $last6 == '</del>' && $numIns == 0 && $numDel == 1) {
                $ret = str_replace('</del>', '', $matches[0]);
                $ret = str_replace('<li><del', '<li', $ret);
                return $ret;
            } else {
                return $matches[0];
            }
        }, $out);
        */

        return $out;
    }
}
