<?php

require_once('inc/maint.inc.php');

$startArr = explode(' ', microtime());
$startTime = (double)$startArr[0] + (double)$startArr[1];

$games = mysqli_query($maint_link, "SELECT db_name FROM se_games WHERE status >= 1 && status != 'paused'");

while (list($game) = mysqli_fetch_row($games)) {
    print "- Game $game -\n";

    /* Remove empty clans */
    mysqli_query($maint_link, "DELETE FROM `{$game}_clans` WHERE `members` = 0");
    $clansRemoved = mysqli_affected_rows($maint_link);
    if ($clansRemoved > 0) {
        print "Removed $clansRemoved empty clan(s)\n";
    }

    /* Missile launch pad countdown */
    mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `launch_pad` = `launch_pad` - 1 WHERE `launch_pad` > 1");
    $launchPads = mysqli_affected_rows($maint_link);
    if ($launchPads > 0) {
        print "Updated $launchPads missile launch pad(s)\n";
    }

    $safeTurns = (int)getVar($game, 'turns_safe');
    $scatter   = (int)getVar($game, 'keep_sol_clear');

    $starQuery = mysqli_query($maint_link, "SELECT `num_stars` FROM `se_games` WHERE `db_name` = '$game'");
    $systemNoRow = mysqli_fetch_row($starQuery);
    $systemNo = (int)($systemNoRow[0] ?? 0);

    if ($scatter == 1) {
        $warning = '<p>You left at least one of your ships in the Sol (#1) ' .
            "Star-System during the last hourly maintenence.</p>\n<p>" .
            'Should the ship(s) be in there during the next maintence they ' .
            'will scattered around the universe.</p>';

        $action = '<p>Your <em>%s</em> has been moved to <strong>system #%d</strong>' .
            " from system <strong>#1</strong>.<p>\n<p>The governer has decided to keep " .
            'system Sol clear, you failed to respond to the warning.</p>';

        $scatterWarn = array();

        $warn = mysqli_query($maint_link, 'SELECT `s`.`login_id`, `s`.`login_name` FROM ' .
            "`{$game}_ships` AS `s`, `{$game}_users` AS `u` WHERE `s`.`location` " .
            "= 1 && `s`.`login_id` > 3 && `u`.`turns_run` > $safeTurns && " .
            "`u`.`login_id` = `s`.`login_id` && `s`.`ship_id` != 1 GROUP BY `u`.`login_id`, `s`.`login_name`");

        while (list($id, $name) = mysqli_fetch_row($warn)) {
            $escapedWarning = mysqli_real_escape_string($maint_link, sprintf($warning, $id, $name));
            $escapedName = mysqli_real_escape_string($maint_link, $name);
            mysqli_query($maint_link, "INSERT INTO `{$game}_messages` (`timestamp`, " .
                "`sender_name`, `sender_id`, `login_id`, `text`) VALUES (" .
                time() . ", '$escapedName', $id, $id, '$escapedWarning')");
            mysqli_query($maint_link, "UPDATE `{$game}_users` SET `second_scatter` = " .
                "`second_scatter` + 1 WHERE `login_id` = $id");
            $scatterWarn[] = $id;
        }

        $scattered = array();

        $toScatter = mysqli_query($maint_link, "SELECT `s`.`login_name`, `s`.`login_id`, " .
            "`s`.`ship_name`, `s`.`ship_id`, `u`.`ship_id` AS `command` FROM " .
            "`{$game}_ships` AS `s` LEFT JOIN `{$game}_users` AS `u` ON " .
            "`u`.`login_id` = `s`.`login_id` WHERE `s`.`location` = 1 && " .
            "`s`.`login_id` > 3 && `u`.`turns_run` > $safeTurns && " .
            "`s`.`ship_id` != 1 && `u`.`second_scatter` = 2");

        while (list($ownerName, $ownerId, $shipName, $shipId, $inCommand) =
                   mysqli_fetch_row($toScatter)) {
            $goto = mt_rand(0, $systemNo - 1);
            $escapedAction = mysqli_real_escape_string($maint_link, sprintf($action, $shipName, $goto));
            $escapedOwner  = mysqli_real_escape_string($maint_link, $ownerName);
            mysqli_query($maint_link, "INSERT INTO `{$game}_messages` (`timestamp`, " .
                "`sender_name`, `sender_id`, `login_id`, `text`) VALUES (" . time() .
                ", '$escapedOwner', $ownerId, $ownerId, '$escapedAction')");
            mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `location` = $goto, " .
                "`towed_by` = 0, `mine_mode` = 0 WHERE `ship_id` = $shipId");
            mysqli_query($maint_link, "UPDATE `{$game}_users` SET `second_scatter` = 0 WHERE `login_id` = $ownerId");
            if ($shipId == $inCommand) {
                mysqli_query($maint_link, "UPDATE `{$game}_users` SET `location` = $goto WHERE `login_id` = $ownerId");
            }
            $scattered[] = "$ownerName: $shipName";
        }

        if (!empty($scattered)) {
            print count($scattered) . " ship(s) have been scattered:\n\t" . implode("\n\t", $scattered) . "\n";
        }

        if (!empty($scatterWarn)) {
            mysqli_query($maint_link, "UPDATE `{$game}_users` SET `second_scatter` = 0 WHERE " .
                "`login_id` != " . implode(' && `login_id` != ', $scatterWarn));
            print "Warned " . count($scatterWarn) . " players about being scattered.\n";
        }
    }

    /* Shield generation */
    $hourlyShields = (int)getVar($game, 'hourly_shields');
    mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `shields` = `shields` + $hourlyShields WHERE `config` REGEXP 'fr'");
    mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `shields` = `shields` + " . ($hourlyShields / 2) . " WHERE `config` REGEXP 'bs'");
    mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `shields` = `shields` + " . ($hourlyShields * 1.5) . " WHERE `config` REGEXP 'sv'");
    mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `shields` = `shields` + " . ($hourlyShields * 2) . " WHERE config REGEXP 'sw'");
    mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `shields` = `shields` + " . ($hourlyShields / 4) . " WHERE config REGEXP 'sh'");
    mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `shields` = `shields` + $hourlyShields");
    mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `shield_charge` = `shield_charge` + $hourlyShields * `shield_gen` WHERE `shield_gen` > 0");
    mysqli_query($maint_link, "UPDATE `{$game}_ships` SET `shields` = `max_shields` WHERE `shields` > `max_shields`");
    mysqli_query($maint_link, "UPDATE `{$game}_planets` SET `shield_charge` = `shield_gen` * 1000 WHERE `shield_charge` > `shield_gen` * 1000");

    /* Mining Metal */
    print 'Mining metal... ';
    $ships = mysqli_query($maint_link,
        "select s.ship_id, s.location, s.mine_rate_metal as mine_rate, s.cargo_bays, s.metal, s.fuel, s.elect, s.organ, s.colon, star.metal AS star_metal " .
        "from {$game}_stars star, {$game}_ships s, {$game}_users u " .
        "where s.mine_mode = 1 && u.login_id = s.login_id && star.star_id = s.location " .
        "&& s.location != 1 && star.metal > 0 " .
        "&& (s.cargo_bays - s.metal - s.fuel - s.elect - s.organ - s.colon) > 0 " .
        "&& mine_rate_metal > 0 group by s.ship_id");

    while ($ship = mysqli_fetch_assoc($ships)) {
        $mins_mined = $ship['mine_rate'] + (mt_rand(0, 3) === 0 ? 1 : (mt_rand(0, 3) === 1 ? -1 : 0));
        $mins_mined = min($mins_mined, $ship['star_metal']);
        $space = $ship['cargo_bays'] - $ship['fuel'] - $ship['metal'] - $ship['organ'] - $ship['elect'] - $ship['colon'];
        $mins_mined = min($mins_mined, $space);
        mysqli_query($maint_link, "update {$game}_ships set metal = metal + $mins_mined where ship_id = " . $ship['ship_id']);
        mysqli_query($maint_link, "update {$game}_stars set metal = metal - $mins_mined where star_id = " . $ship['location']);
    }
    print "done\n";

    /* Mining Fuel */
    print 'Mining fuel... ';
    $ships = mysqli_query($maint_link,
        "select s.ship_id, s.location, s.mine_rate_fuel as mine_rate, s.cargo_bays, s.metal, s.fuel, s.elect, s.organ, s.colon, star.fuel AS star_fuel " .
        "from {$game}_stars star, {$game}_ships s, {$game}_users u " .
        "where s.mine_mode = 2 && u.login_id = s.login_id && star.star_id = s.location " .
        "&& star.fuel > 0 " .
        "&& (s.cargo_bays - s.metal - s.fuel - s.elect - s.organ - s.colon) > 0 " .
        "&& mine_rate_fuel > 0 group by s.ship_id");

    while ($ship = mysqli_fetch_assoc($ships)) {
        $mins_mined = $ship['mine_rate'] + (mt_rand(0, 3) === 0 ? 1 : (mt_rand(0, 3) === 1 ? -1 : 0));
        $mins_mined = min($mins_mined, $ship['star_fuel']);
        $space = $ship['cargo_bays'] - $ship['fuel'] - $ship['metal'] - $ship['organ'] - $ship['elect'] - $ship['colon'];
        $mins_mined = min($mins_mined, $space);
        mysqli_query($maint_link, "update {$game}_ships set fuel = fuel + $mins_mined where ship_id = " . $ship['ship_id']);
        mysqli_query($maint_link, "update {$game}_stars set fuel = fuel - $mins_mined where star_id = " . $ship['location']);
    }
    print "done\n";

    mysqli_query($maint_link, "update {$game}_stars set fuel = 0 where fuel < 0");
    mysqli_query($maint_link, "update {$game}_stars set metal = 0 where metal < 0");

    /* Hourly turns */
    $hourlyTurns = (int)getVar($game, 'hourly_turns');
    print "Increasing turns by $hourlyTurns... ";
    mysqli_query($maint_link, "UPDATE {$game}_users SET turns = turns + $hourlyTurns");
    print "done\n";

    $maxTurns = (int)getVar($game, 'max_turns');
    print "Checking max turns doesn't exceed $maxTurns... ";
    mysqli_query($maint_link, "update {$game}_users SET turns = $maxTurns WHERE turns > $maxTurns");
    print "done\n";

    /* Bilkos Auction House */
    print "- Bilkos Auction House -\n";
    print "Deleting unsold items... ";
    mysqli_query($maint_link, "delete from {$game}_bilkos where timestamp <= " . (time() - 172800) . " && bidder_id = 0 && active=1");
    print "done\n";

    print "Giving Sold Items... ";
    $db = mysqli_query($maint_link, "select bidder_id, item_name, item_id from {$game}_bilkos where timestamp <= " . (time() - 86400) . " && active = 1 && bidder_id > 0");
    while ($lots = mysqli_fetch_assoc($db)) {
        $escapedName = mysqli_real_escape_string($maint_link, $lots['item_name']);
        mysqli_query($maint_link, "insert into {$game}_messages (timestamp, sender_name, sender_id, login_id, text) values(" .
            time() . ", 'Bilkos', '$lots[bidder_id]', '$lots[bidder_id]', 'You have successfully won lot #<b>$lots[item_id]</b> (<b class=b1>$escapedName</b>). <p>You should come to the Auction House in <b class=b1>Sol</b> to collect your goods.')");
        mysqli_query($maint_link, "update {$game}_bilkos set active=0 where item_id = '$lots[item_id]'");
    }
    print "done\n";

    /* Auction house restocking */
    if (mt_rand(0, 3)) {
        $turnip = mt_rand(0, 5);
        if ($turnip == 5) {
            $i_type = 5;
            if (mt_rand(0, 1)) {
                $i_code = mt_rand(4, 9);
                $i_name = "Shield Gen Lvl <b>$i_code</b>";
                $i_price = $i_code * 4000;
                $i_descr = "A level <b>$i_code</b> Shield Generator for a planet.";
            } else {
                $i_code = "MLPad";
                $i_name = "Missile Launch Pad";
                $i_price = 100000;
                $i_descr = "Missile Launch Pad. Used once.";
            }
        } elseif ($turnip >= 3) {
            $i_type = 4;
            $i_code = mt_rand(10, 80);
            $i_name = "Turns <b>$i_code</b>";
            $i_price = $i_code * 110;
            $i_descr = "<b>$i_code</b> turns that can be used for whatever you want.";
        } else {
            $i_type = 3;
            if ($turnip > 41) {
                $i_code = "fig1500"; $i_name = "1500 Fighter Bays"; $i_price = 50000;
                $i_descr = "Capable of fitting 1500 fighters into one upgrade pod.";
            } elseif ($turnip > 37) {
                $i_code = "attack_pack"; $i_name = "Attack Pack"; $i_price = 20000;
                $i_descr = "Increases shield capacity by 200 and fighter capacity by 700.";
            } elseif ($turnip > 32) {
                $i_code = "fig500"; $i_name = "500 Fighter Bays"; $i_price = 10000;
                $i_descr = "Squeeze 500 fighters into one upgrade pod.";
            } elseif ($turnip > 28) {
                $i_code = "upbs"; $i_name = "Battleship Conversion"; $i_price = 20000;
                $i_descr = "Enables more damage when attacking, increases shields/hr by 50%.";
            } else {
                $i_code = "up2"; $i_name = "Terra Maelstrom Upgrade"; $i_price = 1000000;
                $i_descr = "The only Upgrade for the Brobdingnagian. Rare but extremely potent.";
            }
        }
        $escapedCode  = mysqli_real_escape_string($maint_link, $i_code);
        $escapedIName = mysqli_real_escape_string($maint_link, $i_name);
        $escapedDescr = mysqli_real_escape_string($maint_link, $i_descr);
        mysqli_query($maint_link, "insert into {$game}_bilkos (timestamp, item_type, item_code, item_name, going_price, descr, active) " .
            "values(" . time() . ", '$i_type', '$escapedCode', '$escapedIName', '$i_price', '$escapedDescr', 1)");
    }

    print "Hourly maintenance for $game is... ";
    mysqli_query($maint_link, "INSERT INTO {$game}_news (timestamp, headline, login_id) values (" . time() . ", 'Hourly Maintenance Run', '1')");
    print "complete!\n";
    print "------------\n\n";
}

mysqli_close($maint_link);

?>
