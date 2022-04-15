<?php

namespace app\models\sectionTypes;

use app\components\diff\{AmendmentSectionFormatter, Diff, DiffRenderer};
use app\components\{HashedStaticCache, HTMLTools, LineSplitter, UrlHelper};
use app\components\latex\{Content, Exporter};
use app\models\db\{AmendmentSection, Consultation, ConsultationMotionType, Motion, MotionSection};
use app\models\forms\CommentForm;
use app\views\pdfLayouts\{IPDFLayout, IPdfWriter};
use yii\helpers\Html;
use yii\web\View;
use CatoTH\HTML2OpenDocument\Text as ODTText;

class TextSimple extends Text
{
    /** @var bool */
    private $forceMultipleParagraphs = false;

    public function forceMultipleParagraphMode(bool $active): void
    {
        $this->forceMultipleParagraphs = $active;
    }

    public function getMotionFormField(): string
    {
        return $this->getTextMotionFormField(false, !!$this->section->getSettings()->fixedWidth);
    }

    public function getAmendmentFormField(): string
    {
        $this->section->getSettings()->maxLen = 0; // @TODO Dirty Hack
        $fixedWidth                           = !!$this->section->getSettings()->fixedWidth;

        $multipleParagraphs = $this->section->getSettings()->motionType->amendmentMultipleParagraphs;
        if ($this->forceMultipleParagraphs) {
            $multipleParagraphs = ConsultationMotionType::AMEND_PARAGRAPHS_MULTIPLE;
        }

        if ($multipleParagraphs === ConsultationMotionType::AMEND_PARAGRAPHS_MULTIPLE) {
            /** @var AmendmentSection $section */
            $section = $this->section;

            if ($section->getAmendment() && $section->getAmendment()->globalAlternative) {
                $amendmentHtml = $section->data;
            } else {
                $diff = new Diff();
                if ($section->getOriginalMotionSection()) {
                    $origParas = HTMLTools::sectionSimpleHTML($section->getOriginalMotionSection()->getData());
                } else {
                    $origParas = [];
                }
                $amendParas = HTMLTools::sectionSimpleHTML($section->data);
                $amDiffSections = $diff->compareHtmlParagraphs($origParas, $amendParas, DiffRenderer::FORMATTING_ICE);
                $amendmentHtml = implode('', $amDiffSections);
            }

            return $this->getTextAmendmentFormField(false, $amendmentHtml, $fixedWidth);
        } else {
            // On server side, we do not make a difference between single paragraph and single change mode, as this is enforced on client-side only
            $singleChange = ($multipleParagraphs === ConsultationMotionType::AMEND_PARAGRAPHS_SINGLE_CHANGE);
            return $this->getTextAmendmentFormFieldSingleParagraph($fixedWidth, $singleChange);
        }
    }

    public function getTextAmendmentFormFieldSingleParagraph(bool $fixedWidth, bool $singleChange): string
    {
        /** @var AmendmentSection $amSection */
        $amSection = $this->section;
        $moSection = $amSection->getOriginalMotionSection();
        $moParas   = HTMLTools::sectionSimpleHTML($moSection->getData(), false);

        $amParas          = $moParas;
        $changedParagraph = -1;
        foreach ($amSection->diffStrToOrigParagraphs($moParas, false, DiffRenderer::FORMATTING_ICE) as $paraNo => $para) {
            $amParas[$paraNo] = $para;
            $changedParagraph = $paraNo;
        }

        $type = $this->section->getSettings();
        $str  = '<div class="label">' . Html::encode($type->title) . '</div>';
        $str  .= '<div class="texteditorBox" data-section-id="' . $amSection->sectionId . '" ' .
            'data-changed-para-no="' . $changedParagraph . '">';
        foreach ($moParas as $paraNo => $moPara) {
            $nameBase = 'sections[' . $type->id . '][' . $paraNo . ']';
            $htmlId   = 'sections_' . $type->id . '_' . $paraNo;
            $holderId = 'section_holder_' . $type->id . '_' . $paraNo;

            $str .= '<div class="form-group wysiwyg-textarea single-paragraph" id="' . $holderId . '"';
            $str .= ' data-max-len="' . $type->maxLen . '" data-full-html="0"';
            $str .= ' data-original="' . Html::encode($moPara) . '"';
            $str .= ' data-paragraph-no="' . $paraNo . '"';
            $str .= '><label for="' . $htmlId . '" class="hidden">' . Html::encode($type->title) . '</label>';

            $str .= '<textarea name="' . $nameBase . '[raw]" class="raw" id="' . $htmlId . '" ' .
                'title="' . Html::encode($type->title) . '"></textarea>';
            $str .= '<textarea name="' . $nameBase . '[consolidated]" class="consolidated" ' .
                'title="' . Html::encode($type->title) . '"></textarea>';
            $str .= '<div class="texteditor motionTextFormattings';
            if ($fixedWidth) {
                $str .= ' fixedWidthFont';
            }
            $str .= '" data-track-changed="1" data-enter-mode="br" data-no-strike="1" id="' . $htmlId . '_wysiwyg" ' .
                'title="' . Html::encode($type->title) . '">';
            $str .= $amParas[$paraNo];
            $str .= '</div>';

            $str .= '<div class="oneChangeHint hidden"><div class="alert alert-danger"><p>';
            $str .= \Yii::t('amend', 'err_one_change');
            $str .= '</p></div></div>';

            $str .= '<div class="modifiedActions"><button type="button" class="btn btn-link revert">';
            $str .= \Yii::t('amend', 'revert_changes');
            $str .= '</button></div>';

            $str .= '</div>';
        }
        $str .= '</div>';

        return $str;
    }

