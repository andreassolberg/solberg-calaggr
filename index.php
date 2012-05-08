<?php

require("./cal/functions/class.iCalObj.php");
require("./cal/functions/class.Vtimezone.php");
require("./cal/functions/class.Daylight.php");
require("./cal/functions/class.Standard.php");
require("./cal/functions/class.Vcalendar.php");
require("./cal/functions/class.Vevent.php");
require("./cal/functions/class.Vtodo.php");

require("./cal/functions/class.Parser.php");
require("./scaldav.php");
require("./middag.php");




$path = dirname(dirname(dirname(__FILE__))) . '/_config/';
// echo $path; exit;


$credentials = json_decode(file_get_contents($path . 'credentials.js'), true);
$calendars = json_decode(file_get_contents($path . 'calendars.js'), true);

if (isset($_REQUEST['test'])) {
	$calendars = json_decode(file_get_contents($path . 'calendars-test.js'), true);
}

// echo '<pre>'; print_r($credentials); print_r($calendars); exit;
// echo json_encode($calendars); exit;

$result = array();

foreach($calendars AS $key => $cal) {
	$u = $credentials["cal"]["u"];
	$p = str_rot13($credentials["cal"]["p"]);
	// echo $p; exit;
	$c = new sCALDAV($cal['url'], $u, $p, $cal['caldav']);
	// $result[$key] = $c->get();
	$res = $c->get($key);
	if (!empty($res)) {
		$result = array_merge($result, $res);
	} else {
		// echo "NO output from calendar " . $key . " . ";
	}
	
}

// echo '<pre>'; print_r($result); exit;


$structured = array();

$current = date('Y-m-d');
$structured[$current] = array('text' => 'I dag (' . date('l') . ')', 'middag' => array(), 'events' => array());

$currentepoch = strtotime('+1 day', strtotime($current));
$current = date('Y-m-d', $currentepoch);
$structured[$current] = array('text' => 'I morgen (' . date('l', $currentepoch) . ')', 'middag' => array(), 'events' => array());

for ($i = 0; $i < 5; $i++) {
	$currentepoch = strtotime('+1 day', strtotime($current));
	$current = date('Y-m-d', $currentepoch);
	$structured[$current] = array('text' => date('l', $currentepoch), 'middag' => array(), 'events' => array());
}

$structured['future'] = array('text' => 'Senere', 'middag' => array(), 'events' => array());

// echo '<pre>RESULT:';
// print_r($result);

foreach($result AS $event) {
	if (!isset($event['dtstartDate'])) continue;
	if (isset($structured[$event['dtstartDate']])) {
		if ($event['caltype'] === 'multiday') {
			foreach($event['multidays'] AS $d) {
				$structured[$d]['events'][] = $event;
			}
		} else {
			$structured[$event['dtstartDate']]['events'][] = $event;	
		}
		
	} else {
		$structured['future']['events'][] = $event;
	}
}

$middag = new Middag();

$middager = $middag->get();


// print_r($structured);
if (!empty($_GET['debug'])){

	header("Content-Type: text/plain; charset: utf-8");
	print_r($structured);

} else if (!empty($_GET['callback'])) {
	header('Content-Type: application/json; charset=utf-8');
	echo $_GET['callback'] . '('.json_encode($structured).')';
} else {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($structured);
}


