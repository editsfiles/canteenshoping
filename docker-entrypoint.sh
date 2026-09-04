#!/bin/bash
set -e

echo "=== Starting Canteen Shopping Container ==="

APACHE_PORT="${PORT:-80}"
echo "Configuring Apache to listen on port ${APACHE_PORT}..."
sed -i "s/Listen [0-9]*/Listen ${APACHE_PORT}/g" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${APACHE_PORT}>/g" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true

DB_NAME="${DB_NAME:-canteen_db}"
DB_USER="${DB_USER:-canteen_user}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    echo "Using configured external database at: $DB_HOST"
else
    echo "Configuring MariaDB service inside container..."
    mkdir -p /var/run/mysqld /var/lib/mysql /var/log/mysql /etc/mysql/conf.d
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql /var/log/mysql
    chmod 777 /var/run/mysqld

    cat << 'EOF' > /etc/mysql/conf.d/render.cnf
[mysqld]
performance_schema = OFF
innodb_buffer_pool_size = 32M
innodb_log_buffer_size = 1M
innodb_stats_on_metadata = OFF
key_buffer_size = 8M
max_connections = 50
table_open_cache = 100
query_cache_size = 0
bind-address = 127.0.0.1
EOF

    if [ ! -d "/var/lib/mysql/mysql" ]; then
        echo "Initializing MariaDB system tables..."
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db >/dev/null 2>&1
    fi

    echo "Starting MariaDB daemon..."
    /usr/sbin/mariadbd --user=mysql --datadir=/var/lib/mysql > /var/log/mysql.log 2>&1 &

    echo "Waiting for MariaDB..."
    READY=0
    for i in $(seq 1 40); do
        if mysqladmin ping --silent 2>/dev/null; then
            READY=1
            break
        fi
        sleep 1
    done
    if [ "$READY" != "1" ]; then
        echo "ERROR: MariaDB failed to start"
        cat /var/log/mysql.log || true
        exit 1
    fi
    echo "MariaDB is ready!"

    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

    # Create the PHP application user even when its password is empty.
    # This avoids relying on MariaDB root unix_socket authentication.
    mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD'; ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1'; CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

    TABLES_EXIST=$(mysql -N -s -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")
    if [ "$TABLES_EXIST" = "0" ]; then
        if [ -f "/var/www/html/database_setup.sql" ]; then
            echo "Importing database_setup.sql..."
            mysql "$DB_NAME" < /var/www/html/database_setup.sql
            echo "Database import complete!"
        fi
    else
        echo "$DB_NAME already contains $TABLES_EXIST tables."
    fi
fi

echo "Launching Apache on port ${APACHE_PORT}..."
exec "$@"
