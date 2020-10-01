<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Database\Factories;


use Faker\Generator as Faker;
use Illuminate\Support\Str;

$factory->define(App\Models\QuoteInvitation::class, function (Faker $faker) {
    return [
        'key' => Str::random(40),
    ];
});
