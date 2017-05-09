<?php

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

        Eloquent::unguard();

        $this->call('ConstantsSeeder');
        $this->call('CountriesSeeder');
        $this->call('PaymentLibrariesSeeder');
        $this->call('FontsSeeder');
        $this->call('GatewayTypesSeeder');
        $this->call('BanksSeeder');
        $this->call('InvoiceStatusSeeder');
        $this->call('PaymentStatusSeeder');
        $this->call('CurrenciesSeeder');
        $this->call('DateFormatsSeeder');
        $this->call('InvoiceDesignsSeeder');
        $this->call('PaymentTermsSeeder');
        $this->call('PaymentTypesSeeder');
        $this->call('LanguageSeeder');
        $this->call('IndustrySeeder');
        $this->call('FrequencySeeder');
        $this->call('DbServerSeeder');
    }
}
