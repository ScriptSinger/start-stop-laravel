#!/bin/bash
set -e

echo "Creating legacy database and importing old project dump..."

mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS legacy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p"$MYSQL_ROOT_PASSWORD" legacy < /legacy-dump.sql
mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON legacy.* TO '$MYSQL_USER'@'%';"

echo "Legacy database ready."
