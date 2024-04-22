FROM matomo:5-apache

ENV CUSTOM_TRANSLATIONS_VERSION 5.0.0-b1
RUN apt-get update \
  && apt-get -y install git wget \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# dev setup - see https://developer.matomo.org/guides/getting-started-part-1
RUN cd /var/www \
  && git clone https://github.com/matomo-org/matomo matomo \
  && cd matomo \
  && git submodule update --init

RUN cd /var/www/matomo \
  && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
  && php composer-setup.php \
  && php -r "unlink('composer-setup.php');" \
  && php composer.phar install

# install Custom Translations plugin dependency
RUN cd /var/www/matomo/plugins \
  && wget https://github.com/innocraft/plugin-CustomTranslations/archive/refs/tags/${CUSTOM_TRANSLATIONS_VERSION}.tar.gz \
  && tar xvfz ${CUSTOM_TRANSLATIONS_VERSION}.tar.gz \
  && mv plugin-CustomTranslations-${CUSTOM_TRANSLATIONS_VERSION} CustomTranslations

#RUN docker-php-ext-install gd zip mysqli

# fix permissions
#RUN mkdir -p /var/www/html/plugins \
#  && chown -R 33:1000 /var/www/html/plugins

WORKDIR /var/www/html

