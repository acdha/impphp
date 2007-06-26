<?php
class __CLASS__ extends DBObject {
	protected $DBTable;
	protected $Properties     = array(
		'Name'                   => 'string',
		'Subject'                => array('type' => 'string', 'formfield' => true, 'required' => true),
		'Parent'                 => array('type' => 'object', 'class' => __CLASS__),
		'Created'                => array('type' => 'datetime', 'formfield' => true),
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
?>