    /**
     * @param string $data
     */
    public function setMotionData($data, bool $allowForbidden = false): void
    {
        $type = $this->section->getSettings();
        $forbiddenFormattings = ($allowForbidden ? [] : $type->getForbiddenMotionFormattings());
        $this->section->dataRaw = $data;
        $this->section->setData(HTMLTools::cleanSimpleHtml($data, $forbiddenFormattings));
    }

    public function deleteMotionData(): void
    {
        $this->section->setData('');
        $this->section->dataRaw = null;
    }

    /**
     * @param array $data
     * @throws \app\models\exceptions\Internal
     */
    public function setAmendmentData($data): void
    {
        /** @var AmendmentSection $section */
        $section = $this->section;
        $post    = \Yii::$app->request->post();

        $multipleParagraphs = $this->section->getSettings()->motionType->amendmentMultipleParagraphs;
        if ($this->forceMultipleParagraphs) {
            $multipleParagraphs = ConsultationMotionType::AMEND_PARAGRAPHS_MULTIPLE;
        }

        if ($multipleParagraphs === ConsultationMotionType::AMEND_PARAGRAPHS_MULTIPLE) {
            $section->data    = HTMLTools::stripEmptyBlockParagraphs(HTMLTools::cleanSimpleHtml($data['consolidated']));
            $section->dataRaw = $data['raw'];
        } else {
            // On server side, we do not make a difference between single paragraph and single change mode, as this is enforced on client-side only
            $moSection = $section->getOriginalMotionSection();
            $paras     = HTMLTools::sectionSimpleHTML($moSection->getData(), false);
            $parasRaw  = $paras;
            if ($post['modifiedParagraphNo'] !== '' && $post['modifiedSectionId'] == $section->sectionId) {
                $paraNo            = IntVal($post['modifiedParagraphNo']);
                $paras[$paraNo]    = $post['sections'][$section->sectionId][$paraNo]['consolidated'];
                $parasRaw[$paraNo] = $post['sections'][$section->sectionId][$paraNo]['raw'];
            }
            $section->data    = HTMLTools::cleanSimpleHtml(implode('', $paras));
            $section->dataRaw = implode('', $parasRaw);
        }
    }

    public function getSimple(bool $isRight, bool $showAlways = false): string
    {
        $sections = HTMLTools::sectionSimpleHTML($this->section->getData());
        $str      = '';
        foreach ($sections as $section) {
            $str .= '<div class="paragraph"><div class="text motionTextFormattings';
            if ($this->section->getSettings()->fixedWidth) {
                $str .= ' fixedWidthFont';
            }
            $str .= '">' . $section . '</div></div>';
        }
        return $str;
    }

    public function getMotionPlainHtml(): string
    {
        $html = $this->section->getData();
        $html = str_replace('<span class="underline">', '<span style="text-decoration: underline;">', $html);
        $html = str_replace('<span class="strike">', '<span style="text-decoration: line-through;">', $html);
        return $html;
    }

