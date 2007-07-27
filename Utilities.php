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
			if (defined("IMP_FATAL_ERROR_MESSAGE")) {
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
		exit;
	}

	/**
	 * A replacement error handler with improved debugging features
	 * - Backtraces including function parameters will be printed using TextMate URLs when running on localhost
	 * - No output will be generated if IMP_DEBUG is not defined and true
	 * - Most errors (all if IMP_DEBUG is true, < E_STRICT otherwise) are fatal to avoid continuing in abnormal situations
	 * - Errors will always be recorded using error_log()
	 * - MySQL's error will be printed if non-empty
	 * - E_STRICT errors in system paths will be ignored to avoid PEAR/PHP5 compatibility issues
	 */
	function ImpErrorHandler($error, $message, $file, $line, $context) {
		if (!(error_reporting() & $error)) {
			return; // Obey the error_report() level and ignore the error
		}

		if ((substr($file, 0, 5) == '/usr/') and $error == E_STRICT) {
			// TODO: come up with a more precise way to avoid reporting E_STRICT errors for PEAR classes
			return;
		}


		$ErrorTypes = array (
			E_ERROR							=> 'Error',
			E_WARNING						=> 'Warning',
			E_PARSE							=> 'Parsing Error',
			E_NOTICE						=> 'Notice',
			E_CORE_ERROR				=> 'Core Error',
			E_CORE_WARNING			=> 'Core Warning',
			E_COMPILE_ERROR			=> 'Compile Error',
			E_COMPILE_WARNING		=> 'Compile Warning',
			E_USER_ERROR				=> 'User Error',
			E_USER_WARNING			=> 'User Warning',
			E_USER_NOTICE				=> 'User Notice',
			E_STRICT						=> 'Runtime Notice',
			E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
		);

		$ErrorType = isset($ErrorTypes[$error]) ? $ErrorTypes[$error] : 'Unknown';

		// If IMP_DEBUG is defined we make everything fatal - otherwise we abort for anything else than an E_STRICT:
		$fatal = (defined("IMP_DEBUG") and IMP_DEBUG) ? true : ($error != E_STRICT);

		$dbt = debug_backtrace();
		assert($dbt[0]['function'] == __FUNCTION__);
		array_shift($dbt); // Remove our own entry from the backtrace

		if (defined('IMP_DEBUG') and IMP_DEBUG) {
			print '<div class="Error">';

			print "<p><b>$ErrorType</b> at ";
			generate_debug_source_link($file, $line, $message);
			print "</p>";
			if (function_exists('mysql_errno') and mysql_errno() > 0) {
				print '<p>Last MySQL error #' . mysql_errno() . ': <code>' . mysql_error() . '</code></p>';
			}

			generate_debug_backtrace($dbt);

			phpinfo(INFO_ENVIRONMENT|INFO_VARIABLES);
			print '</div>';
		} elseif (defined('IMP_FATAL_ERROR_MESSAGE')) {
			print "\n\n\n";
			print IMP_FATAL_ERROR_MESSAGE;
			print "\n\n\n";
		}

		error_log(__FUNCTION__ . ": $ErrorType in $file on line $line: " . quotemeta($message) . (!empty($dbt) ? ' (Began at ' . kimplode(array_filter_keys(array_last($dbt), array('file', 'line'))) . ')' : ''));

		if ($fatal) exit(1);
	}

	function ImpExceptionHandler(Exception $e) {
		if (!defined('IMP_DEBUG') or !IMP_DEBUG) {
			if (defined('IMP_FATAL_ERROR_MESSAGE')) {
				echo '<div>', IMP_FATAL_ERROR_MESSAGE, '</div>';
			}
			error_log('Unhandled exception: ' . $e);
			exit(1);
		}

		echo '<div class="Error Exception">Uncaught Exception: <code>';
		generate_debug_source_link($e->getFile(), $e->getLine(), $e->getCode());
		echo '</code>';

		echo '<p>', $e->getMessage(), '</p>';

		generate_debug_backtrace($e->getTrace());

		print '</div>';
		exit(1);
	}

	function ImpAssertHandler($file, $line, $code) {
		if (!defined('IMP_DEBUG') or !IMP_DEBUG) {
			if (defined('IMP_FATAL_ERROR_MESSAGE')) {
				echo '<div>', IMP_FATAL_ERROR_MESSAGE, '</div>';
			}
			error_log("Assert failed at $file:$line: $code");
			exit(1);
		}

		echo '<div class="Error Assert">Assert failed: <code>';
		generate_debug_source_link($file, $line, $code);
		echo '</code></div>';
		exit(1);
	}

	function generate_debug_backtrace($bt) {
		print '<h1>Backtrace</h1>';
		print '<ol class="Backtrace">';

		foreach ($bt as $t) {
			foreach (array("class", "type", "function", "file", "line") as $k) {
				if (!isset($t[$k])) $t[$k] = false;
			}

			extract($t, EXTR_PREFIX_ALL, 'bt');
			if (!isset($bt_args)) {
				$bt_args = array();
			}

			$arg_string = html_encode(implode(', ', array_map('var_export_string', $bt_args)));

			print "\t<li><code>$bt_class$bt_type$bt_function(<span title=\"$arg_string\">" . (strlen($arg_string) > 20 ? '...' : $arg_string) . "</span>)</code> at ";
			generate_debug_source_link($bt_file, $bt_line);
			print "</li>\n";
		}
		print '</ol>';
	}

	function generate_debug_source_link($file, $line, $text = false) {
		if ($_SERVER["REMOTE_ADDR"] == "127.0.0.1") {
			echo '<a href="txmt://open?url=file://', rawurlencode($file) , '&line=', $line,'">';
			echo '<span title="' . html_encode($file) . '">', basename($file), '</span>:', $line;
			if (!empty($text)) {
				echo ': ', $text;
			}
			echo '</a>';
		} else {
			echo "$file:$line: $text";
		}
	}

	function var_export_string($mixed) {
		// Work around a bug in var_export which causes it to recurse and die when it finds recursive data structures:
		if (is_object($mixed)) {
			return get_class($mixed) . " Object";
		} else {
			return var_export($mixed, true);
		}
	}

	function pre_print_r($a) {
		print "\n<pre>\n";
		print_r($a);
		print "\n</pre>\n";
	}

	function redirect($URI = false, $Permanent = false) {
		// Check to see if we've got a real URL or just a relative URL
		// RFC 2616 requires absolute URLs so we need to convert relative references
		// See http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.30

		$HTTP_HOST = $_SERVER['HTTP_HOST'];
		$PROTOCOL	 = empty($_SERVER['HTTPS']) ? "http" : "https";

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
		error_log("Rejected request for {$_SERVER['REQUEST_URI']}: $Message; \$_SESSION=" . (isset($_SESSION) ? unwrap(var_export($_SESSION, true)) : 'undefined') . '; $_REQUEST=' . unwrap(var_export($_REQUEST, true)) . '; $_SERVER=' . unwrap(var_export(array_filter_keys($_SERVER, array("HTTP_REFERER", 'HTTP_USER_AGENT', 'HTTP_HOST', 'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING')), true)));
		redirect($Target);
	}

	function require_method($Method, $Target = false) {
		// A simple way to enforce usage of the intended HTTP method for a given page
		if (strcasecmp($_SERVER['REQUEST_METHOD'], $Method) === 0) return;
		error_log("Rejected non-$Method request for {$_SERVER['REQUEST_URI']}. \$_SERVER=" . unwrap(var_export(array_filter_keys($_SERVER, array("HTTP_REFERER", 'HTTP_USER_AGENT', 'HTTP_HOST', 'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING')), true)));
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

	function cmp($a, $b) {
		// For use with usort & friends when we actually need a numeric comparison:
		if ($a == $b) return 0;
		return $a > $b ? 1 : -1;
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
		header("Last-Modified: " . gmdate('r'));													// always modified
		header("Cache-Control: no-cache, must-revalidate");								// HTTP/1.1
		header("Pragma: no-cache");																				// HTTP/1.0
	}

	function check_client_cache($current_etag, $current_mtime, $max_age = 3600) {
		assert(!empty($current_etag));
		assert($max_age > 0);

		$use_cache = false;

		$headers = array_change_key_case(getallheaders(), CASE_LOWER);

		if (!empty($headers["if-none-match"]) and ($headers["if-none-match"] == $current_etag)) {
			$use_cache = true;
		}

		if (!empty($headers['if-modified-since'])) {
			$ims = strtotime($headers['if-modified-since']);
			if ($ims > 0 and $ims >= $current_mtime) {
				$use_cache = true;
			}
		}

		if ($use_cache) {
			header('HTTP/1.1 304 Not changed');
			header('Last-Modified: ' . gmdate("r", $current_mtime));
			exit;
		} else {
			header("ETag: $current_etag");
			header('Last-Modified: ' . gmdate("r", $current_mtime));
			header('Expires: ' . gmdate('r', time() + $max_age));
			header("Cache-Control: max-age=$max_age");
		}
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
		// Returns the named property from the *default* class properties
		$reflection = new ReflectionClass($class);
		return array_value($reflection->getDefaultProperties(), $var);
	}

	function array_value($arr, $k) {
		// Returns the element of the passed array for the passed key. The primary use
		// of this is to avoid unnecessary temporary variables when you want a single
		// value from a function which returns an array, as in get_class_var()
		return $arr[$k];
	}

	function array_first($arr) {
		// Returns the first element of an array - this is can be used in cases where reset() cannot be called directly without using a temporary variable (e.g. reset(array_keys($foo)))
		return reset($arr);
	}
	function array_last($arr) {
		// Returns the last element of an array - this is can be used in cases where end() cannot be called directly without using a temporary variable (e.g. end(array_keys($foo)))
		return end($arr);
	}

?>
