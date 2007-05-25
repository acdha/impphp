<?php
	/*
	 * Simple profiling functions 
	 *
	 * Author: chris@improbable.org
	 *
	 */

	function microTimer() {
		// returns elapsed time since the previous call using microtime()
		static $StartTime;

		if (empty($StartTime)) {
			$StartTime = microtime(true);
			return;
		} else {
			$Duration  = microtime(true) - $StartTime;
			$StartTime = 0;
			return $Duration;
		}
	}

	function elapsedTimer($Position = false) {
		// Maintains a running timer from the first time it is called.
		// Handy for tracing large scripts by placing a line such as elapsedTimer(__FILE__ . ": " . __LINE__)
		// at many locations throughout the script.

		static $i;

		if (empty($i)) {
			$i = microtime(true);
		}
		
		if (empty($Position)) {
			extract(debug_backtrace(), EXTR_PREFIX_ALL, 'dbt');
			$Position = "<code>$dbt_class$dbt_type$dbt_function(<i>" . implode(', ', array_map('var_export_string', isset($dbt_args) ? $dbt_args : array())) . "</i>)</code> at <code>" . $generate_source_link($dbt_file, $dbt_line) . '</code>';
		}

		echo "<p style=\"border: solid yellow 2px;\">Reached $Position - elapsed time: " . number_format(microtime(true) - $i, 5) .	"</p>\n";
		flush();
	}
?>