    public function getMotionPlainHtmlWithLineNumbers(): string
    {
        /** @var MotionSection $section */
        $section = $this->section;
        $paragraphBegin = '<div class="text motionTextFormattings textOrig';
        if ($section->getSettings()->fixedWidth) {
            $paragraphBegin .= ' fixedWidthFont';
        }
        $paragraphBegin .= '">';
        $paragraphEnd = '</div>';

        if ($this->isEmpty()) {
            return '';
        }
        if (!$section->getSettings()->lineNumbers) {
            return $paragraphBegin . $this->getMotionPlainHtml() . $paragraphEnd;
        }

        $lineNo    = $section->getFirstLineNumber();
        $paragraphs     = $section->getTextParagraphObjects(true, true, true);
        $str = '';
        foreach ($paragraphs as $paragraph) {
            $str .= $paragraphBegin;
            foreach ($paragraph->lines as $i => $line) {
                $lineNoStr = '<span class="lineNumber" data-line-number="' . $lineNo++ . '" aria-hidden="true"></span>';
                $line = str_replace('###LINENUMBER###', $lineNoStr, $line);
                $line = str_replace('<br>', '', $line);
                $first3 = substr($line, 0, 3);
                if ($i > 0 && !in_array($first3, ['<ol', '<ul', '<p>', '<di'])) {
                    $str .= '<br>';
                }
                $str .= $line;
            }
            $str .= $paragraphEnd;
        }

        return $str;
    }

    private function getAmendmentPlainHtmlCalcText(AmendmentSection $section, int $firstLine, int $lineLength): string
    {
        $formatter = new AmendmentSectionFormatter();
        $formatter->setTextOriginal($section->getOriginalMotionSection()->getData());
        $formatter->setTextNew($section->data);
        $formatter->setFirstLineNo($firstLine);
        $diffGroups = $formatter->getDiffGroupsWithNumbers($lineLength, DiffRenderer::FORMATTING_INLINE);
        if (count($diffGroups) === 0) {
            return '';
        }

        return TextSimple::formatDiffGroup($diffGroups, '', '', $firstLine);
    }

    public function getAmendmentPlainHtml(bool $skipTitle = false): string
    {
        /** @var AmendmentSection $section */
        $section    = $this->section;
        $firstLine  = $section->getFirstLineNumber();
        $lineLength = $section->getCachedConsultation()->getSettings()->lineLength;

        if ($section->getAmendment()->globalAlternative) {
            $str = ($skipTitle ? '' : '<h3>' . Html::encode($section->getSettings()->title) . '</h3>');
            $str .= '<p><strong>' . \Yii::t('amend', 'global_alternative') . '</strong></p>';
            $str .= $section->data;
            return $str;
        } elseif ($skipTitle) {
            return $this->getAmendmentPlainHtmlCalcText($section, $firstLine, $lineLength);
        } else {
            $cacheDeps = [$section->getOriginalMotionSection()->getData(), $section->data, $firstLine, $lineLength, $section->getSettings()->title];
            $cached    = HashedStaticCache::getCache('getAmendmentPlainHtml', $cacheDeps);
            if (!$cached) {
                $cached = $this->getAmendmentPlainHtmlCalcText($section, $firstLine, $lineLength);
                if ($cached !== '') {
                    $cached = '<h3>' . Html::encode($section->getSettings()->title) . '</h3>' . $cached;
                    HashedStaticCache::setCache('getAmendmentPlainHtml', $cacheDeps, $cached);
                }
            }
            return $cached;
        }
    }

    /**
     * @throws \app\models\exceptions\Internal
     */
    public function getAmendmentFormattedGlobalAlternative(): string
    {
        /** @var AmendmentSection $section */
        $section = $this->section;

        $str = '<div id="section_' . $section->sectionId . '" class="motionTextHolder">';
        $str .= '<h3 class="green">' . Html::encode($section->getSettings()->title) . '</h3>';
        $str .= '<div id="section_' . $section->sectionId . '_0" class="paragraph lineNumbers">';

        $htmlSections = HTMLTools::sectionSimpleHTML($section->data);
        foreach ($htmlSections as $htmlSection) {
            $str .= '<div class="paragraph"><div class="text motionTextFormattings';
            if ($this->section->getSettings()->fixedWidth) {
                $str .= ' fixedWidthFont';
            }
            $str .= '">' . $htmlSection . '</div></div>';
        }

        $str .= '</div>';
        $str .= '</div>';

        return $str;
    }

