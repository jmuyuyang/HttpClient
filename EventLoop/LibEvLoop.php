<?php
include HC_SRC_ROOT."/EventLoop/Timer/Timer.php";
class LibEvLoop implements LoopInterface{
	public $loop;

	public $readCallbacks = array();
	public $writeCallbacks = array();
	public $events = array();
	public $timers = array();
	public $timer_counts = 0;

	function __construct(){
		$this->loop = new EvLoop();
	}

	function addReadEvent($stream,$handle){
		$this->addEvent($stream,$handle,EV::READ,"read");
	}

	function addWriteEvent($stream,$handle){
		$this->addEvent($stream,$handle,EV::WRITE,"write");
	}

	function wrapCallback($stream){
		return function($event,$flags) use ($stream){
			$loop = $event->data;
			$id = (int)$stream;
			if($flags & EV::READ && isset($this->readCallbacks[$id])){
				call_user_func($this->readCallbacks[$id],$stream,$loop);
			}
  
			if($flags & EV::WRITE && isset($this->writeCallbacks[$id])){
				call_user_func($this->writeCallbacks[$id],$stream,$loop);
			}
		};
	}

	function addEvent($stream,$handle,$flags,$type){
		$id = (int)$stream;
		$callback = $this->wrapCallback($stream);
		if($exists = isset($this->events[$id])){
			$this->removeEvent($stream);
		}
		$event = $this->loop->io($stream,$flags,$callback,$this);
		$this->events[$id] = $event;
		$this->{$type."Callbacks"}[$id] = $handle;
	}

	function removeEvent($stream){
		$id = (int)$stream;
		$event = $this->events[$id];
		$event->stop();
		unset($this->events[$id],$this->readCallbacks[$id],$this->writeCallbacks[$id]);
	}

	function addTimer($interval,$callback,$periodic = false){
		$timer = new Timer($this,$interval,$callback,$periodic);
		$timer->setId($this->timer_counts);
		$this->timer_counts++;
		$timer->start();
		return $timer;
	}

	function setupTimer($id,$timer){
		$interval = $timer->getInterval();
		$repeat = $timer->isPeriodic()?$interval:0;
		$event = $this->loop->timer($interval,$repeat,function($event){
			$timer = $event->data;
			call_user_func($timer->getCallback(),$timer);
			if(!$timer->isPeriodic()) $timer->cancel();
		},$timer);
		$this->timers[$id] = $event;
	}

	function cancelTimer($id){
		if(isset($this->timers[$id])){
			$resource = $this->timers[$id];
			$resource->stop();
			unset($this->timers[$id]);
		}
	}

	function tick(){
		$this->loop->run(Ev::RUN_ONCE);
	}

	function stop(){
		$this->loop->stop();
	}

	function run(){
		$this->loop->run();
	}
}

?>
