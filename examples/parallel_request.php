<?php

include "../HttpClient.php";

$loop = Loop::factory();
HttpClient::init($loop);
HttpClient::Get("http://www.example.com",null,function($error,$content){

});

HttpClient::Get("http://www.example.com",null,function($error,$content){

});

$loop->run();