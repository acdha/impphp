<?php
	class Document extends DBObject {
		protected $DBTable       = 'Documents';
		protected $_trackChanges = true;
		protected $Properties    = array(
			'Parent'                => array('type' => 'object', 'class' => 'Document'),
			'Title'                 => array('type' => 'string', 'formfield' => true, 'required' => true),
			'TextID'                => array('type' => 'string', 'formfield' => true),
			'Container'             => array('type' => 'string', 'formfield' => true),
			'Visible'               => 'boolean',
			'DisplayVersion'        => array('type' => 'object', 'class' => 'DocumentVersion'),
			'Created'               => 'datetime',
			'Modified'              => 'timestamp',
			'ChildSortKey'          => array('type' => 'enum', 'default' => 'Title'),
			'ChildSortOrder'        => array('type' => 'enum', 'default' => 'Ascending'),
			'ResourceSortKey'       => array('type' => 'enum', 'default' => 'Title'),
			'ResourceSortOrder'     => array('type' => 'enum', 'default' => 'Ascending')
		);

		public $Children;
		public $Versions;

		public static function &get($id = false) {
			return self::_getInstance(__CLASS__, $id);
		}

		public function __construct($id = false) {
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

		function getETag() {
			// Returns a value suitable for use as an HTTP Entity Tag
			return sha1(serialize($this->Modified . (is_object($this->DisplayVersion) ? $this->DisplayVersion->ID . $this->DisplayVersion->Modified : false)));
		}

		function getBody() {
			if (empty($this->Visible)) {
				error_log("Client {$_SERVER['REMOTE_ADDR']} requested invisible document #{$this->ID}");
				return '<p class="Notice">This document is being edited</p>';
			}

			if (!isset($this->DisplayVersion)) {
				error_log("Client {$_SERVER['REMOTE_ADDR']} requested document #{$this->ID} which has no available revision in DocumentVersions");
				return '<p class="Notice">This document is currently unavailable</p>';
			} else {
				$Version = $this->getVersion();
				if (empty($Version)) return false;
				if (!empty($Version->Deleted)) {
					trigger_error("Client {$_SERVER['REMOTE_ADDR']} requested document version #{$Version->ID} which has been deleted", E_USER_NOTICE);
					return '<p class="Notice">This document is currently unavailable</p>';
				} else {
					return $Version->Body;
				}
			}
		}

		function getVersions() {
			if (!isset($this->Versions)) {
				$this->Versions = DocumentVersion::get(self::$DB->queryValues("SELECT ID FROM DocumentVersions WHERE Document={$this->ID} AND Deleted IS NULL ORDER BY Modified"));
				uasort($this->Versions, array('DocumentVersion', 'defaultSortFunction'));
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

		function deleteVersion($Version) {
			$V = is_object($Version) ? $Version : $this->getVersion($Version);

			assert($V->Document->ID == $this->ID);
			assert($V->Deleted == 0);

			$V->Deleted = time();
			$V->save();

			if (!empty($this->DisplayVersion) and ($this->DisplayVersion->ID == $V->ID)) {
				$this->setDisplayVersion(false);
				$this->save();
			}
		}

		/**
			 * Returns an array of references to child Documents
			 */
		function getChildren($limit = false, $ShowAll = false) {
			// Limit allows us to artificially restrict children to the first $limit according to the default sort order
			if (empty($this->ID)) return;

			// Only load if we haven't done so
			if (!isset($this->Children)) {
				// TODO: Make sure we avoid calling getChildren() where possible
				// TODO: Change this to use DBObject::find() once that interface is mature
				// TODO: this needs to use the defined child sort key settings for this document
				$this->Children = Document::get(self::$DB->queryValues("SELECT ID FROM Documents WHERE Parent = $this->ID" . ($ShowAll ? "" : " AND Visible=1") . ($limit ? " LIMIT $limit" : "")));
				uasort($this->Children, array('Document', 'defaultSortFunction'));
			}

			assert(is_array($this->Children));

			return $this->Children;
		}

		function getChildByName($Name) {
			$cid = self::$DB->queryValue('SELECT ID FROM Documents WHERE Parent = ? AND Title = ?', $this->ID, $Name);
			if (empty($cid)) {
				return false;
			}

			$d = Document::get($cid);
			return $d->isVisible() ? $d : false;
		}

		/**
		 * Returns a Document object for the most recently modified child
		 */
		function getLastModifiedChild() {
			return Document::get(self::$DB->queryValue("SELECT ID FROM Documents WHERE Parent = ? AND Visible = 'True' ORDER BY Modified DESC LIMIT 1", $this->ID));
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

		static function defaultSortFunction($a, $b) {
			return strcasecmp($a->Title, $b->Title);
		}

	}
?>
