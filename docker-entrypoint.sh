#!/bin/bash

echo "=== Starting Canteen Shopping Container ==="

# 1. Adapt Apache to Render's dynamic $PORT
APACHE_PORT="${PORT:-80}"
echo "Configuring Apache to listen on port ${APACHE_PORT}..."
sed -i "s/Listen [0-9]*/Listen ${APACHE_PORT}/g" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${APACHE_PORT}>/g" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true

# 2. Database configuration
DB_NAME="${DB_NAME:-canteen_db}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    echo "Using configured external database at: $DB_HOST"
else
    echo "Configuring lightweight MariaDB service inside container..."

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
bind-address = 0.0.0.0
EOF

    if [ ! -d "/var/lib/mysql/mysql" ]; then
        echo "Initializing MariaDB system tables..."
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db >/dev/null 2>&1 || mysql_install_db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1 || true
    fi

    # Launch daemon directly so it doesn't fail on init.d in Docker
    echo "Starting mariadbd daemon directly..."
    /usr/sbin/mariadbd --user=mysql --datadir=/var/lib/mysql > /var/log/mysql.log 2>&1 &

    echo "Waiting for MariaDB to accept connections..."
    READY=0
    for i in $(seq 1 30); do
        if mysqladmin ping --silent 2>/dev/null; then
            READY=1
            echo "MariaDB is up and ready!"
            break
        fi
        sleep 1
    done

    # Setup database and user permissions
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY '' WITH GRANT OPTION;" 2>/dev/null || true

    if [ -n "$DB_USER" ] && [ "$DB_USER" != "root" ]; then
        mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1'; CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null || true
    fi
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    # Import schema if database is empty
    TABLES_EXIST=$(mysql -N -s -e "SELECT count(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null || echo "0")
    if [ "$TABLES_EXIST" = "0" ] || [ -z "$TABLES_EXIST" ]; then
        if [ -f "/var/www/html/database_setup.sql" ]; then
            echo "Importing initial canteen_db tables from database_setup.sql..."
            mysql "$DB_NAME" < /var/www/html/database_setup.sql 2>/dev/null || true
            echo "Database import complete!"
        fi
    else
        echo "$DB_NAME tables found ($TABLES_EXIST tables)."
    fi
fi

echo "Launching Apache foreground process on port ${APACHE_PORT}..."
exec "$@"
