# $Id$
# $ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
#

DELETE FROM Resources;
DELETE FROM Documents;

# Some sample documents to demonstrate hierarchy and sort order
INSERT INTO Documents
	SET ID            = 1,
		Parent           = 0,
		Owner            = 0,
		Visible          = 1,
		Created          = NOW(),
		Modified         = NOW(),
		Title            = "Welcome to ImpCMS",
		Body             = "<p>I hope you have fun and don't forget to write^H^H^H^H^Hread the documentation!</p><p>This is the body of the root document of the site</p>",
		Hash             = MD5(Body),
		Template         = NULL;

INSERT INTO Documents
	SET Parent        = 1,
		Owner            = 0,
		Visible          = 1,
		Created          = NOW(),
		Modified         = NOW(),
		Title            = "A child document",
		Body             = "<p>This is the body of a child document of the site's root document</p>",
		Hash             = MD5(Body),
		Template         = NULL;

INSERT INTO Documents
	SET Parent        = 1,
		Owner            = 0,
		Visible          = 1,
		Created          = NOW(),
		Modified         = NOW(),
		Title            = "Yet another child document",
		Body             = "<p>This is the body of a child document of the site's root document</p><p>Yes, it's identical to the last one</p>",
		Hash             = MD5(Body),
		Template         = NULL;

INSERT INTO Documents
	SET Parent        = LAST_INSERT_ID(),
		Owner            = 0,
		Visible          = 1,
		Created          = NOW(),
		Modified         = NOW(),
		Title            = "This is a grandchild!",
		Body             = "<p>This is the body of a grandchild of the site's root document</p>",
		Hash             = MD5(Body),
		Template         = NULL;

INSERT INTO Documents
	SET Parent        = 1,
		Owner            = 0,
		Visible          = 0,
		Created          = NOW(),
		Modified         = NOW(),
		Title            = "An invisible document",
		Body             = "<p>This should not be displayed outside of the admin area until you make it visible</p>",
		Hash             = MD5(Body),
		Template         = NULL;

# Two sample resources for the root document:
INSERT INTO Resources
	SET	DocumentID    = 1,
		LocalFilename    = "orchid.jpg",
		OriginalFilename = "Orchid.jpg",

		MimeType         = 5,

		ContentHeight    = 864,
		ContentWidth     = 1152,

		Title            = "Orchid",
		Caption          = "A close-up view of an orchid (Carlsbad, CA)",
		Keywords         = "orchid",

		Created          = NOW(),
		Modified         = NOW();

INSERT INTO Resources
	SET	DocumentID    = 1,
		LocalFilename    = "pip.png",
		OriginalFilename = "pip.png",

		MimeType         = 7,

		ContentHeight    = 842,
		ContentWidth     = 1089,

		Title            = "Pip",
		Caption          = "The most malevolent being known to man. This photo received 2nd place at the 1996 Del Mar Fair.",
		Keywords         = "pip cat eyes",

		Created          = NOW(),
		Modified         = NOW();
