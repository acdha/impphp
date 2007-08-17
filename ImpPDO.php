<?php
	/**
	 * ImpPDO
	 *
	 * A subclass of PDO which provides convenience query functions, debugging improvements and extensive profiling support
	 *
	 * @author Chris Adams
	 */
	require_once('ImpUtils/ImpDB.php');

	class ImpPDO extends ImpDB implements ImpDBO {
		protected $DSN;
		protected $User;
		protected $Password;
		protected $Options;
		protected $PDO;

		function __construct($DSN, $Username = false, $Password = false, array $Options = array()) {
			$this->PDO          = new PDO($DSN, $Username, $Password, $Options);
			$this->DSN          = $DSN;
			$this->Username     = $Username;
			$this->Password     = $Password;
			$this->Options      = $Options;
			$this->setCharacterSet();

			list($this->Scheme) = explode(':', $this->DSN, 2);

			if ($this->Scheme == 'sqlite') {
				// TODO: Find other MySQL-specific functions which could easily be mimiced for SQLite
				$this->PDO->sqliteCreateFunction('FROM_UNIXTIME', create_function('$t', 'return date("Y-m-d H:i:s", $t);'));
				$this->PDO->sqliteCreateFunction('UNIX_TIMESTAMP', create_function('$t', 'return strtotime($t);'));
			}
		}

		function changeUser($Username, $Password) {
			unset($this->PDO);
			$this->PDO = new PDO($this->DSN, $Username, $Password, $this->Options);
			$this->Username = $Username;
			$this->Password = $Password;
			$this->setCharacterSet();
		}

		function __call($method, $args) {
			if (isset($this->PDO) and method_exists($this->PDO, $method)) {
				return call_user_func_array(array($this->PDO, $method), $args);
			}

			throw new Exception("Call to unknown method $method");
		}

		function getPerformanceCounters() {
			$Status = array();

			if ($this->getAttribute(PDO::ATTR_DRIVER_NAME) != 'mysql') {
				return $Status;
			}

			foreach ($this->query('SHOW SESSION STATUS') as $row) {
				$Status[$k] = $v;
			}

			if (!empty($Status['Created_tmp_tables']) and !empty($Status['Com_show_status']) ) {
				$Status['Created_tmp_tables'] -= $Status['Com_show_status'];
				unset($Status['Com_show_status']);
			}

			return $Status;
		}

		function setCharacterSet($charset = 'utf8') {
			switch ($this->getAttribute(PDO::ATTR_DRIVER_NAME)) {
				case 'mysql':
					if (empty($charset)) {
						$charset = $this->queryValue('SELECT @@character_set_database');
					}
					$this->exec("SET NAMES '" . $charset . "'");
					break;

				case 'sqlite':
					assert(!empty($charset));
					$this->exec('PRAGMA encoding="' . $charset . '"');
					break;

				default:
					error_log(__CLASS__ . '->' . __FUNCTION__ . '() cannot set the character set for unknown ' . $this->getAttribute(PDO::ATTR_DRIVER_NAME) . ' database (DSN=' . $this->DSN . ')');
			}
		}

		function disconnect() {
			if (empty($this)) {
				trigger_error(__CLASS__ . "::" . __FUNCTION__ . "() called but no link has been established", E_USER_NOTICE);
			}

			unset($this);
		}

		function getUniqueIdentifier($Table, $ID) {
			assert(!empty($this->DSN));

			switch ($this->Scheme) {
				case 'mysql':
					if (preg_match('/host=([^;]+)/', $this->DSN, $matches)) {
						$Server = $matches[1];
					}
					if (preg_match('/port=([^;]+)/', $this->DSN, $matches)) {
						$Port = $matches[1];
					}
					if (preg_match('/dbname=([^;]+)/', $this->DSN, $matches)) {
						$Database = $matches[1];
					}
					break;

				case 'sqlite':
					$Server = getenv('HOSTNAME');
					list(,$Database) = explode(':', $this->DSN, 2);
					break;

				default:
					trigger_error('Unable to generate unique identifier for unknown ' . $protocol . ' connection', E_USER_ERROR);
			}

			return $this->Scheme . '://' . rawurlencode($Server) . (!empty($Port) ? ":$Port" : '') . '/' . rawurlencode($Database) . '/' . rawurlencode($Table) . "#{$ID}";
		}

		function quote($v) {
			return is_array($v) ? array_map(array($this->PDO, 'quote'), $v) : $this->PDO->quote($v);
		}

		function escape($v) {
			 if (is_array($v)) {
					foreach ($v as $k => $i) {
						$v[$k] = trim($this->PDO->quote($v), "'");
					}
					return $v;
			 } else {
				 return trim($this->PDO->quote($v), "'");;
			 }
		}

		function execute($sql) {
			$args = func_get_args();
			$sql = array_shift($args);
			assert(!empty($sql));

			$this->startTimer();

			if (empty($args)) {
				$ret = call_user_func_array(array($this->PDO, 'query'), $sql)->fetchAll(PDO::FETCH_ASSOC);
			} else {
				$st = $this->PDO->prepare($sql);
				if (!$st) {
					throw new Exception("Unable to prepare '$sql': " . kimplode($this->PDO->errorInfo()));
				}
				$this->lastRowCount = $st->execute($args);
			}

			$this->stopTimer($sql);
		}

		function query($sql) {
			$args = func_get_args();
			$sql = array_shift($args);
			assert(!empty($sql));

			$this->startTimer();

			if (empty($args)) {
				$ret = call_user_func_array(array($this->PDO, 'query'), $sql)->fetchAll(PDO::FETCH_ASSOC);
			} else {
				$st = $this->PDO->prepare($sql);
				if (!$st) {
					throw new Exception("Unable to prepare '$sql': " . kimplode($this->PDO->errorInfo()));
				}
				$st->execute($args);
				$ret = $st->fetchAll(PDO::FETCH_ASSOC);
			}

			$this->stopTimer($sql);
			return $ret;
		}

		function getLastInsertId() {
			return $this->lastInsertId();
		}

		// function queryValue($sql) {
		// 	/**
		// 	 * Returns a single value from the specified column of the first row generated by the passed SELECT statement
		// 	 */
		// 	$args = func_get_args();
		// 	$sql = array_shift($args);
		// 	assert(!empty($sql));
		//
		// 	$r = false;
		// 	$this->startTimer();
		//
		// 	$q = $this->PDO->prepare($sql);
		//
		// 	if (!empty($q)) {
		// 		$q->execute($args);
		// 		$r = $q->fetchColumn();
		// 	}
		//
		// 	$this->stopTimer($sql);
		// 	return $r;
		// }
		//
		// function queryValues($sql) {
		// 	/**
		// 	 * Returns an array containing the values of the first column returned by the passed SELECT statement
		// 	 */
		// 	$args = func_get_args();
		// 	$sql = array_shift($args);
		// 	assert(!empty($sql));
		//
		// 	$r = array();
		//
		// 	$this->startTimer();
		//
		// 	$rs = $this->PDO->prepare($sql);
		//
		// 	if (!empty($rs)) {
		// 		$rs->execute($args);
		// 		while ($t = $rs->fetchColumn()) {
		// 			$r[] = $t;
		// 		}
		// 	}
		//
		// 	$this->stopTimer($sql);
		//
		// 	return $r;
		// }

	}
?>
