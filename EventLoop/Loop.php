<?php
if(!defined("SRC_ROOT")) return;
include SRC_ROOT."/EventLoop/LibEventLoop.php";
include SRC_ROOT."/EventLoop/LibEvLoop.php";

class Loop{
	const LIBEV = "LibEvLoop";
	const LIBEVENT = "LibEventLoop";

	public static function factory($class = self::LIBEVENT){
		if($class == self::LIBEV || $class == self::LIBEVENT){
			return new $class();
		}
	}
}
