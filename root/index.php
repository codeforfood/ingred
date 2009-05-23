<? 
/*
 * Ingred -  RESTful PHP Deployment <http://ingred.burden.cc/>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *  
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *  
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @copyright		Copyright (c) 2009, Mike Garegnani
 * @package			ingred
 * @version			0.3
 * @author			Mike Garegnani
 *
----- n o t e s ---------------------------

--> REQUEST --> asset retrieval --> method--.  
                                             )
<-- RESPONSE <-- mime <-- response code <--' 

REST is logic-kill - think crud

	POST = add = create
	GET = get = read
    PUT = edit = update
	DELETE = delete = delete

# TODO: build sql module
# TODO: sanity checks in the cfg, and val functions
# TODO: consider building flags to make sure certain functions were only used once
# TODO: figure out how long a request takes to execute
# TODO: look into caching
# TODO: Document each class, function, and method - thoroughly
# TODO: Best practices
*/

class ingred {
	
  	# whatever isn't specified in $response 
	public $response = null;
	
	# ends up in $debug
	public $debug = null;
	
	public $cfg = array();
	public $vals = array();

	# tool box
	public $mysql = null; # coming in 0.4
	public $xhtml = null;
	public $xml = null;
	public $io = null;
	
	# modules
	public $project = null; # used to load pages
	public $design = null; 

