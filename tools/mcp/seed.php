<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local', 'testing') || config('database.connections.mysql.database') !== 'ninja_mcp_test') {
    throw new RuntimeException('This seeder only runs against the dedicated local MCP test database.');
}
$builder = new Tests\Fixtures\Mcp\Fixture();
$fixture = \Illuminate\Support\Facades\DB::transaction(fn () => $builder->create());
file_put_contents(base_path('.local/fixture.json'), json_encode($builder->export($fixture), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents(base_path('.local/catalog.json'), json_encode(app(App\Mcp\Catalog::class)->operations(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents(base_path('.local/api-manifest.json'), json_encode(app(App\Mcp\Catalog::class)->inventory(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Synthetic fixture and catalog written to ignored .local files.'.PHP_EOL;
