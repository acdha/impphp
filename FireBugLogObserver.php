<?php
	class FireBugLogObserver extends Log_observer {
		private $FBL;

		function __construct($priority = PEAR_LOG_INFO, $Name = __CLASS__) {
			parent::__construct($priority);
			$this->FBL = &Log::singleton('firebug', '', $Name, array('buffering' => true), $priority);
		}

		function notify($hash) {
			$this->FBL->log($hash['message'], $hash['priority']);
		}
	}
?>
