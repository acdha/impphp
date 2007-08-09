<?php
	interface ImpDBO {
		function changeUser($Username, $Password);
		function execute($sql);
		function getAffectedRowCount();
		function getLastInsertId();
		function getPerformanceCounters();
		function getUniqueIdentifier($Table, $ID);
		function query($sql);
		function queryValue($sql);
		function queryValues($sql);
		function quote($s);
		function setCharacterSet($charset = 'utf8');
	}

	abstract class ImpDB implements ImpDBO {
		# Performance monitoring:
		protected $cummulativeQueryTime = 0;
		protected $extendedProfiling    = false;
		protected $logQueries           = false;
		protected $profileQueries       = false;
		protected $queryCount           = 0;
		protected $queryLog             = array();

		# If non-empty, this->log() calls will call $Log->Log() instead of error_log
		protected $Log;

		protected $lastRowCount;

		function __destruct() {
			if ($this->profileQueries) $this->printQueryLog();
		}

		function logQueries($b = true) {
			$this->logQueries = $b;
		}

		function profileQueries($b = true) {
			$this->profileQueries = $b;
		}

		function setLog(Log $Log) {
			$this->Log = $Log;
		}

		function log($Message, $Priority = false) {
			if (isset($this->Log)) {
				$this->Log->Log($Message, $Priority);
			} else {
				error_log(get_class($this) . ": " . $Message);
			}
		}

		function startTimer() {
			if (!$this->profileQueries) return;
			$this->queryStartTime = microtime(true);

			if ($this->extendedProfiling) {
				$this->initialSessionStatus = $this->getSessionStatus();
			}
		}

		function stopTimer($sql) {
			if (!$this->profileQueries) {
				if ($this->logQueries) {
					$this->log($sql);
				}
				return false;
			}

			$elapsed = microtime(true) - $this->queryStartTime;
			$this->cummulativeQueryTime += $elapsed;

			if ($this->logQueries) {
				$this->log("$sql (Elapsed time: " . number_format($elapsed, 6) . " seconds. Total: {$this->queryCount} queries in " . number_format($this->cummulativeQueryTime, 6) . " seconds)", PEAR_LOG_DEBUG);
			}

			$this->queryCount++;

			if (!isset($this->queryLog[$sql])) {
				$this->queryLog[$sql] = array(
					"Count" => 1,
					"Time" => $elapsed
				);
			} else {
				$this->queryLog[$sql]['Count']++;
				$this->queryLog[$sql]['Time'] += $elapsed;
			}

			if ($this->extendedProfiling) {
				$deltas = array_diff($this->initialSessionStatus, $this->getSessionStatus());
				$tmp = new ImpTable($deltas);
				$tmp->Caption = $sql;
				$tmp->generate();
			}
		}

		function escape($v) {
			 if (is_array($v)) {
					foreach ($v as $k => $i) {
						$v[$k] = $this->quote($i);
					}
					return $v;
			 } else {
				 return $this->quote($v, $this);
			 }
		}

		function escapeString($v) {
			return $this->escape($v);
		}

		function getAffectedRowCount() {
			return $this->lastRowCount;
		}

		function queryValue($sql) {
			$args = func_get_args();
			$rs = call_user_func_array(array($this, 'query'), $args);
			return $rs ? array_first(array_first($rs)) : false;
		}

		function queryValues($sql) {
			$args = func_get_args();
			$ret = array();
			$rs = call_user_func_array(array($this, 'query'), $args);

			foreach ($rs as $r) {
				$ret[] = array_first($r);
			}
			return $ret;
		}


		/* Diagnostic code */
		function printQueryLog() {
			include_once('ImpUtils/ImpTable.php');

			uasort($this->queryLog, create_function('$a,$b', 'return strcmp($b["Count"], $a["Count"]);'));

			$d = array();

			$TotalQueries = 0;
			foreach ($this->queryLog as $k => $v) {
				$d[] = array("Query" => $k, 'Count' => $v['Count'], 'Time' => round($v['Time'] * 1000000), 'Cost' => round($v['Time'] * 1000000 / $v['Count']));
				$TotalQueries += $v['Count'];
			}

			assert($TotalQueries = $this->queryCount);

			$className = get_class($this);

			$QueryTable = new ImpTable($d);

			$QueryTable->DefaultSortKey   = 'Time';
			$QueryTable->DefaultSortOrder = 'Descending';

			$QueryTable->Attributes['id'] = $className . '_Queries';
			$QueryTable->Caption          = $className . ': ' . number_format($TotalQueries) . ' queries in ' . number_format($this->cummulativeQueryTime, 3) . ' seconds';

			$QueryTable->ColumnHeaders    = array(
				'Query' => array('text' => 'SQL Statement', 	'type'=> 'string', 'sortable' => true),
				'Count' => array('text' => 'Count', 					'type'=> 'number', 'sortable' => true, 'formatter' => $className .'_numberFormatter'),
				'Time'	=> array('text' => 'Time (&micro;s)', 'type'=> 'number', 'sortable' => true, 'formatter' => $className .'_numberFormatter'),
				'Cost'	=> array('text' => 'Cost', 						'type'=> 'number', 'sortable' => true, 'formatter' => $className .'_numberFormatter')
			);
			?>
					<style>
						#<?=$className?>_Queries {
							text-align: left;
							padding: 2pt;
							background-color: white;
							color: black;
						}

						#<?=$className?>_Queries caption {
							font-weight: bold;
							text-align: center;
						}

						#<?=$className?>_Queries thead {
							background-color: lightgrey;
						}

						#<?=$className?>_Queries a {
							color: inherit ! important;
							font-weight: inherit ! important;
						}

						#<?=$className?>_Queries th {
							padding: 2pt;
							padding-right: 1em;
							font-weight: bold;
							white-space: nowrap;
						}

						#<?=$className?>_Queries td {
							font-family: monospace;
							font-size: 9px;
						}
					</style>
					<script>
						function <?=$className?>_numberFormatter(elCell, oRecord, oColumn, oData) {
							elCell.style.padding = "2pt";
							elCell.style.textAlign = "right";
							elCell.style.fontFamily = "monospace ! important";
							elCell.style.font = "9px ProFont";
							elCell.innerHTML = oData;
						}
					</script>
			<?
				$QueryTable->generate();
		}
	}
?>
