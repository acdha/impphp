<?php
	/*
			ImpCMS - Improbable Content Management

			Copyright 2002, Chris Adams <chris@improbable.org>

			THANKS:
				Matt, Jim and Kevin for putting up with my [many]
				mistakes and providing so much useful feedback over the
				years

				The PHP, Apache, MySQL developers for producing such a
				comfortable working environment

				Marc Liyanage for providing OS X PHP packages and saving
				me the time I would otherwise have squandered building
				php --with-kitchen-sink

			$Id$
			$ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
	*/

	require_once("ImpUtils/Utilities.php");
	require_once("ImpUtils/DB_MySQL.php");
	require_once("ImpUtils/DBObject.php");
	require_once("ImpUtils/ImpSQLBuilder.php");
	require_once('ImpUtils/ImpSingleton.php');

	require_once("ImpUtils/ImpCMS/includes/ImpCMS-base.php");
	require_once("ImpUtils/ImpCMS/includes/Document.php");
	require_once("ImpUtils/ImpCMS/includes/DocumentVersions.php");
	require_once("ImpUtils/ImpCMS/includes/EventDispatcher.php");
	require_once("ImpUtils/ImpCMS/includes/PermissionManager.php");

	// Site-specific configuration and generally the only file the user needs to edit
	require_once("impcms-config.php");
	
	$ImpCMS = &ImpCMS::getInstance('ImpCMS') or trigger_error("Couldn't create ImpCMS object!", E_USER_ERROR);
?>
