<?php

use \AcceptanceTester;
use Faker\Factory;

class ChartsAndReportsCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function _after(AcceptanceTester $I)
    {

    }

    // tests
    public function updateChartsAndReportsPage(AcceptanceTester $I)
    {   

        $faker = Faker\Factory::create();

        $I->wantTo('Run the report');

		$I->amOnPage('/company/advanced_settings/charts_and_reports');
 
        /*
        $format = 'M d,Y';
        $start_date =  date ( $format, strtotime ( '-7 day' . $format)); 
        $I->fillField(['name' => 'start_date'],$start_date);
        $I->fillField(['name' => 'start_date'], 'April 15, 2015');
        $I->fillField(['name' => 'end_date'], date('M d,Y'));
        $I->fillField(['name' => 'end_date'], 'August 29, 2015');
        */

        $I->checkOption(['name' => 'enable_report']);
        $I->selectOption("#report_type", 'Client');
        $I->checkOption(['name' => 'enable_chart']);

        $rand = ['DAYOFYEAR', 'WEEK', 'MONTH'];
        $I->selectOption("#group_by", $rand[array_rand($rand)]);

        $rand = ['Bar', 'Line'];
        $I->selectOption("#chart_type", $rand[array_rand($rand)]);

        $I->click('Run');
        $I->see('Start Date');
    }

    /*
    public function showDataVisualization(AcceptanceTester $I) {

        $I->wantTo('Display pdf data');
        $I->amOnPage('/company/advanced_settings/data_visualizations');
        
        $optionTest = "1";      // This is the option to test!
        $I->selectOption('#groupBySelect', $optionTest);
        $models = ['Client', 'Invoice', 'Product'];

        //$all = Helper::getRandom($models[array_rand($models)], 'all');
        $all = Helper::getRandom('Client', 'all');
        $labels = $this->getLabels($optionTest);

        $all_items = true;
        $I->seeElement('div.svg-div svg g:nth-child(2)');

        for ($i = 0; $i < count($labels); $i++) {
            $text = $I->grabTextFrom('div.svg-div svg g:nth-child('.($i+2).') text');
            //$I->seeInField('div.svg-div svg g:nth-child('.($i+2).') text', $labels[$i]);
            if (!in_array($text, $labels)) {
                $all_items = false;
                break;
            }
        }

        if (!$all_items) {
            $I->see('Fail', 'Fail');
        }
    }

    private function getLabels($option) {

        $invoices = \App\Models\Invoice::where('user_id', '1')->get();
        $clients = [];

        foreach ($invoices as $invoice) {
            $clients[] = \App\Models\Client::where('public_id', $invoice->client_id)->get();
        }

        $clientNames = [];
        foreach ($clients as $client) {
            $clientNames[] = $client[0]->name;
        }

        return $clientNames;
    }
    */
}