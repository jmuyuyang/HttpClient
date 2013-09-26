<?php
include "../HttpClient.php";
$loop = Loop::factory(Loop::LIBEVENT);
$timer = $loop->addTimer(0.2,function($timer){
	echo "sss";
});
HttpClient::init($loop);
HttpClient::Get("http://www.taobao.com",null,function($error,$content){

});

HttpClient::Get("http://www.baidu.com",null,function($error,$content){

});
$loop->run();