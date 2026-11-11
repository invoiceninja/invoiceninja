<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents(base_path('.local/catalog.json'), json_encode(app(App\Mcp\Catalog::class)->operations(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents(base_path('.local/api-manifest.json'), json_encode(app(App\Mcp\Catalog::class)->inventory(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'API inventory and tool catalog written.'.PHP_EOL;
