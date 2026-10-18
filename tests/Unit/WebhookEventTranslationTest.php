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

namespace Tests\Unit;

use App\Models\Webhook;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookEventTranslationTest extends TestCase
{
    #[Test]
    public function testEventTranslationKeyReturnsExpectedKeys(): void
    {
        $this->assertSame('create_client', Webhook::eventTranslationKey(Webhook::EVENT_CREATE_CLIENT));
        $this->assertSame('sent_invoice', Webhook::eventTranslationKey(Webhook::EVENT_SENT_INVOICE));
        $this->assertSame('accept_purchase_order', Webhook::eventTranslationKey(Webhook::EVENT_ACCEPTED_PURCHASE_ORDER));
        $this->assertSame('create_project', Webhook::eventTranslationKey(Webhook::EVENT_PROJECT_CREATE));
        $this->assertNull(Webhook::eventTranslationKey(999));
    }

    #[Test]
    public function testEventLabelReturnsTranslatedString(): void
    {
        $this->assertSame('Create Client', Webhook::eventLabel(Webhook::EVENT_CREATE_CLIENT));
        $this->assertSame('Accept Purchase Order', Webhook::eventLabel(Webhook::EVENT_ACCEPTED_PURCHASE_ORDER));
        $this->assertSame('', Webhook::eventLabel(999));
    }

    #[Test]
    public function testAllValidEventsHaveTranslationKeys(): void
    {
        foreach (Webhook::$valid_events as $event_id) {
            $this->assertArrayHasKey(
                $event_id,
                Webhook::$event_translation_keys,
                "Missing translation key for webhook event {$event_id}"
            );
        }
    }

    #[Test]
    public function testInstanceGetEventLabel(): void
    {
        $webhook = new Webhook();
        $webhook->event_id = Webhook::EVENT_REMIND_QUOTE;

        $this->assertSame('Remind Quote', $webhook->getEventLabel());
    }
}
