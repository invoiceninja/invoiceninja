<?php

namespace Tests\Unit\EDocument;

use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveSchemeResolver;
use App\Services\EDocument\Gateway\Storecove\Routing\StorecoveRequiredClientFields;
use App\Services\EDocument\Gateway\Storecove\Routing\StorecoveRoutingRules;
use App\Services\EDocument\Gateway\Storecove\StorecoveDeliveryMap;
use PHPUnit\Framework\TestCase;

class StorecoveRoutingServicesTest extends TestCase
{
    public function test_routing_rules_resolve_legal_tax_and_routing_identifiers(): void
    {
        $rules = new StorecoveRoutingRules([
            'BE' => ['B+G', 'BE:EN', 'BE:VAT', 'BE:EN'],
            'FR' => [
                ['G', 'FR:SIRET', false, '0009:11000201100044'],
                ['B', 'FR:SIRENE or FR:SIRET', 'FR:VAT', 'FR:SIRENE or FR:SIRET'],
            ],
        ]);

        $this->assertSame([
            'legal_identifier' => 'BE:EN',
            'tax_identifier' => 'BE:VAT',
        ], $rules->identifiersFor('BE', 'business'));

        $this->assertSame('0009:11000201100044', $rules->routingIdentifierFor('FR', 'government'));
        $this->assertSame('', $rules->taxIdentifierFor('FR', 'government'));
        $this->assertSame('FR:SIRET', $rules->legalIdentifierFor('FR', 'government'));
    }

    public function test_routability_is_strict_but_legacy_lookup_keeps_existing_fallback(): void
    {
        $rules = new StorecoveRoutingRules([
            'DE' => [
                ['G', 'DE:LWID', false, 'DE:LWID'],
                ['B', '', 'DE:VAT', 'DE:VAT'],
            ],
            'GB' => ['B', '', 'GB:VAT', 'GB:VAT'],
        ]);

        $this->assertFalse($rules->isClassificationRoutable('DE', 'individual'));
        $this->assertSame('DE:LWID', $rules->routingIdentifierFor('DE', 'individual'));
        $this->assertSame('DE:LWID', $rules->legalIdentifierFor('DE', 'individual'));

        $this->assertFalse($rules->isClassificationRoutable('GB', 'government'));
        $this->assertSame('GB:VAT', $rules->taxIdentifierFor('GB', 'government'));
    }

    public function test_required_client_fields_include_italy_specific_policy(): void
    {
        $rules = new StorecoveRoutingRules([
            'IT' => [
                ['G', '', 'IT:IVA', 'IT:CUUO'],
                ['B', '', 'IT:IVA', 'IT:CUUO'],
                ['C', '', 'IT:CF', 'Email'],
            ],
        ]);
        $requiredFields = new StorecoveRequiredClientFields($rules);

        $this->assertSame([
            'vat_number' => 'IT:IVA',
            'routing_id' => 'IT:CUUO',
        ], $requiredFields->for('IT', 'business'));

        $this->assertSame(['id_number' => 'IT:CF'], $requiredFields->for('IT', 'individual'));
        $this->assertSame([
            'id_number' => 'IT:CF',
            'routing_id' => 'IT:CUUO',
        ], $requiredFields->for('IT', 'individual', 'IT'));
    }

    public function test_identifier_validator_handles_composites_examples_and_checkdigits(): void
    {
        $validator = new StorecoveIdentifierValidator(
            [
                'BE:EN' => '/^(BE)?[01]\d{9}$/i',
                'FR:SIRENE' => '/^\d{9}$/',
                'FR:SIRET' => '/^\d{14}$/',
            ],
            [
                'FR:SIRENE' => '123456789',
                'FR:SIRET' => '12345678901234',
            ],
        );

        $this->assertTrue($validator->validFormat('BE:EN', '0202239951'));
        $this->assertFalse($validator->validFormat('BE:EN', '0202239952'));
        $this->assertTrue($validator->validFormat('FR:SIRENE or FR:SIRET', '73282932000074'));
        $this->assertFalse($validator->validFormat('FR:SIRENE or FR:SIRET', '12345678901234'));
        $this->assertSame('123456789 or 12345678901234', $validator->formatExample('FR:SIRENE or FR:SIRET'));
    }

    public function test_scheme_resolver_maps_iso_codes_and_public_identifier_fields(): void
    {
        $rules = new StorecoveRoutingRules([
            'BE' => ['B+G', 'BE:EN', 'BE:VAT', 'BE:EN'],
            'FR' => [
                ['G', 'FR:SIRET', false, '0009:11000201100044'],
                ['B', 'FR:SIRENE or FR:SIRET', 'FR:VAT', 'FR:SIRENE or FR:SIRET'],
            ],
        ]);
        $resolver = new StorecoveSchemeResolver([
            'BE:EN' => '0208',
            'BE:VAT' => '9925',
            'FR:SIRENE' => '0002',
            'FR:SIRET' => '0009',
        ], $rules);

        $this->assertSame('0208', $resolver->iso6523('BE:EN'));
        $this->assertSame('0002', $resolver->iso6523('FR:SIRENE or FR:SIRET'));
        $this->assertSame('vat_number', $resolver->publicIdentifierField('BE:VAT'));
        $this->assertSame('id_number', $resolver->publicIdentifierField('BE:EN'));
        $this->assertSame('routing_id', $resolver->publicIdentifierField('GLN'));
    }

    public function test_delivery_map_composes_routability_and_required_fields(): void
    {
        $rules = new StorecoveRoutingRules([
            'BE' => ['B+G', 'BE:EN', 'BE:VAT', 'BE:EN'],
        ]);
        $deliveryMap = new StorecoveDeliveryMap($rules, new StorecoveRequiredClientFields($rules));

        $this->assertSame([
            'classifications' => [
                'business' => true,
                'government' => true,
                'individual' => false,
            ],
            'required_fields' => [
                'business' => [
                    'vat_number' => 'BE:VAT',
                    'id_number' => 'BE:EN',
                ],
                'government' => [
                    'vat_number' => 'BE:VAT',
                    'id_number' => 'BE:EN',
                ],
                'individual' => [],
            ],
        ], $deliveryMap->all()['BE']);
    }
}
