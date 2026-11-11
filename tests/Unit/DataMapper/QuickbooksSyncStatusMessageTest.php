<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\DataMapper;

use App\Casts\ClientSyncCast;
use App\Casts\ExpenseSyncCast;
use App\Casts\PaymentSyncCast;
use App\Casts\ProductSyncCast;
use App\DataMapper\ClientSync;
use App\DataMapper\ExpenseSync;
use App\DataMapper\PaymentSync;
use App\DataMapper\ProductSync;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuickbooksSyncStatusMessageTest extends TestCase
{
    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function syncCastProvider(): array
    {
        return [
            'client' => [ClientSyncCast::class, ClientSync::class],
            'product' => [ProductSyncCast::class, ProductSync::class],
            'payment' => [PaymentSyncCast::class, PaymentSync::class],
            'expense' => [ExpenseSyncCast::class, ExpenseSync::class],
        ];
    }

    #[DataProvider('syncCastProvider')]
    public function testStatusMessageRoundTripsThroughSyncCast(string $cast_class, string $sync_class): void
    {
        $cast = new $cast_class();
        $model = new class () extends Model {};
        $sync = new $sync_class([
            'qb_id' => 'QB-123',
            'qb_status_message' => 'QuickBooks rejected DisplayName.',
        ]);

        $serialized = $cast->set($model, 'sync', $sync, []);
        $restored = $cast->get($model, 'sync', $serialized['sync'], []);

        $this->assertSame('QB-123', $restored->qb_id);
        $this->assertSame('QuickBooks rejected DisplayName.', $restored->qb_status_message);
    }

    #[DataProvider('syncCastProvider')]
    public function testLegacySyncJsonDefaultsStatusMessageToEmptyString(string $cast_class, string $sync_class): void
    {
        $cast = new $cast_class();
        $model = new class () extends Model {};

        $restored = $cast->get($model, 'sync', json_encode(['qb_id' => 'QB-123']), []);

        $this->assertInstanceOf($sync_class, $restored);
        $this->assertSame('', $restored->qb_status_message);
    }
}
