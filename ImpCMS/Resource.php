<?php
	/*
		Resources

		$Id$
		$ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $

		TODO:
			- automatic generation of Image/Video/Audio/UserDefinedResource objects
				based on data type. This allows more intelligent handling of properties:
				Image->htmlImageSrc()
				Image->htmlImageDimensions()
				Video->width()
				Audio->length()
				etc.

	 */

	class Resource {
		var $Owner; // Reference to the Document which this Resource belongs to

		function Resource(&$owner, $ID) {
			assert(is_object($owner));

			$this->Owner = $owner;

			if (!isset($ID)) {
				die("Creating new resources is currently unsupported");
			} else		{
				$this->ID = intval($ID);

				// This query should really never fail as code shouldn't be guessing at resource IDs
				// This class should really only ever be called by a Document in either getResources()
				// or newResource()

				$resource = $this->Owner->CMS->DB->query("SELECT LocalFilename, OriginalFilename, ContentHeight, ContentWidth, Title, Caption, Keywords, Copyright, UNIX_TIMESTAMP(Created) AS Created, UNIX_TIMESTAMP(Modified) AS Modified, MimeTypes.MimeType FROM Resources INNER JOIN MimeTypes ON Resources.MimeType = MimeTypes.ID WHERE Resources.ID = $ID")
					or trigger_error("Unable to retrieve resource #$ID", E_USER_ERROR);

				assert(count($resource) <= 1); // This should never return more than one record

				foreach ($resource[0] as $var => $val) {
					$this->$var = $val;
				}
			}
		}
	}
?>
