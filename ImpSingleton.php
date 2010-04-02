<?php

abstract class ImpSingleton {
  private function __construct() {}
  private function __clone() {
		trigger_error('It is impossible to clone a singleton', E_USER_ERROR);
	}

	private static $Instances;

  static public function &getInstance($ClassName = null) {
		if (isset(self::$Instances[$ClassName])) {
	 	  return self::$Instances[$ClassName];
		} elseif (!empty($ClassName)) {
			self::$Instances[$ClassName] = &new $ClassName();  //TODO Fix Deprecated
			return self::$Instances[$ClassName];
		} else {
			trigger_error('ImpSingleton::getInstance() called without a class or existing instance!', E_USER_ERROR);
		}
  }
}


function ImpSingletonAutoLoader($ClassName) {
  include_once("$ClassName.php");

	if (is_subclass_of($ClassName, 'ImpSingleton')) {
		call_user_func(array($ClassName, 'getInstance'), $ClassName);
	}
}
?>