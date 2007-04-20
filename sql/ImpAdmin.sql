CREATE TABLE AdminAreas (
	ID											INTEGER					NOT NULL AUTO_INCREMENT PRIMARY KEY,
	Name										VARCHAR(64)			NOT NULL,
	URL											VARCHAR(255)		NULL,				# We place this directly into the HREF, allowing off-site links
	CustomSettingsURL				VARCHAR(255)		NULL,				# If not null, this URL leads to a configuration section for this admin area
	AvailableRights					SET('View', 'Create', 'Modify', 'Delete', 'Approve') NOT NULL
) ENGINE=INNODB DEFAULT CHARSET=utf8;

CREATE TABLE AdminAreaRights (
	AreaID									INTEGER NOT NULL REFERENCES AdminAreas.ID,
	UserID									INTEGER NOT NULL REFERENCES Users.ID,
	Rights									SET('View', 'Create', 'Modify', 'Delete', 'Approve') NOT NULL
) ENGINE=INNODB DEFAULT CHARSET=utf8;