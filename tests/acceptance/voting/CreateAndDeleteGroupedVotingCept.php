<?php

/** @var \Codeception\Scenario $scenario */
$I = new AcceptanceTester($scenario);
$I->populateDBData1();

$I->wantTo('enable non-quota speech lists');
$I->gotoConsultationHome();
$I->dontSeeElementInDOM('.currentVotings');
$I->dontSeeElementInDOM('.voting_amendment_3');

$I->wantTo('Create a new voting block for the amendment');
$page = $I->loginAndGotoMotionList()->gotoAmendmentEdit(1);
$I->dontSeeElement('.votingDataHolder');
$I->clickJS('.votingDataOpener');
$I->seeElement('.votingDataHolder');

$I->dontSeeElement('.newBlock');
$I->executeJS('$("#votingBlockId").val("NEW").trigger("change")');
$I->seeElement('.newBlock');

$I->fillField('#newBlockTitle', 'Newly created voting');
$page->saveForm();

$I->seeElement('.votingDataHolder');
$I->seeOptionIsSelected('#votingBlockId', 'Newly created voting');


$I->wantTo('Assign a motion to the same voting block');
$page = $I->gotoMotionList()->gotoMotionEdit(114);
$I->dontSeeElement('.votingDataHolder');
$I->clickJS('.votingDataOpener');
$I->seeElement('.votingDataHolder');

$I->dontSeeElement('.newBlock');
$I->dontSeeElement('.votingItemBlockRow' . AcceptanceTester::FIRST_FREE_VOTING_BLOCK_ID);
$I->executeJS('$("#votingBlockId").val("' . AcceptanceTester::FIRST_FREE_VOTING_BLOCK_ID . '").trigger("change")');
$I->dontSeeElement('.newBlock');
$I->seeElement('.votingItemBlockRow' . AcceptanceTester::FIRST_FREE_VOTING_BLOCK_ID);

$I->selectOption('#votingItemBlockId' . AcceptanceTester::FIRST_FREE_VOTING_BLOCK_ID, 'Ä1');
$page->saveForm();

$I->seeOptionIsSelected('#votingItemBlockId' . AcceptanceTester::FIRST_FREE_VOTING_BLOCK_ID, 'Ä1');


$I->wantTo('Rename the voting and open it as part of that motion');
$I->click('.votingEditLink');

$votingBaseId = '#voting' . AcceptanceTester::FIRST_FREE_VOTING_BLOCK_ID;
$I->seeElement($votingBaseId);
$I->dontSeeElement($votingBaseId . ' .titleSetting');
$I->clickJS($votingBaseId . ' .settingsToggleGroup button');
$I->seeElement($votingBaseId . ' .titleSetting');
$I->seeInField($votingBaseId . ' .titleSetting input', 'Newly created voting');
$I->fillField($votingBaseId . ' .titleSetting input', 'New voting for testing');
$I->selectOption($votingBaseId . ' .assignedMotion select', 'A5: Leerzeichen-Test');
$I->clickJS($votingBaseId . ' .btnSave');
$I->wait(0.3);
$I->see('New voting for testing', $votingBaseId . ' h2');

$I->clickJS($votingBaseId . ' .btnOpen');
$I->wait(0.3);
$I->dontSeeElement($votingBaseId . ' .btnOpen');
$I->seeElement($votingBaseId . ' .btnClose');


$I->wantTo('Vote for it');
$I->gotoConsultationHome();
$I->seeElementInDOM('.currentVotings');
$I->dontSeeElement('.voting_motion_114');

$I->click('.motionLink114');
$I->seeElement('.currentVotings');
$I->seeElement('.voting_motion_114');
$I->see('A5: Leerzeichen-Test', '.voting_motion_114');
$I->see('Ä1 zu A2: O’zapft is!', '.voting_motion_114');
$I->clickJS('.voting_motion_114 .btnYes');
$I->wait(0.3);
$I->see('1 Stimme von 1 Mitglied abgegeben');
$I->click('.votingsAdminLink');


$I->wantTo('Close the voting and see that both motions are accepted');
$I->clickJS($votingBaseId . ' .btnClose');
$I->wait(0.5);
$I->seeElement('.voting_motion_114 .accepted');
$I->click('.adminUrl114');
$votingStatus = $I->executeJS('return $("input[name=votingStatus]:checked").val()');
$I->assertEquals(\app\models\db\IMotion::STATUS_ACCEPTED, $votingStatus);
$I->seeInField('#votesYes', 1);
$I->seeInField('#votesNo', 0);
$I->seeInField('#votesAbstention', 0);

$I->gotoMotionList()->gotoAmendmentEdit(1);
$votingStatus = $I->executeJS('return $("input[name=votingStatus]:checked").val()');
$I->assertEquals(\app\models\db\IMotion::STATUS_ACCEPTED, $votingStatus);
$I->seeInField('#votesYes', 1);
$I->seeInField('#votesNo', 0);
$I->seeInField('#votesAbstention', 0);


$I->wantTo('Delete the voting');
$I->click('.votingEditLink');
$I->dontSeeElement($votingBaseId . ' .btnDelete');
$I->clickJS($votingBaseId . ' .settingsToggleGroup button');
$I->seeElement($votingBaseId . ' .btnDelete');
$I->clickJS($votingBaseId . ' .btnDelete');

$I->seeBootboxDialog('gelöscht');
$I->acceptBootboxConfirm();

$I->wait(0.5);
$I->dontSeeElement($votingBaseId);
