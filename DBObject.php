<?php
		/*
			A subclass for objects which closely track database tables

			Since the concept of having multiple instances representing a
      single table is unwise, this class uses a factory approach to
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
				Subclasses must implement the get() and find() static functions because these cannot be inherited from this class. See DBObject-template.php.

			TODO: Add support for form validation information
			TODO: Remove legacy get/set*() methods
			TODO: Finish implementing find() method
			TODO: Cleanup property handling - we should have an iterator which converts $Properties values to arrays with the appropriate default values
			TODO: Fix set handling to provide all possible values
			TODO: Add proper support for collections (e.g. defining Document->Children as a property of type collection, class = Document, key = Parent=$this->ID)
			TODO: Add lazy loading for properties which are not objects
		 */

		if (!class_exists('ImpSQLBuilder')) {
			require_once('ImpUtils/ImpSQLBuilder.php');
		}

		if (!function_exists('ImpErrorHandler')) {
			require_once('ImpUtils/Utilities.php');
		}

		abstract class DBObject {
			public $ID;
			protected $DBTable;
			protected $Properties;
			protected $_trackChanges  = false;
			protected $_ignoreChanges = array();
			protected $_initialValues = array();
			protected $_lazyObjects   = array();
			public static $_instances = array();

			static $DB;

			abstract public static function &get($id = false);

			protected static function &_getInstance($class, $id = false) {
				if ($id === false) {
					$c = new $class;
					return $c;
				}

				if (!array_key_exists($class, self::$_instances)) {
					self::$_instances[$class] = array();
				}

				if (is_array($id)) {
					$objs = array();

					foreach ($id as $k => $i) {
						if (!empty(self::$_instances[$class][$i])) {
							$objs[$i] = self::$_instances[$class][$i];
							unset($id[$k]);
						}
					}

					if (empty($id)) return $objs;

					$ObjData = DBObject::$DB->query(call_user_func(array($class, '_generateSelect'), get_class_var($class, 'DBTable'), get_class_var($class, 'Properties'), 'ID IN (' . implode(', ', $id) . ')'));

					foreach ($ObjData as $d) {
						$id = $d['ID'];

						if (!array_key_exists($id, self::$_instances[$class])) {
							self::$_instances[$class][$id] = new $class($d);
						}

						$objs[$id] = self::$_instances[$class][$id];
					}

					return $objs;
				} else {

					if (!array_key_exists($id, self::$_instances[$class])) {
						self::$_instances[$class][$id] = new $class($id);
					}

					return self::$_instances[$class][$id];
				}
			}

			public static function purgeInstance($class, $id) {
				// Utility function used mostly for unit testing to force objects to be reloaded:
				unset(self::$_instances[$class][$id]);
			}

			protected function __construct($ID = false) {
				assert(!empty($this->Properties));
				assert(!isset($this->_classOverrides));
				assert(!isset($this->RequiredFormFields));
				assert(!isset($this->FormFields));

				if (!empty($GLOBALS['DB'])) {
					DBObject::$DB = $GLOBALS['DB'];
				}

				if (!empty($ID)) {
					if (is_array($ID)) {
						$this->setProperties($ID);
					}	else {
						$ID = intval($ID);

						if (empty($ID)) {
							throw new Exception("Constructor called with empty id $ID");
						}

						$Q = DBObject::$DB->query($this->_generateSelect($this->DBTable, $this->Properties, "ID=$ID"));

						if (count($Q) < 1) {
							throw new Exception("Constructor called with non-existent id $ID");
						} else {
							assert(count($Q) == 1);
							$this->setID($ID);
							$this->setProperties($Q[0]);
							$this->_initialValues = $Q[0];
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

						if (is_array($def) and array_key_exists('default', $def)) {
							$this->$Name = $def['default'];
						} else {
							switch ($Type) {
								case 'date':
								case 'datetime':
								case 'timestamp':
								case 'integer':
									$this->$Name = (int)0;
									break;

								case 'double':
								case 'currency':
									$this->$Name = (double)0;

								case 'string':
								case 'enum':
									$this->$Name = '';
									break;

								case 'set':
									$this->$Name = array();
									break;

								case 'object':
									break; // There is no meaningful default for this type

								case 'boolean':
									$this->$Name = false;
									break;

								default:
									trigger_error('No default for property of type ' . $Type, E_USER_NOTICE);
							}
						}

						$this->_initialValues[$Name] = $this->$Name;
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
				} elseif (isset($this->Properties[$p]) or array_key_exists($p, get_class_vars(get_class()))) {
					return;
				} else {
					trigger_error("Attempted to get unknown property $p!", E_USER_ERROR);
				}
			}

			public function __set($name, $value) {
				if (!(isset($this->$name) or array_key_exists($name, $this->Properties) or array_key_exists($name, get_class_vars(get_class())))) {
					trigger_error("Attempted to set unknown property $name to $value!", E_USER_ERROR);
				}

				switch ($name) {
					case 'ID':
						if (array_key_exists($name, $this->_initialValues)) {
							unset(self::$_instances[get_class($this)][$this->_initialValues['ID']]);
							assert(!isset(self::$_instances[get_class($this)][$value]));
						}

						$this->ID = (integer)$value;
						break;

					case 'StartDate':
					case 'EndDate':
					case 'Created':
					case 'Modified':
						settype($value, 'integer'); // All dates are stored as time_t values

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
							case 'object':
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
										class_exists($class) or die("Couldn't set $name: the $class class does not exist");
										$tmpObj = call_user_func(array($class, 'get'), $value);
										if (!is_object($tmpObj) or empty($tmpObj->ID)) {
											throw new Exception("Attempted to set $name to invalid ID $value");
										}
										$this->$name = $tmpObj;
									}
								}
								break;

							case 'boolean':
								$this->$name = (bool)$value;
								break;

							case 'set':
									assert(array_key_exists('default', $this->Properties[$name]));
									assert(is_array($this->Properties[$name]['default']));

									if (is_array($value)) {
										$this->$name = array_merge($this->Properties[$name]['default'], $value);
									} else {
										// In addition to hitting the setter only once, the use of a temporary variable avoids the variable variable syntax ambiguity of $name[$n]:

										$tmp = $this->Properties[$name]['default'];

										foreach (explode(',', $value) as $n) {
											$tmp[$n] = true;
										}

										$this->$name = $tmp;
									}
								break;

							case 'integer':
							case 'date':
							case 'datetime':
							case 'timestamp':
								$this->$name = (integer)$value;
								break;

							default:
								$this->$name = $value;
						}
				}
			}

			private function _lazyLoad($p) {
				assert(isset($this->_lazyObjects[$p]));

				$tmpObj = call_user_func(array($this->_lazyObjects[$p]['Class'], 'get'), $this->_lazyObjects[$p]['ID']);

				if (!is_object($tmpObj) or empty($tmpObj->ID)) {
					throw new Exception("Attempted to set $p to invalid ID " . $this->_lazyObjects[$p]['ID']);
				}

				$this->$p = $tmpObj;

				unset($this->_lazyObjects[$p]);
			}

			public static function loadAllLazyObjects(array $objects, $Property) {
				$ids = array();
				$class = false;

				foreach ($objects as $o) {
					if (array_key_exists($Property, $o->_lazyObjects)) {
						$ids[] = $o->_lazyObjects[$Property]['ID'];
						if (empty($class)) $class = $o->_lazyObjects[$Property]['Class'];
					}
				}
				assert(!empty($class));

				call_user_func(array($class, 'get'), $ids);
			}

			protected static function _find($class, array $constraints = array()) {
				$SB = new ImpSQLBuilder(get_class_var($class, 'DBTable'));
				$SB->addColumn('ID', 'integer');

				foreach ($constraints as $k => $c) {
					if (is_array($c)) {
						if (count($c) > 1) throw new Exception('TODO: Support for operators other than the assumed =/IS/IN has not been implemented');
						foreach ($c as $name => $value) {
							$SB->addConstraint($name, $value);
						}
					} else {
						if (is_integer($k)) {
							$SB->addConstraint($c);
						} else {
							$SB->addConstraint($k, $c);
						}
					}
				}

				return call_user_func(array($class, 'get'), DBObject::$DB->queryValues($SB->generateSelect()));
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
				$Q = new ImpSQLBuilder($this->DBTable);

				foreach ($this->Properties as $P => $PropDef) {
					if (is_array($PropDef)) {
						$Type = $PropDef['type'];
					} else {
						$Type = $PropDef;
					}

					switch ($Type) {
						case 'timestamp':
							$Q->addValue($P, time(), 'time'); // CHANGED: timestamps are set to time() to avoid discrepancies between PHP and database timezones
							break;

						case 'datetime':
						case 'date':
							if ($P == 'Created' and $this->$P == 0) {
								// We automatically set the Created column if it's unset:
								$Q->addValue($P, time(), 'time');
							} else {
								$Q->addValue($P, intval($this->$P), 'time');
							}
							break;

						case 'object':
							// Convert from a full object to its ID:
							if (!empty($this->$P)) {
								$Q->addValue($P, is_object($this->$P) ? $this->$P->ID : (integer)$this->$P);
							} else {
								$Q->addValue($P, false);
							}
							break;

						case 'boolean':
							// Convert from boolean true/false to 1/0 for a BIT column
							$Q->addValue($P, $this->$P ? 1 : 0);
							break;

						case 'set':
							// We convert from an array of key => true/false to a
							// comma separated string of the keys whose value was true.
							// This matches the way the MySQL SET column type
							// behaves.
							$Q->addValue($P, implode(',', array_keys(array_filter($this->$P, array(&$this, '_is_true')))), 'set');
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

					$Q->addConstraint('ID=' . $this->ID);
					DBObject::$DB->execute($Q->generateUpdate());

					assert(DBObject::$DB->getAffectedRowCount() <= 1);

				} else {
					DBObject::$DB->execute($Q->generateInsert());
					$this->ID = DBObject::$DB->getLastInsertId();
				}

				return true;
			}

			public function setID($ID) {
				// This function exists only for legacy code which predates the availability of __set():
				$this->ID = $ID;
			}

			public function setProperty($name, $value) {
				// This function exists only for legacy code which predates the availability of __set():
				$this->$name = $value;
			}

			public function setProperties(array $A) {
				// Sets object properties from an associative array such as the one returned
				// by DBObject::$DB->query()

				if (empty($A)) {
					return false;
				}

				foreach ($A as $n => $v) {
					$this->$n = $v;
				}
			}

			private function filterFormFields($a) {
				return (is_array($a) and !empty($a['formfield']) and ($a['formfield'] === true));
			}

			private function filterRequiredFormFields($a) {
				return (is_array($a) and !empty($a['formfield']) and ($a['formfield'] === true) and !empty($a['required']) and ($a['required'] === true));
			}

			public function getFormFields() {
				return array_keys(array_filter($this->Properties, array($this, 'filterFormFields')));
			}

			public function getRequiredFormFields() {
				return array_keys(array_filter($this->Properties, array($this, 'filterRequiredFormFields')));
			}

			private static function _is_true($v) {
				// Convenience function for array_filter() call in $this->save();
				return ($v);
			}

			public function enableChangeTracking($enable = true) {
				$this->_trackChanges = $enable;
			}

			private function recordChanges() {
				assert($this->_trackChanges === true);
				assert(is_array($this->_initialValues));
				assert(!empty($this->_initialValues));
				assert(!empty($this->ID)); // We only track changes against stored data
				assert(!empty($_SERVER['PHP_AUTH_USER']));

				$Q = new ImpSQLBuilder('ChangeLog');
				$Q->addValue('TargetTable', $this->DBTable);
				$Q->addValue('RecordID', $this->ID);
				$Q->addValue('Admin', $_SERVER['PHP_AUTH_USER']);

				foreach ($this->Properties as $Name => $Type) {
					$NewValue = $this->_convertValueToChangeString($this->$Name);
					$OldValue = $this->_convertValueToChangeString($this->_initialValues[$Name]);

					if (empty($this->_ignoreChanges[$Name]) and ($NewValue != $OldValue)) {
						$Q->addValue('Property', $Name, 		'STRING');
						$Q->addValue('OldValue', $OldValue, 'STRING');
						$Q->addValue('NewValue', $NewValue, 'STRING');
						DBObject::$DB->execute($Q->generateInsert());
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
				if (empty($this->ID)) {
					return array();
				}

				return DBObject::$DB->query("SELECT Admin, UNIX_TIMESTAMP(Time) AS Time, Property, OldValue, NewValue FROM ChangeLog WHERE TargetTable='{$this->DBTable}' AND RecordID = {$this->ID} ORDER BY Time, Property");
			}

			public function printChanges() {
				if (empty($this->ID)) {
					return;
				}

				$Changes = $this->getChanges();
				if (empty($Changes)) {
					return;
				}

				// TODO: Add filtering here or consider switching to ImpTable with paged data so we can avoid having a huge changelog on the default view in an admin page
				$Changes = array_reverse($Changes);

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

					$Change_Time = date('Y-M-d H:i:s', $Change_Time);

					switch ($this->Properties[$Change_Property]) {
							case 'datetime':
							case 'timestamp':
								$Change_OldValue = date('Y-j-d H:i:s', (int)$Change_OldValue); // These values are cast to avoid issues with values which had not been set and were thus NULL
								$Change_NewValue = date('Y-j-d H:i:s', (int)$Change_NewValue); // TODO: remove the casts after ChangeLog is updated to serialize() its values
								break;

							case 'date':
								$Change_OldValue = date('Y-j-d', (int)$Change_OldValue);
								$Change_NewValue = date('Y-j-d', (int)$Change_NewValue);
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
				// Returns a generic reference which uniquely identifies this particular object in a reasonably persistent fashion:
				return DBObject::$DB->getUniqueIdentifier($this->DBTable, $this->ID);
			}

			public function getETag() {
				// Returns an HTTP Entity Tag value which is guaranteed to change if any of the component data changes
				assert(!empty($this->ID));
				return sha1(serialize($this->_initialValues));
			}

			public static function defaultSortFunction($a, $b) {
				// This is a function designed to be used with usort() and related functions. By default we sort only in numeric order by database ID:
				if (property_exists($a, 'Name')) {
					return strcmp($a->Name, $b->Name);
				} else {
					return cmp($a->ID, $b->ID);
				}
			}
	}
?>
