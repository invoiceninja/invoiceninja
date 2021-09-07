#!/bin/bash
n=1
TYPE=${!n}

if [ -z "$TYPE" ]; then
    TYPE="all"
fi

echo "$ RUNNING: '$TYPE'"

echo "$ php artisan optimize"
php artisan optimize

echo "=========================================="

GENERIC_TESTS=`find tests/Browser/ClientPortal/ -maxdepth 1  -type f -name '*.php'`

if [ $TYPE == 'gateways' ]; then
    GENERIC_TESTS=""

    echo "$ Skippping generic tests."
    echo "=========================================="
fi

for TEST_CLASS in $GENERIC_TESTS; do
    echo "Test class: $TEST_CLASS"

    echo "$ php artisan migrate:fresh --seed"
    php artisan migrate:fresh --seed &> /dev/null

    echo "$ php artisan ninja:create-single-account"
    php artisan ninja:create-single-account &> /dev/null

    echo "$ php artisan dusk $TEST_CLASS"
    php -d memory_limit=1G artisan dusk ${@:2} --stop-on-error --stop-on-failure $TEST_CLASS || exit 1

    echo "=========================================="
done || exit 1

GATEWAY_TESTS=`find tests/Browser/ClientPortal/Gateways/ -type f -name '*.php'`

if [ $TYPE == 'generic' ]; then
    GATEWAY_TESTS=""

    echo "$ Skippping gateway tests."
    echo "=========================================="
fi

for TEST_CLASS in $GATEWAY_TESTS; do
    echo "Test class: $TEST_CLASS"

    echo "$ php artisan migrate:fresh --seed"
    php artisan migrate:fresh --seed &> /dev/null

    echo "$ php artisan ninja:create-single-account"
    php artisan ninja:create-single-account &> /dev/null

    echo "$ php artisan dusk $TEST_CLASS"
    php -d memory_limit=1G artisan dusk ${@:2} --stop-on-error --stop-on-failure $TEST_CLASS || exit 1

    echo "=========================================="
done || exit 1

echo 'All tests completed successfully.'
