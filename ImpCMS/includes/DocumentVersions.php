<?php
	class DocumentVersion extends DBObject {
		var $Properties = array(
			'Document'			=> 'object',
			'Creator'				=> 'object',
			'Created'				=> 'datetime',
			'Modified'			=> 'timestamp',
			'Deleted'				=> 'datetime',
			'Approved'			=> 'boolean',
			'Comment'				=> 'string',
			'Body'					=> 'string'
		);

		var $_trackChanges = true;
		var $_ignoreChanges = array('Body');

		var $DBTable = 'DocumentVersions';

		function setProperty($name, $value) {
			if ($name == "Creator") {
				if (!empty($value)) {
					$this->Creator = new User($value);
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