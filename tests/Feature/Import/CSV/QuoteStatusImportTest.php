<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Import\CSV;

use App\Import\Transformer\Csv\QuoteTransformer;
use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

class QuoteStatusImportTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->makeTestData();
    }

    public function testQuoteCsvStatusMapIncludesCancelledAndRejected(): void
    {
        $transformer = new QuoteTransformer($this->company);

        $cases = [
            'draft' => Quote::STATUS_DRAFT,
            'sent' => Quote::STATUS_SENT,
            'approved' => Quote::STATUS_APPROVED,
            'converted' => Quote::STATUS_CONVERTED,
            'rejected' => Quote::STATUS_REJECTED,
            'cancelled' => Quote::STATUS_CANCELLED,
            'Cancelled' => Quote::STATUS_CANCELLED,
        ];

        foreach ($cases as $status => $expected) {
            $transformed = $transformer->transform([
                'quote.number' => 'import-quote-status-' . strtolower((string) $status) . '-' . uniqid(),
                'quote.status' => $status,
                'quote.amount' => 10,
                'client.name' => $this->client->name,
            ]);

            $this->assertSame(
                $expected,
                $transformed['status_id'],
                "Expected {$status} to map to status_id {$expected}"
            );
        }
    }

    public function testUnknownQuoteStatusDefaultsToSent(): void
    {
        $transformer = new QuoteTransformer($this->company);

        $transformed = $transformer->transform([
            'quote.number' => 'import-quote-status-unknown-' . uniqid(),
            'quote.status' => 'bogus',
            'quote.amount' => 10,
            'client.name' => $this->client->name,
        ]);

        $this->assertSame(Quote::STATUS_SENT, $transformed['status_id']);
    }
}
