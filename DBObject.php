<?php
		/*
			A subclass for objects which closely track database tables

			Since the concept of having multiple instances representing a
      single table is unwise, this class uses a singleton approach to
      ensure that all future instantiations of a given (class, id)
      will return the first instance

			Usage:
					new DerivedClass() - empty object
					new DerivedClass($ID) - object retrieved from database or false
					new DerivedClass($Properties) - new object initialized using $Properties instead of a database query. array_keys() must match!

					$objects = DerivedClass::get($IDs) - retrieve an array of objects corresponding to the passed array of IDs
					$objects = DerviedClass::find(array('key' => 'value')) - retrieve an array of matching values

			New-style serialization syntax:
			 	- DB column names must match property names exactly
			  - Every DBObject *must* have a single, integer ID (most likely
			    an INTEGER AUTO_INCREMENT PRIMARY KEY column)
			  - Properties is an array. The key is the property name; the
					value is either a string (shorthand for the property's type) or
					an array:
						type      => property type (integer, string, boolean, timestamp, datetime/date, set, enum or object)
						class     => name of a PHP class, only necessary if the class name is different than the property name
						formfield => boolean indicating whether this property corresponds directly to a form field
						required  => boolean indicating whether this is a required form field
						lazy			=> boolean indicating whether this should be loaded on demand (default: true)

			NOTES:
				Subclasses must implement the get() and find() static functions because these cannot be inherited from this class:
					// These functions work around a problem with PHP5 inheritance which causes
					// inherited methods to use the parent class's __CLASS__, $this, etc.
					public static function &get($id = false) {
						return self::_getInstance(__CLASS__, $id);
					}
					public static function find(array $constraints = array()) {
						return self::_find(__CLASS__, $constraints);
					}


			TODO: add support for form validation information
			TODO: remove legacy get/set*() methods
			TODO: implement find() method
			TODO: cleanup property handling - we should have an iterator which converts $Properties values to arrays with the appropriate default values
			TODO: Fix set handling to provide all possible values
		 */

		abstract class DBObject {
			public $ID;
			protected $DBTable;
			protected $Properties;
			protected $_trackChanges   = false;
			protected $_ignoreChanges  = array();
			protected $_initialValues  = array();
			protected $_lazyObjects    = array();
			private static $_instances = array();

			abstract public static function &get($id = false);

			protected static function &_getInstance($class, $id = false) {
				global $DB;

				if ($id === false) {
					// CHANGED: $id is now tested for being literally false as opposed to simply empty()
					$c = new $class;
					return $c;
				}

				if (is_array($id)) {
					$objs = array();

					if (empty($id)) return $objs;

					$ObjData = $DB->query(call_user_func(array($class, '_generateSelect'), get_class_var($class, 'DBTable'), get_class_var($class, 'Properties'), "ID IN (" . implode(', ', $id) . ")"));

					foreach ($ObjData as $d) {
						$id = $d['ID'];

						if (!isset(self::$_instances[$class][$id])) {
							self::$_instances[$class][$id] =& new $class($d);
						}

						$objs[$id] =& self::$_instances[$class][$id];
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
				global $DB, $AppLog;
				assert(!empty($this->Properties));
				// CHANGED: these check to make sure old values have been removed
				assert(!isset($this->_classOverrides));
				assert(!isset($this->RequiredFormFields));
				assert(!isset($this->FormFields));

				if (!empty($ID)) {
					if (is_array($ID)) {
						$this->setProperties($ID);
					}	else {
						$ID = intval($ID);

						if (empty($ID)) {
							error_log(get_class($this) . " constructor called with empty id $ID");
							return;
						}

						$Q = $DB->query($this->_generateSelect($this->DBTable, $this->Properties, "ID=$ID"));

						if (count($Q) < 1) {
							error_log(get_class($this) . " constructor called with non-existent id $ID");
							return;
						} else {
							assert(count($Q) == 1);
							$this->setID($ID);
							$this->setProperties($Q[0]);
						}
					}
				} else {
					foreach ($this->Properties as $Name => $def) {
						if (isset($this->$Name)) continue; // Ignore any values which have already been defined

						if (is_array($def)) {
							assert(isset($def['type']));
							$Type = $def['type'];
						} else {
							$Type = $def;
						}

						// TODO: File PHP bug report because $strings['index key'] is treated as a literal 'i' value!
						if (is_array($def) and isset($def['default'])) {
							$this->$Name = $def['default'];
						} else {
							switch ($Type) {
								case "datetime":
								case "timestamp":
									$this->$Name = 0;
									break;

								case "string":
									$this->$Name = '';
									break;

								case "set":
									$this->$Name = array();
									break;

								default:
								// CHANGED: default is now undefined rather than false so we can generate proper SQL NULLs
							}
						}
					}
				}
			}

			public function __isset($p) {
				if (array_key_exists($p, $this->_lazyObjects) or isset($this->$p)) {
					return true;
				} else {
					return false;
				}
			}

			public function __get($p) {
				if (isset($this->_lazyObjects[$p])) {
					$this->_lazyLoad($p);
					return $this->$p;
				} elseif (isset($this->$p)) {
					return $this->$p;
				} elseif (isset($this->Properties[$p])) {
					return;
				} else {
					trigger_error("Attempted to get unknown property $p!", E_USER_ERROR);
				}
			}

			public function __set($p, $v) {
				if (array_key_exists($p, $this->Properties)) {
					if (!isset($this->_initialValues[$p])) {
						$this->_initialValues[$p] = $v;
					}
					$this->$p = $v;
				} elseif (isset($this->$p)) {
					$this->$p = $v;
				} else {
					trigger_error("Attempted to set unknown property $p to $v!", E_USER_ERROR);
				}
			}

			private function _lazyLoad($p) {
				assert(isset($this->_lazyObjects[$p]));
				$this->$p = call_user_func(array($this->_lazyObjects[$p]['Class'], "get"), $this->_lazyObjects[$p]['ID']);
				$this->_initialValues[$p] = $this->$p;
				unset($this->_lazyObjects[$p]);
			}

			public static function _find($class, array $constraints = array()) {
				global $DB;
				assert(empty($constraints)); // TODO: Implement search constraints

				return call_user_func(array($class, 'get'), $DB->queryValues("SELECT ID FROM " . get_class_var($class, 'DBTable')));
			}

			protected static function _generateSelect($DBTable, $Properties, $Constraints) {
				assert(!empty($Constraints));

				$SQL = new ImpSQLBuilder($DBTable);
				$SQL->addColumn('ID', 'integer');

				foreach ($Properties as $name => $def) {
					if (is_array($def)) {
						assert(isset($def['type']));
						$SQL->addColumn($name, $def['type']);
					} else {
						$SQL->addColumn($name, $def);
					}
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

				foreach ($this->Properties as $P => $PropDef) {
					if (is_array($PropDef)) {
						$Type = $PropDef['type'];
					} else {
						$Type = $PropDef;
					}

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
							if (!empty($this->$P)) {
								assert(is_object($this->$P));
								$Q->addValue($P, $this->$P->ID);
							} else {
								$Q->addValue($P, false);
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
					$DB->execute($Q->generateUpdate());

					assert($DB->getAffectedRowCount() <= 1);

				} else {
					$DB->execute($Q->generateInsert());
					$this->ID = $DB->getLastInsertId();
				}
			}

			public function setID($ID) {
				assert(is_numeric($ID));
				$this->ID = $ID;
			}

			public function setProperty($name, $value) {
				switch ($name) {
					case "ID":
						$this->ID = intval($value);
						break;

					case "StartDate":
					case "EndDate":
					case "Created":
					case "Modified":
						settype($value, "integer"); // All dates are stored as time_t values

					default:
						if (!isset($this->Properties[$name])) {
							trigger_error(get_class($this) . "::setProperty() called for undefined property $name", E_USER_ERROR);
						}

						if (is_array($this->Properties[$name])) {
							$PropertyType = $this->Properties[$name]['type'];
						} else {
							$PropertyType = $this->Properties[$name];
						}

						switch ($PropertyType) {
							case "collection":
								die("Attempted to redefine the $name collection!");
								break;

							case "object":
								if (empty($value)) {
									$this->$name = false;
								} else {
									if (is_object($value)) {
										$this->$name = $value;
										break;
									}

									$class = $name;
									$lazy = true;

									if (is_array($this->Properties[$name])) {
										if (isset($this->Properties[$name]['class'])) {
											$class = $this->Properties[$name]['class'];
										}

										if (isset($this->Properties[$name]['lazy'])) {
											$lazy = $this->Properties[$name]['lazy'];
										}
									}

									if ($lazy) {
										$this->_lazyObjects[$name]['ID'] = intval($value);
										$this->_lazyObjects[$name]['Class'] = $class;
									} else {
										class_exists($class) or die("Couldn't set $name: the $name class does not exist");
										$this->$name = call_user_func(array($class, "get"), $value);
									}
								}

								break;

							case "boolean":
								$this->$name = is_bool($value) ? $value : ($value == 1);
								break;

							case "integer":
								$this->$name = intval($value);
								break;

							default:
								$this->$name = $value;
						}
				}

				return true;
			}

			public function setProperties(array $A) {
				// Sets object properties from an associative array such as the one returned
				// by $DB->Query()

				if (empty($A)) {
					return false;
				}

				foreach ($A as $n => $v) {
					$this->setProperty($n, $v);
				}
			}

			public function getFormFields() {
				return array_keys(array_filter($this->Properties, create_function('$a', 'return (is_array($a) and !empty($a["formfield"]) and ($a["formfield"] === true));')));
			}

			public function getRequiredFormFields() {
				return array_keys(array_filter($this->Properties, create_function('$a', 'return (is_array($a) and !empty($a["formfield"]) and ($a["formfield"] === true) and !empty($a["required"]) and ($a["required"] === true));')));
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
					$NewValue = $this->_convertValueToChangeString($this->$Name);
					$OldValue = $this->_convertValueToChangeString($this->_initialValues[$Name]);

					if (empty($this->_ignoreChanges[$Name]) and ($NewValue != $OldValue)) {
						$Q->addValue("Property", $Name, "STRING");
						$Q->addValue("OldValue", $OldValue, "STRING");
						$Q->addValue("NewValue", $NewValue, "STRING");
						$DB->execute($Q->generateInsert());
					}
				}
			}

			protected function _convertValueToChangeString($v) {
				if (is_object($v)) {
					return $v->ID;
				} elseif (is_array($v)) {
					return implode(',', $v);
				} else{
					return $v;
				}
			}

			public function getChanges() {
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

			public static function defaultSortFunction($a, $b) {
				// This is a function designed to be used with usort() and related functions. By default we sort only in numeric order by database ID:
				return cmp($a->ID, $b->ID);
			}
	}
?>
