<?php
	/**
	 * EventDispatcher - provides a flexible way of handling user extensions to ImpCMS
	 *
	 *			$Id$
	 *		$ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
	 *
	 * This class should never be called directly; rather, ImpCMS should instantiate a subclass
	 * with the appropriate methods overwritten.
	 *
	 * The general convention is that ImpCMS will call a method at certain points. The subclass
	 * should modify the methods to do anything special. A method will return true if the
	 * operation should continue and false to abort it. Parameters will be passed by reference
	 * to allow modification where appropriate
	 */


	class EventDispatcher {
		function EventDispatcher(&$ImpCMS) {
			assert(is_object($ImpCMS));

			$this->ImpCMS = $ImpCMS;
		}

		// Document events
		function PreCreateDocument() {
			return true;
		}

		function PostCreateDocument() {
			return true;
		}

		function PreModifyDocument() {
			return true;
		}

		function PostModifyDocument() {
			return true;
		}

		function PreDeleteDocument() {
			return true;
		}

		function PostDeleteDocument() {
			return true;
		}

		function PreAddResource() {
			return true;
		}

		function PostAddResource() {
			return true;
		}


		// Resource events
		function PreCreateResource() {
			return true;
		}

		function PostCreateResource() {
			return true;
		}

		function PreSaveResource() {
			return true;
		}

		function PostSaveResource() {
			return true;
		}

		function PreDeleteResource() {
			return true;
		}

		function PostDeleteResource() {
			return true;
		}

		// Other events
		function PreEnableAdminAccess() {
			return true;
		}

		function PostEnableAdminAccess() {
			return true;
		}

	}

?>
