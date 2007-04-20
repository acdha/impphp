# This is used for two purposes:
# - Maintaining a list of "acceptable" Mime Types (presumably those for
#   which we want special treatment beyond the generic
#   application/octet-stream
#
# - Storing the URI for the icon used when displaying links to something
#
# $Id$
# $ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
#

CREATE TABLE MimeTypes (
  ID												INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
  MimeType									VARCHAR(255),		# e.g. "application/x-wonder-app"
  ShortDesc									VARCHAR(32),	# e.g. "WonderApp File"
  DefaultThumbnailURI				VARCHAR(255),			# e.g. "/resources/thumbnails/wonder-app-icon.gif"
  KEY TypeKey (MimeType)
) ENGINE=INNODB DEFAULT CHARSET=utf8;

# Text
# ID 1 is special - HTML is our native tongue and should always be present; very odd things could happen otherwise...
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('text/html', 'HTML', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('text/xhtml', 'XHTML', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('text/xml', 'XML', '/resources/defaultthumbnails/generic.gif');

# Images
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/gif', 'GIF Image', '/resources/defaultthumbnails/image.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/jpeg', 'JPEG Image', '/resources/defaultthumbnails/image.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/pict', 'PICT Image', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/png', 'PNG Image', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/svg', 'Scalable Vector Graphics', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/svg-xml', 'Scalable Vector Graphics', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/x-photoshop', 'Photoshop Image', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/x-pict', 'PICT Image', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/x-png', 'PNG Image', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('image/x-quicktime', 'QuickTime Image', '/resources/defaultthumbnails/generic.gif');

# Audio
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('audio/midi', 'MIDI', '/resources/defaultthumbnails/sound.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('audio/wav', 'WAV Audio', '/resources/defaultthumbnails/sound.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('audio/x-pn-realaudio-plugin', 'RealPlayer', '/resources/defaultthumbnails/generic.gif');

# Video
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('video/x-ms-wvv', 'Windows Media', '/resources/defaultthumbnails/movie.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('video/x-ms-wmv', 'Windows Media', '/resources/defaultthumbnails/movie.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('video/x-ms-wax', 'Windows Media', '/resources/defaultthumbnails/movie.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('video/x-ms-wma', 'Windows Media', '/resources/defaultthumbnails/movie.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('video/x-ms-wm', 'Windows Media', '/resources/defaultthumbnails/movie.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('application/asx', 'Windows Media', '/resources/defaultthumbnails/movie.gif');

# Other webish things:
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('application/x-shockwave-flash', 'Shockwave Flash', '/resources/defaultthumbnails/generic.gif');
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('application/x-director', 'Shockwave for Director', '/resources/defaultthumbnails/generic.gif');

INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('application/x-httpd-php-source', 'PHP Source Code', '/resources/defaultthumbnails/source.gif');

# Generic data
INSERT INTO MimeTypes (MimeType, ShortDesc, DefaultThumbnailURI) VALUES ('application/octet-stream', '', '/resources/defaultthumbnails/generic.gif');