	public function ingred(){
		$this->vals['project.loaded'] = $this->get_microtime();
		$this->vals['http.request.time'] = $_SERVER['REQUEST_TIME'];
		$this->vals['ingred.version'] = '0.3';
		
		$this->init_uri();
		
		# figger out where we are
		$this->vals['http.host'] = $_SERVER['HTTP_HOST'];
		# TODO: make http:// less static
		$this->vals['project.url'] = 'http://' . $this->vals['http.host'];
		$this->cfg['project.url.var'] = '$url';
		$this->cfg['project.request_url.var'] = '$request_url';
		if (isset($_SERVER['REQUEST_URI'])) $this->vals['http.request.uri'] = $_SERVER['REQUEST_URI']; 
		$this->vals['http.request.url'] = $this->vals['project.url'] . $this->vals['http.request.uri']; 
		$this->vals['visitor.ip'] = $this->get_ip();

		# default/important settings that are necessary but overwritable
		$this->cfg['project.name'] = 'ingred';
		$this->cfg['project.default.asset'] = 'home';
		$this->cfg['project.default.design'] = 'ingred';
		$this->cfg['project.debug.show'] = false;
		$this->cfg['project.production_status'] = 'dev'; //dev, live, debug
		$this->cfg['project.timezone'] = 'America/Los_Angeles'; // for values see: http://php.net/timezones
		$this->cfg['project.max.uri_len'] = 40;
		$this->cfg['project.file_uploads'] = true;
		$this->cfg['project.max.post'] = null;
		$this->cfg['project.max.filesize'] = null;
		$this->cfg['project.zlib.out'] = true;

		$this->cfg['dir'] = '/'; # some operating systems like \ instead
		#$this->cfg['dir.root'] = 'root'; // root cannot be changed. establish it with ingred. and make sure it's not changed.

		# session happiness
		$this->cfg['session.enabled'] = true;
		$this->cfg['session.name'] = 'sess_id';
		$this->cfg['session.path'] = null; // default
		$this->cfg['session.lifetime'] = 60*60*24*6; // in seconds
	
		# .htaccess (read-only) settings
		$this->vals['php.magic_quotes_gpc'] = get_magic_quotes_gpc();
		$this->vals['php.register_globals'] = ini_get('register_globals');
		$this->vals['php.allow_url_fopen'] = ini_get('allow_url_fopen'); 
		
		# Server stuff
		if(isset($_SERVER['REQUEST_METHOD'])) $this->vals['http.request.method'] = strtolower($_SERVER['REQUEST_METHOD']);
		if(isset($_SERVER['CONTENT_TYPE'])) $this->vals['http.request.content_type'] = $_SERVER['CONTENT_TYPE'];

		if ($this->vals['http.request.method'] == 'put' || $this->vals['http.request.method'] == 'post'){
			if(isset($_SERVER['CONTENT_LENGTH'])) $this->vals['http.request.content_length'] = $_SERVER['CONTENT_LENGTH'];
			$this->get_request_input(); 
		}

		if (isset($_SERVER['HTTP_USER_AGENT'])) $this->vals['http.request.useragent'] = $_SERVER['HTTP_USER_AGENT'];
		if (isset($_SERVER['REDIRECT_ERROR_NOTES'])) $this->vals['http.redirect.error_notes'] =  $_SERVER['REDIRECT_ERROR_NOTES'];
		if (isset($_SERVER['REDIRECT_STATUS'])) $this->vals['http.redirect.status'] = $_SERVER['REDIRECT_STATUS'];
		
		// agent driven negotiation
		if (isset($_SERVER['HTTP_ACCEPT'])) $this->vals['http.request.accept.type'] = $this->sort_accept($_SERVER['HTTP_ACCEPT']); 
		if (isset($_SERVER['HTTP_ACCEPT_CHARSET'])) $this->vals['http.request.accept.charset'] = $this->sort_accept($_SERVER['HTTP_ACCEPT_CHARSET']); 
		if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) $this->vals['http.request.accept.encoding'] = $this->sort_accept($_SERVER['HTTP_ACCEPT_ENCODING']);
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) $this->vals['http.request.accept.language'] = $this->sort_accept($_SERVER['HTTP_ACCEPT_LANGUAGE']); 

	}
	#-------------------------
	# init_dirs
	#-------------------------
	# Setup the directory structure for
	# use within your project
	/*
			ingred will go back a directory from where the script is executing by default

			1. look at execution (most likely in index.php)

			2. directory structure:
		
				/document_root/
				../assets/
				../conf/
				../logs/

			3. more structure:
				$assets/tpl/
				$conf/lib/
	*/
	# example:	this example will overwrite the default conf directory
	# note:		this must be done before $ingred->init(); is executed. 
	#
	# $ingred->cfg('dir.conf', '/var/domains/home/conf');
	# $ingred->init_dirs() will be called, later on, when $ingred->init(); is used
	# 
	private function init_dirs(){
		# TODO:	quality assurance
		# 		strange things may happen if you decide
		#		to change the default directories
		
		# setup directory structures
		$this->debug('init_dirs() ' . time(), 'info');

		//if (empty($this->cfg['dir.root'])) $this->cfg['dir.root'] = substr($_SERVER['SCRIPT_FILENAME'], 0, strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['PHP_SELF']));

		# root is always where the executing script is.
		# i find that i need an anchor so i know where what is. 

		#if (empty($this->cfg['dir.root'])){
			$this->cfg['dir.root'] = explode('/', $_SERVER['SCRIPT_FILENAME']);
			array_pop($this->cfg['dir.root']);
			$this->cfg['dir.root'] = implode('/', $this->cfg['dir.root']) . $this->cfg['dir'];
		#}
		
		if (empty($this->cfg['dir.assets'])){
			#$this->cfg['dir.assets'] = substr($this->cfg['dir.root'], 0, strrpos($this->cfg['dir.root'], '/'))  . 'assets' . $this->cfg['dir'];
			$this->cfg['dir.assets'] = explode('/', $this->cfg['dir.root']);
			array_pop($this->cfg['dir.assets']);
			array_pop($this->cfg['dir.assets']);
			$this->cfg['dir.assets'] = implode('/', $this->cfg['dir.assets'])  . $this->cfg['dir']. 'assets' . $this->cfg['dir'];
			if ($this->cfg['project.whois'] == true) $this->cfg['dir.assets'] .= $this->vals['http.host'] . $this->cfg['dir'];
		}

		if (empty($this->cfg['dir.conf'])){
			#$this->cfg['dir.conf'] = substr($this->cfg['dir.root'], 0, strrpos($this->cfg['dir.root'], '/')) . 'conf' .  $this->cfg['dir'];
			$this->cfg['dir.conf'] = explode('/', $this->cfg['dir.root']);
			array_pop($this->cfg['dir.conf']);
			array_pop($this->cfg['dir.conf']);
			$this->cfg['dir.conf'] = implode('/', $this->cfg['dir.conf'])  . $this->cfg['dir']. 'conf' . $this->cfg['dir'];
		}
		
		$this->cfg['dir.tpl'] = $this->cfg['dir.conf'] . 'tpl' . $this->cfg['dir'];

		$this->cfg['dir.lib'] = $this->cfg['dir.conf'] . 'lib' . $this->cfg['dir'];
		$this->cfg['dir.wsdl'] = $this->cfg['dir.lib'] . 'wsdl' . $this->cfg['dir'];
		$this->cfg['dir.soap'] = $this->cfg['dir.lib'] . 'soap' . $this->cfg['dir'];
		$this->cfg['dir.json'] = $this->cfg['dir.lib'] . 'json' . $this->cfg['dir'];
		if (empty($this->cfg['public.dir.inc'])) $this->cfg['public.dir.inc'] = $this->vals['project.url'] . '/inc';
		$this->cfg['public.dir.css'] = $this->cfg['public.dir.inc'] . '/css';
		$this->cfg['public.dir.js'] = $this->cfg['public.dir.inc'] . '/js';
	}
	#-------------------------
	# init_uri
	#-------------------------		
	# populates $this->vals['http.uri']
	#
	# note:	this function is only called once, and that is when ingred(); is initialized
	#
  	private function init_uri(){
		//$this->debug('init_uri() ' . time(), 'info');
		$array_uri = explode('/', $_SERVER['REQUEST_URI']);
		$uri_count = count($array_uri); // always count on '/' being there. 

		if ($uri_count >= 2 && !empty($array_uri[1])){
			for ($i=1;$i<$uri_count;$i++){ // $i is 1 so we can ignore $i[0] (/)
				$pos = strpos($array_uri[$i], '?');
				if ($pos === false){
					$this->vals['http.uri'][] = $array_uri[$i];
				} else { // get rid of the ?bull=shit (it's in the $_get)
					$this->vals['http.uri'][] = substr($array_uri[$i], 0, $pos);
				}
				unset($pos);
			}
		} else {
			$this->vals['http.uri'][] = null;
		}
		// free up some memories
		unset($i, $uri_count, $array_uri);
	}
	#
	#-------------------------
	# init
	#-------------------------
	# everything is finalized in this function. 
	# directory paths, time zones, all sessions started, cookies set, 
	#
	# example:	$this->init();
  	# note:		this function is only called once, before $ingred->respond();
  	#
	public function init(){
		$this->trace();
		# ingred options, 
		if (empty($this->cfg['project.whois'])) $this->cfg['project.whois'] = false;
			
		# setup the directory structure
		# this function just accepts changes, otherwise creates defaults
		# use once
		
		$this->init_dirs();
		
		# setup time zone
		date_default_timezone_set($this->cfg['project.timezone']);
		$this->cfg['project.timezone'] = date_default_timezone_get();
    
		# setup max post size (not to be confused with upload!)
		if (!empty($this->cfg['project.max.post'])) ini_set('post_max_size', $this->cfg['project.max.post']);
		$this->vals['php.post_max_size'] = ini_get('post_max_size');

		# enable/disable file uploads
		if (!empty($this->cfg['project.file_uploads'])) ini_set('file_uploads', $this->cfg['project.file_uploads']);
		$this->vals['php.file_uploads'] = ini_get('file_uploads');
    
		# set max filesize
		#if (!empty($this->cfg['project.max.filesize'])) ini_set('post_max_filesize', $this->cfg['project.max.post']);
		$this->vals['php.upload_max_filesize'] = ini_get('upload_max_filesize');

		# handle compression
		if (!empty($this->cfg['project.zlib.out'])) ini_set('zlib.output_compression', $this->cfg['project.zlib.out']); 
		$this->vals['php.zlib.output_compression'] = ini_get('zlib.output_compression');

		# session happiness
		if ($this->cfg['session.enabled'] == true) { # purists will definitely want this false
			# set session path
			if (!empty($this->cfg['session.path'])) ini_set('session.cookie_path', $this->cfg['session.path']);
			$this->vals['php.session.cookie_path'] = ini_get('session.cookie_path');
    
			# set session name
			if (!empty($this->cfg['session.name'])) session_name($this->cfg['session.name']);
			$this->vals['php.session.name'] = session_name();
    
			# set session lifetime
			if (!empty($this->cfg['session.lifetime'])) ini_set('session.cookie_lifetime', $this->cfg['session.lifetime']); 
			$this->vals['php.session.cookie_lifetime'] = ini_get('session.cookie_lifetime');

			session_start();
		}
		
    	###########################
		# load the project module
		if (file_exists($this->cfg['dir.conf'] . 'project.php')){
			require($this->cfg['dir.conf'] . 'project.php');
			$this->project = new project();
		}
		
		##########################
		# load the design module
		if (file_exists($this->cfg['dir.conf'] . 'design.php')){
			require($this->cfg['dir.conf'] . 'design.php');
			$this->design = new design();
		}

		# initialize input/output function
		$this->io = new io();
    
		# intialize the xhtml helper/wrapper functions
		$this->xhtml = new xhtml();

		# intialize the xml helper/wrapper functions
		$this->xml = new xml();

		# populate uri with default asset if needed
		if ($this->vals['http.uri'][0] == null){
			$this->vals['http.uri'][0] = $this->cfg['project.default.asset']; 
		}
    
		# setup default template, and style
		if (empty($this->cfg['design.name'])) $this->cfg['design.name'] = $this->cfg['project.default.design'];
	}
	#
	#-------------------------
	# http_process_request
	#-------------------------
	# this function tells $ingred->handle_project_asset() about $ingred->vals['http.uri'][0]
	# otherwise stamps out various http errors (ie: 404, or 414)
	#
	# example:	$ingred->http_process_request();
  	# note:		this function is only called once, before $ingred->respond();
	#
	public function http_process_request(){
		$this->debug('http_process_request() ' . time());
		if (!empty($this->cfg['project.max.uri_len']) && strlen($this->vals['http.request.uri']) > $this->cfg['project.max.uri_len']){
			$this->http_error_message(414);
		} else {
			if ($this->handle_project_asset($this->vals['http.uri'][0])){

			} else {
				$this->http_error_message(404);
			}
		}
	}
	#
	#-----------------------
	# http_error_message
	#-----------------------
	# 1. writes the error message into the appropriate output buffer
	# 2. outputs the appropriate header using: $ingred->http_status_message();
	#
	# example:	$ingred->http_error_message(404);
	#
	public function http_error_message($in){
		$this->debug('http_error_message() ' . time());
		if (!$this->handle_project_asset('_error' . $in)){ // which should at least do what you see below
			$status = $this->http_status_message($in);
			$this->cfg['design.name'] = 'default';
			if ($this->vals['http.request.accept.type'][0] == 'application/xml'){
				$this->xml->body .= "<http status=\"error\"><code>$status[0]</code><description>$status[1]</description></http>"; 
			} else {
				$this->xhtml->body .= '<h1>Error '.$status[0].'</h1><h2>'.$status[1].'</h2>'; 
			}
			unset($status);
		}
	}
	#
	#-----------------------
	# respond
	#-----------------------
	#
	# example: this should be the
	#                         very
	#                         last
	#                         line
    # $this->respond();
 	#
	public function respond(){
		# todo: look at content.
		#       
		#       look at ACCEPT headers for content negotiation
		#       apply template as necessary 
		#        - process mime
		# 
		$this->debug('respond() ' . time());

		if (empty($this->response)){
			if (!empty($this->xhtml->body)){
				# headers, and head content would go here								
				$this->response = $this->xhtml->build('xhtml', '1.0', 'strict', 'en', 'UTF-8');
			}
			if (!empty($this->xml->body)){
				$this->response = $this->xml->build();
			}
			if (empty($this->response)) $this->response = 'no content';

		} else {
			# text headers go here
		}
		
		echo $this->response;
	}
	#
	#-------------------------
	# handle_project_asset
	#-------------------------
	# 1. search for method in project.
	#   - call $_project::asset();
	#
	# 2. search in $ingred->cfg['dir.assets']
	#    - require(asset.php);
	#
	public function handle_project_asset($ass=null){ // yes. that was on purpose
		//$this->debug('handle_project_asset() ' . time(), 'info');
		if (file_exists($this->cfg['dir.conf'] . 'project.php')){
			if (!empty($this->project)){
				if ($this->execute_method($this->project, $ass) != 'error'){
					return true;
				}
			}
		}

		$dir = $this->cfg['dir.assets'];

		if (file_exists($dir . $this->cfg['dir'] . $ass . '.php')){
			require($dir . $this->cfg['dir'] . $ass . '.php');
			return true;
		} 
		return false;
	}
  	#
	#-------------------------
	# handle_generic_asset
	#-------------------------
	# requires a class to sift through
	# takes the uri and replaces / with _ 
	# the tosses it to execute_method()
	#
	public function handle_generic_asset($cls, $prefix=null){
		//$this->debug('handle_generic_asset() ' . time(), 'info');
		$uri = $this->vals['http.uri'];
		array_shift($uri);
		$uri = implode($uri, '_');
		if (!empty($prefix)) $uri = $prefix.$uri;
		return execute_method($cls, $uri);
	}
	#
	#------------------------
	# debug
	#------------------------
	/* example 1
		ob_start();
		echo 'some random scriptz0r you found on the internets';
		$buffer = $this->debug(ob_get_contents());
		ob_end_clean();

		example 2
		$this->debug(var_export($array, 1), 'array export');
	*/
	public function debug($in, $name=null, $trace=null){
		if (!empty($name)) $name = "\n$name\n-----------------";
		if (!empty($in)){
			$this->debug .= "\n-----------------$name\n$in\n"; 
			if ($trace) $this->trace(true);
			return $in;
		} else {
			return false;
		}
	}
	#------------------------
	# trace
	#------------------------
	# example:	$ingred->trace();
	#
	public function trace($from_debug=null){
		$ns = '&nbsp;&nbsp;';
		
		$t = debug_backtrace();
		$t = array_reverse($t);
		if ($from_debug){
			array_pop($t);
			array_pop($t);
			$n = $ns; 	
		} else {
			array_pop($t);
			$n = null;
		}
		
		$c = count($t);
		
		$o = "$n\n-----------------\n";
		$o .= $n . "backtrace ($c deep)\n";
		
		for ($i=0;$c > $i;$i++){
			$ti = $t[$i];
			if (!empty($ti['file'])) $o .= '<span title="'.$ti['file'].':'.$ti['line'].'">'. $n;
			if (!empty($ti['class'])) $o .= $ti['class'].$ti['type'];
			$o .= $ti['function'].'(';
			$o .= $this->comma_sep($ti['args'], "'");
			$o .=")</span>\n";
			$n .= $ns;
		}
		$this->debug .= $o;
	}
	#------------------------
	# 
	#------------------------
	# determine whether debug should be shown to visitor
	#
	# example:	$ingred->show_debug();
	#
	public function show_debug(){
		if (empty($this->debug)){
			# at least check to make sure there is something to show
			return false;
		}

		if ($this->cfg['project.debug.show'] == true){
			return true;
		}
		
		# if it gets this far. 
		#   just don't do it

		return false;
	}

  ##############################
  # Useful functions 
  #   (in alphabetical order)
  ##############################

	public function cfg($cfg=null, $setting=null){
		# pretty simple. if $cfg is null then return the cfg
		# if $cfg is not blank then return that $cfg
		# if $cfg is not blank, and $setting is not blank then change setting

		if (empty($cfg)){
			return $this->cfg;
		} else {
			if (empty($setting)){
				return $this->cfg[$cfg];
			} else {
				$this->cfg[$cfg] = $setting;
				return $this->cfg[$cfg];
				}
		}
	}

	public function comma_sep($a, $wrap=null){
		# expects: a = array
		#          wrap = "wrap", "around", "each", "string"
		# returns a comma seperated string
		
		
		# todo: handle multi-dim. array

		$c = count($a);
		$o = '';
		for ($i=0;$c>$i;$i++){
			if (empty($wrap)){
				$o .= $a[$i];
			} else {
				$o .= $wrap.$a[$i].$wrap;
			}
			if ($i >= $c) $o .= ', ';
		}
		return $o;
	}
	
	public function enforce_nowww(){
		if (strpos($_SERVER['HTTP_HOST'], 'www.') === false) { } else {
		//todo: make http:// less static
			$this->vals['project.url'] = str_replace('http://www.', 'http://', $this->vals['project.url']);
			
			header('Location: ' . $this->vals['project.url'] . $_SERVER['REQUEST_URI']);
			
			exit('courtesy redirect to: '.$this->vals['project.url'] . $_SERVER['REQUEST_URI']); // for the logs (proxy, verbose, etc...)
		}
	}
  
	private function execute_method($cls, $fun){
		if (method_exists($cls, $fun) && !ereg("^_+\w*", $fun)){
			return call_user_func(array($cls, $fun));
		} else {
			return 'error'; 
		}
	}

	public function get_ip(){
		$rad=getenv('REMOTE_ADDR');
		$for=getenv('HTTP_X_FORWARDED_FOR');
		if ($for!='' && ip2long($for)!=-1 ? $ip=$for :  $ip=$rad);
		$ip=substr($ip,0,15);
		return $ip;
	}

	function get_microtime() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	public function get_php_input(){
		$put = fopen('php://input', 'r');
		$rdata = '';
		while ($data = fread($put, $this->vals['http.request.content_length'])) $rdata .= $data;
		fclose($put);
		$this->vals['http.input'] = $rdata;
		unset($put, $rdata);
		return true;
	}

	public function get_request_input(){
		if (isset($this->vals['http.request.content_length']) && $this->vals['http.request.content_length'] > 0) {
			if ($this->vals['http.request.method'] == 'post'){
				//global $HTTP_RAW_POST_DATA;
				if (!empty($HTTP_RAW_POST_DATA)) { // use the magic POST data global if it exists
					$this->vals['http.input'] = $HTTP_RAW_POST_DATA;
				} else {
					$this->get_php_input();
				}
			}
			if ($this->vals['http.request.method'] == 'put') { // put the PUT in $site_cfg['http.input']
				$this->get_php_input(); 
			}
		}
	}
  
	public function http_status_message($code, $content=null){ // can be used as safety check also
		if (empty($code) || !is_numeric($code)){
			return false;
		} else {
			if (empty($content)){
				if(!$content = $this->return_http_status($code)){
					$content = 'Unkown error';
				}
			}
			//ex:  header("HTTP/1.0 404 Not Found");
			//$this->debug('http error ' . $code, 'info');
			header("HTTP/1.1 $code $content");
			return array($code, $content);
		}
	}

 	public function redirect($url, $always=true){
 		if ($always ? $this->http_status_message(301) : $this->http_status_message(201));
		header ("Location: $url");
		exit();
	}

	public function return_http_status($code){ 
		#    this is, by no means, complete. 
		# these are, however, the relevant ones. 
		#       and even THAT'S a stretch. 

		$codes = array(
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			301 => 'Moved Permanently',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Object Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			414 => 'URI character limit reached',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			503 => 'Service Unavailable'
		);

    	if (!empty($code)){
			return $codes[$code];
		} else {
			return false;
		}
	}

	public function show_settings(){
		#todo: look at $cfg, and $vals
		# todo: go through each array
		#  todo: count keys -> for loop ->
		$tmp = null;

		foreach($this->cfg as $setting){
			$count = count($setting);
		}
	}

	public function sort_accept($in){ // example: $_SERVER['HTTP_ACCEPT']
		if (isset($in)){
			$accepts = explode(',', $in);
			$ordered = array();
			foreach ($accepts as $key => $accept) {
				$exploded = explode(';', $accept);
				if (isset($exploded[1]) && substr($exploded[1], 0, 2) == 'q=') {
					$ordered[substr($exploded[1], 2)][] = trim($exploded[0]);
				} else {
					$ordered['1'][] = trim($exploded[0]);
				}
			}    			
			$accepts = array();
			foreach ($ordered as $q => $acceptArray) {
				foreach ($acceptArray as $mimetype) {
					$accepts[] = trim($mimetype);
				}
			}
			unset($ordered, $q, $acceptArray, $mimetype);
			return $accepts;
		}
		return false;
	}

	public function vals($val=null, $setting=null){
		if (empty($val)){
			return $this->vals;
		} else {
			if (empty($setting)){
				return $this->vals[$val];
			} else {
				$this->vals[$val] = $setting;
				return $this->vals[$val];
				}
		}
	}
}

