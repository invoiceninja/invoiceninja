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

namespace Tests\Unit\Services\Quickbooks;

use App\Services\Quickbooks\QuickbooksFaultParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class QuickbooksFaultParserTest extends TestCase
{
    private QuickbooksFaultParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new QuickbooksFaultParser();
    }

    public function testParsesIntuitValidationFault(): void
    {
        $failure = <<<'ERROR'
Request is not made successful. Response Code:[400] with body: [<?xml version="1.0" encoding="UTF-8" standalone="yes"?><IntuitResponse xmlns="http://schema.intuit.com/finance/v3" time="2026-07-29T07:50:17.736-07:00"><Fault type="ValidationFault"><Error code="2040" element="DisplayName"><Message>Invalid String. The String may contain unsupported or illegal chars</Message><Detail>Element contains invalid characters. Regencium: Physical Therapy and Performance - West Greater Houston (C)</Detail></Error></Fault></IntuitResponse>].
ERROR;

        $fault = $this->parser->parse(new RuntimeException($failure));

        $this->assertSame(400, $fault->http_status);
        $this->assertSame('ValidationFault', $fault->fault_type);
        $this->assertSame('2040', $fault->errors[0]['code']);
        $this->assertSame('DisplayName', $fault->errors[0]['element']);
        $this->assertSame(
            'QuickBooks rejected DisplayName while creating the customer (error 2040): Invalid String. The String may contain unsupported or illegal chars Element contains invalid characters. Regencium: Physical Therapy and Performance - West Greater Houston (C)',
            $fault->humanMessage('creating the customer')
        );
        $this->assertSame(
            'Customer DisplayName contains characters QuickBooks does not support (QB 2040). Edit the name and retry.',
            $fault->statusMessage('creating the customer')
        );
    }

    public function testParsesMultipleIntuitErrors(): void
    {
        $failure = <<<'ERROR'
Response Code:[400] with body: [<IntuitResponse xmlns="http://schema.intuit.com/finance/v3"><Fault type="ValidationFault"><Error code="100" element="DocNumber"><Message>Required</Message><Detail>DocNumber is required.</Detail></Error><Error code="200" element="Line"><Message>Invalid line</Message><Detail>A line is invalid.</Detail></Error></Fault></IntuitResponse>].
ERROR;

        $fault = $this->parser->parse($failure);

        $this->assertCount(2, $fault->errors);
        $this->assertStringContainsString('QuickBooks rejected DocNumber (error 100)', $fault->humanMessage());
        $this->assertStringContainsString('QuickBooks rejected Line (error 200)', $fault->humanMessage());
    }

    public function testFallsBackForPlainExceptionMessages(): void
    {
        $message = $this->parser->humanMessage(new RuntimeException('Connection timed out'), 'updating the invoice');

        $this->assertSame(
            'QuickBooks request failed while updating the invoice: Connection timed out',
            $message
        );
    }

    public function testFallsBackWhenXmlIsMalformed(): void
    {
        $failure = 'Request is not made successful. Response Code:[400] with body: [<IntuitResponse><Fault>]';

        $fault = $this->parser->parse($failure);

        $this->assertSame(400, $fault->http_status);
        $this->assertSame('Response Code:[400]', $fault->fallback_message);
    }

    public function testProvidesConciseGuidanceForCommonQuickbooksCodes(): void
    {
        $stale = '<IntuitResponse><Fault type="ValidationFault"><Error code="5010"><Message>Stale Object</Message></Error></Fault></IntuitResponse>';
        $closed = '<IntuitResponse><Fault type="ValidationFault"><Error code="6210"><Message>Account Period Closed</Message></Error></Fault></IntuitResponse>';

        $this->assertSame(
            'The QuickBooks record changed before this update completed (QB 5010). Refresh it and retry.',
            $this->parser->statusMessage($stale)
        );
        $this->assertSame(
            'QuickBooks has closed this accounting period (QB 6210). Reopen the period or make the change in QuickBooks.',
            $this->parser->statusMessage($closed)
        );
        $this->assertSame(
            'QuickBooks is temporarily rate limiting requests. Please retry shortly.',
            $this->parser->statusMessage(new RuntimeException('Too many requests', 429))
        );
    }

    public function testStatusMessageIsCappedAt255Characters(): void
    {
        $message = $this->parser->statusMessage(new RuntimeException(str_repeat('failure ', 100)));

        $this->assertLessThanOrEqual(255, mb_strlen($message));
    }
}
