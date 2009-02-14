<?php
	require_once('./config.php');
	require_once('PHPUnit/Framework.php');
	require_once('PHPUnit/TextUI/TestRunner.php');
	require_once('ImpPHP/ImpPDO.php');
	require_once('ImpPHP/DBObject.php');

	class DBObject_Tests extends PHPUnit_Framework_TestCase {
		function __construct() {
			$this->TempFile = '/tmp/' . __CLASS__ . '.sqlite';
			if (file_exists($this->TempFile)) unlink($this->TempFile) or die("{$this->TempFile} already exists and could not be removed");

			$this->getDB();
			$this->db->execute('CREATE TABLE Test (ID INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, Name VARCHAR(255) NOT NULL, Enabled BIT, Parent NULL REFERENCES Test(ID), Created DATETIME NOT NULL, Modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP, StartDate DATETIME, EndDate DATETIME, ProbablyFive INTEGER, SomeDate DATE, UNIQUE(Name))');
			$this->assertEquals($this->db->getAffectedRowCount(), 0);
			$this->db->execute("CREATE TABLE ChangeLog (
			  Admin VARCHAR(32) NOT NULL DEFAULT '',
			  Time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  TargetTable VARCHAR(32) NOT NULL,
			  RecordID INTEGER NOT NULL,
			  Property VARCHAR(64) NOT NULL,
			  OldValue BLOB,
			  NewValue BLOB
			)");
			$this->assertEquals($this->db->getAffectedRowCount(), 0);
			$this->db->execute('CREATE INDEX TableRecordTime ON ChangeLog(TargetTable,RecordID,Time);');
		}

		function __destruct() {
			if (file_exists($this->TempFile)) unlink($this->TempFile) or die("Couldn't unlink {$this->TempFile}");
		}

		function getDB() {
			$this->db = new ImpPDO('sqlite:' . $this->TempFile);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			DBObject::$DB      = $this->db;
			ImpSQLBuilder::$DB = $this->db;
		}

		function testCreateObject() {
			$this->getDB();
			$t = DBTest::get();
			$this->assertType('object', $t);
			$this->assertObjectHasAttribute('ID', $t);
			$this->assertObjectHasAttribute('DBTable', $t);
			$this->assertObjectHasAttribute('Properties', $t);
			$this->assertAttributeNotContains('ID', 'Properties', $t);

			$propNames = array_keys($t->Properties);
			$objProperties = array_keys(get_object_vars($t));

			// Parent will have no value at this point because it is an object and there is no meaningful default value for an undefined object:
			$this->assertObjectNotHasAttribute('Parent', $t);
			unset($propNames[array_search('Parent', $propNames)]);

			$this->assertTrue(array_diff($propNames, $objProperties) == array());

			// Confirm that our custom __get / __set code works
			$this->assertObjectNotHasAttribute('NoSuchAttribute', $t); // Test __isset()

			$exceptionThrown = false;
			try {
				print $t->Foobar; // Test __get()
			} catch (Exception $e) {
				$exceptionThrown = true;
			}
			$this->assertTrue($exceptionThrown);

			$exceptionThrown = false;
			try {
				$t->Foobar = 5; // Test __set
			} catch (Exception $e) {
				$exceptionThrown = true;
			}
			$this->assertTrue($exceptionThrown);

			$t->Name = 'My First Test Object';
			$this->assertTrue($t->save());
			$this->assertTrue($t->ID > 0);
			$this->assertEquals('My First Test Object', $t->Name);

			$GLOBALS['FirstObjectID'] = $t->ID;

			DBObject::purgeInstance('DBTest', $t->ID);
			unset($t);
		}

		function testLoadObject() {
			$this->getDB();
			$first = DBTest::get($GLOBALS['FirstObjectID']);
			$this->assertType('object', $first);
			$this->assertEquals($first->ID, $GLOBALS['FirstObjectID']);

			// These have not been set:
			$this->assertEquals($first->StartDate, 0);
			$this->assertEquals($first->EndDate, 0);

			// Created should automatically be set by DBObject:
			$this->assertNotEquals($first->Created, 0);
			$this->assertTrue(time() >= $first->Created);
			$this->assertTrue((time() - $first->Created) < 10); 	// This accounts for minor testing delays but should expose major performance problems

			// Modified should automatically be set by the database:
			$this->assertNotEquals($first->Modified, 0);
			$this->assertTrue(time() >= $first->Modified); 				// TODO: bad craziness due to sqlite storing and returning some dates as GMT
			$this->assertTrue((time() - $first->Modified) < 10); 	// This accounts for minor testing delays but should expose major performance problems
		}

		function testObjectIDPromotion() {
			$this->getDB();

			$first = DBTest::get($GLOBALS['FirstObjectID']);

			$child = DBTest::get();
			$child->Parent = $GLOBALS['FirstObjectID']; // The integer ID should automatically be converted to a new DBTest object by DBObject::__set()
			$this->assertType('object', $child->Parent);
			$this->assertSame($child->Parent, $first);
		}

		function testAddChildObject() {
			$this->getDB();

			$first = DBTest::get($GLOBALS['FirstObjectID']);
			$this->assertEquals($first->ID, $GLOBALS['FirstObjectID']);

			$child = DBTest::get();
			$child->Parent = $first;
			$this->assertSame($child->Parent, $first);

			$child->Name = 'Happy Kid';
			$child->SomeDate = time();
			$child->save();

			$this->assertEquals($this->db->queryValue('SELECT Parent FROM Test WHERE ID=?', $child->ID), $first->ID);
		}

		function testCloneChild() {
			$this->getDB();

			$first = DBTest::get($GLOBALS['FirstObjectID']);

			unset($first->ID);

			// We expect this to fail because of the database's UNIQUE(Name) constraint:
			$exceptionThrown = false;
			try {
				$first->save();
			} catch (Exception $e) {
				$exceptionThrown = true;
			}
			$this->assertTrue($exceptionThrown);

			$first->Name = 'New Clone Object';
			$this->assertTrue($first->save());
		}

		function testInvalidParent() {
			// This test confirms that an error will be given when you attempt to set an
			// object property using an invalid ID. This is easiest to do by testing for
			// the subsequent object's ID property as lazy loading will cause the error to
			// be hidden until the property is first accessed

			$this->getDB();

			$first = DBTest::get($GLOBALS['FirstObjectID']);

			$exceptionThrown = false;
			try {
				$t->Parent = 0xdeadbeef;
				$this->assertNotEquals($t->Parent->ID, 0xdeadbeef);
			} catch (Exception $e) {
				$exceptionThrown = true;
			}
			$this->assertTrue($exceptionThrown);
		}

		function testLazyLoad() {
			$this->getDB();

			$childID = $this->db->queryValue('SELECT ID FROM Test WHERE Parent = ? LIMIT 1', $GLOBALS['FirstObjectID']);
			DBObject::purgeInstance('DBTest', $GLOBALS['FirstObjectID']);
			$child = DBTest::get($childID);
			$r = new ReflectionObject($child);
			foreach ($r->getProperties() as $prop) {
				$this->assertNotEquals($prop->getName(), 'Parent'); // Parent should not be defined as we have not yet accessed it
			}

			$foo = $child->Parent;
			$this->assertType('object', $foo);
			$this->assertEquals($foo->ID, $GLOBALS['FirstObjectID']);

			$found = false;
			foreach ($r->getProperties() as $prop) {
				if ($prop->getName() == 'Parent') {
					$found = true;
				}
			}
			$this->assertTrue($found);
		}

		function testLegacySetters() {
			$tmp = DBTest::get();

			$tmp->Name = 'Foobar';
			$tmp->Enabled = true;
			$tmp->StartDate = 53;

			$tmp->ID = 5;
			$this->assertEquals($tmp->ID, 5);

			$tmp->setProperty('ID', 6);
			$this->assertEquals($tmp->ID, 6);

			$tmp->setProperties(array('ID' => 7, 'Name' => 'Baaz'));
			$this->assertEquals($tmp->ID, 7);
			$this->assertEquals($tmp->Name, 'Baaz');
			// Ensure that other properties weren't clobbered:
			$this->assertEquals($tmp->Enabled, true);
			$this->assertEquals($tmp->StartDate, 53);

			@$tmp->setID(8);
			$this->assertEquals($tmp->ID, 8);
		}

		function testChangeTracking() {
			$this->getDB();

			$first = DBTest::get($GLOBALS['FirstObjectID']);
			$first->enableChangeTracking();

			foreach ($first->Properties as $p => $v) {
				$this->assertNotEquals($p, 'ID'); // Make sure that ID never shows up in Properties
				if ($p != 'Parent')	$first->$p = 0xdeadbeef;
			}

			$_SERVER['PHP_AUTH_USER'] = basename(__FILE__); // TODO: Remove this once DBObject change tracking no longer relies on it
			$first->save();

			$changes = $first->getChanges();
			$this->assertType('array', $changes);
			foreach ($changes as $c) {
				$this->assertEquals($c['Admin'], $_SERVER['PHP_AUTH_USER']);
				$this->assertTrue(time() - $c['Time'] < 5);
				$this->assertEquals($c['NewValue'], 0xdeadbeef);
			}
		}

		function testGetFormFields() {
			$this->getDB();

			$first = DBTest::get($GLOBALS['FirstObjectID']);

			$this->assertEquals($first->getFormFields(), array('Name', 'Enabled'));
			$this->assertEquals($first->getRequiredFormFields(), array('Enabled'));

		}

		// TODO: test that new DBTest returns an error (constructor is protected)

		// TODO: test these functions:
		// public static function find(array $constraints = array())
		// TODO: test set and enum functionality

		function testGetUniqueIdentifier() {
			$this->getDB();

			$first = DBTest::get($GLOBALS['FirstObjectID']);
			$second = DBTest::get($this->db->queryValue('SELECT ID FROM Test WHERE ID != ? LIMIT 1', $first->ID));

			$this->assertNotEquals($first->ID, $second->ID);

			$this->assertTrue(strlen($first->getUniqueIdentifier()) > 0);
			$this->assertNotEquals($first->getUniqueIdentifier(), $second->getUniqueIdentifier());
		}


		function testSorting() {
			$this->getDB();

			$requestedIDs = $this->db->queryValues('SELECT ID FROM Test ORDER BY RANDOM()');

			$this->assertTrue(count($requestedIDs) > 2);
			$this->assertEquals($requestedIDs, array_unique($requestedIDs));

			$objects = DBTest::get($requestedIDs);

			$this->assertType('array', $objects);
			$this->assertEquals(count($objects), count($requestedIDs));

			$foundIDs = array();
			foreach ($objects as $o) {
				$this->assertType('object', $o);
				$foundIDs[] = $o->ID;
			}
			sort($foundIDs);
			sort($requestedIDs);

			$this->assertEquals($foundIDs, array_unique($foundIDs));

			$this->assertEquals(array(), array_diff($requestedIDs, $foundIDs));

			$this->assertTrue(usort($objects, array('DBTest', 'defaultSortFunction')));
		}

	}

	class DBTest extends DBObject {
			protected $DBTable        = 'Test';
			protected $Properties     = array(
				'Name'                   => array('type' => 'string', 'formfield' => true),
				'Parent'                 => array('type' => 'object', 'class' => __CLASS__),
				'Created'                => array('type' => 'datetime'),
				'Modified'               => array('type' => 'timestamp'),
				'StartDate'              => 'datetime',
				'EndDate'                => 'datetime',
				'SomeDate'               => 'date',
				'Enabled'                => array('type' => 'boolean', 'formfield' => true, 'required' => true),
				'ProbablyFive'           => array('type' => 'integer', 'default' => 5),
					// TODO: come up with tests for MySQL's proprietary SET/ENUM types
			);
			protected $_ignoreChanges = array();

			// These functions work around a problem with PHP5 inheritance which causes
			// inherited methods to use the parent class's __CLASS__, $this, etc.
			public static function &get($id = false) {
				return self::_getInstance(__CLASS__, $id);
			}
			public static function find(array $constraints = array()) {
				return self::_find(__CLASS__, $constraints);
			}
	}

	if (!defined('PHPUnit_MAIN_METHOD')) {
		$s = new PHPUnit_Framework_TestSuite(__FILE__);
		$s->addTestSuite('DBObject_Tests');
		PHPUnit_TextUI_TestRunner::run($s);
	}
?>
