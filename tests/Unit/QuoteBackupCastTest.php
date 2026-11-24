<?php

namespace Tests\Unit;

use App\DataMapper\QuoteBackup;
use App\Factory\CloneQuoteFactory;
use App\Factory\CloneQuoteToInvoiceFactory;
use App\Models\Company;
use App\Models\Quote;
use InvalidArgumentException;
use JsonException;
use Tests\TestCase;

class QuoteBackupCastTest extends TestCase
{
    public function testNullBackupRemainsNull(): void
    {
        $quote = new Quote();
        $quote->setRawAttributes(['backup' => null], true);

        $this->assertNull($quote->backup);
        $this->assertNull($quote->attributesToArray()['backup']);

        $quote->backup = new QuoteBackup();
        $quote->backup = null;

        $this->assertNull($quote->getAttributes()['backup']);
    }

    public function testRulesAndOrderedStepsSurviveHydrationAndMutation(): void
    {
        $data = [
            'schema_version' => 1,
            'rules' => [
                'deposit' => ['required' => true, 'amount' => 200],
                'optional' => ['enabled' => true],
                'acceptance' => ['require_signature' => true],
                'payment' => ['required' => false],
            ],
            'steps' => ['optional', 'acceptance', 'payment'],
            'guid' => '',
        ];
        $quote = new Quote();
        $quote->setRawAttributes(['backup' => json_encode($data, JSON_THROW_ON_ERROR)], true);

        $this->assertInstanceOf(QuoteBackup::class, $quote->backup);
        $this->assertSame($data, $quote->attributesToArray()['backup']);
        $this->assertSame($data, json_decode(json_encode($quote->backup, JSON_THROW_ON_ERROR), true));
        $this->assertFalse($quote->isDirty('backup'));

        $quote->backup->rules['deposit']['amount'] = 300;
        $quote->backup->guid = 'document-guid';

        $this->assertTrue($quote->isDirty('backup'));
        $stored_data = json_decode($quote->getAttributes()['backup'], true);
        $this->assertSame(300, $stored_data['rules']['deposit']['amount']);
        $this->assertSame($data['steps'], $stored_data['steps']);
        $this->assertSame('document-guid', $stored_data['guid']);
    }

    public function testAcceptsArrayObjectAndJsonInput(): void
    {
        $data = [
            'schema_version' => 1,
            'rules' => ['deposit' => ['amount' => 200]],
            'steps' => ['deposit', 'acceptance'],
            'guid' => 'document-guid',
        ];
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        foreach ([$data, json_decode($json), $json, QuoteBackup::fromArray($data)] as $value) {
            $quote = new Quote();
            $quote->backup = $value;

            $this->assertSame($data, json_decode($quote->getAttributes()['backup'], true));
        }
    }

    public function testHydratesExistingGuidWithoutWorkflowConfiguration(): void
    {
        $quote = new Quote();
        $quote->setRawAttributes(['backup' => '{"guid":"legacy-guid"}']);

        $this->assertSame('legacy-guid', $quote->backup->guid);

        $quote->backup->steps = ['acceptance'];

        $this->assertSame([
            'schema_version' => 1,
            'rules' => [],
            'steps' => ['acceptance'],
            'guid' => 'legacy-guid',
        ], json_decode($quote->getAttributes()['backup'], true));
    }

    public function testQuoteCloneCopiesOnlyConfigurationIntoAnIndependentObject(): void
    {
        $quote = new Quote();
        $quote->setRawAttributes([
            'amount' => 1000,
            'line_items' => '[]',
            'backup' => '{"schema_version":1,"rules":{"deposit":{"amount":200}},"steps":["acceptance"],"guid":"source-guid"}',
        ]);
        $quote->backup->option_selection = [
            'state' => QuoteBackup::STATE_PENDING_ACCEPTANCE,
            'line_items' => [['product_key' => 'Original optional work', 'is_optional' => true]],
            'partial' => 200,
            'selected_options' => [],
            'contact_id' => 1,
            'confirmed_at' => '2026-11-09T00:00:00+00:00',
            'revision' => 'confirmed-scope',
        ];

        $clone_quote = CloneQuoteFactory::create($quote, 1);

        $this->assertNotSame($quote->backup, $clone_quote->backup);
        $this->assertSame([
            'schema_version' => 1,
            'rules' => ['deposit' => ['amount' => 200]],
            'steps' => ['acceptance'],
            'guid' => '',
        ], $clone_quote->backup->toArray());

        $clone_quote->backup->rules['deposit']['amount'] = 300;

        $this->assertSame(200, $quote->backup->rules['deposit']['amount']);
        $this->assertSame('source-guid', $quote->backup->guid);
        $this->assertNull($clone_quote->backup->option_selection);
    }

    public function testConfirmedOptionSelectionSurvivesHydration(): void
    {
        $selection = [
            'state' => QuoteBackup::STATE_PENDING_ACCEPTANCE,
            'line_items' => [['product_key' => 'Chimney repairs', 'is_optional' => true]],
            'partial' => 200,
            'selected_options' => [],
            'contact_id' => 1,
            'confirmed_at' => '2026-11-09T00:00:00+00:00',
            'revision' => 'confirmed-scope',
        ];
        $quote = new Quote();
        $quote->backup = new QuoteBackup(guid: 'reserved-guid', option_selection: $selection);

        $restored_quote = new Quote();
        $restored_quote->setRawAttributes($quote->getAttributes(), true);

        $this->assertTrue($restored_quote->hasOptionSelection());
        $this->assertSame($selection, $restored_quote->backup->option_selection);
        $this->assertSame('reserved-guid', $restored_quote->backup->guid);
    }

    public function testLegacySelectionRemainsReadableAndQuoteStatusControlsResumption(): void
    {
        $quote = new Quote();
        $quote->backup = new QuoteBackup(option_selection: ['confirmed_at' => '2026-11-09T00:00:00+00:00']);
        $quote->status_id = Quote::STATUS_SENT;

        $this->assertTrue($quote->hasPendingOptionSelection());

        $quote->status_id = Quote::STATUS_REJECTED;
        $this->assertFalse($quote->hasPendingOptionSelection());
        $this->assertTrue($quote->hasOptionSelection());

        $quote->status_id = Quote::STATUS_SENT;
        $quote->backup->option_selection['state'] = QuoteBackup::STATE_ACCEPTED;
        $this->assertFalse($quote->hasPendingOptionSelection());
    }

    public function testRejectsMalformedJsonWithoutReplacingItWithDefaults(): void
    {
        $quote = new Quote();
        $quote->setRawAttributes(['backup' => '{invalid']);

        $this->expectException(JsonException::class);

        $quote->backup;
    }

    public function testQuoteConfigurationIsNotCopiedIntoInvoiceBackup(): void
    {
        $quote = new Quote();
        $quote->backup = new QuoteBackup(rules: ['deposit' => ['amount' => 200]]);
        $quote->setRelation('company', new Company());

        $invoice = CloneQuoteToInvoiceFactory::create($quote, 1);

        $this->assertArrayNotHasKey('backup', $invoice->getAttributes());
        $this->assertSame(200, $quote->backup->rules['deposit']['amount']);
    }

    public function testRejectsScalarPayloads(): void
    {
        $quote = new Quote();

        $this->expectException(InvalidArgumentException::class);

        $quote->backup = 'false';
    }
}
