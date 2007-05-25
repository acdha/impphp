<?php
	class Document extends DBObject {
		var $Properties = array(
			'Parent'         => array('type' => 'object', 'class' => 'Document', 'lazy' => true),
			'Title'          => array('type' => 'string', 'formfield' => true),
			'TextID'         => array('type' => 'string', 'formfield' => true),
			'Container'      => array('type' => 'string', 'formfield' => true),
			'Container'      => 'string',
			'Visible'        => 'boolean',
			'DisplayVersion' => array('type' => 'object', 'class' => 'DocumentVersion', 'lazy' => true)
		);

		var $FormFields = array(
			'Title',
			'TextID',
			'Container'
		);

		var $DBTable			 = 'Documents';
		var $_trackChanges = true;
		var $Children;

		public static function &get($id = false) {
			return self::_getSingleton(__CLASS__, $id);
		}
		
		protected function __construct($id = false) {
			if (empty($id)) {
				$this->Created	= time();
				$this->Modified = time();
			}
			
			parent::__construct($id);
		}

		/**
		 * Returns true if the document should be displayed to the public
		 */
		function isVisible() {
			return $this->Visible;
		}

		function getBody() {
			global $DB;

			if (empty($this->Visible)) {
				error_log("Client {$_SERVER['REMOTE_ADDR']} requested invisible document #{$this->ID}");
				return '<p class="Notice">This document is being edited</p>';
			}

			if (!isset($this->DisplayVersion)) {
				error_log("Client {$_SERVER['REMOTE_ADDR']} requested document #{$this->ID} which has no available revision in DocumentVersions");
				return '<p class="Notice">This document is currently unavailable</p>';
			} else {
				$Version = $this->getVersion();
				if (!empty($Version->Deleted)) {
					trigger_error("Client {$_SERVER['REMOTE_ADDR']} requested document version #{$Version->ID} which has been deleted", E_USER_NOTICE);
					return '<p class="Notice">This document is currently unavailable</p>';
				} else {
					return $Version->Body;
				}
			}
		}

		function getVersions() {
			global $DB;

			if (empty($this->Versions)) {
				$this->Versions = array();
				foreach ($DB->queryValues("SELECT ID FROM DocumentVersions WHERE Document={$this->ID} AND Deleted IS NULL ORDER BY Modified") as $id) {
					$this->Versions[] = new DocumentVersion($id);
				}
			}

			return $this->Versions;
		}

		function getVersion($Version = false) {
			$Version = intval($Version);

			if (empty($Version) and isset($this->DisplayVersion)) {
				return $this->DisplayVersion;
			} elseif (!empty($Version)) {
				return DocumentVersion::get($Version);
			} else {
				return false;
			}
		}

		function setDisplayVersion($Version) {
			global $DB;
			$Version = intval($Version);
			$this->setProperty("DisplayVersion", empty($Version) ? false : $Version );
		}

		/**
			 * Returns an array of references to child Documents
			 */
		function getChildren($limit = false, $ShowAll = false) {
			// Limit allows us to artificially restrict children to the first $limit according to the default sort order

			// Only load if we haven't done so
			if (!isset($this->Children)) {
				$this->loadChildren($limit, $ShowAll);
			}

			assert(is_array($this->Children));

			return $this->Children;
		}

		/**
		 * Returns a Document object for the most recently modified child
		 */
		function getLastModifiedChild() {
			$lmcID = $DB->queryValue("SELECT ID FROM Documents WHERE Parent = {$this->ID} AND Visible = 'True' ORDER BY Modified DESC LIMIT 1");
			return Document::get($lmcID);
		}

		/**
			 * Returns an array of references to this Document's Resources
			 */
		function getResources() {
			// Only load if we haven't done so - note the use of isset() instead of empty() to avoid reloading if there's no children
			if (!isset($this->Resources)) {
				$this->loadResources();
			}

			assert(is_array($this->Resources));

			return $this->Resources;
		}


		function loadChildren($limit = false, $ShowAll = false) {
			global $DB;

			$this->Children = array();

			if (empty($this->ID))	return;

			// FIXME: Ugly - this will load *way* too much until we get a proper on-demand load going:
			// FIXME: this needs to use the defined sort key settings for this document
			// FIXME: we have a defined set of visibility rules for these things. Use them!

			$children = $DB->query("SELECT ID FROM Documents WHERE Parent = $this->ID" . ($ShowAll ? "" : " AND Visible=1") . ($limit ? " LIMIT $limit" : "") );

			foreach ($children as $child) {
				$this->Children[] = Document::get($child["ID"]); // Store a reference to avoid multiple copies floating around
			}
		}

		function loadResources() {
			// FIXME: Ugly - this will load *way* too much until we get a proper on-demand load going:
			// FIXME: this needs to use the defined sort key settings for this document

			$resources = $DB->query("SELECT ID FROM Resources WHERE DocumentID = $this->ID");

			$this->Resources = array();
			foreach ($resources as $resource) {
				$this->Resources[] = new Resource($resource["ID"]); // Store a reference to avoid multiple copies floating around
			}
		}
	}
?>
