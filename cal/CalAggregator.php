<?php

/**
 * Calendar aggregator
 */
class CalAggregator {

	protected $fdb, $user, $groupid, $date, $from, $to, $slotsize, $days;

	protected $slots;
	protected $users;
	protected $availability;	
	
	
	// ($this->fdb, $this->user, $groupid, $date, 'Europe/Amsterdam', 8, 16, $duration);
	public function CalAggregator($db, $user, $groupid, $date, $timezone, $from = 8, $to = 16, $slotsize = 1, $days = 1) {
	
		$this->fdb = $db;
		$this->user = $user;
		$this->groupid = $groupid;
		$this->date = $date;
		$this->timezone = $timezone;
		$this->from = $from;
		$this->to = $to;
		$this->slotsize = $slotsize;
		$this->days = $days;
		
		$this->prepareSlots();
		$this->loadUsers();
		$this->checkavail();
	}
	
	private function toEpoch($str) {
		if (empty($str)) return FALSE;
		if (!strtotime($str)) return FALSE;
		$tdz = null;
		if (!empty($this->timezone)) {
			$tdz = new DateTimeZone($this->timezone);
			$d =  new DateTime($str, $tdz);
#			error_log('String [' . $str . '] to timezone ' . $this->timezone);
		} else {
			$d =  new DateTime($str);
#			error_log('String [' . $str . '] ');
		}

#		$d =  new DateTime($str, $tdz);
		return $d->format('U');
	}
	
	private function getRelDate($i) {
	
		$c = abs($i);
		
		error_log('getRelDAte input: ' . $i);
		error_log('getRelDAte input: ' . $this->date);		
		
		$current = strtotime($this->date);
		
		while($c > 0) {
			error_log('current ' . $current);
			$current = strtotime(($i>0 ? '+1 day' : '-1 day'), $current);
			
			// Do not count sunday (0) or saturday (6)
			if (!in_array(date('w', $current), array(0, 6))) {
				$c--;
			}

		}
		
		error_log('Returning ' . date('Y-m-d', $current));	
	
		return date('Y-m-d', $current);
	}
	
	private function prepareSlots() {
		if (!in_array($this->slotsize, array(1,2,3,4,5,6,7,8))) throw new Exception('Invalid slot size ' . (int) $this->slotsize);
		
		// Iterate multiple days
		for ($d = 0; $d < $this->days; $d++) {	
			
			$curdate = $this->getRelDate($d);
			
			$dayslots = array(
				'date' => $curdate,
				'dateh' => date('l j. F Y', strtotime($curdate)),
				'slots' => array()
			);
			
			// Iterate slots for each day.
			for($i = $this->from; $i <= ($this->to - $this->slotsize); $i++) {
				
				$textFrom =  sprintf("%'02s",  $i) . ':00';
				$textTo =  sprintf("%'02s",  ($i + $this->slotsize)) . ':00';
				
				$datefrom = $curdate . ' ' . $textFrom; 
				$dateto   = $curdate . ' ' . $textTo; 
				
				$epochFrom = $this->toEpoch($datefrom);
				$epochTo   = $this->toEpoch($dateto);
				
	
				
				$dayslots['slots'][] = array(
	// 				'from' => $i,
	// 				'to' => $i + $this->slotsize,
					'fromText' => $textFrom,
					'toText' => $textTo,
					'fromEpoch' => $epochFrom,
					'toEpoch' => $epochTo,
				);
			}
			$this->slots[] = $dayslots;
			
		
		
		}
		

		
		
		
	}
	
	private function loadUsers() {
		$list = $this->fdb->getContactlist($this->user, $this->groupid);
		
		$this->users = array();
		
		foreach($list as $uc) {
			$user = $this->fdb->readUser($uc['userid']);
			if (!$user->hasCalendar()) continue;
			$this->users[] = $user;
		}
		
		
//		$this->db->();
	}
	
	private function checkavail() {


		
		// Iterate all users
		foreach($this->users AS $user) {
		
			if (!$user->hasCalendar()) continue;
			$aggregator = $user->getCalendarAggregator();
			
			
			foreach($this->slots AS $dkey => $day) {
				
				
				// Each $day entry has these properties
				// 		date - string
				//		slots []
				
				foreach($day['slots'] AS $key => $dslot) {
					
					$slotres = 1;
					
					// error_log('Checking availability for slot: ' .$dslot['fromEpoch'] . ' to ' .  $dslot['toEpoch'] );
					
					$crash = $aggregator->available($dslot['fromEpoch'], $dslot['toEpoch']);
					if ($crash['available'] === 'BUSY') $slotres = 0;
					if ($crash['available'] === 'BUSY-TENTATIVE') $slotres = 2;
	
					if(empty($this->slots[$dkey]['slots'][$key]['avail'])) {
						$this->slots[$dkey]['slots'][$key]['avail'] = array();
					}
					$this->slots[$dkey]['slots'][$key]['avail'][] = $slotres;
					
					
				}
				
		
			}
			

		}
	
	

	


	}
	
	public function dumpSlots() {
		echo '<pre>Slots:'; print_r($this->slots); echo '</pre>';
		echo '<pre>Users:'; print_r($this->users); echo '</pre>';
		echo '<pre>Availability:'; print_r($this->availability); echo '</pre>';
	}

	public function getData($days = 1) {
		
		$data = array();
		
		
		$data['timezone'] = $this->timezone;
		$data['firstday']  = $this->date;
		$data['firstdayh'] = date('l j. F Y', strtotime($this->date));
		$data['slots'] = $this->slots;
		
		$data['next'] = $this->getRelDate($this->days);
		$data['prev'] = $this->getRelDate(-($this->days));

		// Create list of users
		$data['users'] = array();
		foreach($this->users AS $user) {
			
			$newuser = array();
			$newuser['userid'] = $user->userid;
			if (!empty($user->username)) $newuser['name'] = $user->username;
			$data['users'][] = $newuser;
		}

		return $data;
	}



}
