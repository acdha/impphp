<?
	/**
	 * ImpCMS Configuration
	 *
	 * $Id$
	 * $ProjectHeader: $
	 *
	 **/

	// Database connection information
	define('IMPCMS_DB_SERVER',						'UNCONFIGURED_IMPCMS_DB_SERVER');
	define('IMPCMS_DB_NAME',							'UNCONFIGURED_IMPCMS_DB_NAME');

	// Account used if ImpCMS->EnableAdminAccess() has been called
	define('IMPCMS_DB_ADMIN',							'UNCONFIGURED_IMPCMS_DB_ADMIN_ACCOUNT');
	define('IMPCMS_DB_ADMIN_PASSWORD',		'UNCONFIGURED_IMPCMS_DB_ADMIN_ACCOUNT_PASSWORD');

	// Normal, unprivileged account used otherwise
	define('IMPCMS_DB_USER',							'UNCONFIGURED_IMPCMS_DB_USER_ACCOUNT');
	define('IMPCMS_DB_USER_PASSWORD',			'UNCONFIGURED_IMPCMS_DB_USER_ACCOUNT_PASSWORD');

	/*
	 * Resources are stored in a directory using the ID as the filename
	 * To construct a link, we use something like IMPCMS_RESOURCE_ROOT . $Resource->ID
	 * Normal user code should just use $Resource->LocalFilename.
	 */
	define('IMPCMS_RESOURCE_ROOT',				'UNCONFIGURED_IMPCMS_RESOURCE_ROOT');

	// No debugging info:
	define('IMP_DEBUG', false);

	// Used by XML-RPC interface:
	define("IMPCMS_USERAGENT", "ImpCMS (See http://improbable.org/chris/Software/ImpCMS)");

	// Use the bundled error handler, which tries to be like the PHP error handler
	// and offers improved debugging output. This can be disabled, with the caveat
	// that you will want to double-check your php.ini log settings (good idea!)
	set_error_handler("ImpErrorHandler");

	// This class allows you to hook the CMS's handling of key events. Override
	// a method to run your code. See EventDispatcher.php for details.
	class UserEventDispatcher extends EventDispatcher {
		function PreEnableAdminAccess() {
			return false;
		}
	}
?>
