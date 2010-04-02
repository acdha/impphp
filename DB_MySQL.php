<?php
	/**
	 * DB_MySQL
	 *
	 * Wrapper class for communicating with MySQL
	 *
	 * RATIONALE
	 *	Unlike PEAR's DB class, this class is designed to allow the use of all MySQL
	 *	features with an eye toward performance and ease of development at the expense
	 *	of database portability. Since MySQL is free and runs just about anywhere PHP
	 *	runs this is an acceptable tradeoff for many applications.
	 *
	 *	Use PEAR DB if you need to support many databases and can live
	 *	with the subset of common functionality
	 *
	 *	Use DB_MySQL if you don't need to support other databases (or
	 *	plan on maintaining optimized SQL codebases anyway)
	 *	and want to support MySQL as easily as possible
	 *
	 * TODO:
	 *		- wrap the following functions:
	 *			mysqli_create_db()
	 *			mysqli_drop_db()
	 *			mysqli_db_name()
	 *			mysqli_error() / mysqli_errno()
	 *			mysqli_fetch_(array|field|lengths|object) - perhaps in a complex query mode
	 *			mysqli_field_*
	 *			mysqli_list_*
	 *
	 *		- query trapping feature to dump queries as they are executed & related debugging info
	 *
	 *		- complex query mode to retrieve large amounts of data:
	 *			- mysqli_unbuffered_query() to avoid retrieving masses of data
	 *			- mysqli_fetch_(field|row) to retrieve it row-by-row or one field at a time
	 *			- mysqli_field_len() to avoid fetching large data
	 *			- mysqli_data_seek() to avoid fetching some rows or to see a row again
	 */
	require_once('ImpPhp/ImpDB.php');

	class DB_MySQL extends ImpDB implements ImpDBO {
		protected $Server;
		protected $Database;
		protected $Username;
		protected $Password;
		protected $Handle;

		# Internal flag:
		protected $isPersistent         = true;

		function __construct() {
			/**
			 * The constructor can either be called with an existing database handle
			 * or a set of four parameters to use with mysqli_[p]connect():
			 *
			 * $db = new DB_MySQL($my_existing_mysqli_link_handle);
			 *
			 * or
			 *
			 * $db = new DB_MySQL("localhost", "my_db", "db_user", "db_password");
			 *
			 */
			switch (func_num_args()) {
				case 1:
					$h = func_get_arg(0);

					// Make sure what we got really is a mysql link
					assert(is_resource($h));
					$t = get_resource_type($h);

					if ($t == "mysql link") {
						$this->isPersistent = false;
					} elseif ($t == "mysql link persistent") {
						$this->isPersistent = true;
					} else {
						trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() Passed handle isn't a mysql connection - it's a $t resource!", E_USER_ERROR);
					}

					$this->Handle = $t;
					return;

				case 4:
					list ($this->Server, $this->Database, $this->Username, $this->Password) = func_get_args();
					break;

				default:
					trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() called with " . func_num_args() . " arguments - it should be called with either 1 (a mysql connection handle) or 4 (the server, db_name, user, and password to connect with)", E_USER_ERROR);
			}
		}

		function getUniqueIdentifier($Table, $ID) {
			return 'mysql://' . rawurlencode($this->Server) . '/' . rawurlencode($this->Database) . '/' . rawurlencode($Table) . '#' . $ID;
		}

		function getPerformanceCounters() {
			$q = mysqli_query('SHOW SESSION STATUS', $this->Handle) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() mysqli_query() failed: " . mysqli_error($this->Handle), E_USER_ERROR);

			$r = array();

			for ($i = 0; $i < mysqli_num_rows($q); $i++) {
				list($k, $v) = mysqli_fetch_row($q) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() mysqli_fetch_assoc() failed: " . mysqli_error($this->Handle), E_USER_ERROR);
				$r[$k] = $v;
			}

			mysqli_free_result($q);

			if (!empty($r['Created_tmp_tables']) and !empty($r['Com_show_status']) ) {
				$r['Created_tmp_tables'] -= $r['Com_show_status'];
				unset($r['Com_show_status']);
			}

			return $r;
		}

		function changeUser($Username, $Password) {
			if (!strcmp($Username, $this->Username) or !strcmp($Password, $this->Password)) {
				mysqli_change_user($Username, $Password, $Name, $this->Handle) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() mysqli_change_user() failed: " . mysqli_error(), E_USER_ERROR);
				$this->Username			= $Username;
				$this->Password = $Password;
			}
		}

		function setCharacterSet($charset = 'utf8') {
			$this->execute("SET NAMES '" . $charset . "'");
		}

		function connect() {
			assert(func_num_args() == 0);

			if (!empty($this->Handle)) {
				trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() called when a link has already been established", E_USER_NOTICE);
			}

			$this->Handle = mysqli_connect($this->Server, $this->Username, $this->Password, $this->Database) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() $connect_func() failed: " . mysqli_error(), E_USER_ERROR);
			$this->setCharacterSet();
		}

		function disconnect() {
			assert(func_num_args() == 0);

			if (empty($this->Handle)) {
				trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() called but no link has been established", E_USER_NOTICE);
			} else {
				mysqli_close($this->Handle) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() mysqli_close() failed: " . mysqli_error($this->Handle), E_USER_ERROR);
				unset($this->Handle);
			}
		}

		function query($sql) {
			$args = func_get_args();
			assert(!empty($args));
			$sql = array_shift($args);

			if (empty($this->Handle)) {
				$this->connect();
			}

			$this->startTimer();

			$stmt = mysqli_stmt_init($this->Handle);
			$stmt->prepare($sql) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() mysqli_prepare() failed: " . mysqli_error($this->Handle), E_USER_ERROR);

			if (!empty($args)) {
				call_user_func_array(array($stmt, 'bind_param'), array_merge(array(array_reduce($args, array($this, '_reduceBindTypes'))), $args));
			}

			$stmt->execute();

			$r = array();

			$rowBindVars = array();
			$rProto = array();

			foreach (mysqli_fetch_fields($stmt->result_metadata()) as $f) {
				$rProto[$f->name] = 0x0badf00d;
				$rowBindVars[$f->name] =& $rProto[$f->name];
			}

			call_user_func_array(array($stmt, 'bind_result'), $rowBindVars);

			while ($stmt->fetch()) {
				$ra = array();
				foreach ($rowBindVars as $k => $v) {
					$ra[$k] = $v;
				}

				$r[] = $ra;
			}

			unset($stmt);

			$this->stopTimer($sql);

			return $r;
		}


		private function _reduceBindTypes($t, $new) {
			return $t . (gettype($new) == 'string' ? 's' : 'i');
 		}

		function execute($sql) {
			/**
			 * Executes a SQL statement which does not return any records
			 */
			if (empty($sql)) {
				trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() called without a SQL query!", E_USER_WARNING);
			}

			if (empty($this->Handle)) {
				$this->connect();
			}

			$this->startTimer();
			$q = mysqli_query($this->Handle, $sql) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() mysqli_query('$sql') failed: " . mysqli_error($this->Handle), E_USER_ERROR);
			$this->stopTimer($sql);
		}

		function getAffectedRowCount() {
			/**
			 * Returns the number of rows affected by the last statement
			 */

			assert(func_num_args() == 0);

			if (empty($this->Handle)) {
				trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() called without a current database connection", E_USER_WARNING);
				return false;
			}

			$i = mysqli_affected_rows($this->Handle);

			return $i;
		}

		function getLastInsertId() {
			/**
			 * Returns the numeric ID of the last insert statement (used with AUTO_INCREMENT colums)
			 */
			assert(func_num_args() == 0);

			if (empty($this->Handle)) {
				trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() called without a current database connection", E_USER_WARNING);
				return false;
			}

			$i = mysqli_insert_id($this->Handle) or trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() mysqli_insert_id() failed: " . mysqli_error($this->Handle), E_USER_ERROR);

			return $i;
		}

		function quote($v) {
			/**
			 * Returns $v with any characters which have special meaning to MySQL escaped
			 */

			 if (is_array($v)) {
					foreach ($v as $k => $i) {
						$v[$k] = mysqli_real_escape_string($this->Handle, $i);
					}
			 } else {
				 return mysqli_real_escape_string($this->Handle, $v);
			 }
		}
	}
?>
