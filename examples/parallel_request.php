<?php
include "../HttpClient.php";
$loop = Loop::factory(Loop::LIBEVENT);
$timer = $loop->addTimer(0.1,function($timer){
});
HttpClient::init($loop);
HttpClient::Get("http://www.baidu.com",null,function($error,$content){
});

HttpClient::Get("http://www.baidu.com",null,function($error,$content){
});
$loop->run();