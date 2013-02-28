<?php

/*@author yuyang  20th february 2013 jmuljy@163.com 
* @version 1.0
*/

class HttpClient {
    public $event_base;
    public $timeout = 20;
    public $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
    public $urls = array();

    private $_callNum = 0;
    private $_callConfig = array(
        "host" => null,
        "port" => 80,
        "method" => "GET",
        "callback" => null,
        "data" => array(),
        "options" => array()
    );

    private $_options = array(
        "accept_encoding" =>  "gzip",
        "accept_language" => "zh-CN,zh;q=0.8",
        "user_agent" => "HttpClient v1.0",
        "cookies" => array(),
        "referer" => null
    );

    /*init request config and dispatch requests
    *@params: $event_base base event call from event_base_new
    */
    public function init($event_base){
        $this->event_base = $event_base;
        $urls = $this->urls;
        $this->_callNum = count($urls);
        while(current($urls)){
            $this->request(key($urls));
            next($urls);
        }
    }

    /*set request config base on get method 
    *@params: $url request url
              $data request data
              $callback request callback,it will be called when request data return 
    */
    public function get($url,$data = false,$callback,$options = array()) {
        $config = $this->_analysisUrl($url);
        if ($data) {
            $config['path'] .= '?'.http_build_query($data);
        }
        $config['callback'] = $callback;
        $config['options'] = $options+$config['options'];
        $this->urls[] = $config;
    }

    /*set request config base on post method 
    *@params: $url request url
              $data request data
              $callback request callback,it will be called when request data return 
    */
    public function post($url,$data = false,$callback,$options = array()) {
        $config = $this->_analysisUrl($url);
        $config['method'] = "POST";
        $config['data'] = http_build_query($data);
        $config['callback'] = $callback;
        $config['options'] = $options+$config['options'];
        $this->urls[] = $config;
    }

    /*do request base on Event-driven
    *@params: $idx index of url configs  
    */
    public function request($idx = 0) {
        $fp = stream_socket_client(
            $this->urls[$idx]['host'].":".$this->urls[$idx]['port'], 
            $errno, 
            $errstr, 
            (int) $this->timeout,
            STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT 
        );
        stream_set_blocking($fp, 0);
        stream_set_timeout($fp, $this->timeout);
		if (!$fp) {
        	$errormsg = $this->setError($errno,$errstr);
            $this->_callback($idx,$errormsg,null);
            return false;
        }
        $base_fd = $this->event_base;
        $writeEvent = event_new();
        event_set($writeEvent, $fp, EV_WRITE | EV_PERSIST, 
            array($this, 'onAccept'), array($writeEvent, $base_fd,$idx));
        event_base_set($writeEvent, $base_fd);
        event_add($writeEvent);

        $readEvent = event_new();
        event_set($readEvent, $fp, EV_READ | EV_PERSIST, 
            array($this, 'onRead'), array($readEvent, $base_fd,$idx));
        event_base_set($readEvent, $base_fd);
        event_add($readEvent);
    }

    public function onAccept($fp, $event, $args){
        $idx = $args[2];
        $options = (array)$this->urls[$idx]['options'];
        $options+=$this->_options;
        $request = $this->_buildRequest($idx,$options);
        fwrite($fp, $request);
    }

    public function onRead($fp,$event,$args){
        $idx = $args[2];
        $headers = array();
        $content = "";
        $error = null;
        $inHeaders = true;
        $atStart = true;
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if ($atStart) {
                // Deal with first line of returned data
                $atStart = false;
                if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
                    $error = "Status code line invalid: ".htmlentities($line);
                    break;
                }
                continue;
            }
            if ($inHeaders) {
                if (trim($line) == '') {
                    $inHeaders = false;
                    continue;
                }
                if (!preg_match('/([^:]+):\\s*(.*)/', $line, $m)) {
                    // Skip to the next header
                    continue;
                }
                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
                if (isset($headers[$key])) {
                    if (is_array($headers[$key])) {
                        $headers[$key][] = $val;
                    } else {
                        $headers[$key] = array($headers[$key], $val);
                    }
                } else {
                    $headers[$key] = $val;
                }
                continue;
            }
            // not in the headers, so append the line to the contents
            $content .= $line;
        }
        fclose($fp);
        event_del($args[0]);
        if($content && $headers){
            if (isset($headers['content-encoding']) && $headers['content-encoding'] == 'gzip') {
                $content = substr($content, 10); 
                $content = gzinflate($content);
            }
            $this->_callback($idx,null,array(
                'headers' => $headers,
                'content' => $content
            ));
            return true;
        }else{
            $error = $error?:"cannot fetch stream data";
            $this->_callback($idx,$error,null);
            return false;
        }
    }

    public function setError($errno,$errstr){
        switch($errno) {
            case -3:
                $errormsg = 'Socket creation failed (-3)';
            case -4:
                $errormsg = 'DNS lookup failure (-4)';
            case -5:
                $errormsg = 'Connection refused or timed out (-5)';
            default:
                $errormsg = 'Connection failed ('.$errno.')';
        }
        return $errormsg .= ' '.$errstr;
    }

    private function _analysisUrl($url){
        $config = $this->_callConfig;
        $urlConfig = parse_url($url);
        $config['host'] = $urlConfig['host'];
        $config['port'] = $urlConfig['port']?:80;
        $config['path'] = $urlConfig['path']?:"/";
        return $config;
    }

    private function _buildRequest($idx,$options) {
        $headers = array();
        $headers[] = "{$this->urls[$idx]['method']} {$this->urls[$idx]['path']} HTTP/1.1"; // Using 1.1 leads to all manner of problems, such as "chunked" encoding
        $headers[] = "Host: {$this->urls[$idx]['host']}";
        $headers[] = "User-Agent: {$options['user_agent']}";
        $headers[] = "Accept: {$this->accept}";
        $headers[] = "Accept-language: {$options['accept_language']}";
        if ($this->referer) {
            $headers[] = "Referer: {$options['referer']}";
        }
    	// Cookies
    	if ($options['cookies']) {
    	    $cookie = 'Cookie: ';
    	    foreach ($options['cookies'] as $key => $value) {
    	        $cookie .= "$key=$value; ";
    	    }
    	    $headers[] = $cookie;
    	}
    	$postData = $this->urls[$idx]['data'];
    	if ($this->urls[$idx]['method'] == "POST") {
    	    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    	    $headers[] = 'Content-Length: '.strlen($postData);
    	}
        $headers[] = "Connection: Close";
    	$request = implode("\r\n", $headers)."\r\n\r\n".$postData;
    	return $request;
    }

    private function _callback($idx,$err,$req){
        $callback = $this->urls[$idx]['callback'];
        if($callback){
			call_user_func($callback,$err,$req);
        }
        $this->_loopExit();
    }

    private function _loopExit(){
        $this->_callNum--;
        if(!$this->_callNum){
            $this->urls = array();
            event_base_loopexit($this->event_base);
        }
    }

    private static function _instance(){
        static $instance = null;
        if(!$instance){
            $instance = new self();
        }
        return $instance;
    }

    public static function call($url,$method,$data,$callback){
        $obj = self::_instance();
        $obj->{$method}($url,$data,$callback);
    }

    /*begin request loop 
    *@params: $loop_callback this will be called while all request has been dispatched
    */
    public static function loop($loop_callback = null){
        $obj = self::_instance();
        $event_base = event_base_new();
        $obj->init($event_base);
        if($loop_callback) call_user_func($loop_callback);
        event_base_loop($event_base);
    }
}
?>
