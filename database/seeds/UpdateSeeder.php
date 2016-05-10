<?php

class UpdateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Running UpdateSeeder...');

        $this->call('PaymentLibrariesSeeder');
        $this->call('FontsSeeder');
        $this->call('BanksSeeder');
        $this->call('InvoiceStatusSeeder');
        $this->call('CurrenciesSeeder');
        $this->call('DateFormatsSeeder');
        $this->call('InvoiceDesignsSeeder');
        $this->call('PaymentTermsSeeder');
        $this->call('LanguageSeeder');
    }
}