    public function getAmendmentFormatted(string $sectionTitlePrefix = ''): string
    {
        /** @var AmendmentSection $section */
        $section = $this->section;

        if ($section->getAmendment()->globalAlternative) {
            return $this->getAmendmentFormattedGlobalAlternative();
        }

        $lineLength = $section->getCachedConsultation()->getSettings()->lineLength;
        $firstLine  = $section->getFirstLineNumber();

        $formatter = new AmendmentSectionFormatter();
        $formatter->setTextOriginal($section->getOriginalMotionSection()->getData());
        $formatter->setTextNew($section->data);
        $formatter->setFirstLineNo($firstLine);
        $diffGroups = $formatter->getDiffGroupsWithNumbers($lineLength, DiffRenderer::FORMATTING_CLASSES_ARIA);

        if (count($diffGroups) === 0) {
            return '';
        }

        if ($sectionTitlePrefix) {
            $sectionTitlePrefix .= ': ';
        }
        $title     = $sectionTitlePrefix . $section->getSettings()->title;
        $str = '<div id="section_' . $section->sectionId . '" class="motionTextHolder">';
        $str .= '<h3 class="green">' . Html::encode($title);
        $str .= '<div class="btn-group btn-group-xs greenHeaderDropDown amendmentTextModeSelector">
          <button type="button" class="btn btn-link dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="' . \Yii::t('amend', 'textmode_set') . '">
            <span class="sr-only">' . \Yii::t('amend', 'textmode_set') . '</span>
            <span class="glyphicon glyphicon-cog" aria-hidden="true"></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-right">
          <li class="selected"><a href="#" class="showOnlyChanges">' . \Yii::t('amend', 'textmode_only_changed') . '</a></li>
          <li><a href="#" class="showFullText">' . \Yii::t('amend', 'textmode_full_text') . '</a></li>
          </ul>';
        $str .= '</h3>';
        $str       .= '<div id="section_' . $section->sectionId . '_0" class="paragraph lineNumbers">';
        $wrapStart = '<section class="paragraph"><div class="text motionTextFormattings';
        if ($section->getSettings()->fixedWidth) {
            $wrapStart .= ' fixedWidthFont';
        }
        $wrapStart .= '">';
        $wrapEnd   = '</div></section>';
        if ($this->motionContext && $section->getAmendment() && $section->getAmendment()->motionId !== $this->motionContext->id) {
            $linkMotion = $section->getAmendment()->getMyMotion();
        } else {
            $linkMotion = null;
        }
        $str .= '<div class="onlyChangedText">';
        $str .= TextSimple::formatDiffGroup($diffGroups, $wrapStart, $wrapEnd, $firstLine, $linkMotion);
        $str .= '</div>';

        $str .= '<div class="fullMotionText text motionTextFormattings textOrig ';
        if ($section->getSettings()->fixedWidth) {
            $str .= 'fixedWidthFont ';
        }
        $str .= 'hidden">';
        $diffSections = $formatter->getDiffSectionsWithNumbers($lineLength, DiffRenderer::FORMATTING_CLASSES_ARIA);
        $lineNo = $firstLine;
        foreach ($diffSections as $diffSection) {
            $lineNumbers = substr_count($diffSection, '###LINENUMBER###');
            $str .= LineSplitter::replaceLinebreakPlaceholdersByMarkup($diffSection, $section->getSettings()->lineNumbers, $lineNo);
            $lineNo += $lineNumbers;
        }
        $str .= '</div>';

        $str       .= '</div>';
        $str       .= '</div>';

        return $str;
    }

    public function printMotionToPDF(IPDFLayout $pdfLayout, IPdfWriter $pdf): void
    {
        if ($this->isEmpty()) {
            return;
        }

        /** @var MotionSection $section */
        $section = $this->section;

        if ($section->getSettings()->printTitle) {
            $pdfLayout->printSectionHeading($section->getSettings()->title);
        }

        $pdf->printMotionSection($section);
    }

    public function printAmendmentToPDF(IPDFLayout $pdfLayout, IPdfWriter $pdf): void
    {
        /** @var AmendmentSection $section */
        $section = $this->section;
        if ($section->getAmendment()->globalAlternative) {
            if ($section->getSettings()->printTitle) {
                $pdfLayout->printSectionHeading($this->section->getSettings()->title);
                $pdf->ln(7);
            }

            $pdf->writeHTMLCell(170, '', 24, '', $section->data, 0, 1, 0, true, '', true);
        } else {
            $firstLine  = $section->getFirstLineNumber();
            $lineLength = $section->getCachedConsultation()->getSettings()->lineLength;

            $formatter = new AmendmentSectionFormatter();
            $formatter->setTextOriginal($section->getOriginalMotionSection()->getData());
            $formatter->setTextNew($section->data);
            $formatter->setFirstLineNo($firstLine);
            $diffGroups = $formatter->getDiffGroupsWithNumbers($lineLength, DiffRenderer::FORMATTING_INLINE);

            if (count($diffGroups) > 0) {
                if ($section->getSettings()->printTitle) {
                    $pdfLayout->printSectionHeading($this->section->getSettings()->title);
                    $pdf->ln(7);
                }

                $html               = static::formatDiffGroup($diffGroups);
                $replaces           = [];
                $replaces['<ins ']  = '<span ';
                $replaces['</ins>'] = '</span>';
                $replaces['<del ']  = '<span ';
                $replaces['</del>'] = '</span>';
                $html               = str_replace(array_keys($replaces), array_values($replaces), $html);

                // instead of <span class="strike"></span> TCPDF can only handle <s></s>
                // for striking through text
                $pattern = '/<span class="strike">(.*)<\/span>/iUs';
                $replace = '<s>${1}</s>';
                $html    = preg_replace($pattern, $replace, $html);

                $pdf->writeHTMLCell(170, '', 24, '', $html, 0, 1, 0, true, '', true);
            }
        }
        $pdf->ln(7);
    }

