FROM php:8.2-cli

RUN docker-php-ext-enable opcache \
 && { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=1'; \
      echo 'opcache.memory_consumption=64'; \
      echo 'opcache.interned_strings_buffer=8'; \
      echo 'opcache.max_accelerated_files=2000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/ticky-opcache.ini

WORKDIR /app

COPY . .

EXPOSE 10000


ENV PHP_CLI_SERVER_WORKERS=4

CMD ["php", \
     "-d", "opcache.enable_cli=1", \
     "-S", "0.0.0.0:10000", \
     "index.php"]
