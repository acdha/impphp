<?php
		/*
		 * A subclass for objects which closely track database tables
		 *
		 * TODO: Add support for form validation and generation
		 * TODO: Remove legacy get/set*() methods
		 * TODO: Add support for bind variables
		 * TODO: Expand find() method
		 * TODO: Cleanup property handling - we should have an iterator which converts $Properties values to arrays with the appropriate default values
		 * TODO: Improve set/enum handling
		 * TODO: Cleanup currency type handling
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

			public static function &get($id = false) {
				throw new Exception("Your class must override DBObject::get()!");
			}

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

					$ObjData = self::$DB->query(call_user_func(array($class, '_generateSelect'), get_class_var($class, 'DBTable'), get_class_var($class, 'Properties'), 'ID IN (' . implode(', ', $id) . ')'));

					foreach ($ObjData as $d) {
						$id = $d['ID'];

						if (!array_key_exists($id, self::$_instances[$class])) {
							self::$_instances[$class][$id] = new $class($d);
						}

						$objs[$id] = self::$_instances[$class][$id];
					}

					uasort($objs, array($class, 'defaultSortFunction'));

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
						$this->_initialValues = $ID;
					}	else {
						$ID = intval($ID);

						if (empty($ID)) {
							throw new InvalidArgumentException(get_class($this) . " constructor called with empty id $ID");
						}

						$Q = self::$DB->query($this->_generateSelect($this->DBTable, $this->Properties, "ID=$ID"));

						if (count($Q) < 1) {
							throw new InvalidArgumentException(get_class($this) . " constructor called with ID $ID which is not in {$this->DBTable}");
						} else {
							assert(count($Q) == 1);
							$this->ID = $ID;
							$this->setProperties($Q[0]);
							$this->_initialValues = $Q[0];
						}
					}
				} else {
					foreach ($this->Properties as $Name => $def) {
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

								case 'collection':
									break;

								case 'object':
									// TODO: this breaks __set(), presumably due to a recursive call: $this->$Name = null;
									break;

								case 'boolean':
									$this->$Name = false;
									break;

								default:
									throw new InvalidArgumentException('No default for property of type ' . $Type);
							}
						}

						$this->_initialValues[$Name] = $this->$Name;
					}
				}
			}

			public function __isset($p) {
				return (array_key_exists($p, $this->_lazyObjects) or isset($this->$p));
			}

			public function __get($p) {
				if (isset($this->_lazyObjects[$p])) {
					return $this->_lazyLoad($p);
				} elseif (isset($this->$p)) {
					return $this->$p;
				} elseif (array_key_exists($p, $this->Properties)) {
					if (is_array($this->Properties[$p]) and $this->Properties[$p]['type'] == 'collection') {
						return (array) $this->_loadCollection($p); // FIXME: cast to work around a bug in PHP < 5.2.4 which causes returned arrays to be read-only which breaks e.g. foreach because they update the internal array pointer
					} else {
						return null;
					}
				} else {
					throw new InvalidArgumentException("Attempted to get unknown property " . get_class($this) . "->$p");
				}
			}

			public function __set($name, $value) {
				if (!array_key_exists($name, $this->Properties) and !array_key_exists($name, get_class_vars(get_class()))) {
					throw new InvalidArgumentException("Attempted to set unknown property " . get_class($this) . "->$name to $value!");
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
						if (is_array($this->Properties[$name])) {
							$PropertyType = $this->Properties[$name]['type'];
						} else {
							$PropertyType = $this->Properties[$name];
						}

						switch ($PropertyType) {
							case 'object':
								if (empty($value)) {
									$this->$name = null;
								} elseif (is_object($value)) {
									$this->$name = $value;
								} else {
									$value = (integer)$value;
									$class = $name;
									$lazy  = true;

									if (is_array($this->Properties[$name])) {
										if (isset($this->Properties[$name]['class'])) {
											$class = $this->Properties[$name]['class'];
										}

										if (isset($this->Properties[$name]['lazy'])) {
											$lazy = $this->Properties[$name]['lazy'];
										}
									}

									if ($lazy) {
										$this->_lazyObjects[$name]['ID']    = $value;
										$this->_lazyObjects[$name]['Class'] = $class;
									} else {
										if (!class_exists($class)) throw new Exception("Couldn't set $name to $class ($value): there is no $class class");

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

										foreach (array_filter(explode(',', $value)) as $n) {
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

			public function __clone() {
				unset($this->ID);
				$this->_trackChanges  = false;
				$this->_initialValues = array();
				
				foreach ($this as $k => $v) {
					if (isset($v) and is_object($v)) {
						$this->$k = clone $v;
					}
				}
				
				foreach (array("Properties", "_lazyObjects") as $f) {
					foreach ($this->Properties as $k => $v) {
						if (isset($v) and is_object($v)) {
							$this->$f[$k] = clone $v;
						}
					}
				}
			}

			public function __sleep() {
				// It usually doesn't make sense to serialize DBObjects: the database is the serialization layer
				if (empty($this->ID)) {
					return array();
				} else {
					return array("ID");
				}
			}
			
			public function __wakeup() {
				// Rather than rely on possibly stale, serialized data we'll reload the known-current data from the database:
				$this->__construct($this->ID);
			}

			private function _loadCollection($p) {
				if (empty($this->ID)) return null;

				assert(is_array($this->Properties[$p]));
				assert(!empty($this->Properties[$p]['type']));
				assert($this->Properties[$p]['type'] == 'collection');
				assert(!isset($this->$p));

				extract($this->Properties[$p]);

				if (empty($class)) {
					throw new Exception("Property definition for $p needs to specify a class name");
				}

				if (empty($table))					$table         = $p;
				if (empty($our_column))			$our_column    = get_class($this);
				if (empty($member_column))	$member_column = 'ID';
				if (empty($sort_function))	$sort_function = array($class, 'defaultSortFunction');

				$sql = "SELECT $member_column FROM $table WHERE $our_column=?";
				if (!empty($constraint)) 	$sql .= " AND ($constraint)";
				if (!empty($constraints))	$sql .= ' AND (' . implode(') AND (', $constraints) . ')';

				// CHANGED: get_class_var() fails on get_class_var(array($class,'DB)) because ReflectionClass->getDefaultProperties() doesn't include static properties prior to 5.2.4 (http://bugs.php.net/bug.php?id=41884)
				$tmp = call_user_func(array($class, 'get'), self::$DB->queryValues($sql, $this->ID));
				assert(is_array($tmp));

				if (!empty($filter)) {
					$tmp = array_filter($tmp, $filter);
				}

				uasort($tmp, $sort_function);
				$this->$p = $tmp;
				return $tmp;
			}

			private function _lazyLoad($p) {
				assert(isset($this->_lazyObjects[$p]));

				$tmpObj = call_user_func(array($this->_lazyObjects[$p]['Class'], 'get'), $this->_lazyObjects[$p]['ID']);

				if (!is_object($tmpObj) or empty($tmpObj->ID)) {
					throw new Exception("Attempted to set $p to invalid ID " . $this->_lazyObjects[$p]['ID']);
				}

				$this->$p = $tmpObj;

				unset($this->_lazyObjects[$p]);

				return $tmpObj;
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
					if (empty($c)) continue;

					if (is_integer($k)) {
						if (is_array($c)) {
							foreach ($c as $name => $value) {
								$SB->addConstraint($name, $value);
							}
						} else {
							$SB->addConstraint($c);
						}
					} else {
						$SB->addConstraint($k, $c);
					}
				}

				return call_user_func(array($class, 'get'), self::$DB->queryValues($SB->generateSelect()));
			}

			protected static function _generateSelect($DBTable, $Properties, $Constraints) {
				assert(!empty($Constraints));

				$SQL = new ImpSQLBuilder($DBTable);
				$SQL->addColumn('ID', 'integer');

				foreach ($Properties as $name => $def) {
					if (is_array($def)) {
						if (!isset($def['type'])) throw new Exception("$DBTable property $name must have a defined type");

						if ($def['type'] == 'collection') continue;

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
						case 'collection':
							break;

						case 'timestamp':
							$Q->addValue($P, time(), 'time'); // CHANGED: timestamps are set to time() to avoid discrepancies between PHP and database timezones
							break;

						case 'datetime':
						case 'date':
							if ($P == 'Created' and $this->$P == 0) {
								// We automatically set the Created column if it's unset:
								$Q->addValue($P, time(), 'time');
							} else {
								$Q->addValue($P, (integer)$this->$P, 'time');
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
							$Q->addValue($P, implode(',', array_keys(array_filter($this->$P))), 'set');
							break;

						case 'enum':
							$Q->addValue($P, $this->$P, 'enum');
							break;

						case 'integer':
							$Q->addValue($P, (integer)$this->$P);
							break;

						case 'currency':
							$Q->addValue($P, (double)$this->$P);
							break;

						default:
							$Q->addValue($P, empty($this->$P) ? false : $this->$P);
					}
				}

				if (!empty($this->ID)) {
					if ($this->_trackChanges) {
						$this->recordChanges();
					}

					$Q->addConstraint('ID=' . $this->ID);
					self::$DB->execute($Q->generateUpdate());
					assert(self::$DB->getAffectedRowCount() <= 1);

				} else {
					self::$DB->execute($Q->generateInsert());
					$this->ID = self::$DB->getLastInsertId();
				}

				return true;
			}

			public function setID($ID) {
				trigger_error(get_class($this) . '->setID() is deprecated; you should use ' . get_class($this) . '->ID = $id instead', E_USER_NOTICE);
				$this->ID = $ID;
			}

			public function setProperty($name, $value) {
				// This function exists only for legacy code which predates the availability of __set():
				$this->$name = $value;
			}

			public function setProperties(array $A) {
				// Sets object properties from an associative array such as the one returned
				// by self::$DB->query()

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

				foreach ($this->Properties as $Name => $def) {
					if (is_array($def) and $def['type'] == 'collection') continue;

					$NewValue = $this->_convertValueToChangeString($this->$Name);
					$OldValue = $this->_convertValueToChangeString($this->_initialValues[$Name]);

					if (empty($this->_ignoreChanges[$Name]) and ($NewValue != $OldValue)) {
						$Q->addValue('Property', $Name, 		'STRING');
						$Q->addValue('OldValue', $OldValue, 'STRING');
						$Q->addValue('NewValue', $NewValue, 'STRING');
						self::$DB->execute($Q->generateInsert());
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

				return self::$DB->query("SELECT Admin, UNIX_TIMESTAMP(Time) AS Time, Property, OldValue, NewValue FROM ChangeLog WHERE TargetTable='{$this->DBTable}' AND RecordID = {$this->ID} ORDER BY Time, Property");
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
				$CSS_ID = ImpHTML::makeSafeCSSName($this->getUniqueIdentifier());

				echo '<table class="DBObjectChanges" id="', $CSS_ID, '">';
				print '<caption>';
				print '<a name="' . html_encode("{$this->DBTable}_{$this->ID}_Changes") . '"></a>';
				print !empty($this->Name) ? html_encode($this->Name) : "{$this->DBTable} #{$this->ID}";
				print  ' History</caption>';
				echo '<tr><td colspan="5"><a href="#" onclick="return toggleChangeLog_', $CSS_ID, '()" class="DBObjectChangesToggle">Hide Change Log</a></td></tr>';
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
				?>
					<script type="text/javascript" charset="utf-8">
						function toggleChangeLog_<?=$CSS_ID?>() {
							var changelog = document.getElementById("<?=$CSS_ID?>");
							var toggle = document.getElementById("<?=$CSS_ID?>_toggle");

							if (changelog.style.display == 'none') {
								changelog.style.display = 'table';
								toggle.innerHTML        = 'Hide Change Log';
							} else {
								changelog.style.display = 'none';
								toggle.innerHTML        = 'Show Change Log';
							}

							return false;
						}

						document.write('<a href="#" onclick="return toggleChangeLog_<?=$CSS_ID?>()" id="<?=$CSS_ID?>_toggle" class="DBObjectChangesToggle">Show Change Log</a>');
						toggleChangeLog_<?=$CSS_ID?>();
					</script>
				<?
			}

			public function getUniqueIdentifier() {
				// Returns a generic reference which uniquely identifies this particular object in a reasonably persistent fashion:
				return self::$DB->getUniqueIdentifier($this->DBTable, $this->ID);
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