##################################
###### end ingred class ##########
##################################

class io{

	public function parse_tpl($file) {
		$str = $this->read_tpl($file);
		$str = $this->parse($str);
		return $str;
	} 

	public function read_tpl($file){
		if(!ereg('\.tpl$', $file)) $file .= '.tpl'; // append .tpl in the event it's not already there -- or someone's medling
		$str = $this->read($file);	
		return $str;
	}

	public function read($file){
		global $ingred;
		#$fd = @fopen($file, "r") or die (time() . " - cannot read $file");
		$fd = @fopen($file, "r");
		if (!empty($fd)){
			$str = '';
			while($line = fgets($fd, filesize($file))) $str .= $line;
			fclose($fd);
			return $str;
		} else { 
			$ingred->debug('File not found: ' . $file, 'ERROR');
			return false; 
		}
	}
    # unstable
	public function parse($input){
		ob_start();
		eval ("?>" . $input . "<?");
		$str = ob_get_contents();
		ob_end_clean();
	
		$str = addslashes($str);
		$str = str_replace("\\'","'",$str); // may be unneccesary
    
		return $str;
	}
	
	public function read_dir($dir){
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
					$type = filetype($dir . '/'. $file);
					$files[$type][] = $file;
				}
				closedir($dh);
				return $files;
			}
		}
		return false;
	}
}

