#!/bin/bash

echo "Configuring application at $WEBROOT/userguide..."
CONFIG_FILE=$WEBROOT/userguide/inc/config.php

cp $WEBROOT/userguide/inc/config-template.php $CONFIG_FILE
chown nginx:nginx $CONFIG_FILE
for i in BASE_DOMAIN DB_SERVER DB_USERNAME DB_PASSWORD DB_BASENAME IMPORT_DIR EXPORT_DIR REF_DIR ; do
	if [ -z "${!i}" ]; then
		echo "$i wasn't passed as an environment variable!"
	fi
	sed -i "s@%%$i%%@${!i}@g" $CONFIG_FILE
done

# Lock it down
chmod 440 $CONFIG_FILE
