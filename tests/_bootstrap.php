<?php

// This is global bootstrap for autoloading
use Codeception\Util\Fixtures;

Fixtures::add('url', 'http://localhost');
Fixtures::add('username', 'user@example.com');
Fixtures::add('password', 'password');

Fixtures::add('api_secret', 'password');
Fixtures::add('stripe_secret_key', 'sk_test_g888H1K4efDxHKj7fSFTBGgU');
Fixtures::add('stripe_publishable_key', '');
