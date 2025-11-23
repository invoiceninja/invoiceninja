<?php

namespace Tests\Feature\EInvoice\Verifactu;

use App\Services\EDocument\Standards\Validation\VerifactuDocumentValidator;
use PHPUnit\Framework\TestCase;

class VerifactuDocumentValidatorTest extends TestCase
{
    /**
     * Test that XSD errors are formatted in a human-readable way
     */
    public function test_xsd_errors_are_formatted_readably()
    {
        // Create a mock LibXMLError object that simulates the error from the log
        $mockError = new \LibXMLError();
        $mockError->line = 12;
        $mockError->message = 'Element \'{https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd}Desglose\': Missing child element(s). Expected is ( {https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd}DetalleDesglose ).';

        // Use reflection to test the private method
        $validator = new VerifactuDocumentValidator('<xml></xml>');
        $reflection = new \ReflectionClass($validator);
        $formatMethod = $reflection->getMethod('formatXsdError');
        $formatMethod->setAccessible(true);

        $formattedError = $formatMethod->invoke($validator, $mockError);

        // The formatted error should be more readable
        $this->assertStringContainsString('Line 12:', $formattedError);
        $this->assertStringContainsString('Missing required child element:', $formattedError);
        $this->assertStringContainsString('DetalleDesglose (Tax Detail)', $formattedError);
        $this->assertStringNotContainsString('https://www2.agenciatributaria.gob.es', $formattedError);
    }

    /**
     * Test that error context provides helpful information
     */
    public function test_error_context_provides_helpful_information()
    {
        $validator = new VerifactuDocumentValidator('<xml></xml>');
        $reflection = new \ReflectionClass($validator);
        $contextMethod = $reflection->getMethod('getErrorContext');
        $contextMethod->setAccessible(true);

        $context = $contextMethod->invoke($validator, 'Missing child element: DetalleDesglose');

        $this->assertStringContainsString('Desglose (Tax Breakdown)', $context);
        $this->assertStringContainsString('DetalleDesglose (Tax Detail)', $context);
        $this->assertStringContainsString('requires', $context);
    }

    /**
     * Test that error suggestions provide actionable advice
     */
    public function test_error_suggestions_provide_actionable_advice()
    {
        $validator = new VerifactuDocumentValidator('<xml></xml>');
        $reflection = new \ReflectionClass($validator);
        $suggestionMethod = $reflection->getMethod('getErrorSuggestion');
        $suggestionMethod->setAccessible(true);

        $suggestion = $suggestionMethod->invoke($validator, 'Missing child element: DetalleDesglose');

        $this->assertStringContainsString('Add a DetalleDesglose element', $suggestion);
        $this->assertStringContainsString('Example:', $suggestion);
        $this->assertStringContainsString('<DetalleDesglose>', $suggestion);
    }

    /**
     * Test error summary provides clear overview
     */
    public function test_error_summary_provides_clear_overview()
    {
        $validator = new VerifactuDocumentValidator('<xml></xml>');

        // Initially no errors
        $summary = $validator->getErrorSummary();
        $this->assertEquals('Document validation passed successfully.', $summary);

        // Add some mock errors
        $reflection = new \ReflectionClass($validator);
        $errorsProperty = $reflection->getProperty('errors');
        $errorsProperty->setAccessible(true);
        $errorsProperty->setValue($validator, [
            'xsd' => ['Error 1', 'Error 2'],
            'structure' => ['Error 3']
        ]);

        $summary = $validator->getErrorSummary();
        $this->assertStringContainsString('Validation failed with 3 total error(s):', $summary);
        $this->assertStringContainsString('Schema Validation Errors: 2', $summary);
        $this->assertStringContainsString('Structural Errors: 1', $summary);
    }

    /**
     * Test formatted errors provide structured information
     */
    public function test_formatted_errors_provide_structured_information()
    {
        $validator = new VerifactuDocumentValidator('<xml></xml>');

        // Add some mock errors
        $reflection = new \ReflectionClass($validator);
        $errorsProperty = $reflection->getProperty('errors');
        $errorsProperty->setAccessible(true);
        $errorsProperty->setValue($validator, [
            'xsd' => ['Error 1', 'Error 2'],
            'business' => ['Error 3']
        ]);

        $formatted = $validator->getFormattedErrors();

        $this->assertArrayHasKey('xsd', $formatted);
        $this->assertArrayHasKey('business', $formatted);
        $this->assertEquals(2, $formatted['xsd']['count']);
        $this->assertEquals('high', $formatted['xsd']['severity']);
        $this->assertEquals('low', $formatted['business']['severity']);
    }
}
