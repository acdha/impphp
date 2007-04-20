<?php
		/*
		 * A subclass for objects which closely track database tables
		 *
		 * New-style serialization syntax:
		 *	- DB column names must match property names exactly
		 *	- Every DBObject *must* have a single, integer ID (most likely an INTEGER AUTO_INCREMENT PRIMARY KEY column)
		 *	- Arrays:
		 *			Properties = object property name => type
		 *			FormFields = fieldnames shown to the user
		 *			RequiredFormFields = fields which the user must enter
		 *
		 */

		abstract class DBObject {	
			public $ID              = false;
			public $DBTable;
			private $_trackChanges  = false;
			private $_initialValues = array();
			private static $_instances;
			
			abstract public static function &get($id = false);
			
			protected static function &_get($class, $id = false) {
				global $DB;
				
				if (empty($id)) {
					// Work around a PHP bug which requires the use of a temporary variable:
					$c =& new $class(); 
					return $c;
				}

				if (is_array($id)) {					
					$objs = array();
					
					$ObjData = $DB->query(call_user_func(array($class, '_generateSelect'), get_class_var($class, 'DBTable'), get_class_var($class, 'Properties'), "ID IN (" . implode(', ', $id) . ")"));
					
					foreach ($ObjData as $d) {
						$id = $d['ID'];
						
						if (!isset(self::$_instances[$class][$id])) {
							self::$_instances[$class][$id] =& new $class($id);
						}
						
						$objs[] =& self::$_instances[$class][$id];
					}
					return $objs;
					
				} else {
					if (!isset(self::$_instances[$class][$id])) {
						self::$_instances[$class][$id] =& new $class($id);
					}
					return self::$_instances[$class][$id];
				}

			}

			protected function __construct($ID = false) {
				global $DB;
				assert(!empty($this->Properties));

				if (isset($ID) and is_array($ID)) {
					$this->setProperties($ID);
				}	else if ($ID) {
					$ID = intval($ID);
					if (empty($ID)) {
						trigger_error(get_class($this) . " constructor called with empty id $ID", E_USER_ERROR);
					}

					$Q = $DB->query($this->_generateSelect($this->DBTable, $this->Properties, "ID=$ID"));

					if (count($Q) != 1) {
						trigger_error(get_class($this) . " constructor called with bogus id $ID", E_USER_ERROR);
					} else {
						$this->setID($ID);
						$this->setProperties($Q[0]);
					}
				} else {
					foreach ($this->Properties as $Name => $Type) {
						if (isset($this->$Name)) continue; // Ignore any values which have already been defined

						switch ($Type) {
							case "datetime":
							case "timestamp":
								$this->$Name = isset($this->_defaultValues[$Name]) ? $this->_defaultValues[$Name] : 0;
								break;

							case "string":
								$this->$Name = isset($this->_defaultValues[$Name]) ? $this->_defaultValues[$Name] : '';
								break;

							case "object":
							default:
								$this->$Name = false;
						}
					}
				}

				foreach ($this->Properties as $Name => $Type) {
					$this->_initialValues[$Name] = $this->$Name;
				}
			}
			
			protected static function _generateSelect($DBTable, $Properties, $Constraints) {
				assert(!empty($Constraints));
				
				$SQL = new ImpSQLBuilder($DBTable);

				foreach ($Properties as $name => $type) {
					$SQL->addColumn($name, $type);
				}

				if (is_array($Constraints)) {
					foreach ($Constraints as $c) {
						$SQL->addConstraint($c);
					}
				} else {
					$SQL->addConstraint($Constraints);
				}
				return $SQL->generateSelect();				
			}

			public function save() {
				global $DB;

				$Q = new ImpSQLBuilder($this->DBTable);

				foreach ($this->Properties as $P => $Type) {
					switch ($Type) {
						case "timestamp": // Timestamps are automatically updated by the database
							break;

						case "datetime":
						case "date":
							if ($P == "Created" and $this->$P == 0) {
								// We automatically set the Created column if it's unset:
								$Q->addValue($P, time(), "time");
							} else {
								$Q->addValue($P, intval($this->$P), "time");
							}
							break;

						case "object":
							// Convert from a full object to its ID:
							if (is_object($this->$P)) {
								$Q->addValue($P, $this->$P->ID);
							} elseif (is_integer($this->$P)) {
								// Hmmm - an unexpanded object ID - we'll use it verbatim:
								$Q->addValue($P, $this->$P);
							}
							break;

						case "boolean":
							// Convert from boolean true/false to 1/0 for a BIT column
							$Q->addValue($P, $this->$P ? 1 : 0);
							break;

						case "set":
							// We convert from an array of key => true/false to a
							// comma separated string of the keys whose value was true.
							// This matches the way the MySQL SET column type
							// behaves.
							$Q->addValue($P, implode(",", array_keys(array_filter($this->$P, array(&$this, "_is_true")))), 'set');
							break;

						case 'enum':
							$Q->addValue($P, $this->$P, 'enum');
							break;

						case 'integer':
							$Q->addValue($P, intval($this->$P));
							break;

						default:
							$Q->addValue($P, $this->$P);
					}
				}

				if (!empty($this->ID)) {
					if ($this->_trackChanges) {
						$this->recordChanges();
					}

					$Q->addConstraint("ID=" . $this->ID);
					$DB->Execute($Q->generateUpdate());

					if ($DB->getAffectedRowCount() > 1) {
						trigger_error(get_class() . "::save() expected to change one record but changed " . $DB->getAffectedRowCount(), E_USER_ERROR);
					}

				} else {
					$DB->Execute($Q->generateInsert());

					$this->setID($DB->getLastInsertId());
				}
			}

			public function setID($ID) {
				assert(is_numeric($ID));
				$this->ID = $ID;
			}

			public function setUser (&$User) {
				// Sets a reference to the passed User object
				assert(is_object($User));
				assert((get_class($User) == "user") or is_subclass_of($User, "User"));

				$this->User =& $User;
			}

			public function setProperty($name, $value) {
				switch ($name) {
					case "StartDate":
					case "EndDate":
					case "Created":
					case "Modified":
						settype($value, "integer"); // All dates are stored as time_t values

					default:
						// We only accept updates for official properties:

						if (isset($this->Properties[$name]) or isset($this->$name)) {
							if ($this->Properties[$name] == "object") {
								if (empty($value)) {
									$this->$name = false;
								} else {
									if (is_object($value)) {
										$this->$name = $value;
									} else {
										if (!empty($this->_classOverrides[$name])) {
											$this->$name = call_user_func(array($this->_classOverrides[$name], "get"), $value);
										} else {
											class_exists($name) or die("Couldn't set $name: the $name class does not exist");
											$this->$name = call_user_func(array($name, "get"), $value);
										}
									}
								}

								// BUG: icky temporary variable:
								$oid = "{$name}ID";
								$this->$oid = $value;

							} elseif ($this->Properties[$name] == "boolean") {
								$this->$name = is_bool($value) ? $value : ($value == 1);
							} elseif ($this->Properties[$name] == "integer") {
								$this->$name = $value;
								settype($this->name, "integer");
							} else {
								$this->$name = $value;
							}
						} else {
							trigger_error(get_class($this) . "::setProperty() called for undefined property $name", E_USER_WARNING);
						}
				}

				return true;
			}

			public function setProperties($A) {
				// Sets object properties from an associative array such as the one returned
				// by $DB->Query()

				if (empty($A)) {
					return false;
				}

				foreach ($A as $n => $v) {
					$this->setProperty($n, $v);
				}
			}

			private static function _is_true($v) {
				// Convenience function for array_filter() call in $this->save();
				return ($v);
			}

			public function enableChangeTracking($enable = true) {
				$this->_trackChanges = $enable;
			}

			private function recordChanges() {
				global $DB;

				assert($this->_trackChanges === true);
				assert(is_array($this->_initialValues));
				assert(!empty($this->_initialValues));
				assert(!empty($this->ID)); // We only track changes against stored data
				assert(!empty($_SERVER['PHP_AUTH_USER']));

				$Q = new ImpSQLBuilder("ChangeLog");
				$Q->addValue("TargetTable", $this->DBTable);
				$Q->addValue("RecordID", $this->ID);
				$Q->addValue("Admin", $_SERVER['PHP_AUTH_USER']);

				foreach ($this->Properties as $Name => $Type) {
					$NewValue = is_object($this->$Name) ? $this->$Name->ID : $this->$Name;
					$OldValue = is_object($this->_initialValues[$Name]) ? $this->_initialValues[$Name]->ID : $this->_initialValues[$Name];

					if (empty($this->_ignoreChanges[$Name]) and ($NewValue != $OldValue)) {
						$Q->addValue("Property", $Name, "STRING");
						$Q->addValue("OldValue", $OldValue, "STRING");
						$Q->addValue("NewValue", $NewValue, "STRING");
						$DB->Execute($Q->generateInsert());
					}
				}
			}

			private function getChanges() {
				global $DB;

				if (empty($this->ID)) {
					return array();
				}

				return $DB->query("SELECT Admin, UNIX_TIMESTAMP(Time) AS Time, Property, OldValue, NewValue FROM ChangeLog WHERE TargetTable='{$this->DBTable}' AND RecordID = {$this->ID} ORDER BY Time, Property");
			}

			public function printChanges() {
				if (empty($this->ID)) {
					return;
				}

				$Changes = $this->getChanges();
				if (empty($Changes)) {
					return;
				}

				print '<table class="DBObjectChanges">';
				print '<caption>';
				print '<a name="' . html_encode("{$this->DBTable}_{$this->ID}_Changes") . '"></a>';
				print !empty($this->Name) ? html_encode($this->Name) : "{$this->DBTable} #{$this->ID}";
				print  ' History</caption>';
				print '<tr><th>Time</th><th>User</th><th>Property</th><th>Old Value</th><th>New Value</th></tr>';

				$LastGroup = false;
				$GroupCount = 0;

				foreach ($Changes as $Change) {
					extract($Change, EXTR_PREFIX_ALL, 'Change');

					$Change_Time = date("Y-M-d H:i:s", $Change_Time);

					switch ($this->Properties[$Change_Property]) {
							case "datetime":
							case "timestamp":
								$Change_OldValue = date("Y-j-d H:i:s", $Change_OldValue);
								$Change_NewValue = date("Y-j-d H:i:s", $Change_NewValue);
								break;

							case "date":
								$Change_OldValue = date("Y-j-d", $Change_OldValue);
								$Change_NewValue = date("Y-j-d", $Change_NewValue);
								break;
					}

					if ($LastGroup != "$Change_Admin $Change_Time") {
						$LastGroup = "$Change_Admin $Change_Time";
						$GroupCount++;
					} else {
						$Change_Admin = '';
						$Change_Time = '';
					}

					print '<tr class="' . ($GroupCount % 2 ? 'Even' : 'Odd') . '">';
					print "<td class=\"Timestamp\">$Change_Time</td><td class=\"UserName\">$Change_Admin</td><td class=\"PropertyName\">$Change_Property</td><td class=\"OldValue\">$Change_OldValue</td><td class=\"NewValue\">$Change_NewValue</td>";
					print "</tr>\n";
				}

				print "</table>\n";
			}

			public function getUniqueIdentifier() {
				global $DB;
				// Returns a generic reference which uniquely identifies this particular object in a reasonably persistent fashion:
				return 'mysql://' . rawurlencode($DB->Server) . '/' . rawurlencode($DB->Name) . '/' . rawurlencode($this->DBTable) . "#{$this->ID}";
			}
	}
?>
