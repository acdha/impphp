<?php
	class DocumentVersion extends DBObject {
		var $Properties = array(
			'Document'			=> array('type' => 'object', 'lazy' => true),
			'Creator'				=> array('type' => 'object', 'class' => 'User', 'lazy' => true),
			'Created'				=> 'datetime',
			'Modified'			=> 'timestamp',
			'Deleted'				=> 'datetime',
			'Approved'			=> 'boolean',
			'Comment'				=> 'string',
			'Body'					=> 'string'
		);

		protected $_trackChanges = true;
		protected $_ignoreChanges = array('Body');
		var $DBTable = 'DocumentVersions';

		public static function &get($id = false) {
			return self::_getSingleton(__CLASS__, $id);
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
				$this->Creator = $_SESSION['User']->ID;
			}

			return parent::save();
		}

		function saveAsNewVersion() {
			unset($this->ID);
			$this->Approved = false;
			$this->Created = time();
			$this->Modified = time();
			$this->Comment = '';
		}

	}
?>