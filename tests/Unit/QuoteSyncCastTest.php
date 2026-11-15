<?php

namespace Tests\Unit;

use App\Casts\InvoiceSyncCast;
use App\Casts\QuoteSyncCast;
use App\DataMapper\QuoteSync;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\PurchaseOrder;
use App\Enum\InvoiceQbStatus;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class QuoteSyncCastTest extends TestCase
{
    public static function documentModels(): array
    {
        return [[Invoice::class], [Quote::class], [PurchaseOrder::class]];
    }

    #[DataProvider('documentModels')]
    public function testDirtyFlagDefaultsToFalseAndSurvivesCasting(string $model_class): void
    {
        $model = new $model_class();
        $model->setRawAttributes(['sync' => '{"qb_id":"QB-123"}'], true);

        $this->assertFalse($model->sync->dn_dirty);

        $model->sync->dn_dirty = true;
        $stored = $model->getAttributes()['sync'];
        $model->setRawAttributes(['sync' => $stored], true);

        $this->assertTrue($model->sync->dn_dirty);
        $this->assertSame('QB-123', $model->sync->qb_id);
    }

    public function test_it_round_trips_quickbooks_status_fields(): void
    {
        $cast = new QuoteSyncCast();
        $model = new class () extends Model {};

        $sync = new QuoteSync(
            qb_id: 'QB-QUOTE-1',
            qb_status: InvoiceQbStatus::Linkable->value,
            qb_sync_token: '3',
            qb_status_message: 'Possible QuickBooks match found.',
        );

        $serialized = $cast->set($model, 'sync', $sync, []);
        $restored = $cast->get($model, 'sync', $serialized['sync'], []);

        $this->assertInstanceOf(QuoteSync::class, $restored);
        $this->assertSame('QB-QUOTE-1', $restored->qb_id);
        $this->assertSame(InvoiceQbStatus::Linkable->value, $restored->qb_status);
        $this->assertSame('3', $restored->qb_sync_token);
        $this->assertSame('Possible QuickBooks match found.', $restored->qb_status_message);
    }

    public function test_invoice_sync_cast_can_serialize_quote_sync(): void
    {
        $cast = new InvoiceSyncCast();
        $model = new class () extends Model {};

        $sync = new QuoteSync(
            qb_id: 'QB-QUOTE-2',
            dn_completed: true,
            qb_status: InvoiceQbStatus::Synced->value,
            qb_sync_token: '7',
            qb_status_message: '',
        );

        $serialized = $cast->set($model, 'sync', $sync, []);
        $payload = json_decode($serialized['sync'], true);

        $this->assertSame('QB-QUOTE-2', $payload['qb_id']);
        $this->assertTrue($payload['dn_completed']);
        $this->assertSame(InvoiceQbStatus::Synced->value, $payload['qb_status']);
        $this->assertSame('7', $payload['qb_sync_token']);
        $this->assertSame('', $payload['qb_status_message']);
    }
}
