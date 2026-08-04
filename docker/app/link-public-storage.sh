#!/bin/sh -eu

public_storage=/var/www/html/public/storage
persistent_storage=/var/www/html/storage/app/public

mkdir -p "$persistent_storage"

if [ -f "$public_storage/.htaccess" ] && [ ! -f "$persistent_storage/.htaccess" ]; then
    cp "$public_storage/.htaccess" "$persistent_storage/.htaccess"
fi

rm -rf "$public_storage"
ln -s ../storage/app/public "$public_storage"
