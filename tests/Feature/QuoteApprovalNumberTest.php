<?php

namespace Tests\Feature;

use App\Events\Quote\QuoteWasApproved;
use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class QuoteApprovalNumberTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        $settings = $this->client->settings;
        $settings->counter_number_applied = 'when_sent';
        $settings->auto_convert_quote = false;
        $this->client->settings = $settings;
        $this->client->save();
    }

    public static function approvalMethods(): array
    {
        return [
            'approval' => ['approve'],
            'approval without conversion' => ['approveWithNoCoversion'],
        ];
    }

    #[DataProvider('approvalMethods')]
    public function testDirectDraftApprovalPersistsNumberBeforeApprovalEvent(string $method): void
    {
        $quote = $this->draftQuote();
        $quote->service()->applyNumber()->save();
        $this->assertEmpty($quote->fresh()->number);

        // Capture the value during dispatch: the event retains a mutable model.
        $number_at_approval = null;
        Event::forget(QuoteWasApproved::class);
        Event::listen(QuoteWasApproved::class, function ($event) use (&$number_at_approval) {
            $number_at_approval = $event->quote->fresh()->number;
        });
        $quote->service()->{$method}($this->client->contacts()->first())->save();

        $this->assertEquals(Quote::STATUS_APPROVED, $quote->fresh()->status_id);
        $this->assertNotEmpty($quote->fresh()->number, 'Direct draft approval must persist a quote number.');
        $this->assertSame($quote->fresh()->number, $number_at_approval);
    }

    public function testApplyNumberNumbersApprovedQuoteWhenSent(): void
    {
        $quote = $this->draftQuote();
        $quote->service()->setStatus(Quote::STATUS_APPROVED)->applyNumber()->save();

        $this->assertNotEmpty($quote->fresh()->number);
    }

    public function testApplyNumberLeavesDraftUnnumberedWhenSent(): void
    {
        $quote = $this->draftQuote();
        $quote->service()->applyNumber()->save();

        $this->assertEmpty($quote->fresh()->number);
    }

    public function testApplyNumberStillNumbersSentQuote(): void
    {
        $quote = $this->draftQuote();
        $quote->service()->markSent()->save();

        $this->assertNotEmpty($quote->fresh()->number);
    }

    #[DataProvider('approvalMethods')]
    public function testApprovalPreservesExistingQuoteNumber(string $method): void
    {
        Event::fake([QuoteWasApproved::class]);
        $quote = $this->draftQuote();
        $quote->number = 'CUSTOM-QUOTE-123';
        $quote->saveQuietly();

        $quote->service()->{$method}($this->client->contacts()->first())->applyNumber()->save();

        $this->assertSame('CUSTOM-QUOTE-123', $quote->fresh()->number);
    }

    private function draftQuote(): Quote
    {
        return Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => Quote::STATUS_DRAFT,
            'number' => null,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);
    }
}
