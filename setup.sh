#!/bin/bash

#remove non-heartland gateways
echo "removing gateways..."

for f in app/Ninja/PaymentDrivers/*.php
do
    file=$(basename $f)
    
    if [ "$file" != "HeartlandPaymentDriver.php" ] && [ "$file" != "BasePaymentDriver.php" ]
    then
        rm -f "app/Ninja/PaymentDrivers/$file"
    fi
done