#!/bin/bash

echo "$ php artisan optimize"
php artisan optimize

GENERIC_TESTS=`find tests/Browser/ClientPortal/ -maxdepth 1  -type f -name '*.php'`

for TEST_CLASS in $GENERIC_TESTS; do
    echo "Test class: $TEST_CLASS"

    echo "$ php artisan migrate:fresh --seed"
    php artisan migrate:fresh --seed &> /dev/null

    echo "$ php artisan ninja:create-single-account"
    php artisan ninja:create-single-account &> /dev/null

    echo "$ php artisan dusk $TEST_CLASS"
    php artisan dusk --stop-on-failure $TEST_CLASS

    echo "=========================================="
done || exit 1

GATEWAY_TESTS=`find tests/Browser/ClientPortal/Gateways/ -type f -name '*.php'`

for TEST_CLASS in $GATEWAY_TESTS; do
    echo "Test class: $TEST_CLASS"

    echo "$ php artisan migrate:fresh --seed"
    php artisan migrate:fresh --seed &> /dev/null

    echo "$ php artisan ninja:create-single-account"
    php artisan ninja:create-single-account &> /dev/null

    echo "$ php artisan dusk $TEST_CLASS"
    php artisan dusk --stop-on-failure $TEST_CLASS

    echo "=========================================="
done || exit 1

echo 'All tests completed successfully.'
