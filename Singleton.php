<?php

abstract class Singleton {
  private function __construct() {}
  private function __clone() { 
		trigger_error('It is impossible to clone a singleton', E_USER_ERROR);
	}
 
	private static $Instances;

  static public function &getInstance($ClassName = null) {
		if (isset(self::$Instances[$ClassName])) {
	 	  return self::$Instances[$ClassName];
		} elseif (!empty($ClassName)) {
			self::$Instances[$ClassName] = &new $ClassName();
			return self::$Instances[$ClassName];
		} else {
			trigger_error('Singleton::getInstance() called without a class or existing instance!', E_USER_ERROR);
		}
  }
}


function __autoload($ClassName) {
  include_once("$ClassName.php");

	if (is_subclass_of($ClassName, 'Singleton')) {
		call_user_func(array($ClassName, 'getInstance'), $ClassName);
	}
}
?>