class xhtml{

	public $title = 'default';
	public $head = null;
	public $body = null;

	public $meta_content = array();	#  ex: $this->meta_content['unique_name']['attribute'] = 'content';
									#    : $this->meta_content['name']['name'] = 'author';
									#    : $this->meta_content['name']['content'] = 'Your Name';

	public $link_content = array();	# ex: $this->link_content['unique_name'][href] = 'path/to/object.css';
									#     $this->link_content['unique_name'][media] = 'screen';
									#     $this->link_content['unique_name'][rel] = 'style';
									#     $this->link_content['unique_name'][type] = 'text/css';

	
	public $script_content = array();	# ex: $this->script_content['unique'][path] = 'path/to/object.js';
										#   : $this->script_content['unique']['type'] = 'text/javascript';
										#   : $this->script_content['unique']['noscript'] = 'Javascript is disabled'

	#####################
	# header tags
	# <META>
	
	# http://www.w3schools.com/tags/tag_meta.asp
	# http://en.wikipedia.org/wiki/Meta_tag
	# http://www.clickfire.com/tools/searchengine/mettyonline_meta_tag_generator.php
	public function build_meta_object($id, $name, $content){
		$this->meta_content[$id][$name] = $content;
	}

	public function exists_meta_object($id){
		if (empty($this->meta_content)){
			return false;
		} else {
			return array_key_exists($this->meta_content, $id);
		}
	}

