<div v-if="isYfjVoting">
    <span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Voting IS set up as <strong>YFJ Voting</strong><br>
    @TODO Show eligible users
    <!--
    $html .= VotingHelper::getEligibleUserCountByGroup($votingBlock, [VotingHelper::class, 'conditionVotingIsNycGroup']) . ' NYC members<br>';
    $html .= VotingHelper::getEligibleUserCountByGroup($votingBlock, [VotingHelper::class, 'conditionVotingIsIngyoGroup']) . ' INGYO members';
    -->
</div>
<div v-if="isYfjRollCall">
    <span class="glyphicon glyphicon-ok" aria-hidden="true"></span> Voting IS set up as YFJ <strong>Roll Call</strong><br>
</div>
<div v-if="!isYfjVoting && !isYfjRollCall">
    Voting is NEITHER set up as YFJ Voting nor YFJ Roll Call
</div>
