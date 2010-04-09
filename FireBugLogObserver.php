<?php
	class FireBugLogObserver extends Log_observer {
		private $FBL;

		function __construct($priority = 6, $Name = __CLASS__) { //Priority uses syslog standard, compatible with PEAR Log and Zend_Log
			parent::__construct($priority);
			$this->FBL = &Log::singleton('firebug', '', $Name, array('buffering' => true), $priority);
		}

		function notify($hash) {
			$this->FBL->log($hash['message'], $hash['priority']);
		}
	}
?>
