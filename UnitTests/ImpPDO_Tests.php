<?php
	require_once('./config.php');
	require_once('PHPUnit/Framework.php');
	require_once('PHPUnit/TextUI/TestRunner.php');
	require_once('ImpPhp/ImpPDO.php');

	class ImpPDO_Tests extends PHPUnit_Framework_TestCase {
		protected $db;

		function __construct() {
			$this->TempFile = '/tmp/' . __CLASS__ . '.sqlite';
			if (file_exists($this->TempFile)) unlink($this->TempFile) or die("{$this->TempFile} already exists and could not be removed");
		}

		function __destruct() {
			if (file_exists($this->TempFile)) unlink($this->TempFile) or die("Couldn't unlink {$this->TempFile}");
		}

		function getDB() {
			$this->db = new ImpPDO('sqlite:' . $this->TempFile);
		}

		function testCreateDB() {
			$this->getDB();
			$this->assertTrue(is_object($this->db));
		}

		function testCreateTable() {
			$this->getDB();
			$this->db->execute('CREATE TABLE Test (ID INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, Name VARCHAR(255) NOT NULL)');
			$this->assertEquals($this->db->getAffectedRowCount(), 0);
		}

		function testInsertData() {
			$this->getDB();
			$this->db->execute('INSERT INTO Test (ID, Name) VALUES (1, "Test 1")');
			$this->db->execute('INSERT INTO Test (ID, Name) VALUES (2, "Test 2")');
			$this->db->execute('INSERT INTO Test (ID, Name) VALUES (3, "Test 3")');
			$this->db->execute('INSERT INTO Test (ID, Name) VALUES (4, "Test 4")');

			$this->assertEquals($this->db->query('SELECT ID, Name FROM Test ORDER BY ID') , array(array('ID' => '1', 'Name' => 'Test 1'), array('ID' => '2', 'Name' => 'Test 2'), array('ID' => '3', 'Name' => 'Test 3'), array('ID' => '4', 'Name' => 'Test 4')));
		}

		function testUpdateData() {
			$this->getDB();
			$this->db->execute('UPDATE Test SET Name = "Foobar 2000" WHERE ID=4');
			$this->assertEquals($this->db->getAffectedRowCount(), 1);
			$this->assertEquals($this->db->query('SELECT ID, Name FROM Test ORDER BY ID') , array(array('ID' => '1', 'Name' => 'Test 1'), array('ID' => '2', 'Name' => 'Test 2'), array('ID' => '3', 'Name' => 'Test 3'), array('ID' => '4', 'Name' => 'Foobar 2000')));
		}

		function testGetLastInsertId() {
			$this->getDB();
			$this->db->execute("INSERT INTO Test (Name) VALUES ('Who am I?')");
			$this->assertEquals(5, $this->db->getLastInsertId());
		}

		function testRowCounts() {
			$this->getDB();
			$this->db->execute("INSERT INTO Test (Name) VALUES ('Who am I?')");
			$lid = $this->db->getLastInsertId();

			$this->db->execute("DELETE FROM Test WHERE ID=999999");
			$this->assertEquals($this->db->getAffectedRowCount(), 0);
			$this->db->execute("DELETE FROM Test WHERE ID=?", 99999);
			$this->assertEquals($this->db->getAffectedRowCount(), 0);

			$this->assertEquals($this->db->exec("DELETE FROM Test WHERE ID=$lid"), 1);

			$this->db->execute("INSERT INTO Test (Name) VALUES ('Who am I?')");
			$lid = $this->db->getLastInsertId();
			$this->db->execute("DELETE FROM Test WHERE ID=?", $lid);
			$this->assertEquals($this->db->getAffectedRowCount(), 1);

			$this->db->execute("INSERT INTO Test (Name) VALUES ('Who am I?')");
			$lid = $this->db->getLastInsertId();
			$this->db->execute("DELETE FROM Test WHERE ID=$lid");
			$this->assertEquals($this->db->getAffectedRowCount(), 1);
		}

		function testGetPerformanceCounters() {
			$this->getDB();
			$this->assertType('array', $this->db->getPerformanceCounters());
		}
		function testGetUniqueIdentifier() {
			$this->getDB();
			$this->assertTrue(strlen($this->db->getUniqueIdentifier('Test', 1)) > 0);
			$this->assertNotEquals($this->db->getUniqueIdentifier('Test', 1), $this->db->getUniqueIdentifier('Test', 5));
		}

		function testQuery() {
			$this->getDB();
			$this->assertEquals(array(array(1 => 1)), $this->db->query('SELECT 1'));
		}

		function testQueryValue() {
			$this->getDB();
			$this->assertEquals(1, $this->db->queryValue('SELECT 1'));
		}

		function testQueryValues() {
			$this->getDB();
			$this->assertEquals(range(1,5), $this->db->queryValues('SELECT ID FROM Test ORDER BY ID'));
		}

		function testQuote() {
			$this->getDB();

			$this->assertEquals("'Foobar'", $this->db->quote('Foobar'));
			$this->assertEquals("'Foo''bar'", $this->db->quote("Foo'bar"));
			$this->assertEquals('Foobar', $this->db->escape('Foobar'));
			$this->assertEquals("Foo''bar", $this->db->escape("Foo'bar"));

			$randstr = '';
			for ($i = 0; $i < 2000; $i++) {
				$randstr .= chr(rand(0, 0xFF));
			}

			$this->assertEquals($this->db->quote($randstr), "'" . $this->db->escape($randstr) . "'");
		}

		// TODO: function testSetCharacterSet();
	}

	if (!defined('PHPUnit_MAIN_METHOD')) {
		$s = new PHPUnit_Framework_TestSuite(__FILE__);
		$s->addTestSuite('ImpPDO_Tests');
		PHPUnit_TextUI_TestRunner::run($s);
	}
?>
