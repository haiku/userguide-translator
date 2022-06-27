# Userguide application container

This is a custom container that contains php-apache with pdo and pdo_pgsql
modules installed.

## Using

This container requires the following environment variables defined:

* BASE_DOMAIN - base domain to run /userguide on. (ex: https://i18n.haiku-os.org)

* DB_SERVER - Database server hostname
* DB_USERNAME - Database server username
* DB_PASSWORD - Database server password
* DB_BASENAME - Database name

* EXPORT_DIR - Directory for exports
* IMPORT_DIR - Directory for imports

* REF_DIR - Directory to store historical changes to userguides
