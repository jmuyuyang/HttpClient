<?php
include HC_SRC_ROOT."/EventLoop/Timer/Timer.php";

class LibEventLoop implements LoopInterface{
	public $base_loop;

	public $events = array();
	public $readCallbacks = array();
	public $writeCallbacks = array();
	public $timers = array();
	public $timer_counts = 0;

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

	function addTimer($interval,$callback,$periodic = false){
		$timer = new Timer($this,$interval,$callback,$periodic);
		$timer->setId($this->timer_counts);
		$this->timer_counts++;
		$timer->start();
		return $timer;
	}

	function setupTimer($id,$timer){
		$resource = event_new();
		$timers = $this->timers;
		$timers[$id] = $resource;
		$callback = function ($stream,$flag,$timer) use (&$timers) {
			$id = $timer->getId();
        	if (isset($timers[$id])) {
            	call_user_func($timer->getCallback(), $timer);
 
                if ($timer->isPeriodic() && isset($timers[$timer])) {
                    event_add($timers[$id], $timer->getInterval() * 1000000);
                } else {
                    $timer->cancel();
	           	}
            }
        };
	
		event_timer_set($resource,$callback,$timer);
        event_base_set($resource, $this->base_loop);
        event_add($resource, $timer->getInterval() * 1000000);
	}

	function cancelTimer($timer_id){
		if(isset($this->timers[$timer_id])){
			$resource = $this->timers[$timer_id];
			event_del($resource);
			event_free($resource);
			unset($this->timers[$timer_id]);
		}
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

	function tick(){
		event_base_loop($this->base_loop, EVLOOP_ONCE | EVLOOP_NONBLOCK);
	}

	function run(){
		event_base_loop($this->base_loop);
	}

	function stop(){
		event_base_loopexit($this->base_loop);
	}
}

?>