	public function remove_meta_object($id){
		unset($this->meta_content[$id]);
	}

	public function build_meta_objects(){	
		foreach($this->meta_content as $aname){
			$shit = '';
			foreach($aname as $bname=>$bcontent){
				$shit .= $bname . '="' . $bcontent . '" ';
			}
			$this->head .= '<meta '.$shit.'/>'. "\n";	
		}
		unset($aname, $bname, $bcontent, $shit, $name, $content); // i wonder if this really improves performance
	}

	# <LINK>

    # http://www.w3.org/TR/REC-html40/struct/links.html#h-12.3
	# http://www.w3schools.com/TAGS/tag_link.asp
	public function build_link_object($id, $href, $hreflang=null, $rel=null, $rev=null, $media=null, $target=null, $type=null){

		if (!empty($type)) $this->link_content[$id]['type'] = $type;
		$this->link_content[$id]['href'] = $href;
		if (!empty($hreflang)) $this->link_content[$id]['hreflang'] = $hreflang;
		if (!empty($rel)) $this->link_content[$id]['rel'] = $rel;
		if (!empty($media)) $this->link_content[$id]['media'] = $media;
		if (!empty($rev)) $this->link_content[$id]['rev'] = $rev;
		if (!empty($target)) $this->link_content[$id]['target'] = $target;
	}

