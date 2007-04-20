<?php
	/**
	 *	ImpUtil has a number of handy functions which do not fit naturally
	 *	into a single project or object model
	 */
	require_once("URLValidator.php");
	require_once("ImpHTML.php");		

	/**
	 *A replacement for die() which uses a friendlier error and doesn't disclose information
	 *
	 * See set_user_error_handler() in the PHP manual
	 *
	 * @param string
	 *
	 */
	function ImpDie($message) {

		// If we're in debug mode, don't bother dumping the boilerplate
		if (!(defined("IMP_DEBUG") && IMP_DEBUG)) {

			// Use a site-specific error message if one has been defined:
			if (defined("IMPDIE_MESSAGE")) {
				print IMPDIE_MESSAGE;
			} else {
				print "<p>An error occurred while processing your request. The administrator has been notified.</p>";

				// This only exists if we're an Apache module. Such is life:
				if (!empty($_SERVER["SERVER_ADMIN"])) {
					print "<p>Please contact <a href=\"${_SERVER['SERVER_ADMIN']}\">${_SERVER['SERVER_ADMIN']}</a> if the problem persists.</p>";
				}
			}
		}

		// Trigger a fatal error so we can take advantage of the normal logging:
		trigger_error($message, E_USER_ERROR);
	}

	/**
	 * A replacement error handler with improved debugging features
	 */
	function ImpErrorHandler($error, $message, $file, $line) {
		if (error_reporting() & $error == 0) {
			return; // Ignore the error
		}

		// If IMP_DEBUG is defined, use it's value instead so that any
		// error will halt in debugging mode
		$fatal = defined("IMP_DEBUG") ? IMP_DEBUG : false;
		switch ($error) {
			case E_USER_ERROR:
				$fatal     = true;
				$ErrorType = "USER ERROR";
				break;
			case E_USER_NOTICE:
				$ErrorType = "USER NOTICE";
				break;
			case E_USER_WARNING:
				$ErrorType = "USER WARNING";
				break;
			case E_ERROR:
				$fatal     = true;
				$ErrorType = "ERROR";
				break;
			case E_NOTICE:
				$ErrorType = "NOTICE";
				break;
			case E_WARNING:
				$ErrorType = "WARNING";
				break;
			default:
				$ErrorType = "UNKNOWN";
				break;
		}

		if (defined("IMP_DEBUG") and IMP_DEBUG) {
			print "<p><b>$ErrorType</b> at <code>$file</code>:$line:</p><code>$message</code>";
			if (mysql_errno()) {
				print "<p>Last MySQL error #" . mysql_errno() . ": <code>" . mysql_error() . "</code></p>";
			}

			if (function_exists("debug_backtrace")) {
				// Mmmm - PHP 4.3+ - see http://php.net/debug_backtrace
				print '<h1>Backtrace</h1>';
				print "<ol id=\"ImpErrorHandlerBacktrace\">\n";

				$btKeys = array("class", "type", "function", "file", "line");

				$dbt = debug_backtrace();
				assert($dbt[0]['function'] == __FUNCTION__);
				array_shift($dbt); // Remove our own entry from the backtrace

				foreach ($dbt as $t) {

					foreach ($btKeys as $k) {
						if (!isset($t[$k])) {
							$t[$k] = false;
						}
					}

					extract($t, EXTR_PREFIX_ALL, 'bt');
					if (!isset($bt_args)) {
						$bt_args = array();
					}

					print "\t<li><code>$bt_class$bt_type$bt_function(" . implode(', ', array_map('var_export_string', $bt_args)) . ")</code> at $bt_file:$bt_line</li>\n";
				}
				print '</ol>';
			}

			print '<h1>PHP Information</h1>';
			phpinfo();
		}

		error_log("$ErrorType at '$file' on line $line: $message");

		exit(1);
	}

 	function var_export_string($mixed) {
 		// Work around a bug in var_export which causes it to recurse and die when it finds recursive data structures:
 		if (is_object($mixed)) {
 			return get_class($mixed) . " Object";
 		} else {
 			return var_export($mixed, true);
 		}
 	}

	function redirect($URI = false, $Permanent = true) {
		// Check to see if we've got a real URL or just a relative URL
		// RFC 2616 requires absolute URLs so we need to convert relative references
		// See http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.30

		$HTTP_HOST = $_SERVER['HTTP_HOST'];
		$PROTOCOL  = empty($_SERVER['HTTPS']) ? "http" : "https";

		if (empty($URI)) {
			if (!empty($_SERVER['HTTP_REFERER'])) {
				$URI = $_SERVER['HTTP_REFERER'];
			} else {
				$URI = dirname($_SERVER['SCRIPT_NAME']);
			}
		}

		if (!eregi("^(http|https|ftp):.*$", $URI)) {
			if ($URI[0] == '/') {
				$URI = "$PROTOCOL://$HTTP_HOST$URI";
			} else {
				$PATH = dirname($_SERVER['PHP_SELF']);
				$URI = "$PROTOCOL://$HTTP_HOST$PATH/$URI";
			}
		}

		header("Location: $URI", true, $Permanent ? 301 : 302);
		exit;
	}

	function reject($Message, $Target = false) {
		// Similar to redirect() but with an added error message which gets logged; useful for rejecting attempts to access invalid/unavailable resources since it records some state
		error_log($Message . '; $_SESSION=' . (isset($_SESSION) ? unwrap(var_export($_SESSION, true)) : 'undefined') . '; $_REQUEST=' . unwrap(var_export($_REQUEST, true)) . '; $_SERVER=' . unwrap(var_export(array_filter_keys($_SERVER, array("HTTP_REFERER", 'HTTP_USER_AGENT', 'HTTP_HOST', 'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING')), true)));
		redirect($Target);
	}

	function http_gone($Message) {
		header("HTTP/1.1 410 $Message");
		print "$Message\n";
		exit;
	}

	function array_filter_keys($Haystack, $Keys) {
		// Returns the elements of $Haystack whose keys are values of $Keys

		$Filtered = array();
		foreach (array_intersect(array_keys($Haystack), $Keys) as $k) {
			$Filtered[$k] = $Haystack[$k];
		}

		return $Filtered;
	}

	function array_not_empty($input) {
		// Returns a subset of an array containing only the non-empty values:
		return array_filter($input, create_function('$i', 'return !empty($i);'));
	}

	function unwrap($text) {
		// Takes a word-wrapped string and returns a single-line version:
		return preg_replace('/\\s+/s', ' ', $text);
	}

	function read_file($filename) {
		// Like readfile() but it returns the data in a string instead of printing it

		if (!file_exists($filename)) return false;

		if (function_exists("file_get_contents")) {
			return file_get_contents($filename);
		}

		$contents = "";
		$fp = fopen($filename, "r") or ImpDie("read_file: couldn't open $filename");
		while (!feof($fp)) {
			$contents .= fread($fp, 16384);
		}
		fclose($fp);

		return $contents;
	}

	function get_files_in_directory($path, $includeHidden = false) {
		$files = array();

		if (!is_dir($path)) {
			trigger_error("get_files_in_directory() called on non-existent path: '$path'");
		}

		$dir = opendir($path);
		while (($file = readdir($dir)) !== false) {
			// Filter hidden files and directories:
			if (($includeHidden or ($file[0] != ".")) and is_file("$path/$file")) {
				$files[] = $file;
			}

		}
		closedir($dir);

		return $files;
	}

	function http_expire_now() {
		/*
		 * Generates headers which should cause the page to be reloaded completely each time
		 * In practice, IE5 frequently ignores the HTTP standard.
		 */
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");									// Date in the past
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");		// always modified
		header("Cache-Control: no-cache, must-revalidate");								// HTTP/1.1
		header("Pragma: no-cache");																				// HTTP/1.0
	}

	function import_vars() {
		// Pulls in the named variables from $_REQUEST or initializes them to "" if they weren't passed:

		foreach (func_get_args() as $var) {
			if (is_array($var)) {
				foreach ($var as $v) {
					$GLOBALS[$v] = array_key_exists($v, $_REQUEST) ? strip_magic_quotes($_REQUEST[$v]) : "";
				}
			} else {
				$GLOBALS[$var] = array_key_exists($var, $_REQUEST) ? strip_magic_quotes($_REQUEST[$var]) : "";
			}
		}
	}

	function strip_magic_quotes($foo) {
		if (!get_magic_quotes_gpc()) {
			return $foo;
		}

		if (is_array($foo)) {
			return array_map('strip_magic_quotes', $foo);
		} else {
			return stripslashes($foo);
		}
	}

	function get_request_protocol() {
		// Returns the protocol used for the current request, suitable for URL generation:
		if (!empty($_SERVER['HTTPS']) and ($_SERVER['HTTPS'] == "on")) {
			return 'https';
		} else {
				return 'http';
		}
	}

	function kimplode($Array, $ElementSeparator = ", ", $KeySeparator = "=") {
		// Collapses an array into a comma-delimited list of key=value pairs:
		assert(is_array($Array));

		$ret = array();

		foreach ($Array as $k => $v) {
			$ret[] = "$k$KeySeparator$v";
		}

		return implode($ElementSeparator, $ret);
	}

  if (!function_exists('json_encode')) {
  	function json_encode($Data) {
  		switch (gettype($Data)) {
  			case 'NULL':
  				return 'null';

  			case 'boolean':
  				return $Data ? 'true' : 'false';

  			case 'integer':
  				return (int) $Data;

  			case 'double':
  			case 'float':
  				return (float) $Data;

  			case 'string':
  				return '"' . $Data . '"';

  			case 'array':
          // Associative and sparse arrays have to be handled with objects as the JS array type doesn't support this:
  				if (!empty($Data) && (array_keys($Data) !== range(0, sizeof($Data) - 1))) {
  					return '{' . join(',', array_map(create_function('$n,$v', 'return "\"$n\":" . json_encode($v);'), array_keys($Data), array_values($Data))) . '}';
  				} else {
  					return '[' . join(',', array_map('json_encode', $Data)) . ']';
  				}

  			default:
  				die(__FUNCTION__ . " can't encode " . gettype($Data) . " data");
  		}
  	}
  }

	function html_encode($var) {
		// See http://www.nicknettleton.com/zine/php/php-utf-8-cheatsheet
		return htmlentities($var, ENT_QUOTES, ini_get('default_charset'));
	}

	function iso8601($time_t) {
		return date("c", $time_t);
	}

	function get_class_var($class, $var) {
		// Returns the named property from the *default* class properties (consult
		// get_class_vars() documentation)
		return array_value(get_class_vars($class), $var);
	}

	function array_value($arr, $k) {
		// Returns the element of the passed array for the passed key. The primary use
		// of this is to avoid unnecessary temporary variables when you want a single
		// value from a function which returns an array, as in get_class_var()
		return $arr[$k];
	}

?>
