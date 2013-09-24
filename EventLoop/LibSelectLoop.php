<?php
class LibSelectLoop implements LoopInterface{

	public $readStream;
	public $writeStream;
	public $_start = true;

	function __construct(){
		$this->readStream = array();
		$this->writeStream = array();
		$this->streamHandle = array();
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

	public function run(){
		$except = null;
		while($this->_start && ((count($this->readStream) > 0) || (count($this->writeStream) > 0))){
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
	}

	public function stop(){
		$this->_start = false;
	}
}