<?php

class sCALDAV {
	protected $url, $u, $p, $caldav;
	function __construct($url, $u, $p, $caldav = true) {
		$this->url = $url; 
		$this->u = $u; 
		$this->p = $p; 
		$this->caldav = $caldav;
	}
	
	function file_get_contents_curl($url, $headers = array(), $method = "GET", $body = null) {
		$ch = curl_init();

	 	$ha = array();
	 	foreach($headers AS $k => $v) {
	 		$ha[] = $k . ': ' . $v;
	 	}
	 	curl_setopt($ch, CURLOPT_HTTPHEADER, $ha);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array()); 
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		if (!empty($body)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		curl_setopt($ch, CURLOPT_USERPWD, $this->u . ':' . $this->p);

		$data = curl_exec($ch);
		curl_close($ch);

		// print_r($ch);
	 
		return $data;
	}


	function xml2json ($fileContents) {
		$simpleXml = simplexml_load_string($fileContents, 'SimpleXMLElement', LIBXML_NOCDATA);
		$json = json_encode($simpleXml);
		return $json;
	}

	function report($from, $to) {
		$headers = array(
			"Content-Type:" => "text/calendar",
		);
		$req = '<?xml version="1.0" encoding="utf-8" ?>
		<C:calendar-query xmlns:D="DAV:"
		              xmlns:C="urn:ietf:params:xml:ns:caldav">
		  <D:prop>
		    <D:getetag/>
		    <C:calendar-data>
		      <C:comp name="VCALENDAR">
		        <C:prop name="VERSION"/>
		        <C:comp name="VEVENT">
		          <C:prop name="SUMMARY"/>
		          <C:prop name="UID"/>
		          <C:prop name="DTSTART"/>
		          <C:prop name="DTEND"/>
		          <C:prop name="DURATION"/>
		          <C:prop name="RRULE"/>
		          <C:prop name="RDATE"/>
		          <C:prop name="EXRULE"/>
		          <C:prop name="EXDATE"/>
		          <C:prop name="RECURRENCE-ID"/>
				</C:comp>
		        <C:comp name="VTIMEZONE"/>
		      </C:comp>
		    </C:calendar-data>
		  </D:prop>
		  <C:filter>
		    <C:comp-filter name="VCALENDAR">
		      <C:comp-filter name="VEVENT">
		      <C:time-range start="' . $from . '"
		                      end="' . $to . '"/>
		      </C:comp-filter>
		    </C:comp-filter>
		  </C:filter>
		</C:calendar-query>';
		

		$rawdata = $this->file_get_contents_curl($this->url, $headers, "REPORT", $req);
		$data = json_decode($this->xml2json($rawdata), true);

			// echo '<pre>rawdata:';
		 // print_r($rawdata); 

		if (empty($data["response"])) return array();

		if (isset($data["response"]["href"])) {
			return array($data["response"]["href"]);
		} else {
			$res = array();
			foreach($data["response"] AS $r) {
				// print_r($r); 
				if (isset($r["href"])) {
					$res[] = $r["href"];
				}
			}
			return $res;
		}

		// return $data["response"]["href"];
	}


	function reportFile($file) {

	}

	function reportFiles($files) {

		$filetxt ='';

		foreach($files AS $file) {
			$filetxt .= '<D:href>' . $file . '</D:href>';
		}

		$headers = array(
			"Content-Type:" => "text/calendar",
		);
		$req = '<?xml version="1.0" encoding="utf-8" ?>
		<C:calendar-multiget xmlns:D="DAV:"
		                 xmlns:C="urn:ietf:params:xml:ns:caldav">
		  <D:prop>
		    <D:getetag/>
		    <C:calendar-data/>
		  </D:prop>
		  ' . $filetxt . '
		</C:calendar-multiget>';
		// echo $req;
		// $raw = $this->file_get_contents_curl($this->url, $headers, "REPORT", $req);
		$data = json_decode($this->xml2json($this->file_get_contents_curl($this->url, $headers, "REPORT", $req)), true);

		// $simpleXml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);


		 // echo '<pre>'; print_r($data); exit;

		
		if (isset($data["response"]["propstat"]["prop"]["calendar-data"])) {
			return array($data["response"]["propstat"]["prop"]["calendar-data"]);
		} else {
			$res = array();	
			foreach($data["response"] AS $r) {
				$res[] = $r["propstat"]["prop"]["calendar-data"];
			}
			return $res;
		}
		return null;

		// return $data["response"]["propstat"]["prop"]["calendar-data"];
	}

	function processEvent($event, $key) {
		if (isset($event->dtstart)) {
			$event->dtstartEpoch = strtotime($event->dtstart["value"]);
			$event->dtstartDate = date('Y-m-d', $event->dtstartEpoch);
		}
		if (isset($event->dtend)) {
			$event->dtendEpoch = strtotime($event->dtend["value"]);	
			$event->dtendDate = date('Y-m-d', $event->dtendEpoch);
		}
		$event->caltype = 'na';
		if (isset($event->dtstartDate) && isset($event->dtendDate)) {
			if ($event->dtstartDate === $event->dtendDate) {
				$event->caltype = 'singleday';

				$event->timerange = date('H:i', $event->dtstartEpoch) . '-' . date('H:i', $event->dtendEpoch);

			} else {
				$event->caltype = 'multiday';
				$event->multidays = array($event->dtstartDate);
				$current = strtotime($event->dtstartDate);
				$more = true;
				while($more) {
					$current = strtotime('+1 day', $current);
					if ($current < $event->dtendEpoch) {
						$event->multidays[] = date('Y-m-d', $current);
					} else {
						$more = false;	
					}
				}
			}
		}
		$event->calendar = $key;
		return $event;
	}


	function get($key) {
		$from = gmdate('Ymd\THis\Z', time() - (3600*24));
		$to = gmdate('Ymd\THis\Z', time() + (3600*24*30));

		$cals = array();


		if ($this->caldav) {
			$files = $this->report($from, $to);
			// echo '<pre>'; print_r($files); exit;
			if (empty($files)) return null;
			$cals = $this->reportFiles($files);
			// echo '<pre>'; print_r($cal); exit;
		} else {
			$raw = file_get_contents($this->url);
			$cals = array($raw);
			// echo '<pre>Raw: '; print_r($cals); exit;
		}


		$events = array();

		foreach($cals AS $cal) {
			// echo '<pre>Processing: ' . $cal;
			$p = new Parser();
			$p->process_file($cal);
			//echo '<pre>eventlist: '; print_r($p->event_list);
			if (!empty($p->event_list)) {
				foreach($p->event_list AS $e) {
					$events[] = $this->processEvent($e, $key);
				}
			}
		}

		return json_decode(json_encode($events), true);

		// return $p->event_list;

		// $ical = new ical();
		// $ical->parse($cal);
		// echo "<pre>";
		// $ical->get_all_data();

	}


}