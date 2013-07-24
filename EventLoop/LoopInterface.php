<?php

interface LoopInterface{
	public function addWriteEvent($stream,$handle);
	public function addReadEvent($stream,$handle);
	public function removeEvent($stream);

	public function tick();
	public function run();
	public function stop();
}