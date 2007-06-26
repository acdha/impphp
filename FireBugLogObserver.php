<?php
	class FireBugLogObserver extends Log_observer {
		private $FBL;

		function __construct($priority = PEAR_LOG_INFO) {
			parent::__construct($priority);
			$this->FBL = &Log::singleton('firebug', '', 'NIPS', array('buffering' => true), PEAR_LOG_DEBUG);
		}

		function notify($hash) {
			$this->FBL->log($hash['message'], $hash['priority']);
		}
	}
?>
