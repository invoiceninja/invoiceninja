<?php

use App\Models\Frequency;

class FrequencySeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $frequencies = [
            ['name' => 'Weekly', 'date_interval' => '1 week'],
            ['name' => 'Two weeks', 'date_interval' => '2 weeks'],
            ['name' => 'Four weeks', 'date_interval' => '4 weeks'],
            ['name' => 'Monthly', 'date_interval' => '1 month'],
            ['name' => 'Two months', 'date_interval' => '2 months'],
            ['name' => 'Three months', 'date_interval' => '3 months'],
            ['name' => 'Four months', 'date_interval' => '4 months'],
            ['name' => 'Six months', 'date_interval' => '6 months'],
            ['name' => 'Annually', 'date_interval' => '1 year'],
            ['name' => 'Two years', 'date_interval' => '2 years'],
        ];

        foreach ($frequencies as $frequency) {
            $record = Frequency::whereName($frequency['name'])->first();
            if ($record) {
                $record->date_interval = $frequency['date_interval'];
                $record->save();
            } else {
                Frequency::create($frequency);
            }
        }
    }
}
