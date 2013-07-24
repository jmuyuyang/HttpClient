#HttpClient
####Async HttpClient base on Event-driven written in php
#Requirement：
- PHP 5.3+
- libevent extension or libev extension 

###Documents
```
include "HttpClient.php"  
function callback($err,$req){  
	if($err) return;  
	var_dump($req);  
}
$loop = Loop::factory();
//$loop = Loop::factory(Loop::LIBEV);
HttpClient::init($loop);
HttpClient::Get("http://www.baidu.com",$data,"callback");
HttpClient::Get("http://www.baidu.com",$data,"callback");
$loop->run();
```
more example in examples directory
