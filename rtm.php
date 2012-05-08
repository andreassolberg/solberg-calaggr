<?php

/**
 * Remember the Milk API Class for the Remember the Milk API
 *
 * Provides an easy way for PHP users to implement the RTM API.
 * 
 * NOTE: In order to use this API you need an API key and shared secret. To get these go to: http://www.rememberthemilk.com/services/api/keys.rtm
 *
 * Usage Examples (View http://www.rememberthemilk.com/services/api/ for more details):
 * 1) Initialize the class:
 *		$rtm = new RTM([API_KEY],[SHARED_SECRET]);
 * 2) Get a link so the user can authorize your program:
 *  	$url = $rtm->genAuthURL([ACCESS_TYPE_REQUIRED]);
 *  		Allowed access types are: read, write, and delete
 * 3) Use the frob returned to get a token (Uses SimpleXMLElement to read the returned XML):
 *  	$ret = $rtm->doMethod('auth','getToken');
 *		$xml = new SimpleXMLElement($ret);
 *		$token = (string) $xml->auth->token;
 * 4) Use the token to do any other method:
 *  	$args['auth_token'] = $token:		// Requires the term 'auth_token' to work
 *		echo $rtm->doMethod('auth','checkToken',$args);
 *		
 * The doMethod() method is very versatile. First parameter is the method type (ex. auth, tasks, contacts), second parameter is the method (ex. checkToken, getList), and third parameter is an optional array for arguments.
 * To use the thrid parameter, simply setup an array with all arguments you want other then api_key, api_sig, and method:
 *		Two styles:
 *			$args = array(
 *				'auth_token'	=> $token,
 *				'timeline'		=> $timeline,
 *				'list_id'		=> $list_id
 *			);
 *			echo $rtm->doMethod('lists','archive',$args);
 *			
 *			OR
 *			
 *			$args['auth_token'] = $token;
 *			$args['timeline'] = $timeline;
 *			$args['list_id'] = $list_id;
 *			echo $rtm->doMethod('lists','archive',$args);
 *			
 * This API Kit is under open development. This means it is still being improved and if there are any outside additions/improvements, please send me an email so I can include them.
 *	
 * (c)Tyler Johnson. This code can be modified, copied, and redistrbuted, but not sold. Donations accepted.
 *			
 * @author		Tyler Johnson, Anti-Radiant Creative
 * @email		tylerj@arcreate.net
 * @link		http://arcreate.net/stuff/random/RTM/rtm_php_v0.1b.zip
 * @version		0.1b
 */
 
// ------------------------------------------------------------------------

/**
 * RTM API Class Base
 *
 * The main two functions used by the developer.
 * 
 * @access public
 * @return mixed
 */

abstract class RTM_Base
{
	/**
	 * Generate Authorization URL
	 *
	 * Generates the URL needed for the application to be authorized to access someones account.
	 *
	 * @access	public
	 * @param	string		read, write, or delete
	 * @return	string
	 */
	function genAuthURL($perms)
	{
		$args['perms'] = $perms;
		$api_sig = md5($this->api_sig(false,$args));
		return 'http://www.rememberthemilk.com/services/auth/?api_key='.$this->apikey.'&perms='.$perms.'&api_sig='.$api_sig;
	}

	/**
	 * Do API Method
	 *
	 * Sends a get request to RTM and returns the xml. Use SimplXMLElement to read.
	 *
	 * @access	public
	 * @param	string		method type
	 * @param	string		method
	 * @param	array		arguments
	 * @return	string		format: xml
	 */
	function doMethod($type, $method, $args = array())
	{
		$method = $type.'.'.$method;
		$ret = $this->apiCall($method, 'get', $args, true);
		return $ret;
	}

}

/**
 * Main RTM API Class
 *
 * Connections.
 * @access	public
 * @param	string		api_key
 * @param	string		shared secret
 * @return	string
 */
class RTM extends RTM_Base {
 
	function __construct($apikey,$secret) {
		$this->apikey = $apikey;
		$this->secret = $secret;
	}
	
	/**
	 * Generate API URL
	 *
	 * Generates the URL to make a get a request.
	 *
	 * @access	public
	 * @param	string		method
	 * @param	array		arguments
	 * @param	bool		require signature
	 * @return	string
	 */
	function apiURL($rtm_method, $args = array(), $require_sig = true)
	{
		$api_url = 'http://api.rememberthemilk.com/services/rest/';
		$api_url .= '?method=rtm.' . $rtm_method . '&api_key=' . $this->apikey;
		if (is_array($args) && count($args) > 0) {
			$api_url .= '&' . http_build_query($args);
		}
		if ($require_sig) {
			$api_sig = $this->api_sig($rtm_method,$args);
			$api_sig = md5($api_sig);
			$api_url .= '&api_sig='.$api_sig;
		}
		return $api_url;
	}
	
	/**
	 * Generate API Signature
	 *
	 * Generates the signature needed to make certain requests.
	 *
	 * @access	public
	 * @param	string		method
	 * @param	array		arguments
	 * @return	string
	 */
	function api_sig($rtm_method,$args = array())
	{
		$args['api_key'] = $this->apikey;
		if ($rtm_method) {
			$args['method'] = 'rtm.'.$rtm_method;
		}
		ksort($args);
		$api_sig = $this->secret;
		foreach ($args as $key => $value) {
			$api_sig .= $key.$value;
		}
		
		return $api_sig;
	}
 
	/**
	 * Make the Get Request
	 *
	 * Makes the get request and returns the xml
	 *
	 * @access	public
	 * @param	string		method
	 * @param	string		curl method (always get unless RTM changes to post)
	 * @param	array		arguments
	 * @param	bool		require signature
	 * @return	string
	 */
	function apiCall($rtm_method, $http_method, $args = array(), $require_sig = true) {
	
		$api_url = $this->apiURL($rtm_method, $args, $require_sig);
		
		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL, $api_url);
		if ($http_method == 'post') {
			curl_setopt($curl_handle, CURLOPT_POST, true);
		}
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array('Expect:'));
		$rtm_data = curl_exec($curl_handle);
		$this->http_status = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
		$this->last_api_call = $api_url;
		curl_close($curl_handle);
		return $rtm_data;
	}
 
}
?>