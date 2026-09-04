#!/bin/bash
set -e

echo "=== Starting Canteen Shopping Container ==="

# Check if external DB_HOST is defined (and not localhost / 127.0.0.1)
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    echo "Configured to use external MySQL host: $DB_HOST"
else
    echo "Starting local MariaDB service inside container..."
    mkdir -p /var/run/mysqld /var/lib/mysql
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
    chmod 777 /var/run/mysqld

    # Initialize data directory if not initialized
    if [ ! -d "/var/lib/mysql/mysql" ]; then
        echo "Initializing MariaDB system tables..."
        mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 || mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1
    fi

    # Start MariaDB service
    service mariadb start || /usr/bin/mysqld_safe --datadir='/var/lib/mysql' &

    # Wait for MariaDB to accept connections
    echo "Waiting for MariaDB to start..."
    for i in {1..30}; do
        if mysqladmin ping --silent 2>/dev/null; then
            echo "MariaDB is ready!"
            break
        fi
        sleep 1
    done

    # Create database and grant full permissions to root
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
    fi
fi

exec "$@"