    /**
     * @param CommentForm|null $commentForm
     * @param int[] $openedComments
     * @return string
     */
    public function showMotionView(?CommentForm  $commentForm, array $openedComments): string
    {
        return (new View())->render(
            '@app/views/motion/showSimpleTextSection',
            [
                'section'        => $this->section,
                'openedComments' => $openedComments,
                'commentForm'    => $commentForm,
            ],
            \Yii::$app->controller
        );
    }

    public function isEmpty(): bool
    {
        return ($this->section->getData() === '');
    }

    /*
     * Workaround for https://github.com/CatoTH/antragsgruen/issues/137#issuecomment-305686868
     */
    public static function stripAfterInsertedNewlines(string $text): string
    {
        return preg_replace_callback('/((<br>\s*)+<\/ins>)(?<rest>.*)(?<end><\/[a-z]+>*)$/siu', function ($match) {
            $rest = $match['rest'];
            if (strpos($rest, '<') !== false) {
                return $match[0];
            } else {
                return '</ins>' . $match['end'];
            }
        }, $text);
    }

    public static function formatDiffGroup(array $diffGroups, string $wrapStart = '', string $wrapEnd = '', int $firstLineOfSection = -1, ?Motion $linkMotion = null): string
    {
        $out = '';
        foreach ($diffGroups as $diff) {
            $text      = $diff['text'];
            $text      = static::stripAfterInsertedNewlines($text);
            $hasInsert = $hasDelete = false;
            if (mb_strpos($text, 'class="inserted"') !== false) {
                $hasInsert = true;
            }
            if (mb_strpos($text, 'class="deleted"') !== false) {
                $hasDelete = true;
            }
            if (preg_match('/<ins( [^>]*)?>/siu', $text)) {
                $hasInsert = true;
            }
            if (preg_match('/<del( [^>]*)?>/siu', $text)) {
                $hasDelete = true;
            }
            $out            .= $wrapStart;
            $out            .= '<h4 class="lineSummary">';
            $isInsertedLine = (mb_strpos($diff['text'], '###LINENUMBER###') === false);
            if ($isInsertedLine) {
                if (($hasInsert && $hasDelete) || (!$hasInsert && !$hasDelete)) {
                    $out .= \Yii::t('diff', 'after_line');
                } elseif ($hasDelete) {
                    $out .= \Yii::t('diff', 'after_line_del');
                } elseif ($hasInsert) {
                    if ($diff['lineTo'] < $firstLineOfSection) {
                        $out .= str_replace('#LINE#', (string)($diff['lineTo'] + 1), \Yii::t('diff', 'pre_line_ins'));
                    } else {
                        $out .= \Yii::t('diff', 'after_line_ins');
                    }
                }
            } elseif ($diff['lineFrom'] == $diff['lineTo']) {
                if (($hasInsert && $hasDelete) || (!$hasInsert && !$hasDelete)) {
                    $out .= \Yii::t('diff', 'in_line');
                } elseif ($hasDelete) {
                    $out .= \Yii::t('diff', 'in_line_del');
                } elseif ($hasInsert) {
                    $out .= \Yii::t('diff', 'in_line_ins');
                }
            } else {
                if (($hasInsert && $hasDelete) || (!$hasInsert && !$hasDelete)) {
                    $out .= \Yii::t('diff', 'line_to');
                } elseif ($hasDelete) {
                    $out .= \Yii::t('diff', 'line_to_del');
                } elseif ($hasInsert) {
                    $out .= \Yii::t('diff', 'line_to_ins');
                }
            }
            $lineFrom = ($diff['lineFrom'] < $firstLineOfSection ? $firstLineOfSection : $diff['lineFrom']);
            $out      = str_replace(['#LINETO#', '#LINEFROM#'], [(string)$diff['lineTo'], (string)$lineFrom], $out);
            if ($linkMotion) {
                $link = UrlHelper::createMotionUrl($linkMotion);
                $out .= ' <span class="linkedMotion">(' . Html::a(Html::encode($linkMotion->getTitleWithPrefix()), $link) . ')</span>';
            }
            $out      .= ':</h4><div>';
            if ($text[0] != '<') {
                $out .= '<p>' . $text . '</p>';
            } else {
                $out .= $text;
            }
            $out .= '</div>';
            $out .= $wrapEnd;
        }

        $aria        = str_replace('%DEL%', \Yii::t('diff', 'space'), \Yii::t('diff', 'aria_del'));
        $strSpaceDel = '<del class="space" aria-label="' . $aria . '">[' . \Yii::t('diff', 'space') . ']</del>';

        $aria          = str_replace('%DEL%', \Yii::t('diff', 'space'), \Yii::t('diff', 'aria_del'));
        $strNewlineDel = '<del class="space" aria-label="' . $aria . '">[' . \Yii::t('diff', 'newline') . ']</del>';

        $aria        = str_replace('%INS%', \Yii::t('diff', 'space'), \Yii::t('diff', 'aria_ins'));
        $strSpaceIns = '<ins class="space" aria-label="' . $aria . '">[' . \Yii::t('diff', 'space') . ']</ins>';

        $aria          = str_replace('%INS%', \Yii::t('diff', 'newline'), \Yii::t('diff', 'aria_ins'));
        $strNewlineIns = '<ins class="space" aria-label="' . $aria . '">[' . \Yii::t('diff', 'newline') . ']</ins>';

        $out  = preg_replace('/<del[^>]*> <\/del>/siu', $strSpaceDel, $out);
        $out  = preg_replace('/<ins[^>]*> <\/ins>/siu', $strSpaceIns, $out);
        $out  = preg_replace('/<del[^>]*><br><\/del>/siu', $strNewlineDel . '<del><br></del>', $out);
        $out  = preg_replace('/<ins[^>]*><br><\/ins>/siu', $strNewlineIns . '<ins><br></ins>', $out);
        $out  = str_replace($strSpaceDel . $strNewlineIns, $strNewlineIns, $out);
        $out  = str_replace($strSpaceDel . '<ins></ins><br>', '<br>', $out);
        $out  = str_replace('###LINENUMBER###', '', $out);
        $repl = '<br></p></div>';
        if (mb_substr($out, mb_strlen($out) - mb_strlen($repl), mb_strlen($repl)) == $repl) {
            $out = mb_substr($out, 0, mb_strlen($out) - mb_strlen($repl)) . '</p></div>';
        }
        return $out;
    }

