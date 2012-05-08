<?php

require('rtm.php');
ini_set( 'default_charset', 'UTF-8' );
setlocale(LC_TIME, "no_NO");

function cmp($a, $b) {
	if (empty($a["due"])) return 1;
	if (empty($b["due"])) return -1;
	if ($a["due"] == $b["due"]) return 0;
	return ($a["due"] < $b["due"]) ? -1 : 1;
}


class Middag {

	function prettyDue($t) {

		$d = strtotime($t);
		$today = date('Y-m-d');
		$tomorrow = date('Y-m-d', strtotime('+1 day'));
		$date = date('Y-m-d', $d);

		if ($date == $today) {
			return 'i dag';
		} else if ($date == $tomorrow) {
			return 'i morgen';
		} else if ($d < time()) {
			return $date;
		} else {
			return date('l', $d);
			return strftime('%A', $d);
		}

	}

	function get($cred) {

		$rtm = new RTM($cred["key"],$cred["secret"]);
		$frob = $cred["frob"];
		$token = $cred["token"];

		$args = array(
			'auth_token'	=> $token,
			'format' => 'json',
			'list_id' => '13927610',
		);
		$ret = $rtm->doMethod('tasks','getList', $args);

		$tdata = json_decode($ret, true);

		$data = array();
		foreach($tdata["rsp"]["tasks"]["list"]["taskseries"] AS $k => $v) {

			if (empty($v['task']["completed"])) {
				$n = array();
				foreach($v AS $kk => $kv) { 
					$n[$kk] = $kv; } 
				$n["has_due_time"] = $v["task"]["has_due_time"];
				$n["due"] = $v["task"]["due"];
				$n["dn"] = $n["name"];
				if (!empty($v["task"]["due"])) {
					$n["date"] = date('Y-m-d', strtotime($v["task"]["due"]));
					$n["prettydue"] = $this->prettyDue($v["task"]["due"]);
					$n["dn"] = $n["dn"] . ' ( ' . $n["prettydue"] . ' )';
					// echo '<p>' . $v["task"]["due"] . " becomes " . htmlentities($n["prettydue"]);
				}
				$data[] = $n;
			}

		}
		usort($data, "cmp");

		return $data;
	}

	function output() {

		$data = $this->get();

		if (!empty($_GET['debug'])){

			header("Content-Type: text/plain; charset: utf-8");
			print_r($data);

		} else if (!empty($_GET['callback'])) {
			header('Content-Type: application/json; charset=utf-8');
			echo $_GET['callback'] . '('.json_encode($data).')';
		} else {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($data);
		}
	}



}






