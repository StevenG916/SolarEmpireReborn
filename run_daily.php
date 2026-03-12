<?php

require_once('inc/maint.inc.php');

// Daily tips
$tips = mysqli_query($maint_link, "SELECT `tip_id` FROM `daily_tips` ORDER BY RAND() LIMIT 1");
if ($tips && $row = mysqli_fetch_row($tips)) {
    $newTip = (int)$row[0];
    mysqli_query($maint_link, "UPDATE `se_games` SET `todays_tip` = $newTip");
}

$games = mysqli_query($maint_link, "SELECT db_name FROM se_games WHERE status >= 1 && status != 'paused'");

while (list($game) = mysqli_fetch_row($games)) {
    print "- Game $game -\n";

    mysqli_query($maint_link, "INSERT INTO `{$game}_news` (`timestamp`, `headline`, `login_id`) values (" .
        time() . ", 'Daily Maintenance Running...', '1')");

    /* Retire out-of-date players */
    $limit = 6;
    $removed = array();

    $playerInfo = mysqli_query($maint_link, 'SELECT `clan_id`, `login_id`, `login_name` FROM `' .
        $game . '_users` WHERE `login_id` > 5 && `last_request` < ' .
        (time() - 60 * 60 * 24 * $limit));

    while (list($clan, $id, $name) = mysqli_fetch_row($playerInfo)) {
        if ($clan > 1) {
            $leader = mysqli_query($maint_link, "SELECT `leader_id` FROM `{$game}_clans` WHERE `clan_id` = $clan");
            $leaderRow = mysqli_fetch_row($leader);
            if ($leaderRow && $leaderRow[0] == $id) {
                mysqli_query($maint_link, "UPDATE `{$game}_users` SET `clan_id` = 0 WHERE `clan_id` = $clan");
                mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `clan_id` = -1 WHERE `clan_id` = $clan");
                mysqli_query($maint_link, "DELETE FROM `{$game}_clans` WHERE `clan_id` = $clan");
            } else {
                mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `clan_id` = -1 WHERE `owner_id` = $id");
                mysqli_query($maint_link, "UPDATE `{$game}_clans` SET `members` = `members` - 1 WHERE `clan_id` = $clan");
            }
        }

        $escapedGame = mysqli_real_escape_string($maint_link, $game);
        $escapedName = mysqli_real_escape_string($maint_link, $name);
        mysqli_query($maint_link, "DELETE FROM `{$game}_ships` WHERE `login_id` = $id");
        mysqli_query($maint_link, "DELETE FROM `{$game}_diary` WHERE `login_id` = $id");
        mysqli_query($maint_link, "INSERT INTO `user_history` VALUES ($id, " . time() . ", '$escapedGame', 'Removed from game after $limit days of in-activity.', '', '')");
        mysqli_query($maint_link, "DELETE FROM `{$game}_user_options` WHERE `login_id` = $id");
        mysqli_query($maint_link, "DELETE FROM `{$game}_users` WHERE `login_id` = $id");
        mysqli_query($maint_link, "UPDATE `{$game}_politics` set `login_id` = 0, `login_name` = 0, `timestamp` = 0 WHERE `login_id` = $id");

        $removed[] = $name;
    }

    if (!empty($removed)) {
        $escapedNames = mysqli_real_escape_string($maint_link, implode("</li>\n\t<li>", $removed));
        mysqli_query($maint_link, "INSERT INTO `{$game}_news` (`timestamp`, `headline`, `login_id`) VALUES (" .
            time() . ", 'Players retired after $limit days of in-activity:\n<ul>\n\t<li>" .
            $escapedNames . "</li>\n</ul>', 1)");
        print "Players retired after $limit days of in-activity:\n\t" . implode("\n\t", $removed) . "\n";
    }

    /* Bounty interest: 4% increase */
    print "Increasing Bounties... ";
    mysqli_query($maint_link, "UPDATE {$game}_users SET bounty = `bounty` * 1.04");
    print "done\n";

    /* Planet builds */
    print "Planet stuff... ";
    $planets = mysqli_query($maint_link,
        "SELECT planet_id, planet_name, p.login_id, tax_rate, " .
        "fuel, metal, elect, colon, alloc_fight, alloc_elect, alloc_organ, " .
        "u.planet_report FROM {$game}_planets AS p LEFT JOIN {$game}_user_options " .
        "AS u ON u.login_id = p.login_id WHERE p.planet_id != 1");
    print "done\n";

    while (list($id, $name, $ownerId, $tax, $fuel, $metal, $elect,
                 $colonists, $allocFigs, $allocElect, $allocOrgan, $report) =
               mysqli_fetch_row($planets)) {
        print "Planet #$id ($name)\n";

        $reportStr = "<p><strong>Manufacturing report for $name</strong></p>\n";

        /* Fighters */
        $fighterMax = floor($allocFigs / 100);
        $resourceUsed = min($fuel, $metal, $elect, $fighterMax);
        $figsProduced = floor($resourceUsed * 10);

        if ($figsProduced > 0) {
            mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `fighters` = `fighters` + " .
                "$figsProduced, `fuel` = `fuel` - $resourceUsed, `metal` = `metal` - " .
                "$resourceUsed, `elect` = `elect` - $resourceUsed WHERE `planet_id` = $id");
            $elect -= $resourceUsed;
            $fuel  -= $resourceUsed;
            $metal -= $resourceUsed;
            $reportStr .= "<p>Produced <strong>$figsProduced fighters</strong>.</p>\n";
            print "\t$figsProduced fighters\n";
        }

        /* Electronics: 1 per 50 colonists allocated */
        $electBudget = min(floor($allocElect / 50), $fuel, $metal);
        if ($electBudget > 0) {
            mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `elect` = `elect` + $electBudget, " .
                "`fuel` = `fuel` - $electBudget, `metal` = `metal` - $electBudget " .
                "WHERE `planet_id` = $id");
            $reportStr .= "<p>Produced <strong>$electBudget electronics</strong>.</p>\n";
            print "\t$electBudget electronics\n";
        }

        /* Organics: 1 per 500 colonists */
        $organicProduce = floor($allocOrgan / 500);
        if ($organicProduce > 0) {
            mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `organ` = `organ` + " .
                "$organicProduce WHERE `planet_id` = $id");
            $reportStr .= "<p>Produced <strong>$organicProduce organics</strong>.</p>\n";
            print "\t$organicProduce organics\n";
        }

        $boredCols = $colonists - ($allocFigs + $allocElect + $allocOrgan);
        $taxed = floor($boredCols * $tax / 100);
        if ($taxed > 0) {
            print "\ttaxed colonists for $taxed credits ($tax%)\n";
        }

        $perc = 0.3 - 0.03 * $tax;
        $newPop = floor($boredCols * $perc);
        print "\tpopulation changed by $newPop\n";

        if ($report >= 1) {
            $escapedReport = mysqli_real_escape_string($maint_link, $reportStr);
            mysqli_query($maint_link, "INSERT INTO `{$game}_messages` (`timestamp`, `sender_name`, " .
                "`sender_id`, `login_id`, `text`) values(" . time() . ", 'The Universe', " .
                "$ownerId, $ownerId, '$escapedReport')");
        }
    }

    /* Process planet taxes and population growth */
    mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `cash` = `cash` + FLOOR((`colon` - " .
        "(`alloc_fight` + `alloc_elect` + `alloc_organ`)) * `tax_rate` / 100)");
    mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `colon` = `colon` + FLOOR((`colon` - " .
        "(`alloc_fight` + `alloc_elect` + `alloc_organ`)) * (0.3 - `tax_rate` * 0.03))");
    mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `alloc_fight` = 0, `alloc_elect` = 0, " .
        "`alloc_organ` = 0 WHERE (`alloc_fight` + `alloc_elect` + `alloc_organ`) > `colon`");

    /* Resource regeneration */
    $metalChance    = (int)getVar($game, 'rr_metal_chance');
    $metalChanceMin = (int)getVar($game, 'rr_metal_chance_min');
    $metalChanceMax = (int)getVar($game, 'rr_metal_chance_max');
    $fuelChance     = (int)getVar($game, 'rr_fuel_chance');
    $fuelChanceMin  = (int)getVar($game, 'rr_fuel_chance_min');

    mysqli_query($maint_link, "UPDATE `{$game}_stars` SET `metal` = `metal` + (RAND() * " .
        ($metalChanceMax - $metalChanceMin) . ") + $metalChanceMin WHERE (RAND() * 100) < $metalChance");
    mysqli_query($maint_link, "UPDATE `{$game}_stars` SET `fuel` = `fuel` + (RAND() * " .
        ($metalChanceMax - $metalChanceMin) . ") + $fuelChanceMin WHERE (RAND() * 100) < $fuelChance");

    /* Days left countdown */
    mysqli_query($maint_link, "UPDATE `{$game}_db_vars` SET `value`=`value`-1 WHERE " .
        "`name` = 'count_days_left_in_game' and `value` > 0");

    /* Optimize tables */
    mysqli_query($maint_link, "OPTIMIZE TABLE `{$game}_bilkos`, `{$game}_clans`, `{$game}_diary`, " .
        "`{$game}_messages`, `{$game}_news`, `{$game}_planets`, `{$game}_ships`, " .
        "`{$game}_stars`, `{$game}_user_options`, `{$game}_users`");

    print "Daily maintenance for $game is... ";
    mysqli_query($maint_link, "INSERT INTO {$game}_news (timestamp, headline, login_id) values (" .
        time() . ", '...Daily maintenance complete', 1)");
    print "complete!\n";
    print "------------\n\n";
}

mysqli_close($maint_link);

?>
