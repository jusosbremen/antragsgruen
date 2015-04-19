<?php

namespace app\models\pdfLayouts;

use app\models\db\Consultation;
use app\models\db\Motion;
use TCPDF;
use Yii;

class IPDFLayout
{
    /** @var Consultation */
    protected $consultation;

    /** @var TCPDF */
    protected $pdf;

    /**
     * @param Consultation $consultation
     */
    public function __construct(Consultation $consultation)
    {
        $this->consultation = $consultation;
    }

    /**
     * @return TCPDF
     */
    public function createPDFClass()
    {
        $pdf = new TCPDFWithFooter($this);

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        $pdf->SetMargins(25, 40, 25);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM - 5);

//set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->SetFont('dejavusans', '', 10);

        $this->pdf = $pdf;

        return $pdf;
    }

    /**
     */
    public function getFonts()
    {

    }

    /**
     * @param Motion $motion
     */
    public function printMotionHeader(Motion $motion)
    {
        $pdf = $this->pdf;

        if (file_exists($this->consultation->getSettings()->logoUrl)) {
            $pdf->setJPEGQuality(100);
            $pdf->Image($this->consultation->getSettings()->logoUrl, 22, 32, 47, 26);
        }

        if (!$this->consultation->getSettings()->hideRevision) {
            $revName = $motion->titlePrefix;
            if ($revName == "") {
                $revName = 'Entwurf';
                $pdf->SetFont("helvetica", "I", "25");
                $width = $pdf->GetStringWidth($revName, "helvetica", "I", "25") + 3.1;
            } else {
                $pdf->SetFont("helvetica", "B", "25");
                $width = $pdf->GetStringWidth($revName, "helvetica", "B", "25") + 3.1;
            }
            if ($width < 35) {
                $width = 35;
            }

            $pdf->SetXY(192 - $width, 37, true);
            $borderStyle = ['width' => 3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [150, 150, 150]];
            $this->pdf->MultiCell(
                $width,
                21,
                $revName,
                ['LTRB' => $borderStyle],
                "C",
                false,
                1,
                "",
                "",
                true,
                0,
                false,
                true,
                21, // defaults
                "M"
            );
        }

        $str = $motion->motionType->title;
        $pdf->SetFont("helvetica", "B", "25");
        $width = $pdf->GetStringWidth($str);

        $pdf->SetXY((210 - $width) / 2, 60);
        $pdf->Write(20, $str);
        $pdf->SetLineStyle(array(
            "width" => 3,
            'color' => array(150, 150, 150),
        ));
        $pdf->Line((210 - $width) / 2, 78, (210 + $width) / 2, 78);

        $pdf->SetY(90);
        $intro = \Yii::t('pdf', 'introduction');
        if ($intro) {
            $pdf->SetX(24);
            $pdf->SetFont("helvetica", "B", 12);
            $pdf->MultiCell(160, 13, $intro, 0, "C");
            $pdf->Ln(7);
        }


        $pdf->SetX(12);

        $pdf->SetFont("helvetica", "B", 12);
        $pdf->MultiCell(12, 0, "", 0, "L", false, 0);
        $pdf->MultiCell(50, 0, Yii::t('pdf', 'Initiators') . ":", 0, "L", false, 0);
        $pdf->SetFont("helvetica", "", 12);
        $pdf->MultiCell(120, 0, $motion->getInitiatorsStr(), 0, "L");

        $pdf->Ln(5);
        $pdf->SetX(12);


        $pdf->SetFont("helvetica", "B", 12);
        $pdf->MultiCell(12, 0, "", 0, "L", false, 0);

        $pdf->MultiCell(50, 0, "Gegenstand:", 0, "L", false, 0);
        $pdf->SetFont("helvetica", "B", 12);
        $borderStyle = ['width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => [150, 150, 150]];
        $pdf->MultiCell(
            100,
            0,
            $motion->title,
            ['B' => $borderStyle],
            "L"
        );

        $pdf->Ln(9);
    }
}
