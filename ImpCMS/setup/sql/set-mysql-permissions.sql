# Note - this file must be preprocessed.
# Best to let the setup script to do this for you...
#
# $Id$
# $ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
#


# Admin user has full "classic sql" privileges. No fancy stuff like local file i/o.
GRANT SELECT,INSERT,DELETE,UPDATE ON UNCONFIGURED_IMPCMS_DB_NAME.*				TO UNCONFIGURED_IMPCMS_DB_ADMIN_ACCOUNT@localhost IDENTIFIED BY 'UNCONFIGURED_IMPCMS_DB_ADMIN_ACCOUNT_PASSWORD';

# The account used by normal (= read-only) access has a very limited set of privileges:
GRANT SELECT ON UNCONFIGURED_IMPCMS_DB_NAME.*								TO UNCONFIGURED_IMPCMS_DB_USER_ACCOUNT@localhost IDENTIFIED BY 'UNCONFIGURED_IMPCMS_DB_USER_ACCOUNT_PASSWORD';

# Disabled until we find a compelling reason to add this in:
# Used to cache generated pages and content retrieved from remote servers:
# GRANT SELECT,INSERT,UPDATE,DELETE ON UNCONFIGURED_IMPCMS_DB_NAME.PHPCache	TO UNCONFIGURED_IMPCMS_DB_USER_ACCOUNT@localhost IDENTIFIED BY 'UNCONFIGURED_IMPCMS_DB_USER_ACCOUNT_PASSWORD';
