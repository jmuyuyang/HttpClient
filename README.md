#HttpClient
####Async HttpClient base on Event-driven written in php
#Requirement：
- PHP 5.3+
- libevent extension

###Documents
`
include "HttpClient.php"  
function callback($err,$req){  
	if($err) return;  
	var_dump($req);  
}
HttpClient::call($url,$method,$data,"callback",$options);  
HttpClient::call($url,$method,$data,"callback",$options);  
HttpClient::loop($loop_callback);
/* the loop_callback will be called when all request has been dispatch */
`
