<?php

// Evidence checks for the SDK scenarios against the real fixture database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'ninja_mcp_test') {
    throw new RuntimeException('Not the fixture database.');
}
$fixture = json_decode(file_get_contents(base_path('.local/fixture.json')), true, flags: JSON_THROW_ON_ERROR);
$company = $fixture['companies']['alpha'];
$checks = [];
$checks['client_update'] = \App\Models\Client::where('company_id', $company)->where('name', 'MCP E2E Updated')->exists();
$task = \App\Models\Task::where('company_id', $company)->where('description', 'MCP E2E Tracked')->first();
$checks['time_tracking'] = $task !== null && $task->calcDuration() === 7200.0;
$checks['offline_refund'] = \App\Models\Payment::where('company_id', $company)->where('refunded', '>', 0)->exists();
$checks['activity_note'] = \App\Models\Activity::where('company_id', $company)->where('notes', 'MCP E2E Note')->exists();
file_put_contents(base_path('.local/db-effects.json'), json_encode($checks, JSON_PRETTY_PRINT));
echo json_encode($checks, JSON_PRETTY_PRINT).PHP_EOL;
if (in_array(false, $checks, true)) {
    exit(1);
}
