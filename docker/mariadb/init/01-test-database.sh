#!/bin/sh
set -eu

mariadb --protocol=socket -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`${TEST_DATABASE}\`;
GRANT ALL PRIVILEGES ON \`${TEST_DATABASE}\`.* TO '${MARIADB_USER}'@'%';
SQL

echo "init: created ${TEST_DATABASE}"
