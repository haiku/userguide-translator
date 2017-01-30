<?php

class a2_log{
	public $logfile;
	public $name;
	public $log;

	public function read_log(){
		if(!file_exists($this->logfile))
			return false;
		if(!is_readable($this->logfile))
			return false;

		if($this->log = file_get_contents($this->logfile))
			return true;

		return false;
	}

	public function get_log(){
		if(is_null($this->log))
			if(!$this->read_log())
				return null;

		return $this->log;
	}
}