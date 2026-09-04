FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

# Install MariaDB server, client, and dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo pdo_mysql

RUN a2enmod rewrite

# Enable AllowOverride All for /var/www/html
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html/

# Initialize MariaDB and pre-load canteen_db schema during image build
RUN mkdir -p /var/run/mysqld /var/lib/mysql && \
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql && \
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql --skip-test-db > /dev/null 2>&1 && \
    /usr/sbin/mariadbd --user=mysql --datadir=/var/lib/mysql --skip-networking=0 & \
    DB_PID=$! && \
    sleep 3 && \
    mysql -e "CREATE DATABASE IF NOT EXISTS \`canteen_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" 2>/dev/null && \
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;" 2>/dev/null && \
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;" 2>/dev/null && \
    mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY '' WITH GRANT OPTION;" 2>/dev/null && \
    mysql -e "FLUSH PRIVILEGES;" 2>/dev/null && \
    mysql canteen_db < /var/www/html/database_setup.sql 2>/dev/null && \
    kill -s TERM $DB_PID && \
    wait $DB_PID 2>/dev/null || true

# Setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80 10000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
