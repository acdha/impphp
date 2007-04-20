# A Document may have attached resources. Resources are generic
# and can contain any sort of data. Site-specific code may wish
# to do special things based on the mime-type and there is some
# standard support for any image type supported by GetImageSize
#
# Thumbnails are assumed to have the same local filename in the
# thumbanil directory to prevent confusion with filenames.
#
# $Id$
# $ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
#

CREATE TABLE Resources (
	ID										INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
	DocumentID						INTEGER NOT NULL REFERENCES Documents,

	LocalFilename					VARCHAR(255),			# Relative to the defined resource root for ImpCMS
	OriginalFilename			VARCHAR(255),			# Used to give the default file name when presenting a download dialog
	MimeType							INTEGER NOT NULL REFERENCES MimeTypes,

	# This is provided to make it easier to display a popup window or
	# embedded object for this resource.
	ContentHeight					INTEGER NOT NULL DEFAULT 0,
	ContentWidth					INTEGER NOT NULL DEFAULT 0,

	Title									VARCHAR(128),
	Caption								VARCHAR(255),
	Keywords							VARCHAR(255),
	Copyright							VARCHAR(64),

	Created								DATETIME NOT NULL,
	Modified							DATETIME NOT NULL,

	INDEX(DocumentID)
) ENGINE=INNODB DEFAULT CHARSET=utf8;

