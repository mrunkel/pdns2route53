#!/usr/bin/php
<?php
/**
 * Reads a powerDNS mysql database and generates commands to load the data into route53 via cli53
 * User: mrunkel
 * Date: 3/25/14
 * Time: 2:50 PM
 */

$shortOptions = "u::"; //optional mysql username
$shortOptions .= "p::"; // optional mysql password
$shortOptions .= "d::"; // optional database name
$shortOptions .= "h::"; // optional hostname
$shortOptions .= "z:"; // required zone to transfer.
$options = getopt($shortOptions);

if (array_key_exists('u',$options)) {
    $user = $options['u'];
} else {
    $user = 'root';
}

if (array_key_exists('p',$options)) {
    $password = $options['p'];
} else {
    $password = 'root';
}

if (array_key_exists('h',$options)) {
    $hostname = $options['h'];
} else {
    $hostname = 'localhost';
}

if (array_key_exists('d',$options)) {
    $database = $options['d'];
} else {
    $database = 'pdns';
}

if (array_key_exists('z',$options)) {
    $zone = $options['z'];
} else {
    die ("Need to specify at least a zone to dump via -z");
}

$db = new PDO("mysql:host=".$hostname.";dbname=".$database, $user, $password,
                    array(PDO::ATTR_EMULATE_PREPARES => false,  // newish version of MySQL
                          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)); // throw exceptions on error

date_default_timezone_set("America/Los_Angeles");

function getDomainId ($domain) {
    global $db;

    $stmt = $db->prepare("SELECT id FROM domains WHERE name=?");
    $stmt->execute(array($domain));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stmt->rowCount() <= 0) {
        return false;
    } else {
        return $row['id'];
    }
}

if ($id = getDomainId($zone)) {
    $stmt = $db->prepare("SELECT name, TYPE, content, ttl, prio FROM records WHERE domain_id = ?");
    $stmt->execute(array($id));
    if ($stmt->rowCount() <= 0) {
        die ("No records found for that domain.");
    }
    $mxList = array();
    $srvList = array();
    $nsList = array();
//    usage: cli53 rrcreate [-h] [-x TTL] [-w WEIGHT] [-i IDENTIFIER]
//                      [--region REGION] [-r] [--wait] [--dump]
//                      zone rr {A,AAAA,CNAME,SOA,NS,MX,PTR,SPF,SRV,TXT,ALIAS}
//                      values [values ...]
    echo "#!/bin/bash" . PHP_EOL;
    echo 'echo "Purging old records."' . PHP_EOL;
    echo "cli53 rrpurge --confirm " . $zone . PHP_EOL;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // add the period to the end of the record name
        $name = $row['name'];
        if ($name == "") {
            $name = "''";
        } else {
            $name .= ".";
        }
        $row['name'] = $name;

        switch ($row['TYPE']) {
            case 'SOA':
                // no need to move the SOA, it already exists at amazon
                break;
            case 'MX':
		// save up all the MX records and make the Amazon combo MX records later
                $mxList[$name][] = $row;
                break;
            case 'SRV':
		// save up all the SRV records and make the Amazon combo SRV records later
                $srvList[$name][] = $row;
                break;
            case 'NS':
                if ($name != $zone . ".") { // don't bother with our own NS records
                    $nsList[$name][] = $row;
                }
                break;
            default:
		echo 'echo "creating ' . $row['TYPE'] . ' record for ' . $name . ' with content: ' . $row['content'] . '"' . PHP_EOL; 
                echo "cli53 rrcreate -x " . $row['ttl'] . " " .  $zone . " " . $name . " " . $row['TYPE'] . " " . $row['content'] . PHP_EOL;
        }
    }
    // Create MX entries
    foreach ($mxList as $mx) {
        $serverList = "";
        foreach ($mx as $row) {
            $serverList .= "'" . $row['prio'] . " " . $row['content'] . "' ";
            $name = $row['name'];
            $ttl = $row['ttl'];
        }
	echo 'echo "Creating MX Records."' . PHP_EOL;
        echo "cli53 rrcreate -x " . $ttl . " " .  $zone . " " . $name . " MX " . $serverList . PHP_EOL;
    }
    foreach ($srvList as $srv) {
        $serverList = "";
        foreach ($srv as $row) {
            $serverList .= "'" . $row['content'] . "' ";
            $name = $row['name'];
            $ttl = $row['ttl'];
        }
	echo 'echo "Creating SRV Records."' . PHP_EOL;
        echo "cli53 rrcreate -x " . $ttl . " " .  $zone . " " . $name . " SRV " . $serverList . PHP_EOL;
    }
    foreach ($nsList as $ns) {
        $serverList = "";
        foreach ($ns as $row) {
            $serverList .= "'" . $row['content'] . "' ";
            $name = $row['name'];
            $ttl = $row['ttl'];
        }
	echo 'echo "Creating NS Records."' . PHP_EOL;
        echo "cli53 rrcreate -x " . $ttl . " " .  $zone . " " . $name . " NS " . $serverList . PHP_EOL;
    }
} else {
    die ("domain not found!" . PHP_EOL);
}
