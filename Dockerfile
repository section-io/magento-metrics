FROM php:7.1

RUN apt-get update && apt-get install --assume-yes git curl zip

RUN curl -OL https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar

RUN git clone https://github.com/magento/marketplace-eqp.git

RUN php phpcs.phar --config-set installed_paths marketplace-eqp/