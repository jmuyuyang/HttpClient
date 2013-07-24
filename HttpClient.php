<?php

/*@author yuyang  20th february 2013 jmuljy@163.com 
* @version 1.0
*/

define("SRC_ROOT",__DIR__);
include SRC_ROOT."/EventLoop/LoopInterface.php";
include SRC_ROOT."/EventLoop/Loop.php";

class HttpClient {
    public $timeout = 20;
    public $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
    public $urls = array();

    private $_callConfig = array(
        "host" => null,
        "port" => 80,
        "method" => "GET",
        "pipe" => null,
        "data" => array(),
        "options" => array()
    );

    private $_options = array(
        "accept_encoding" =>  "gzip",
        "accept_language" => "zh-CN,zh;q=0.8",
        "user_agent" => "HttpClient v1.0"
    );

    /*set request config base on get method 
    *@params: $url request url
              $data request data
              $pipe request to pipe,it will be called when request data return 
    */
    public function http_get($url,$data = null,$pipe,$options = array()) {
        $config = $this->_analysisUrl($url);
        $id = (int)$this->request($config['host'],$config['port']);
        if ($data) {
            $config['path'] .= '?'.http_build_query($data);
        }
        $config['pipe'] = $pipe;
        $config['options'] = $options+$config['options'];
        $this->urls[$id] = $config;
    }

    /*set request config base on post method 
    *@params: $url request url
              $data request data
              $stdout request to pipe,it will be called when request data return 
    */
    public function http_post($url,$data = null,$stdout,$options = array()) {
        $config = $this->_analysisUrl($url);
        $id = (int)$this->request($config['host'],$config['port']);
        $config['method'] = "POST";
        $config['data'] = http_build_query($data);
        $config['pipe'] = $stdout;
        $config['options'] = $options + $this->_options;
        $this->urls[$id] = $config;
    }

    /*do request base on Event-driven
    *@params: $idx index of url configs  
    */
    public function request($host,$port) {
        $fp = stream_socket_client(
            $host.":".$port, 
            $errno, 
            $errstr, 
            (int) $this->timeout,
            STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT 
        );
        stream_set_blocking($fp, 0);
        stream_set_timeout($fp, $this->timeout);
		if (!$fp) {
            $this->callback($stream,$this->getError($errno,$errstr),null);
            return false;
        }
        $this->loop->addWriteEvent($fp,array($this,"write"));
        return $fp;
    }

    public function write($stream,$loop){
        $idx = (int)$stream;
        $request = $this->_buildRequest($this->urls[$idx]);
        fwrite($stream, $request);
        $loop->addReadEvent($stream,array($this,"read"));
    }

    public function read($stream,$loop){
        $headers = $this->_parseHttpHeaders($stream);
        if(!$this->pipe($stream)){
            while(!feof($stream)){
                $content .= fgets($stream,4096);
            }
            if($headers && $content){
                if (isset($headers['content-encoding']) && $headers['content-encoding'] == 'gzip'){
                    $this->httpUnGzip($content);
                }
                $this->callback($stream,null,array("headers" => $headers,"content" => $content));
            }else{
                $this->callback($stream,"cannot fetch stream data",null);
            }
        }
        if(feof($stream)) $this->end($stream);
    }

    public function pipe($stream){
        $idx = (int)$stream;
        $stdout = $this->urls[$idx]['pipe'];
        if(is_resource($stdout)){
            stream_copy_to_stream($stream, $stdout);
            return true;
        }
        return false;
    }

    public function end($stream){
        $idx = (int)$stream;
        $this->loop->removeEvent($stream);
        unset($this->urls[$idx]);
        fclose($stream);
        if(!$this->urls) $this->loop->stop();
    }

    public function callback($stream,$error,$req){
        $idx = (int)$stream;
        $stdout = $this->urls[$idx]['pipe'];
        if(is_callable($stdout)){
            call_user_func($this->urls[$idx]['pipe'],$err,$req);
        }
    }

    public function getError($errno,$errstr){
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
        return $errormsg." ".$errstr;
    }

    protected function _analysisUrl($url){
        $config = $this->_callConfig;
        $urlConfig = parse_url($url);
        $config['host'] = $urlConfig['host'];
        $config['port'] = $urlConfig['port']?:80;
        $config['path'] = $urlConfig['path']?:"/";
        return $config;
    }

    protected function _httpUnGzip($content){
        $content = substr($content, 10); 
        return gzinflate($content);
    }

    protected function _parseHttpHeaders($stream){
        $startLine = true;
        $headers = array();
        while(!feof($stream)){
            $line = fgets($stream,4096);
            if($startLine){
                if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)){
                    break;
                }
                $startLine = false;
                continue;
            }
            if(trim($line) == ''){
                break;
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
        }
        return $headers;
    }

    protected function _buildRequest($config) {
        $headers = array();
        $headers[] = "{$config['method']} {$config['path']} HTTP/1.1"; // Using 1.1 leads to all manner of problems, such as "chunked" encoding
        $headers[] = "Host: {$config['host']}";
        $headers[] = "User-Agent: {$config['options']['user_agent']}";
        $headers[] = "Accept: {$this->accept}";
        $headers[] = "Accept-language: {$config['options']['accept_language']}";
        if (isset($config['options']['referer'])) {
            $headers[] = "Referer: {$config['options']['referer']}";
        }
    	// Cookies
    	if (isset($config['options']['cookies'])) {
    	    $cookie = 'Cookie: ';
    	    foreach ($options['cookies'] as $key => $value) {
    	        $cookie .= "$key=$value; ";
    	    }
    	    $headers[] = $cookie;
    	}
        //authorization
        if(isset($config['options']['auth']) && ($auth = $config['options']['auth']))  
            $headers[] = "Authorization: Basic ".base64_encode($auth[0].":".$auth[1]);

    	$postData = $config['data'];
    	if ($config['method'] == "POST") {
    	    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    	    $headers[] = 'Content-Length: '.strlen($postData);
    	}
        $headers[] = "Connection: Close";
    	$request = implode("\r\n", $headers)."\r\n\r\n".$postData;
    	return $request;
    }

    private static function _instance(){
        static $instance = null;
        if(!$instance){
            $instance = new self();
        }
        return $instance;
    }

    /**
    *init event loop
    */
    public static function init(LoopInterface $loop){
        $obj = self::_instance();
        $obj->loop = $loop;
    }

    /**
    *request $url across get method base on event loop
    */
    public static function Get($url,$data,$pipe = null,$options = array()){
        $obj = self::_instance();
        $obj->http_get($url,$data,$pipe);
    }

    /**
    *request $url across post method base on event loop
    */
    public static function Post($url,$data,$pipe = null,$options = array()){
        $obj = self::_instance();
        $obj->http_post($url,$data,$pipe);
    }
}
?>

