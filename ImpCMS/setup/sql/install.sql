# Use mysql [-u user -p] database install.sql to initialize the ImpCMS
# database tables.
#
# WARNING: ALL EXISTING TABLES WILL BE REMOVED! Backup accordingly...
#
# $Id$
# $ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
#



# Support tables
source languages.sql;		# Language and character set information
source mimetypes.sql;	# Mime types used for resources

# The core tables:
source users.sql;			# Account and permission information
source documents.sql;		# Document and category support
source resources.sql;		# Resources

# Some basic content to demonstrate the ImpCMS when first installed:
source demo-content.sql;
