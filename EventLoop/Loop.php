<?php
if(!defined("HC_SRC_ROOT")) return;

class Loop{
	const LIBEV = "LibEvLoop";
	const LIBEVENT = "LibEventLoop";
	const LIBSELECT = "LibSelectLoop";

	public static function factory($class = self::LIBSELECT){
		if($class == self::LIBSELECT || $class == self::LIBEV || $class == self::LIBEVENT){
			include HC_SRC_ROOT."/EventLoop/".$class.".php";
			return new $class();
		}
	}
}
