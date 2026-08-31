FROM richarvey/nginx-php-fpm:3.1.6

COPY . /var/www/html
COPY conf/nginx/nginx-site.conf /etc/nginx/sites-available/default.conf

ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1

CMD ["/start.sh"]