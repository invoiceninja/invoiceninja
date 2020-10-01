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
namespace Database\Seeders;


use App\Models\Timezone;
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

        Eloquent::unguard();

        $this->call('ConstantsSeeder');
        $this->call('PaymentLibrariesSeeder');
        $this->call('BanksSeeder');
        $this->call('CurrenciesSeeder');
        $this->call('LanguageSeeder');
        $this->call('CountriesSeeder');
        $this->call('IndustrySeeder');
        //$this->call('PaymentTermsSeeder');
        $this->call('PaymentTypesSeeder');
        $this->call('GatewayTypesSeeder');
        $this->call('DateFormatsSeeder');
        $this->call('DesignSeeder');
    }
}
