<?php
	class __CLASS__ extends DBObject {
		protected $DBTable;
		protected $Properties     = array(
			// TODO: Add the real properties for this class
			'Name'                   => 'string',
			'Subject'                => array('type' => 'string', 'formfield' => true, 'required' => true),
			'Parent'                 => array('type' => 'object', 'class' => __CLASS__),
			'Created'                => array('type' => 'datetime', 'formfield' => true),
		);

		// PHP5's inheritance model means that static functions cannot use __CLASS__ (it will be the parent) so we have these stubs:
		public static function &get($id = false) 									{ return self::_getInstance(__CLASS__, $id); }
		public static function find(array $constraints = array()) {	return self::_find(__CLASS__, $constraints); }
	}
?>