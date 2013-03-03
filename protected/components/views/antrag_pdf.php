<?php

/**
 * @var Antrag $antrag
 * @var TCPDF $pdf
 * @var Sprache $sprache
 * @var Person $initiator
 */

$absae = $antrag->getParagraphs();

// set font
$pdf->SetFont('dejavusans', '', 10);

// add a page
$pdf->AddPage();

$logo = Yii::app()->params['pdf_logo'];
if (file_exists($logo)) {
	$pdf->setJPEGQuality(100);
	$pdf->Image($logo, 22, 32, 47, 26);
}

$pdf->SetXY(155, 37, true);

if ($antrag->revision_name == "") {
	$name = "Entwurf";
	$pdf->SetFont("helvetica", "I", "25");
} else {
	$name = $antrag->revision_name;
	$pdf->SetFont("helvetica", "B", "25");
}
$pdf->MultiCell(37, 21, $name,
	array('LTRB' => array('width' => 3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(150, 150, 150))), "C",
	false, 1, "", "", true, 0, false, true, 21, // defaults
	"M"
);

$str = Antrag::$TYPEN[$antrag->typ];
$pdf->SetFont("helvetica", "B", "25");
$width = $pdf->GetStringWidth($str);

$pdf->SetXY((210 - $width) / 2, 60);
$pdf->Write(20, $str);
$pdf->SetLineStyle(array(
	"width" => 3,
	'color' => array(150, 150, 150),
));
$pdf->Line((210 - $width) / 2, 78, (210 + $width) / 2, 78);

$pdf->SetXY(25, 90);
$pdf->SetFont("helvetica", "B", 12);
$pdf->MultiCell(160, 13, $antrag->veranstaltung0->antrag_einleitung);

$pdf->SetXY(25, 110);

$pdf->SetFont("helvetica", "B", 12);
$pdf->MultiCell(50, 0, $sprache->get("AntragsstellerIn") . ":", 0, "L", false, 0);
$pdf->SetFont("helvetica", "", 12);
$pdf->MultiCell(150, 0, $initiator->name, 0, "L");

$pdf->SetFont("helvetica", "B", 8);
$pdf->Ln();

$pdf->SetFont("helvetica", "B", 12);
$pdf->MultiCell(50, 0, "Gegenstand:", 0, "L", false, 0);
$pdf->SetFont("helvetica", "B", 12);
$pdf->MultiCell(100, 0, $antrag->name,
	array('B' => array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(150, 150, 150))),
	"L"
);

$pdf->Ln();

$pdf->SetFont("helvetica", "", 12);

$pdf->writeHTML("<h3>" . $sprache->get("Antragstext") .  "</h3>");
$pdf->SetFont("Courier", "", 10);
$pdf->Ln(8);


$linenr = $antrag->getFirstLineNo();


foreach ($absae as $i=>$abs) {
	/** @var AntragAbsatz $abs */
	$text = $abs->str_html;
	$zeilen = substr_count($text, "<span class='zeilennummer'>");

	$abstand_bevor = array();

	//preg_match_all("/<div[^>]*antragabsatz_holder[^>]*>(?:.*)<span class=[\"']zeilennummer[\"']>([0-9]+)<\/span>/siuU", $text, $matches);
	//foreach ($matches[1] as $line) if ($line > 1) $abstand_bevor[$line] = 25;

	preg_match_all("/<li><span class=[\"']zeilennummer[\"']>([0-9]+)<\/span>/siuU", $text, $matches);
	foreach ($matches[1] as $line) if (isset($abstand_bevor[$line])) $abstand_bevor[$line] += 10;
	else $abstand_bevor[$line] = 10;

	preg_replace("/<li><span class=[\"']zeilennummer[\"']>([0-9]+)<\/span>/siuU", "<li style='margin-top: 10px;'>", $text);

	preg_match_all("/<div[^>]*antragabsatz_holder[^>]*>(?:.*)<span class=[\"']zeilennummer[\"']>([0-9]+)<\/span>/siuU", $text, $matches);

	$text = preg_replace("/<span class=[\"']zeilennummer[\"']>([0-9]+)<\/span>/sii", "", $text);

	$zeilennrs = array();
	for ($i = 0; $i < $zeilen; $i++) $zeilennrs[] = $linenr++;
	$text2 = implode("<br>", $zeilennrs);

	$y = $pdf->getY();
	$pdf->writeHTMLCell(12, '', 12, $y, $text2, 0, 0, 0, true, '', true);
	$pdf->writeHTMLCell(170, '',24, '', $text, 0, 1, 0, true, '', true);

	$pdf->Ln(8);

}

if (trim($antrag->begruendung) != "") {
	$html = '
	</div>
	<h3>Begründung</h3>
	<div class="textholder consolidated">
		' . HtmlBBcodeUtils::bbcode2html($antrag->begruendung) . '
	</div>
</div>';
	$pdf->SetFont("helvetica", "", 10);
	$pdf->writeHTML($html, true, false, true, false, '');
}
