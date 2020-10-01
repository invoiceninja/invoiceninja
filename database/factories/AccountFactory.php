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

$factory->define(App\Models\Account::class, function (Faker $faker) {
    return [
        'default_company_id' => 1,
        'key' => Str::random(32),
        'report_errors' => 1,
    ];
});
