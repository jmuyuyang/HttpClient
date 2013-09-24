<?php
class Timer{
	public $loop;
	public $interval;
	public $callback;
	public $periodic;

	function __construct($loop,$interval,$callback,$periodic){
		$this->loop = $loop;
		$this->interval = $interval;
		$this->callback = $callback;
		$this->periodic = $periodic;
	}

	function setId($id){
		$this->_id = $id;
	}

	function getId(){
		return $this->_id;
	}

	function getCallback(){
		return $this->callback;
	}

	function getInterval(){
		return $this->interval;
	}

	function isPeriodic(){
		return $this->periodic;
	}

	function start(){
		$this->loop->setupTimer($this->_id,$this);
	}

	function cancel(){
		$this->loop->cancelTimer($this->_id);
	}
}