<?php
class LibEventLoop{
	public $base_loop;

	public $events = array();
	public $readCallbacks = array();
	public $writeCallbacks = array();

	function __construct(){
		$this->base_loop = event_base_new();
	}

	function callback($stream,$flags,$loop){
		$id = (int)$stream;
		try{
			if(($flags & EV_READ) && isset($this->readCallbacks[$id])){
				call_user_func($this->readCallbacks[$id],$stream,$loop);
			}

			if (($flags & EV_WRITE) && isset($this->writeCallbacks[$id])) {
                call_user_func($this->writeCallbacks[$id], $stream, $loop);
            }

		}catch(Execption $e){
			$loop->stop();
			throw $e;
		}
	}

	function addReadEvent($stream,$handle){
		$this->addEvent($stream,EV_READ,$handle,"read");
	}	

	function addWriteEvent($stream,$handle){
		$this->addEvent($stream,EV_WRITE,$handle,"write");
	}

	function addEvent($stream,$flag,$handle,$type){
		$id = (int)$stream;
		if($exists = isset($this->events[$id])){
			$event = $this->events[$id];
			event_del($event);
		}else{
			$event = event_new();
		}

		event_set($event,$stream,$flag | EV_PERSIST,array($this,"callback"),$this);
		if(!$exists){
			event_base_set($event, $this->base_loop);
		}

		event_add($event);

		$this->events[$id] = $event;
		$this->{$type."Callbacks"}[$id] = $handle;
	}

	function removeEvent($stream){
		$id = (int)$stream;

		if(isset($this->events[$id])){
			$event = $this->events[$id];
			event_del($event);
			event_free($event);

			unset($this->events[$id]);
			unset($this->readCallBacks[$id],$this->writeCallbacks[$id]);
		}
	}

	function run(){
		event_base_loop($this->base_loop);
	}

	function stop(){
		event_base_loopexit($this->base_loop);
	}
}

?>
