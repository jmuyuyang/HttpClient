<?php
include "../HttpClient.php";

$files = array(
	'node-v0.6.18.tar.gz' => 'http://nodejs.org/dist/v0.6.18/node-v0.6.18.tar.gz',
   	'php-5.4.3.tar.gz' => 'http://it.php.net/get/php-5.4.3.tar.gz/from/this/mirror',
);

$loop = Loop::factory();
HttpClient::init($loop);
foreach ($files as $file => $url) {
	$fp = fopen($file,"w");
	HttpClient::Get($url,null,$fp);
}
$loop->run();