	public function exists_link_object($id){
		if (empty($this->link_content)){
			return false;
		} else {
			return array_key_exists($this->link_content, $id);
		}
	}

	public function remove_link_object($id){
		unset($this->link_content[$id]);
	}

 	public function build_link_objects(){		
		foreach($this->link_content as $shit){
			$l = '<link ';
			foreach($shit as $cock=>$slut){
				$l .= "$cock=\"$slut\" ";
			}
			$l .= '/>' . "\n";
		    $this->head .= $l;
		}
	}

	# TODO: finish up the script happiness =)
    # <SCRIPT> 

	# http://www.w3schools.com/TAGS/tag_script.asp
	# 
	public function build_script_object($id, $src, $type=null, $charset=null, $defer=null, $cdata=null){
		if (!empty($type)) $this->script_content[$id]['type'] = $type;
		if (!empty($src)) $this->script_content[$id]['src'] = $src;
		if (!empty($charset)) $this->script_content[$id]['charset'] = $charset;
		if (!empty($defer)) $this->script_content[$id]['defer'] = $defer;
		if (!empty($cdata)) $this->script_content[$id]['cdata'] = $cdata;
	}

	public function exists_script_object($id){
		if (empty($this->script_content)){
			return false;
		} else {
			return array_key_exists($this->script_content, $id);
		}

	}

