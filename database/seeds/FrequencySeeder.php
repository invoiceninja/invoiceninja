<?php

use App\Models\Frequency;

class FrequencySeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $frequencies = [
            ['name' => 'Weekly'],
            ['name' => 'Two weeks'],
            ['name' => 'Four weeks'],
            ['name' => 'Monthly'],
            ['name' => 'Two months'],
            ['name' => 'Three months'],
            ['name' => 'Six months'],
            ['name' => 'Annually'],
        ];

        foreach ($frequencies as $frequency) {
            $record = Frequency::whereName($frequency['name'])->first();
            if ($record) {
                //$record->save();
            } else {
                Frequency::create($frequency);
            }
        }
    }
}
