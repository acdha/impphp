<?php
	/*
	 * Some functions to help profile code
	 *
	 * Author: chris@improbable.org
	 *
	 */

	function micro_time() {
		// Works the way microtime() should have worked (PHP5 addresses this with
    // the optional get_as_float parameter) and returns a single floating
    // point number
		list($usec, $sec) = explode(' ', microtime());
		return ((double) $usec + (double) $sec);
	}

	function microTimer() {
		// returns elapsed time since the previous call using microtime()
		static $StartTime;

		if (empty($StartTime)) {
			$StartTime = micro_time();
			return;
		} else {
			$Duration  = micro_time() - $StartTime;
			$StartTime = 0;
			return $Duration;
		}
	}

	function elapsedTimer($Position) {
		// Maintains a running timer from the first time it is called.
		// Handy for tracing large scripts by placing a line such as elapsedTimer(__FILE__ . ": " . __LINE__)
		// at many locations throughout the script.

		static $i;

		if (empty($i)) {
			$i = micro_time();
		}

		echo "<P>Reached $Position - elapsed time: " . number_format(micro_time() - $i, 5) .	"</P>\n";
		flush();
	}
?>