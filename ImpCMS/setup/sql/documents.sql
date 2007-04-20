# $Id$
# $ProjectHeader:  $

CREATE TABLE Documents (
	ID									INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,

	# The Parent ID allows a hiearchal structure within the Documents table:
	Parent							INTEGER NULL REFERENCES Documents.ID,
	# The Container URL allows a document to be anchored under an arbitrary
	# external object. While URL-like in our usage the only real requirement is that it be
	# internally consistent.
	#
	# Example:
	#		mysql://db.example.com/Database/Table#Key
	#		x-mysite:///Something/Somewhere#Somepart
	Container						VARCHAR(255) NULL,


	# TextID will be unique across the entire database, just like ID and quite unlike Title, and can thus be used as a permanent identifier
	TextID							VARCHAR(96) NULL,

	# A simple control which prevents documents from being shown to non-admins:
	Visible							ENUM('True','False') NOT NULL DEFAULT 'False',

	Title								VARCHAR(128) NOT NULL,

	# How do we sort child Documents?
	ChildSortKey				ENUM('Created','Modified','Title')		NOT NULL DEFAULT 'Modified',
	ChildSortOrder			ENUM('Ascending', 'Descending')			NOT NULL DEFAULT 'Descending',

	# How do we sort this Document's Resources?
	ResourceSortKey			ENUM('Title', 'Type', 'Position')		NOT NULL DEFAULT 'Title',
	ResourceSortOrder		ENUM('Ascending', 'Descending')			NOT NULL DEFAULT 'Ascending',

	# Used to ameliorate the performance hit for using permanent text ids instead of
	# numeric IDs
	UNIQUE(TextID),
	INDEX(ContainerURL),
	INDEX(Parent)
) ENGINE=INNODB DEFAULT CHARSET=utf8;

CREATE TABLE DocumentVersions (
	ID									INTEGER									NOT NULL AUTO_INCREMENT PRIMARY KEY,
	Document						INTEGER									NOT NULL REFERENCES Documents.ID,

	Creator							INTEGER									NOT NULL REFERENCES Users.ID,

	Created							DATETIME								NOT NULL DEFAULT NOW(),
	Modified						TIMESTAMP								NOT NULL DEFAULT CURRENT_TIMESTAMP,
	Deleted							DATETIME								NULL,

	Comment							TEXT										NOT NULL,

	Approved						ENUM('True','False')		NOT NULL DEFAULT 'False',

	# Automatic display windowing:
	StartDisplay				DATETIME								NULL,
	EndDisplay					DATETIME								NULL,

	Body								TEXT										NOT NULL
) ENGINE=INNODB DEFAULT CHARSET=utf8;
