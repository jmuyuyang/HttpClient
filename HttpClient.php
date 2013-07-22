<?php

/*@author yuyang  20th february 2013 jmuljy@163.com 
* @version 1.0
*/

define("SRC_ROOT",__DIR__);
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
    public function get($url,$data = false,$pipe,$options = array()) {
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
    public function post($url,$data = false,$stdout,$options = array()) {
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
        	$errormsg = $this->setError($errno,$errstr);
            $this->end($idx,$errormsg,null);
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
        $headers = array();
        $content = "";
        $error = null;
        $inHeaders = true;
        $atStart = true;
        while (!feof($stream)) {
            $line = fgets($stream, 4096);
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
        $loop->removeEvent($stream);
        if($content && $headers){
            if (isset($headers['content-encoding']) && $headers['content-encoding'] == 'gzip') {
                $content = substr($content, 10); 
                $content = gzinflate($content);
            }
            $this->end($stream,null,array(
                'headers' => $headers,
                'content' => $content
            ));
            return true;
        }else{
            $error = $error?:"cannot fetch stream data";
            $this->end($stream,$error,null);
            return false;
        }
    }

    public function end($stream,$err,$req){
        $idx = (int)$stream;
        $this->pipe($stream,$err,$req);
        unset($this->urls[$idx]);
        fclose($stream);
        if(!$this->urls) $this->loop->stop();
    }

    public function pipe($stream,$err,$req){
        $idx = (int)$stream;
        $stdout = $this->urls[$idx]['pipe'];
        if($stdout){
            if(is_resource($stdout)){
                fwrite($stdout,$err?$err:$req);
            }
            if(is_callable($stdout)){
                call_user_func($stdout,$err,$req);
            }
        }else{
            $data = $err?$err:$req;
            print($data);
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

    private function _buildRequest($config) {
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
    public static function init($loop){
        $obj = self::_instance();
        $obj->loop = $loop;
    }

    /**
    *request $url base on event loop
    */
    public static function call($url,$method,$data,$pipe){
        $obj = self::_instance();
        $obj->{$method}($url,$data,$pipe);
    }
}

?>

