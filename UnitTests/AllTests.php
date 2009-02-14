<?php
if (!defined('PHPUnit_MAIN_METHOD')) {
		define('PHPUnit_MAIN_METHOD', 'AllTests::main');
}

require_once('PHPUnit/Framework.php');
require_once('PHPUnit/TextUI/TestRunner.php');

require_once('./config.php');

require_once('ImpPDO_Tests.php');
require_once('DBObject_Tests.php');
/*
TODO: require_once('Utilities_Tests.php');
TODO: require_once('ImpHTML_Tests.php');
TODO: require_once('ImpTable_Tests.php');
TODO: require_once('ImpSQLBuilder_Tests.php');
*/

class AllTests {
		public static function main() {
				PHPUnit_TextUI_TestRunner::run(self::suite());
		}

		public static function suite() {
				$suite = new PHPUnit_Framework_TestSuite('ImpPHP');
				$suite->addTestSuite('ImpPDO_Tests');
				$suite->addTestSuite('DBObject_Tests');
				// TODO: $suite->addTestSuite('Utilities_Tests');
				// TODO: $suite->addTestSuite('ImpHTML_Tests');
				// TODO: $suite->addTestSuite('ImpTable_Tests');
				// TODO: $suite->addTestSuite('ImpSQLBuilder_Tests');
				return $suite;
		}
}

if (PHPUnit_MAIN_METHOD == 'AllTests::main') {
		AllTests::main();
}

?>
