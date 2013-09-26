<?php
include HC_SRC_ROOT."/EventLoop/Timer/Timer.php";

class LibSelectLoop implements LoopInterface{

	public $readStream;
	public $writeStream;
	public $timers;
	public $scheduler;
	public $timerCount = 0;
	public $_start = true;

	function __construct(){
		$this->readStream = array();
		$this->writeStream = array();
		$this->streamHandle = array();
		$this->timers = array();
		$this->scheduler = new SplPriorityQueue();
	}

	public function addReadEvent($stream,$handle){
		$fd = (int)$stream;
		$this->removeEvent($stream);
		$this->readStream[] = $stream;
		$this->streamHandle[$fd] = $handle;	
	}

	public function addWriteEvent($stream,$handle){
		$fd = (int)$stream;
		$this->removeEvent($stream);
		$this->writeStream[] = $stream;
		$this->streamHandle[$fd] = $handle;
	}

	public function removeEvent($stream){
		$fd = (int)$stream;
		$idx = array_search($stream,$this->readStream);
		if($idx === false){
			$idx = array_search($stream, $this->writeStream);
			if($idx !== false){
				unset($this->writeStream[$idx]);
			}
		}else{
			unset($this->readStream[$idx]);
		}
		unset($this->streamHandle[$fd]);
	}

	public function tick(){
		$except = null;
		$readStream = $this->readStream;
		$writeStream = $this->writeStream;
		$stream_num = stream_select($readStream, $writeStream, $except, 0.5);
		if($stream_num !== false && $stream_num > 0){
			foreach ($readStream as $stream){
				$fd = (int)$stream; 
				call_user_func($this->streamHandle[$fd],$stream,$this);
			}

			foreach ($writeStream as $stream) {
				$fd = (int)$stream; 
				call_user_func($this->streamHandle[$fd],$stream,$this);
			}
		}
	}

	public function addTimer($interval,$callback,$periodic = false){
		$timer = new Timer($this,$interval,$callback,$periodic = false);
		$timer->setId($this->timerCount);
		$timer->when_run = microtime(true) + $interval;
		$this->timerCount++;
		$this->timers[$timer->getId()] = $timer;
		$this->scheduler->insert($timer,$timer->when_run);
	}

	public function cancelTimer($id){
		unset($this->timers[$id]);
	}

	public function findMinTimer(){
		$time = microtime(true);
		$timer = $this->scheduler->top();
		return $timer->when_run-$time;
	}

	public function runTimers(){
		$time = microtime(true);
		while(!$this->scheduler->isEmpty()){
			$timer = $this->scheduler->top();
			if(!isset($this->timers[$timer->getId()])){
				$this->scheduler->extract();
				continue;
			}

			if($timer->when_run > $time){
				break;
			}

			$this->scheduler->extract();
			call_user_func($timer->getCallback(),$timer);
			if($timer->isPeriodic()){
				$timer->when_run = $time + $timer->getInterval();
				$this->scheduler->insert($timer,$timer->when_run);
			}else{
				$timer->cancel();
			}
		}
	}

	public function run(){
		$except = null;
		$time_out = 0.5;
		while($this->_start && ((count($this->readStream) > 0) || (count($this->writeStream) > 0))){
			if(!$this->scheduler->isEmpty()) $time_out = $this->findMinTimer();
			$readStream = $this->readStream;
			$writeStream = $this->writeStream;
			$stream_num = stream_select($readStream, $writeStream, $except, $time_out);
			if($stream_num !== false && $stream_num > 0){
				if(!$this->scheduler->isEmpty()) $this->runTimers();
				foreach ($readStream as $stream){
					$fd = (int)$stream; 
					call_user_func($this->streamHandle[$fd],$stream,$this);
				}

				foreach ($writeStream as $stream) {
					$fd = (int)$stream; 
					call_user_func($this->streamHandle[$fd],$stream,$this);
				}
			}
		}
	}

	public function stop(){
		$this->_start = false;
	}
}