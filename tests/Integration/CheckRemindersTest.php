<?php

namespace Tests\Integration;

use App\Models\Invoice;
use App\Utils\Traits\MakesReminders;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\MakesReminder
 */
class CheckRemindersTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use MakesReminders;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function test_after_invoice_date_reminder()
    {
        $this->invoice->date = now();
        $this->invoice->due_date = Carbon::now()->addDays(30);

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 7;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 1;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->markSent();
        $this->invoice->setReminder($settings);

        $inv = Invoice::find($this->invoice->id);

        $this->assertEquals($inv->next_send_date, Carbon::now()->addDays(7));
    }

    public function test_no_reminders_sent_to_paid_invoices()
    {
        $this->invoice->date = now();
        $this->invoice->due_date = Carbon::now()->addDays(30);

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 7;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 1;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->markSent();
        $this->invoice->setStatus(Invoice::STATUS_PAID);
        $this->invoice->setReminder($settings);

        $inv = Invoice::find($this->invoice->id);

        $this->assertEquals($inv->next_send_date, null);
    }

    public function test_before_due_date_reminder()
    {
        $this->invoice->date = now();
        $this->invoice->due_date = Carbon::now()->addDays(30);

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 50;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 29;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->markSent();
        $this->invoice->setReminder($settings);

        $inv = Invoice::find($this->invoice->id);

        $this->assertEquals($inv->next_send_date, Carbon::parse($this->invoice->due_date)->subDays(29));
    }

    public function test_after_due_date_reminder()
    {
        $this->invoice->date = now();
        $this->invoice->due_date = Carbon::now()->addDays(30);

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 50;
        $settings->enable_reminder2 = false;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 50;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->markSent();
        $this->invoice->setReminder($settings);

        $inv = Invoice::find($this->invoice->id);

        $this->assertEquals($inv->next_send_date, Carbon::parse($this->invoice->due_date)->addDays(1));
    }

    public function test_turning_off_reminders()
    {
        $this->invoice->date = now();
        $this->invoice->due_date = Carbon::now()->addDays(30);

        $settings = $this->company->settings;
        $settings->enable_reminder1 = false;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 50;
        $settings->enable_reminder2 = false;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 50;
        $settings->enable_reminder3 = false;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->markSent();
        $this->invoice->setReminder($settings);

        $inv = Invoice::find($this->invoice->id);

        $this->assertEquals($inv->next_send_date, null);
    }
}