    public function getMotionPlainText(): string
    {
        return HTMLTools::toPlainText($this->section->getData());
    }

    public function getAmendmentPlainText(): string
    {
        return HTMLTools::toPlainText($this->section->getData());
    }

    public function printMotionTeX(bool $isRight, Content $content, Consultation $consultation): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $tex = '';
        /** @var MotionSection $section */
        $section  = $this->section;
        $settings = $section->getSettings();

        $hasLineNumbers = !!$settings->lineNumbers;
        $lineLength     = ($hasLineNumbers ? $consultation->getSettings()->lineLength : 0);
        $fixedWidth     = !!$settings->fixedWidth;
        $firstLine      = $section->getFirstLineNumber();

        if ($settings->printTitle) {
            $tex .= '\subsection*{\AntragsgruenSection ' . Exporter::encodePlainString($settings->title) . '}' . "\n";
        }

        $cacheDeps = [$hasLineNumbers, $lineLength, $firstLine, $fixedWidth, $section->getData()];
        $tex2      = HashedStaticCache::getCache('printMotionTeX', $cacheDeps);

        if (!$tex2) {
            $tex2 = '';
            if ($fixedWidth || $hasLineNumbers) {
                if ($hasLineNumbers) {
                    $tex2 .= "\\linenumbers\n";
                    $tex2 .= "\\resetlinenumber[" . $firstLine . "]\n";
                }

                $paragraphs = $section->getTextParagraphObjects($hasLineNumbers);
                foreach ($paragraphs as $paragraph) {
                    $tex2 .= Exporter::getMotionLinesToTeX($paragraph->lines) . "\n";
                }

                if ($hasLineNumbers) {
                    if (substr($tex2, -9, 9) == "\\newline\n") {
                        $tex2 = substr($tex2, 0, strlen($tex2) - 9) . "\n";
                    }
                    $tex2 .= "\n\\nolinenumbers\n";
                }
            } else {
                $paras = $section->getTextParagraphLines();
                foreach ($paras as $para) {
                    $html = str_replace('###LINENUMBER###', '', implode('', $para));
                    $tex2 .= Exporter::getMotionLinesToTeX([$html]) . "\n";
                }
            }

            $tex2 = Exporter::fixLatexErrorsInFinalDocument($tex2);

            HashedStaticCache::setCache('printMotionTeX', $cacheDeps, $tex2);
        }

