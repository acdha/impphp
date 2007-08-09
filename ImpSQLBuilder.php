<?php
	/**
	 * ImpQuerybuilder - Programmatically build complex SQL statements
	 *
	 *
	 * Unless otherwise stated, terms are used in first-passed, first-generated order
	 *
	 * Examples:
	 *
	 *	$Query = new ImpSQLBuilder("Table");
	 *
	 *	// Add columns to the query:
	 *	$Query->setColumns(array("ID", "Subject", "Body"));
	 *	$Query->addColumn("Modified"[, "timestamp"]);
	 *
	 *	// Add joins:
	 *	$Query->addJoin("INNER JOIN Users ON Documents.OwnerID = Users.ID");
	 *
	 *	// Add WHERE constraints:
	 *	$Query->addConstraint("Name='cadams'");
	 *	$Query->addConstraint("Visible=1");
	 *
	 *	// Add sort keys
	 *	$Query->addOrder("Created DESC");
	 *	$Query->addOrder("Subject");
	 *
	 *	// Use a subset of the matching rows:
	 *	$Query->SetPageSize(15);
	 *	$Query->SetCurrentPage(5);
	 *
	 *	// INSERT/UPDATE values:
	 *	$Query->addValues(array(
	 *	  "OwnerID"			=> 5,
	 *	  "Subject"			=> "This is a test which actual touches the database. Isn't it nifty?",
	 *	  "Visible"			=> 1
	 *	  )
	 *	);
	 *
	 *	Passing data for INSERT/UPDATE:
	 *
	 *		Currently we pass 2 part values:
	 *				- value
	 *				- type [optional]
	 *
	 *			$Update->addValue("Modified", "NOW()");								// Strings are passed directly
	 *			$Update->addValue("Expires", time() + (86400 * 30), "TIME");		// Integers are put in FROM_UNIXTIME() calls
	 *
	 *	A different approach would be to pre-declare the column name and
	 *	type and call with the values. This would allow more natural updates
	 *	in a loop and opens the possibility of caching parts of the query
	 *	for use in a placeholder-style implementation:
	 *
	 *	$Query->addColumn("Created",		"DATE");
	 *	$Query->addColumn("Subject",		"STRING");
	 *	$Query->addColumn("Flags",			"SET");
	 *
	 *	$Query->prepareInsert();
	 *
	 *	$DB->execute($Query->generate(time(), $Subject, "Visible");
	 *
	 *	$DB->execute($Query->generate(time(), "Secret message", array("Visible", "ReadOnly"));
	 *
	 *	foreach ($NewMessages as $Message) {
	 *		// $Message has keys Created,Subject,Flags
	 *		$DB->execute($Query->generate($Message);
	 *	}
	 *
	 *	The table data types could be retrieved using mysql_list_fields() if
	 *	we have a database connection. This would allow us to easily handle
	 *	all of mysql's types, at the expense of requiring database-specific
	 *	code.
	 *
	 *	foreach (mysql_list_fields() as $f) {
	 *		$this->Columns[mysql_field_name($f)] = mysql_field_type($f);
	 *	}
	 *
	 */

	class ImpSQLbuilder {
		protected $Columns    = array();	// Contains a list of column expressions to be retrieved
		protected $JoinTerms  = array();
		protected $WhereTerms = array();
		protected $GroupTerms = array();
		protected $OrderTerms = array();
		protected $DataValues = array();	// Contains key=value pairs for columns in UPDATE/INSERT

		protected $PageSize;
		protected $CurrentPage;

		protected $DB;

		function ImpSQLBuilder($table, $query_type = "SELECT") {
			$this->Table = $table;
			$this->queryType = strtoupper($query_type);
		}

		function setDB(ImpDB $DB) {
			$this->DB = $DB;
		}

		function escape($data) {
			if (!empty($this->DB)) {
				return $this->DB->escape($data);
			} else {
				return mysql_real_escape_string($data); // Fall-back for legacy code
			}
		}

		function setType($query_type = "SELECT") {
			$this->queryType = strtoupper($query_type);
		}

		function setColumns() {
			// Set the entire list of columns in one pass

			$Columns = array();

			foreach (func_get_args() as $arg) {
				if (is_array($arg)) {
					$this->Columns = array_merge($this->Columns, $arg);
				} else {
					$this->Columns[] = $arg;
				}
			}
		}

		function addColumn($col, $type = false) {
			assert(is_string($col));

			switch ($type) {
				case "date":
				case "datetime":
				case "timestamp":
					assert($this->queryType == "SELECT");

					$col = "UNIX_TIMESTAMP($col) AS $col";

				default:
					array_push($this->Columns, $col);
			}
		}

		function addColumns() {
			foreach (func_get_args() as $arg) {
				if (is_array($arg)) {
					foreach ($arg as $name => $type) {
						$this->addColumn($name, $type);
					}
				} else {
					$this->addColumn($arg);
				}
			}
		}

		function addValue($name, $val, $type = false) {
			assert(is_string($name));

			if (empty($this->DataValues[$name])) {
				if (!$type) {
					$type = is_string($val) ? "STRING" : "GENERAL";
				}

				$this->DataValues[$name] = array($val, $type);
			} else {
				$this->DataValues[$name][0] = $val;
			}
		}

		function addValues($ary) {
			assert(is_array($ary));
			foreach ($ary as $k => $v) {
				$this->addValue($k, $v);
			}
		}

		function generateUpdateColumnClause() {
			$c = array();

			foreach ($this->getProcessedValues() as $Column => $Value) {
				$c[] = "$Column = $Value";
			}

			return "\t\t" . implode(",\n\t\t", $c) . "\n";
		}

		function getProcessedValues() {
			// Returns key=value pairs ready to be inserted in the database:
			$a = array();

			foreach ($this->DataValues as $Key => $V) {
				list($Value, $Type) = $V;

				if ((!isset($Value)) or ($Value === false )) {
					$a[$Key] = 'NULL';
				} else {
					switch (strtolower($Type)) {
						case "time":
								// Unix timestamps get special treatment; strings get treated as normal strings
							if (is_integer($Value)) {
								$a[$Key] = ($Value == 0) ? "NULL" : "FROM_UNIXTIME($Value)";
								break;
							}

						case 'set':
						case 'enum':
							if (is_array($Value)) {
								$a[$Key] = "'" . $this->escape(implode(',', $Value)) . "'";
							} else {
								$a[$Key] = "'" . $this->escape($Value) . "'";
							}
							break;

						case "string":
							$a[$Key] = "'" . $this->escape($Value) . "'";
							break;

						default:
							$a[$Key] = $Value;
					}
				}
			}

			return $a;
		}

		function addJoin($term) {
			assert(is_string($term));
			array_push($this->JoinTerms, $term);
		}

		function generateJoinClause() {
			return "\t" . implode("\n\t", $this->JoinTerms) . "\n";
		}

		function addConstraint($term) {
			assert(is_string($term));
			array_push($this->WhereTerms, $term);
		}

		function generateWhereClause($Op = "AND") {
			// TODO: support something other than pure AND statements
			if (empty($this->WhereTerms)) {
				return "";
			}

			return "WHERE \n\t(" . implode(")\n\t$Op (", $this->WhereTerms) . ")\n";
		}

		function addGroup($term) {
			assert(is_string($term));
			array_push($this->GroupTerms, $term);
		}

		function generateGroupClause() {
			if (empty($this->GroupTerms)) {
				return;
			}

			return "GROUP BY \n\t" . implode(",\n\t", $this->GroupTerms) . "\n";
		}

		function addOrder($term, $PushTerm = true) {
			assert(is_string($term));

			if ($PushTerm) {
				array_push($this->OrderTerms, $term);
			} else {
				array_unshift($this->OrderTerms, $term);
			}
		}

		function generateOrderClause() {
			if (empty($this->OrderTerms)) {
				return;
			}

			return "ORDER BY \n\t" . implode(",\n\t", $this->OrderTerms) . "\n";
		}

		function setPageSize($size) {
			$this->PageSize = intval($size);
			assert($this->PageSize > 0);
		}

		function setCurrentPage($page) {
			$this->CurrentPage = intval($page);
			assert($this->CurrentPage >= 0);
		}

		function generateLimit() {
			// MySQL-specific limit generator
			if (empty($this->PageSize)) {
				return;
			}

			if ($this->queryType == "SELECT") {
				if (empty($this->CurrentPage)) {
					return "LIMIT $this->PageSize";
				} else {
					return "LIMIT " . ($this->CurrentPage * $this->PageSize) . ", $this->PageSize";
				}
			} elseif ($this->queryType == "DELETE" or $this->queryType == "UPDATE") {
				// DELETE/UPDATE don't support paging but we might stil want to limit the number of rows affected:
				return "LIMIT $this->PageSize";
			}
		}

		function generateSelect() {
			$sql = "SELECT " . (empty($this->cacheQueries) ? '' : 'SQL_CACHE') . "\n\t" . join(",\n\t", $this->Columns ) . "\nFROM {$this->Table}\n";

			$sql .= $this->generateJoinClause();
			$sql .= $this->generateWhereClause();
			$sql .= $this->generateGroupClause();
			$sql .= $this->generateOrderClause();

			$sql .= $this->generateLimit();

			$sql .= "\n";

			return $sql;
		}

		function generateInsert() {
			$sql = "INSERT INTO {$this->Table} \n";

			$v = $this->getProcessedValues();

			$sql .= "\t(" . implode(", ", array_keys($v)) . ")\n";
			$sql .= "VALUES\n";
			$sql .= "\t(" . implode(", ", array_values($v)) . ")\n";

			$sql .= "\n";

			return $sql;
		}

		function generateUpdate() {
			$sql = "UPDATE {$this->Table} SET\n";

			$sql .= $this->generateUpdateColumnClause();
			$sql .= $this->generateWhereClause();
			$sql .= $this->generateLimit();

			$sql .= "\n";

			return $sql;
		}

		function generateReplace() {
			// MySQL-specific INSERT/UPDATE combo - eliminates the need to choose which one to use

			$sql = "REPLACE INTO {$this->Table} SET\n";

			$sql .= $this->generateUpdateColumnClause();
			$sql .= $this->generateWhereClause();
			$sql .= $this->generateLimit();

			$sql .= "\n";

			return $sql;
		}

		function generateDelete() {
			$sql = "DELETE FROM {$this->Table}\n";

			$sql .= $this->generateWhereClause();
			$sql .= $this->generateOrderClause(); // MySQL 4
			$sql .= $this->generateLimit();

			$sql .= "\n";

			return $sql;
		}
	}
?>
