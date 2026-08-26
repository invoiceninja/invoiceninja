<?php

namespace Tests\Unit\Http\Middleware;

use App\DataMapper\Analytics\DbQuery;
use App\Http\Middleware\QueryLogging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Turbo124\Beacon\Facades\LightLogs;

class QueryLoggingTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::disableQueryLog();
        DB::flushQueryLog();

        parent::tearDown();
    }

    public function testItScopesTheQueryLogToTheRequestAndClearsItAfterTermination(): void
    {
        config([
            'ninja.environment' => 'hosted',
            'beacon.enabled' => true,
        ]);

        DB::enableQueryLog();
        DB::select('select 1');

        $this->assertCount(1, DB::getQueryLog());

        $request = Request::create('/api/v1/test', 'GET');
        $middleware = new QueryLogging();
        $response = $middleware->handle($request, function (): Response {
            DB::select('select 1');

            return new Response();
        });

        $this->assertTrue(DB::connection()->logging());
        $this->assertCount(1, DB::getQueryLog());

        $batch = Mockery::mock();
        $batch->shouldReceive('batch')->once();

        LightLogs::shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (DbQuery $metric): bool {
                $this->assertSame(1, $metric->int_metric1);

                return true;
            }))
            ->andReturn($batch);

        $middleware->terminate($request, $response);

        $this->assertFalse(DB::connection()->logging());
        $this->assertSame([], DB::getQueryLog());
    }
}
