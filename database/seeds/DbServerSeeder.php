<?php

use App\Models\DbServer;

class DbServerSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $servers = [
            ['name' => 'db-ninja-1'],
            ['name' => 'db-ninja-2'],
        ];

        foreach ($servers as $server) {
            $record = DbServer::where('name', '=', $server['name'])->first();

            if ($record) {
                // do nothing
            } else {
                DbServer::create($server);
            }
        }
    }
}
