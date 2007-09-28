<?php

	class ImpAdmin {
		private $Rights;

		public $DefaultUserID;
		public $DefaultArea;

		function _getUserID($UserID) {
			// This function contains the common code needed to sanitized and check a passed UserID, falling back to $this->DefaultUserID if it exists
			$UserID = intval($UserID);

			if (empty($UserID) and empty($this->DefaultUserID)) {
				throw new Exception('A valid UserID was not provided and no default exists');
				return false;
			}

			return empty($UserID) ? $this->DefaultUserID : $UserID;
		}

		function _getArea($Area) {
			// This function contains the common code needed to sanitized and check a passed Area, falling back to $this->DefaultArea if it exists
			if (empty($Area) and empty($this->DefaultArea)) {
				throw new Exception('A valid Area was not provided and no default exists');
				return false;
			}

			return empty($Area) ? $this->DefaultArea : $Area;
		}

		function getUserRights($UserID = false) {
			$UserID = $this->_getUserID($UserID);
			$this->loadRightsForUser($UserID);
			return $this->Rights[$UserID];
		}

		function getAdminAreas() {
			$this->loadAdminAreas();
			return $this->AdminAreas;
		}

		function checkAdminStatus($UserID = false) {
			// This is a fast, simple check to see whether a given user has admin access at all:
			$UserID = $this->_getUserID($UserID);

			if (empty($UserID)) {
				throw new Exception(__METHOD__ . "() called with an invalid user ID");
				return false;
			}

			$this->loadRightsForUser($UserID);
			return !empty($this->Rights[$UserID]);
		}

		function hasRight($Right, $Area = false, $UserID = false) {
			/*
				$Right may be:
				 	- a single value (e.g. "Create") in $Area
				 	- an array containing multiple values in $Area
				  - an array containing AreaName => Right values
			 */
			$UserID = $this->_getUserID($UserID);
			$Area = $this->_getArea($Area);

			assert(is_string($Area) and !empty($Area));
			assert(!empty($Right));

			$this->loadRightsForUser($UserID);

			if (empty($this->Rights[$UserID][$Area])) {
				return false;
			}

			$result = true;

			if (is_array($Right)) {
				foreach ($Right as $k => $v) {
					if (is_integer($k)) {
						if (!in_array($v, $this->Rights[$UserID][$Area]['Rights'])) {
							$result = false; break;
						}
					} else {
						if (empty($this->Rights[$UserID][$k]) or !in_array($v, $this->Rights[$UserID][$k]['Rights'])) {
							$result = false;	break;
						}
					}
				}
			} else {
				$result = in_array($Right, $this->Rights[$UserID][$Area]['Rights']);
			}
			return $result;
		}

		function loadRightsForUser($UserID = false) {
			global $DB;

			$UserID = $this->_getUserID($UserID);

			if (isset($this->Rights[$UserID])) {
				return; // We've already loaded this user
			}

			$this->Rights[$UserID] = array();

			$Areas = $DB->query("SELECT AreaID, Rights, Name, URL FROM AdminAreaRights INNER JOIN AdminAreas ON AreaID=AdminAreas.ID WHERE UserID = ?", $UserID);

			foreach ($Areas as $Area) {
				$Area['Rights'] = explode(',', $Area['Rights']); // Convert this from a comma-separated list to an array
				assert(!empty($Area['Rights']));
				$this->Rights[$UserID][$Area['Name']] = $Area;
			}

			ksort($this->Rights[$UserID]);
		}

		function loadAdminAreas() {
			global $DB;

			if (!empty($this->AdminAreas)) {
				return;
			}

			foreach ($DB->query("SELECT ID, Name, URL, CustomSettingsURL, AvailableRights FROM AdminAreas") as $Area) {
				assert(!isset($this->AdminAreas[$Area['Name']]));

				$Area['AvailableRights'] = explode(',', $Area['AvailableRights']); // Convert this from a comma-separated list to an array
				assert(!empty($Area['AvailableRights']));
				$this->AdminAreas[$Area['Name']] = $Area;
			}

			ksort($this->AdminAreas);
		}

		function setUserRights($UserID, $AreaName, array $Rights) {
			global $DB;

			$UserID = (integer) $UserID;
			assert(!empty($UserID));

			$this->loadAdminAreas();

			$AreaID = $this->AdminAreas[$AreaName]['ID'];
			assert(!empty($AreaID));

			$DB->execute("BEGIN");
			try {
				$DB->execute("DELETE FROM AdminAreaRights WHERE UserID=$UserID and AreaID=?", $AreaID);
				if (!empty($Rights)) {
					$DB->execute("INSERT INTO AdminAreaRights (UserID, AreaID, Rights) VALUES ($UserID, $AreaID, '" . implode(',', $Rights) . "')");
				}
				$DB->execute("COMMIT");
			} catch (Exception $e) {
				$DB->execute('ROLLBACK');
				throw $e;
			}

			if (isset($this->Rights[$UserID])) {
				unset($this->Rights[$UserID]); // Clear out the cache for this user to avoid any discrepancies
			}
		}
	}
?>