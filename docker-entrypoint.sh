#!/bin/bash
set -e

echo "=== Starting Canteen Shopping Application ==="

# 1. Adapt Apache to listen on Render's dynamic $PORT (defaults to 80 if not set)
APACHE_PORT="${PORT:-80}"
echo "Configuring Apache to listen on port ${APACHE_PORT}..."
sed -i "s/Listen [0-9]*/Listen ${APACHE_PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${APACHE_PORT}>/g" /etc/apache2/sites-available/000-default.conf

# 2. Check Database Strategy
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    echo "Using configured external database at: $DB_HOST"
else
    echo "Configuring lightweight MariaDB server inside container..."
    mkdir -p /var/run/mysqld /var/lib/mysql /etc/mysql/conf.d
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
    chmod 777 /var/run/mysqld

    # Low-memory configuration optimized for Render free tier (512MB RAM)
    cat << 'EOF' > /etc/mysql/conf.d/render-low-mem.cnf
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

    # Initialize MariaDB data directory if not present
    if [ ! -d "/var/lib/mysql/mysql" ]; then
        echo "Initializing MariaDB system tables..."
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db > /dev/null 2>&1 || mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1
    fi

    # Start MariaDB service with proper user
    echo "Starting MariaDB daemon..."
    /usr/bin/mysqld_safe --user=mysql --datadir=/var/lib/mysql > /var/log/mysql.log 2>&1 &

    # Wait for MariaDB to accept connections
    echo "Waiting for MariaDB to become ready..."
    for i in {1..35}; do
        if mysqladmin ping --silent 2>/dev/null; then
            echo "MariaDB is up and running!"
            break
        fi
        sleep 1
    done

    # Setup database and user permissions
    mysql -e "CREATE DATABASE IF NOT EXISTS \`canteen_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" 2>/dev/null || true
    mysql -e "CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY '';" 2>/dev/null || true
    mysql -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;" 2>/dev/null || true
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true

    # Import initial canteen_db schema if tables do not exist
    TABLES_EXIST=$(mysql -N -s -e "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'canteen_db';" 2>/dev/null || echo "0")
    if [ "$TABLES_EXIST" -eq "0" ] || [ -z "$TABLES_EXIST" ]; then
        if [ -f "/var/www/html/database_setup.sql" ]; then
            echo "Importing initial schema from database_setup.sql..."
            mysql canteen_db < /var/www/html/database_setup.sql 2>/dev/null || true
            echo "Database import complete!"
        fi
    else
        echo "Existing canteen_db tables detected ($TABLES_EXIST tables)."
    fi
fi

echo "Launching Apache foreground process on port ${APACHE_PORT}..."
exec "$@"
