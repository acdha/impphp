<?php
	class DocumentVersion extends DBObject {
		protected $Properties = array(
			'Document'			=> array('type' => 'object'),
			'Creator'				=> array('type' => 'object', 'class' => 'User'),
			'Created'				=> 'datetime',
			'Modified'			=> 'timestamp',
			'Deleted'				=> 'datetime',
			'Approved'			=> 'boolean',
			'Comment'				=> 'string',
			'Body'					=> 'string'
		);

		protected $_trackChanges = true;
		protected $_ignoreChanges = array('Body');
		protected $DBTable = 'DocumentVersions';

		public static function &get($id = false) {
			return self::_getInstance(__CLASS__, $id);
		}

		function setProperty($name, $value) {
			if ($name == "Creator") {
				if (!empty($value)) {
					$this->Creator = User::get($value);
				} else {
					$this->Creator = false;
				}
				return true;
			} else {
				return parent::setProperty($name, $value);
			}
		}

		function save() {
			if (empty($this->Creator)) {
				$this->Creator = $_SESSION['User'];
			}

			return parent::save();
		}

		function saveAsNewVersion() {
			unset($this->ID);
			$this->Created  = time();
			$this->Modified = time();
			$this->Approved = false;
			$this->Comment  = '';
			unset($this->Creator);
		}

	}
?>