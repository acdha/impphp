<?php
	/*
		PermissionManager

		$Id$
		$ProjectHeader: $

		The methods in this class should be overriden by the UserPermissionManager subclass
		in impcms-config.php according to your security policy.

		At various points, permissions will be checked by calling these methods:

		if ($ImpCMS->Permissions->ModifyDocument($Document->ID)) {
			... do something ...
		} else {
			die("You can't do that!");
		}
	 */

	 class PermissionManager {
			function PermissionManager(&$CMS) {
				$this->CMS = $CMS;
			}

		function CreateDocument($Parent = false) {
			return false;
		}

		function ModifyDocument($ID) {
			return false;
		}

		function ApproveDocument($ID) {
			return false;
		}

		function DeleteDocument($ID) {
			return false;
		}


	 }
?>