	public function remove_script_object($id){
		unset($this->script_content[$id]);
	}

 	public function build_script_objects(){		
		foreach($this->script_content as $shit){
			$l = '<script';
			foreach($shit as $cock=>$slut){
				$l .= " $cock=\"$slut\"";
			}
			$l .= '></script>' . "\n";
		    $this->head .= $l;
		}
	}
	
	public function build_css_object($id, $href, $media=null, $title=null){
		if ($media==null) $media = 'screen';
		$this->build_link_object($id, $href, null, 'stylesheet', null, $media, null, 'text/css');
	}

	public function build_js_tag($id, $src){
		$this->build_script_object($id, $src, 'text/javascript');
	}

	#-------------------------
	# build_header_objects
	#-------------------------
	# "compile" meta, link, and javascript tags into $this->head
	# this serves as our "last chance" spot for any default settings

	public function build_header_objects(){
		$this->build_meta_objects();
		$this->build_link_objects();
		$this->build_script_objects();
	}
	##########################
	# block level tags
	
	# queue "wah wah" sound clip
	# TODO: build block level tags in XHTML class
	
	############################
	# custom scripting options 

	public function style($script, $type='text/css', $media=null){
		$o = "<style type=\"$type\"";
		if ($charset) $o .= " media=\"$media\"";
		$o .= ">\n//<![CDATA[\n";
		$o .= $script;
		$o .= "\n//]]>\n</style>\n";
		return $o;

	}

	public function script($script, $type='text/javascript', $charset=null, $noscript=null){
		$o = "<script type=\"$type\"";
		if ($charset) $o .= " charset=\"$charset\"";
		$o .= ">\n//<![CDATA[\n";
		$o .= $script;
		$o .= "\n//]]>\n</script>\n";
		if ($noscript) $o .= '<noscript>' . $noscript . '</noscript>';
		return $o;
	}

	##########################
	# form related tags

	public function input($type, $name, $value='', $id='', $class='', $title='', $onClick='', $size='', $tab='', $disabled = false){
		$o  = '<input type="'.$type.'" name="'.$name.'"'; 
		$o .= ' value="'.$value.'"';
		$o .= ($id)   ? ' id="'.$id.'"' : '';
		$o .= ($class)    ? ' class="'.$class.'"' : '';
		$o .= ($title)    ? ' title="'.$title.'"' : '';
		$o .= ($onClick)  ? ' onclick="'.$onClick.'"' : '';
		$o .= ($tab)  ? ' tabindex="'.$tab.'"' : '';
		$o .= ($size)     ? ' size="'.$size.'"' : '';
		$o .= ($disabled) ? ' disabled="disabled"' : '';
		$o .= " />";
		return $o;
	}

	public function select($name, $data, $caption_is_name=true, $selected = '', $id='', $class='', $not_required=false, $zero_caption='Select one'){
		$o = '<select name="'. $name .'"';
		$o .= ($id)		? ' id="'.$id.'"' : '';
		$o .= ($class)	? ' class="'.$class.'"' : '';
		$o .= '>';
		if ($not_required) $o .= '<option value="">'.$zero_caption.'</option>';
  
		foreach ($data as $kanga=>$roo){
			if ($caption_is_name==true) $kanga = $roo; // roflmao
			if ($roo == $selected || $kanga == $selected){
				$o .= '<option value="' . $kanga . '" selected="selected">'. ucwords(str_replace('_', ' ', $roo)) . '</option>';
			} else {
				$o .= '<option value="' . $kanga . '">' . ucwords(str_replace('_', ' ', $roo)) . '</option>';
			}
		}
		$o .= "</select>";
		return $o;
	}

	public function textarea($name, $cols='45', $rows='4', $value='', $id='', $class='', $title='', $onClick='', $tab='', $disabled = false){
		$o  = '<textarea name="'.$name.'"'; 
		$o .= ($cols)		? ' cols="'.$cols.'"' : '';
		$o .= ($rows)		? ' rows="'.$rows.'"' : '';
		$o .= ($id)			? ' id="'.$id.'"' : '';
		$o .= ($class)		? ' class="'.$class.'"' : '';
		$o .= ($title)		? ' title="'.$title.'"' : '';
		$o .= ($onClick)	? ' onclick="'.$onClick.'"' : '';
		$o .= ($tab)		? ' tabindex="'.$tab.'"' : '';
		$o .= ($disabled)	? ' disabled="disabled"' : '';
		$o .= ">$value</textarea>";
		return $o;
	}
  
	public function label($caption, $for){
		return '<label for="' . $for .'">' . $caption . '</label>';
	}

