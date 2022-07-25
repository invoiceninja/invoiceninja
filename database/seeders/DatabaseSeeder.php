<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Seeders;

use App\Models\Timezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Running DatabaseSeeder');

        if (Timezone::count()) {
            $this->command->info('Skipping: already run');

            return;
        }

        Model::unguard();

        $this->call([
            ConstantsSeeder::class,
            PaymentLibrariesSeeder::class,
            BanksSeeder::class,
            CurrenciesSeeder::class,
            LanguageSeeder::class,
            CountriesSeeder::class,
            IndustrySeeder::class,
            PaymentTypesSeeder::class,
            GatewayTypesSeeder::class,
            DateFormatsSeeder::class,
            DesignSeeder::class,
        ]);
    }
}
