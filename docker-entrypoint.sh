#!/bin/bash

echo "=== Starting Canteen Shopping Container ==="

APACHE_PORT="${PORT:-80}"
echo "Configuring Apache to listen on port ${APACHE_PORT}..."
sed -i "s/Listen [0-9]*/Listen ${APACHE_PORT}/g" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${APACHE_PORT}>/g" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true

DB_NAME="${DB_NAME:-canteen_db}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
    echo "Using configured external database at: $DB_HOST"
else
    echo "Configuring MariaDB service inside container..."
    mkdir -p /run/mysqld /var/run/mysqld /var/lib/mysql /var/log/mysql /etc/mysql/conf.d
    chown -R mysql:mysql /run/mysqld /var/run/mysqld /var/lib/mysql /var/log/mysql
    chmod 777 /run/mysqld /var/run/mysqld

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

    echo "Starting MariaDB daemon..."
    /usr/sbin/mariadbd --user=mysql --datadir=/var/lib/mysql > /var/log/mysql.log 2>&1 &

    echo "Waiting for MariaDB to accept connections..."
    READY=0
    for i in $(seq 1 35); do
        if mysqladmin ping --silent 2>/dev/null; then
            READY=1
            echo "MariaDB is ready!"
            break
        fi
        sleep 1
    done

    # Setup database and user permissions for root, canteen_user, and configured DB_USER
    APP_PASS="${DB_PASSWORD:-canteen_pass}"
    mysql << 'EOSQL' || true
CREATE DATABASE IF NOT EXISTS `canteen_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Allow root full access without password
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY '' WITH GRANT OPTION;
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');
ALTER USER 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('');

-- Grant canteen_user full access with canteen_pass
CREATE USER IF NOT EXISTS 'canteen_user'@'localhost' IDENTIFIED BY 'canteen_pass';
ALTER USER 'canteen_user'@'localhost' IDENTIFIED BY 'canteen_pass';
GRANT ALL PRIVILEGES ON *.* TO 'canteen_user'@'localhost' WITH GRANT OPTION;

CREATE USER IF NOT EXISTS 'canteen_user'@'127.0.0.1' IDENTIFIED BY 'canteen_pass';
ALTER USER 'canteen_user'@'127.0.0.1' IDENTIFIED BY 'canteen_pass';
GRANT ALL PRIVILEGES ON *.* TO 'canteen_user'@'127.0.0.1' WITH GRANT OPTION;

CREATE USER IF NOT EXISTS 'canteen_user'@'%' IDENTIFIED BY 'canteen_pass';
ALTER USER 'canteen_user'@'%' IDENTIFIED BY 'canteen_pass';
GRANT ALL PRIVILEGES ON *.* TO 'canteen_user'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;
EOSQL

    if [ -n "$DB_PASSWORD" ] && [ "$DB_PASSWORD" != "canteen_pass" ]; then
        mysql -e "
            ALTER USER 'canteen_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
            ALTER USER 'canteen_user'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
            ALTER USER 'canteen_user'@'%' IDENTIFIED BY '$DB_PASSWORD';
            FLUSH PRIVILEGES;
        " || true
    fi

    if [ -n "$DB_USER" ] && [ "$DB_USER" != "root" ] && [ "$DB_USER" != "canteen_user" ]; then
        mysql -e "
            CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
            ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
            GRANT ALL PRIVILEGES ON *.* TO '$DB_USER'@'localhost' WITH GRANT OPTION;
            CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
            ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
            GRANT ALL PRIVILEGES ON *.* TO '$DB_USER'@'127.0.0.1' WITH GRANT OPTION;
            CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASSWORD';
            ALTER USER '$DB_USER'@'%' IDENTIFIED BY '$DB_PASSWORD';
            GRANT ALL PRIVILEGES ON *.* TO '$DB_USER'@'%' WITH GRANT OPTION;
            FLUSH PRIVILEGES;
        " || true
    fi

    TABLES_EXIST=$(mysql -N -s -e "SELECT count(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>/dev/null || echo "0")
    if [ "$TABLES_EXIST" = "0" ] || [ -z "$TABLES_EXIST" ]; then
        if [ -f "/var/www/html/database_setup.sql" ]; then
            echo "Importing initial database schema from database_setup.sql..."
            mysql "$DB_NAME" < /var/www/html/database_setup.sql || true
            echo "Database import complete!"
        fi
    else
        echo "$DB_NAME already contains $TABLES_EXIST tables."
    fi
fi

echo "Launching Apache on port ${APACHE_PORT}..."
exec "$@"