	# used in the event that the design class couldn't produce
	# - very skeleton like
		# TODO: use $ingred->xhtml->style to eliminate having to distribute a css file
	public function default_design(){
		global $ingred;
		
		$this->build_css_object($ingred->cfg('design.name'), $ingred->cfg('public.dir.css') . '/default.css');
		/* 
		example of using the templating class, instead
		
		$tpl = new tpl();
		$tpl->buffer = $ingred->io->read_tpl($ingred->cfg['dir.tpl'].'_'.$ingred->cfg['design.name']);
		$tpl->replace = array(
							'$title'=>$ingred->cfg['project.name'],
							'$body'=>$this->body);

		$this->body = $tpl->commit();
		*/
	}

	#############
	# BUILD

	public function build($lang='xhtml', $ver='1.0', $dtd='strict', $locale='en', $char_encoding='UTF-8'){ // currently we only support xhtml 1.0 strict -- sorry, 1995
		global $ingred;

		$uber_html_tag = '<?xml version="1.0" encoding="'.$char_encoding.'"?>' . "\n";
		$uber_html_tag = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'. "\n";
		$uber_html_tag .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="'.$locale.'" lang="'.$locale.'">' . "\n";
		
		# allowing over-ride in script
		if ($this->exists_meta_object('http-equiv') == false){
			$this->build_meta_object('http-equiv', 'http-equiv', 'Content-Type');
			$this->build_meta_object('http-equiv', 'content', 'text/html; charset=' . $char_encoding);
		}

		if ($ingred->show_debug()){ 
			# don't mind whatever it is that hides behinds curtains, you did not see this...
			$this->head .= $this->script("
				function close_div_debug(){
					var a = document.getElementById('div_debug');
					document.body.removeChild(a);
				}
				function close_lnk_debug(){
					var a = document.getElementById('div_debug');
					var b = document.getElementById('lnk_debug');
					document.body.removeChild(b);
					if (a.style.display=='none'){
						close_div_debug();
					}
				}
				function view_div_debug(){
					var a = document.getElementById('div_debug');
					a.style.display = 'block';
					a.style.position = 'absolute';
					a.style.top = 0;
					a.style.left = 0;
					close_lnk_debug();
				}");
			
			$this->body .= '<div id="div_debug" style="display: none;text-align: left;background-color: #000;color: #fff;width: 1000%;"><pre>' .$ingred->debug. "</pre><small><a href=\"javascript:close_div_debug();\">Close Debug</a></small></div>\n";
			$this->body .= '<p id="lnk_debug"><a href="javascript:close_lnk_debug();">x</a> <small><a href="javascript:view_div_debug();">View Debug</a></small></p>';
		}

        ###########################
		# IMPLEMENT DESIGN ()?
		# 
		# todo: explore the possibility of moving this out of the xhtml class, and moving it to the ingred class
		
		if (!empty($ingred->design)){
			if (method_exists($ingred->design, $ingred->cfg('design.name'))){
				call_user_func(array($ingred->design, $ingred->cfg('design.name')));
			} else {
				$this->default_design();
			}
		} else {
			$this->default_design();
		}

		$this->build_header_objects();
		
		$html = $uber_html_tag;
		$html .= "<head>\n";
		$html .= '<title>' . $this->title . "</title>\n";
		$html .= $this->head;
		$html .= "</head>\n";
		$html .= "<body>\n";
		$html .= $this->body;
		#$html .= '<!-- ' . var_export(debug_backtrace(), 1) . '-->';
		$html .= "</body>\n";
		$html .= "</html>\n";
		return $html;
	}
}

class xml{
	public $body = null;

	public function build($ver='1.0', $char_encoding='UTF-8', $standalone='no'){
		header('Content-type: application/xml');
		return '<?xml version="1.0" encoding="'.$char_encoding.'" standalone="'.$standalone.'"?>' . "\n" . $this->body;
	}
}

class tpl{
	public $buffer = null; // master template
	public $replace = array(); // list of things to replace

	public function commit(){ # my version of sprintf, i guess
		foreach($this->replace as $key=>$val){
			$this->buffer = str_replace($key, $val, $this->buffer);
		}
		return $this->buffer;
	}
}

############################################
# begin index.php execution, finally!
############################################

error_reporting(E_ALL);
ob_start();


$ingred = new ingred(); // initializes a fuck load of vars

  # make changes to the default configuration
  # - session parameters
  # - directories
  # - timezone

$ingred->cfg('project.debug.show', true);
#$ingred->cfg('project.whois', true); // enable multiple domain support

# ready?
$ingred->init(); // confirm changes to default values

   # session, cookies, dirs, timezone, and,and,and,and
   # project()
   # design()
   # io()
   # xhtml()
   # xml()

//$ingred->enforce_nowww(); # i don't like dubdubdubdot
$ingred->http_process_request();
print_r($ingred->vals());

# set.
$ingred->debug(ob_get_contents(), 'Buffer Contents');
ob_end_clean();

# go!
$ingred->respond(); ?>