        if ($isRight) {
            $content->textRight .= $tex . $tex2;
        } else {
            $content->textMain .= $tex . $tex2;
        }
    }

    public function printAmendmentTeX(bool $isRight, Content $content): void
    {
        /** @var AmendmentSection $section */
        $section    = $this->section;
        $firstLine  = $section->getFirstLineNumber();
        $lineLength = $section->getCachedConsultation()->getSettings()->lineLength;

        $cacheDeps = [
            $firstLine, $lineLength, $section->getOriginalMotionSection()->getData(), $section->data,
            $section->getAmendment()->globalAlternative
        ];
        $tex       = HashedStaticCache::getCache('printAmendmentTeX', $cacheDeps);

        if (!$tex) {
            if ($section->getAmendment()->globalAlternative) {
                $title = Exporter::encodePlainString($section->getSettings()->title);
                if ($title == \Yii::t('motion', 'motion_text')) {
                    $titPattern = \Yii::t('amend', 'amendment_for_prefix');
                    $title      = str_replace('%PREFIX%', $section->getMotion()->titlePrefix, $titPattern);
                }

                $tex .= '\subsection*{\AntragsgruenSection ' . Exporter::encodePlainString($title) . '}' . "\n";
                $tex .= Exporter::encodeHTMLString($section->data);
            } else {
                $formatter = new AmendmentSectionFormatter();
                $formatter->setTextOriginal($section->getOriginalMotionSection()->getData());
                $formatter->setTextNew($section->data);
                $formatter->setFirstLineNo($firstLine);
                $diffGroups = $formatter->getDiffGroupsWithNumbers($lineLength, DiffRenderer::FORMATTING_CLASSES);

                if (count($diffGroups) > 0) {
                    $title = Exporter::encodePlainString($section->getSettings()->title);
                    if ($title == \Yii::t('motion', 'motion_text')) {
                        $titPattern = \Yii::t('amend', 'amendment_for_prefix');
                        $title      = str_replace('%PREFIX%', $section->getMotion()->titlePrefix, $titPattern);
                    }

                    $tex  .= '\subsection*{\AntragsgruenSection ' . Exporter::encodePlainString($title) . '}' . "\n";
                    $html = TextSimple::formatDiffGroup($diffGroups, '', '<p></p>');

                    $tex  .= Exporter::encodeHTMLString($html);
                }
            }

            HashedStaticCache::setCache('printAmendmentTeX', $cacheDeps, $tex);
        }

        if ($isRight) {
            $content->textRight .= $tex;
        } else {
            $content->textMain .= $tex;
        }
    }

    public function getMotionODS(): string
    {
        return $this->section->getData();
    }

    public function getAmendmentODS(): string
    {
        /** @var AmendmentSection $section */
        $section = $this->section;

        if ($section->getAmendment()->globalAlternative) {
            return $section->data;
        }

        $firstLine    = $section->getFirstLineNumber();
        $lineLength   = $section->getCachedConsultation()->getSettings()->lineLength;
        $originalData = $section->getOriginalMotionSection()->getData();
        return static::formatAmendmentForOds($originalData, $section->data, $firstLine, $lineLength);
    }

    public function getAmendmentUnchangedVersionODS(): string
    {
        /** @var AmendmentSection $section */
        $section = $this->section;

        if ($section->getAmendment()->globalAlternative) {
            return $section->data;
        }

        $firstLine    = $section->getFirstLineNumber();
        $lineLength   = $section->getCachedConsultation()->getSettings()->lineLength;
        $originalData = $section->getOriginalMotionSection()->getData();

        $formatter = new AmendmentSectionFormatter();
        $formatter->setTextOriginal($originalData);
        $formatter->setTextNew($section->data);
        $formatter->setFirstLineNo($firstLine);
        $diffGroups = $formatter->getDiffGroupsWithNumbers($lineLength, DiffRenderer::FORMATTING_CLASSES);

        $unchanged = [];
        foreach ($diffGroups as $diffGroup) {
            $lineFrom = ($diffGroup['lineFrom'] > 0 ? $diffGroup['lineFrom'] : 1);
            $unchanged[] = LineSplitter::extractLines($originalData, $lineLength, $firstLine, $lineFrom, $diffGroup['lineTo']);
        }

        return implode('<br><br>', $unchanged);
    }

    public function printMotionToODT(ODTText $odt): void
    {
        if ($this->isEmpty()) {
            return;
        }
        $section = $this->section;
        /** @var MotionSection $section */
        $lineNumbers = $section->getMotion()->getMyConsultation()->getSettings()->odtExportHasLineNumers;
        $odt->addHtmlTextBlock('<h2>' . Html::encode($section->getSettings()->title) . '</h2>', false);
        if ($section->getSettings()->lineNumbers && $lineNumbers) {
            $paragraphs = $section->getTextParagraphObjects(true, false, false);
            foreach ($paragraphs as $paragraph) {
                $lines = [];
                foreach ($paragraph->lines as $line) {
                    $lines[] = preg_replace('/<br> ?\n?$/', '', $line);
                }
                $html = implode('<br>', $lines);
                $html = str_replace('###LINENUMBER###', '', $html);
                if (mb_substr($html, 0, 1) != '<') {
                    $html = '<p>' . $html . '</p>';
                }

                $html = str_replace('<br><ul>', '<ul>', $html);
                $html = str_replace('<br><ol>', '<ol>', $html);
                $html = str_replace('<br><li>', '<li>', $html);

                $html = HTMLTools::correctHtmlErrors($html);
                $odt->addHtmlTextBlock($html, true);
            }
        } else {
            $paras = $section->getTextParagraphLines();
            foreach ($paras as $para) {
                $html = str_replace('###LINENUMBER###', '', implode('', $para));
                $html = HTMLTools::correctHtmlErrors($html);
                $odt->addHtmlTextBlock($html, false);
            }
        }
    }

    public function printAmendmentToODT(ODTText $odt): void
    {
        /** @var AmendmentSection $section */
        $section = $this->section;

        if ($section->getAmendment()->globalAlternative) {
            $odt->addHtmlTextBlock('<h2>' . Html::encode($this->section->getSettings()->title) . '</h2>', false);

            $html = HTMLTools::correctHtmlErrors($section->data);
            $odt->addHtmlTextBlock($html, false);
        } else {
            $firstLine  = $section->getFirstLineNumber();
            $lineLength = $section->getCachedConsultation()->getSettings()->lineLength;

            $formatter = new AmendmentSectionFormatter();
            $formatter->setTextOriginal($section->getOriginalMotionSection()->getData());
            $formatter->setTextNew($section->data);
            $formatter->setFirstLineNo($firstLine);
            $diffGroups = $formatter->getDiffGroupsWithNumbers($lineLength, DiffRenderer::FORMATTING_CLASSES);

            if (count($diffGroups) == 0) {
                return;
            }

            $odt->addHtmlTextBlock('<h2>' . Html::encode($this->section->getSettings()->title) . '</h2>', false);

            $firstLine = $section->getFirstLineNumber();
            $html      = TextSimple::formatDiffGroup($diffGroups, '', '', $firstLine);

            $html = HTMLTools::correctHtmlErrors($html);
            $odt->addHtmlTextBlock($html, false);
        }
    }

    public function matchesFulltextSearch(string $text): bool
    {
        $data = strip_tags($this->section->getData());
        return (mb_stripos($data, $text) !== false);
    }

    public static function formatAmendmentForOds(string $originalText, string $newText, int $firstLine, int $lineLength): string
    {
        $formatter = new AmendmentSectionFormatter();
        $formatter->setTextOriginal($originalText);
        $formatter->setTextNew($newText);
        $formatter->setFirstLineNo($firstLine);
        $diffGroups = $formatter->getDiffGroupsWithNumbers($lineLength, DiffRenderer::FORMATTING_CLASSES);

        $diff = static::formatDiffGroup($diffGroups, '', '', $firstLine);
        $diff = str_replace('<h4', '<br><h4', $diff);
        $diff = str_replace('</h4>', '</h4><br>', $diff);
        if (mb_substr($diff, 0, 4) == '<br>') {
            $diff = mb_substr($diff, 4);
        }
        return $diff;
    }
}
