<?php

use App\Models\Industry;

class IndustrySeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $industries = [
            ['name' => 'Accounting & Legal'],
            ['name' => 'Advertising'],
            ['name' => 'Aerospace'],
            ['name' => 'Agriculture'],
            ['name' => 'Automotive'],
            ['name' => 'Banking & Finance'],
            ['name' => 'Biotechnology'],
            ['name' => 'Broadcasting'],
            ['name' => 'Business Services'],
            ['name' => 'Commodities & Chemicals'],
            ['name' => 'Communications'],
            ['name' => 'Computers & Hightech'],
            ['name' => 'Defense'],
            ['name' => 'Energy'],
            ['name' => 'Entertainment'],
            ['name' => 'Government'],
            ['name' => 'Healthcare & Life Sciences'],
            ['name' => 'Insurance'],
            ['name' => 'Manufacturing'],
            ['name' => 'Marketing'],
            ['name' => 'Media'],
            ['name' => 'Nonprofit & Higher Ed'],
            ['name' => 'Pharmaceuticals'],
            ['name' => 'Professional Services & Consulting'],
            ['name' => 'Real Estate'],
            ['name' => 'Retail & Wholesale'],
            ['name' => 'Sports'],
            ['name' => 'Transportation'],
            ['name' => 'Travel & Luxury'],
            ['name' => 'Other'],
            ['name' => 'Photography'],
            ['name' => 'Construction'],
        ];

        foreach ($industries as $industry) {
            $record = Industry::whereName($industry['name'])->first();
            if (! $record) {
                Industry::create($industry);
            }
        }

        Eloquent::reguard();
    }
}
