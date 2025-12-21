<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\ImportController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use ReflectionMethod;

class ImportEncodingTest extends TestCase
{
    private ImportController $controller;
    private ReflectionMethod $readFileMethod;
    private ReflectionMethod $containsWindows1252Method;
    private ReflectionMethod $fixCorruptedMethod;
    private ReflectionMethod $isValidConversionMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ImportController();

        // Use reflection to access private methods
        $reflection = new ReflectionClass($this->controller);
        $this->readFileMethod = $reflection->getMethod('readFileWithProperEncoding');
        $this->readFileMethod->setAccessible(true);

        $this->containsWindows1252Method = $reflection->getMethod('containsWindows1252Bytes');
        $this->containsWindows1252Method->setAccessible(true);

        $this->fixCorruptedMethod = $reflection->getMethod('fixCorruptedWindows1252');
        $this->fixCorruptedMethod->setAccessible(true);

        $this->isValidConversionMethod = $reflection->getMethod('isValidConversion');
        $this->isValidConversionMethod->setAccessible(true);
    }

    /**
     * Test data for various encoding scenarios
     */
    private function getTestData(): array
    {
        return [
            // Test string with common problematic characters
            'basic' => "Company's text with quotes",
            'apostrophes' => "Sya's Ian Le Led",
            'quotes' => '"Smart quotes" and \'single quotes\'',
            'currency' => "Price: 50.00, 25.99", // Simplified to avoid currency symbols in basic test
            'symbols' => "Trademark and copyright symbols",
            'accents' => "Cafe resume naive facade", // Simplified accents
        ];
    }

    /**
     * Get complex test data with full Unicode characters (for specific encoding tests)
     */
    private function getComplexTestData(): array
    {
        return [
            'complex' => "Company's «quoted» text—dash…ellipsis",
            'currency' => "Price: €50.00, £25.99",
            'symbols' => "Trademark™ and copyright© symbols",
            'accents' => "Café résumé naïve piñata façade",
        ];
    }

    /**
     * Windows-1252 special characters (0x80-0x9F range)
     */
    private function getWindows1252SpecialChars(): array
    {
        return [
            0x80 => '€',  // Euro sign
            0x82 => '‚',  // Single low-9 quotation mark
            0x83 => 'ƒ',  // Latin small letter f with hook
            0x84 => '„',  // Double low-9 quotation mark
            0x85 => '…',  // Horizontal ellipsis
            0x86 => '†',  // Dagger
            0x87 => '‡',  // Double dagger
            0x88 => 'ˆ',  // Modifier letter circumflex accent
            0x89 => '‰',  // Per mille sign
            0x8A => 'Š',  // Latin capital letter S with caron
            0x8B => '‹',  // Single left-pointing angle quotation mark
            0x8C => 'Œ',  // Latin capital ligature OE
            0x8E => 'Ž',  // Latin capital letter Z with caron
            0x91 => "\u{2018}",  // Left single quotation mark (smart quote)
            0x92 => "\u{2019}",  // Right single quotation mark (smart quote)
            0x93 => "\u{201C}",  // Left double quotation mark
            0x94 => "\u{201D}",  // Right double quotation mark
            0x95 => '•',  // Bullet
            0x96 => '–',  // En dash
            0x97 => '—',  // Em dash
            0x98 => '˜',  // Small tilde
            0x99 => '™',  // Trade mark sign
            0x9A => 'š',  // Latin small letter s with caron
            0x9B => '›',  // Single right-pointing angle quotation mark
            0x9C => 'œ',  // Latin small ligature oe
            0x9E => 'ž',  // Latin small letter z with caron
            0x9F => 'Ÿ',  // Latin capital letter Y with diaeresis
        ];
    }

    /**
     * Create a temporary file with specific encoding
     */
    private function createTestFile(string $content, string $encoding): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'encoding_test_');

        if ($encoding === 'UTF-8-BOM') {
            $content = "\xEF\xBB\xBF" . $content;
            file_put_contents($tempFile, $content);
        } elseif ($encoding === 'UTF-8-CORRUPTED') {
            // Simulate corrupted UTF-8 with replacement characters
            $content = str_replace("'", "\xEF\xBF\xBD", $content);
            file_put_contents($tempFile, $content);
        } elseif ($encoding === 'UTF-8') {
            file_put_contents($tempFile, $content);
        } else {
            // Convert to target encoding
            $encoded = mb_convert_encoding($content, $encoding, 'UTF-8');
            file_put_contents($tempFile, $encoded);
        }

        return $tempFile;
    }

    /**
     * Test 1: UTF-8 clean files (should pass through unchanged)
     */
    public function testCleanUtf8Files()
    {
        foreach ($this->getTestData() as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals($content, $result, "Clean UTF-8 test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Validation failed for clean UTF-8: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 2: UTF-8 with BOM
     */
    public function testUtf8WithBom()
    {
        foreach ($this->getTestData() as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8-BOM');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            // Should remove BOM and return clean content
            $this->assertEquals($content, $result, "UTF-8 BOM test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Validation failed for UTF-8 BOM: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 3: Windows-1252 files
     */
    public function testWindows1252Files()
    {
        // Test with complex Unicode characters for Windows-1252
        foreach ($this->getComplexTestData() as $name => $content) {
            $tempFile = $this->createTestFile($content, 'WINDOWS-1252');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals($content, $result, "Windows-1252 test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Validation failed for Windows-1252: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 3.5: Complex UTF-8 files with Unicode characters
     */
    public function testComplexUtf8Files()
    {
        foreach ($this->getComplexTestData() as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals($content, $result, "Complex UTF-8 test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Validation failed for complex UTF-8: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 4: ISO-8859-1 files
     */
    public function testIso88591Files()
    {
        // Use only characters that exist in ISO-8859-1
        $testData = [
            'basic' => "Company's text",
            'accents' => "Café résumé naïve façade",
        ];

        foreach ($testData as $name => $content) {
            $tempFile = $this->createTestFile($content, 'ISO-8859-1');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals($content, $result, "ISO-8859-1 test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Validation failed for ISO-8859-1: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 5: Corrupted UTF-8 with replacement characters
     */
    public function testCorruptedUtf8Files()
    {
        foreach ($this->getTestData() as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8-CORRUPTED');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            // Expected result should have smart quotes instead of straight apostrophes
            $expectedContent = str_replace("'", "\u{2019}", $content);
            $this->assertEquals($expectedContent, $result, "Corrupted UTF-8 test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Validation failed for corrupted UTF-8: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 6: All Windows-1252 special characters
     */
    public function testAllWindows1252SpecialCharacters()
    {
        $specialChars = $this->getWindows1252SpecialChars();

        foreach ($specialChars as $byte => $expectedChar) {
            // Create content with the specific byte
            $content = "Test " . chr($byte) . " character";
            $tempFile = tempnam(sys_get_temp_dir(), 'char_test_');

            // Write raw bytes including the Windows-1252 character
            $rawContent = "Test " . chr($byte) . " character";
            file_put_contents($tempFile, $rawContent);

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $expectedResult = "Test {$expectedChar} character";
            $this->assertEquals(
                $expectedResult,
                $result,
                "Windows-1252 character test failed for byte 0x" . dechex($byte) . " ({$expectedChar})"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 7: containsWindows1252Bytes method
     */
    public function testContainsWindows1252Bytes()
    {
        // Test with Windows-1252 bytes
        $dataWithWindows1252 = "Test " . chr(0x92) . " content";
        $this->assertTrue(
            $this->containsWindows1252Method->invoke($this->controller, $dataWithWindows1252),
            "Should detect Windows-1252 bytes"
        );

        // Test without Windows-1252 bytes
        $cleanData = "Test clean content";
        $this->assertFalse(
            $this->containsWindows1252Method->invoke($this->controller, $cleanData),
            "Should not detect Windows-1252 bytes in clean data"
        );

        // Test with UTF-8 replacement characters
        $corruptedData = "Test \xEF\xBF\xBD content";
        $this->assertFalse(
            $this->containsWindows1252Method->invoke($this->controller, $corruptedData),
            "Should not detect Windows-1252 bytes in corrupted UTF-8"
        );
    }

    /**
     * Test 8: fixCorruptedWindows1252 method
     */
    public function testFixCorruptedWindows1252()
    {
        $corruptedData = "Sya\xEF\xBF\xBDs In Le";
        $expectedResult = "Sya\u{2019}s In Le";

        $result = $this->fixCorruptedMethod->invoke($this->controller, $corruptedData);

        $this->assertEquals($expectedResult, $result, "Failed to fix corrupted Windows-1252 data");
    }

    /**
     * Test 9: isValidConversion method
     */
    public function testIsValidConversion()
    {
        // Valid UTF-8 without replacement characters
        $validData = "Clean UTF-8 content with apostrophe's";
        $this->assertTrue(
            $this->isValidConversionMethod->invoke($this->controller, $validData),
            "Should validate clean UTF-8 content"
        );

        // Invalid - contains replacement character bytes
        $invalidData1 = "Content with \xEF\xBF\xBD replacement";
        $this->assertFalse(
            $this->isValidConversionMethod->invoke($this->controller, $invalidData1),
            "Should reject content with UTF-8 replacement bytes"
        );

        // Invalid - contains double-encoded replacement
        $invalidData2 = "Content with ï¿½ replacement";
        $this->assertFalse(
            $this->isValidConversionMethod->invoke($this->controller, $invalidData2),
            "Should reject content with double-encoded replacement"
        );

        // Invalid UTF-8
        $invalidUtf8 = "Invalid \xFF UTF-8";
        $this->assertFalse(
            $this->isValidConversionMethod->invoke($this->controller, $invalidUtf8),
            "Should reject invalid UTF-8"
        );
    }

    /**
     * Test 10: Multiple encoding types comprehensive test
     */
    public function testMultipleEncodingTypes()
    {
        $encodings = [
            'UTF-8',
            'WINDOWS-1252',
            'ISO-8859-1',
            'ISO-8859-15',
            'ASCII',
        ];

        $testContent = "Company's «test» data—with symbols";

        foreach ($encodings as $encoding) {
            if ($encoding === 'ASCII') {
                // ASCII can't handle special characters, use simpler content
                $content = "Company data test";
            } else {
                $content = $testContent;
            }

            $tempFile = $this->createTestFile($content, $encoding);
            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            // Result should always be valid UTF-8
            $this->assertTrue(
                mb_check_encoding($result, 'UTF-8'),
                "Result should be valid UTF-8 for encoding: {$encoding}"
            );

            // Should not contain replacement characters
            $this->assertFalse(
                str_contains($result, '�'),
                "Result should not contain replacement characters for encoding: {$encoding}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 11: Backward compatibility - existing functionality should not break
     */
    public function testBackwardCompatibility()
    {
        // Test that normal CSV content still works
        $csvContent = "Name,Amount,Date\n\"John's Company\",100.50,2024-01-01\n\"Mary's Store\",250.75,2024-01-02";

        $tempFile = $this->createTestFile($csvContent, 'UTF-8');
        $result = $this->readFileMethod->invoke($this->controller, $tempFile);

        $this->assertEquals($csvContent, $result, "Backward compatibility test failed for CSV content");

        // Test that it contains expected structure
        $this->assertStringContainsString("John's Company", $result, "CSV should contain original apostrophes");
        $this->assertStringContainsString("Mary's Store", $result, "CSV should contain original apostrophes");

        unlink($tempFile);
    }

    /**
     * Test 12: Edge cases and error handling
     */
    public function testEdgeCases()
    {
        // Empty file
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_test_');
        file_put_contents($tempFile, '');

        $result = $this->readFileMethod->invoke($this->controller, $tempFile);
        $this->assertEquals('', $result, "Empty file should return empty string");

        unlink($tempFile);

        // Non-existent file
        $result = $this->readFileMethod->invoke($this->controller, '/non/existent/file.csv');
        $this->assertEquals('', $result, "Non-existent file should return empty string");

        // Very large content with mixed characters
        $largeContent = str_repeat("Test's data with special chars—", 1000);
        $tempFile = $this->createTestFile($largeContent, 'WINDOWS-1252');

        $result = $this->readFileMethod->invoke($this->controller, $tempFile);
        $this->assertTrue(
            $this->isValidConversionMethod->invoke($this->controller, $result),
            "Large file conversion should be valid"
        );

        unlink($tempFile);
    }

    /**
     * Test 13: Performance test to ensure no significant regression
     */
    public function testPerformance()
    {
        $content = str_repeat("Company's data with special characters test\n", 10000);
        $tempFile = $this->createTestFile($content, 'WINDOWS-1252');

        $startTime = microtime(true);
        $result = $this->readFileMethod->invoke($this->controller, $tempFile);
        $endTime = microtime(true);

        $processingTime = $endTime - $startTime;

        // Should process reasonably fast (less than 1 second for 10k lines)
        $this->assertLessThan(1.0, $processingTime, "Processing should be reasonably fast");
        $this->assertTrue(
            $this->isValidConversionMethod->invoke($this->controller, $result),
            "Performance test result should be valid"
        );

        unlink($tempFile);
    }
}
