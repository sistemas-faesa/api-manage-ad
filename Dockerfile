FROM php:8.2-cli

WORKDIR /usr/src/app

RUN apt-get update && apt-get install -y gnupg2

ENV ACCEPT_EULA=Y

RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
RUN curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list    

RUN apt-get update && apt-get install -y \
    unixodbc-dev \
    unixodbc \
    msodbcsql17

RUN pecl install sqlsrv pdo_sqlsrv
RUN docker-php-ext-enable sqlsrv pdo_sqlsrv

RUN apt-get install libzip-dev -y && \
    docker-php-ext-install zip

# install PHP LDAP support
RUN \
    apt-get update && \
    apt-get install libldap2-dev -y && \
    apt-get install libldap-common -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap

RUN rm -rf /etc/ldap/ldap.conf
COPY ldap.conf /etc/ldap

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer