FROM invoiceninja/invoiceninja:4.4.3

COPY ./app /var/www/app/app
COPY ./resources /var/www/app/resources
COPY ./storage /var/www/app/storage

