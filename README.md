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
HttpClient::call("http://www.baidu.com","get",$data,"callback");
HttpClient::call("http://www.baidu.com","get",$data,"callback");
$loop->run();
```
