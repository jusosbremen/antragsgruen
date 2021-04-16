<?php

/** @var \Codeception\Scenario $scenario */
$I = new AcceptanceTester($scenario);
$I->populateDBData1();

$I->gotoConsultationHome();

$I->see('Dein Antragsrot', '#sidebar');

$I->wantTo('disable the ad');
$I->loginAsStdAdmin();
$page = $I->gotoStdAdminPage()->gotoAppearance();
$I->uncheckOption('#showAntragsgruenAd');
$page->saveForm();

$I->gotoConsultationHome();
$I->dontSee('Dein Antragsrot', '#sidebar');

$I->wantTo('enable it again');

$page = $I->gotoStdAdminPage()->gotoAppearance();
$I->checkOption('#showAntragsgruenAd');
$page->saveForm();

$I->gotoConsultationHome();
$I->see('Dein Antragsrot', '#sidebar');
