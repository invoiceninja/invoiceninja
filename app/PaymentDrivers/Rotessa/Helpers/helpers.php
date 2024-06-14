<?php

include app_path("PaymentDrivers/Rotessa/vendor/autoload.php");

class_alias("App\\PaymentDrivers\\Rotessa\\PaymentMethod","App\\PaymentDrivers\\Rotessa\\BankTransfer");
class_alias("App\\PaymentDrivers\\Rotessa\\PaymentMethod","App\\PaymentDrivers\\Rotessa\\DirectDebit");
class_alias("App\\PaymentDrivers\\Rotessa\\PaymentMethod","App\\PaymentDrivers\\Rotessa\\Acss");
class_alias("App\\PaymentDrivers\\Rotessa\\PaymentMethod","App\\PaymentDrivers\\Rotessa\\Bacs");