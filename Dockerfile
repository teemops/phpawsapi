FROM php

RUN apt-get update
RUN apt-get install git unzip -y
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');    \
    \$sig = file_get_contents('https://composer.github.io/installer.sig');      \
    if (trim(\$sig) === hash_file('SHA384', 'composer-setup.php')) exit(0);     \
    echo 'ERROR: Invalid installer signature' . PHP_EOL;                        \
    unlink('composer-setup.php');                                               \
    exit(1);"                                                                   \
 && php composer-setup.php -- --filename=composer --install-dir=/usr/local/bin  \
 && rm composer-setup.php
RUN git clone https://github.com/teemops/phpawsapi.git
WORKDIR /phpawsapi
RUN /usr/local/bin/composer install

WORKDIR /phpawsapi/public
EXPOSE 8001
CMD ["php", "-S", "0.0.0.0:8081"]
