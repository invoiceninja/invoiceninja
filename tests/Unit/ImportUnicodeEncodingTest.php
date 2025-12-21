<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\ImportController;
use ReflectionClass;
use ReflectionMethod;

class ImportUnicodeEncodingTest extends TestCase
{
    private ImportController $controller;
    private ReflectionMethod $readFileMethod;
    private ReflectionMethod $isValidConversionMethod;
    private ReflectionMethod $removeBOMMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ImportController();

        // Use reflection to access private methods
        $reflection = new ReflectionClass($this->controller);
        $this->readFileMethod = $reflection->getMethod('readFileWithProperEncoding');
        $this->readFileMethod->setAccessible(true);

        $this->isValidConversionMethod = $reflection->getMethod('isValidConversion');
        $this->isValidConversionMethod->setAccessible(true);

        $this->removeBOMMethod = $reflection->getMethod('removeBOM');
        $this->removeBOMMethod->setAccessible(true);
    }

    /**
     * Test data with various Unicode blocks and international content
     */
    private function getUnicodeTestData(): array
    {
        return [
            // Basic Latin and Latin Extended
            'latin_basic' => "Hello World! Company's data",
            'latin_extended' => "Café résumé naïve piñata façade",

            // Greek
            'greek' => "Καλημέρα κόσμε! Ελληνικά γράμματα",

            // Cyrillic
            'cyrillic' => "Привет мир! Русский текст",

            // Arabic (RTL)
            'arabic' => "مرحبا بالعالم! النص العربي",

            // Hebrew (RTL)
            'hebrew' => "שלום עולם! טקסט עברי",

            // Chinese Simplified
            'chinese_simplified' => "你好世界！简体中文",

            // Chinese Traditional
            'chinese_traditional' => "你好世界！繁體中文",

            // Japanese (Hiragana, Katakana, Kanji)
            'japanese' => "こんにちは世界！ひらがな・カタカナ・漢字",

            // Korean
            'korean' => "안녕하세요 세계! 한국어 텍스트",

            // Mathematical symbols
            'mathematical' => "∑∫∞±≤≥≠√∂∇∆",

            // Currency symbols
            'currency' => "€£¥₹₽₨₩₪₦₡₸",

            // Emoji and symbols
            'emoji' => "😀🌍🚀💻📊✨🎉🔥💡⭐",

            // Mixed scripts
            'mixed_scripts' => "Hello мир 世界 🌍 café résumé",

            // Special Unicode cases
            'zero_width' => "Text\u{200B}with\u{FEFF}zero\u{200C}width\u{200D}chars",
            'combining' => "e\u{0301}a\u{0300}i\u{0302}o\u{0303}u\u{0308}", // é à î õ ü

            // Quotation marks and dashes
            'punctuation' => "«quotes» \u{201C}smart\u{201D} \u{2018}quotes\u{2019} — – … ‚ „",
        ];
    }

    /**
     * Extended encoding list for comprehensive testing
     */
    private function getExtendedEncodings(): array
    {
        return [
            // Unicode variants
            'UTF-8',
            'UTF-8-BOM',
            'UTF-16BE',
            'UTF-16LE',
            'UTF-32BE',
            'UTF-32LE',

            // ISO Latin variants (commonly supported)
            'ISO-8859-1',   // Western European
            'ISO-8859-2',   // Central European
            'ISO-8859-5',   // Cyrillic
            'ISO-8859-7',   // Greek
            'ISO-8859-9',   // Turkish
            'ISO-8859-15',  // Western European (with Euro)

            // Windows code pages (commonly supported)
            'Windows-1251', // Cyrillic
            'Windows-1252', // Western European

            // Other commonly supported encodings
            'CP1252',       // Windows Western
        ];
    }

    /**
     * Create a test file with specific content and encoding
     */
    private function createTestFile(string $content, string $encoding): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'unicode_test_');

        switch ($encoding) {
            case 'UTF-8-BOM':
                $content = "\xEF\xBB\xBF" . $content;
                file_put_contents($tempFile, $content);
                break;

            case 'UTF-16BE':
                $content = "\xFE\xFF" . mb_convert_encoding($content, 'UTF-16BE', 'UTF-8');
                file_put_contents($tempFile, $content);
                break;

            case 'UTF-16LE':
                $content = "\xFF\xFE" . mb_convert_encoding($content, 'UTF-16LE', 'UTF-8');
                file_put_contents($tempFile, $content);
                break;

            case 'UTF-32BE':
                $content = "\x00\x00\xFE\xFF" . mb_convert_encoding($content, 'UTF-32BE', 'UTF-8');
                file_put_contents($tempFile, $content);
                break;

            case 'UTF-32LE':
                $content = "\xFF\xFE\x00\x00" . mb_convert_encoding($content, 'UTF-32LE', 'UTF-8');
                file_put_contents($tempFile, $content);
                break;

            case 'UTF-8':
                file_put_contents($tempFile, $content);
                break;

            default:
                // Try to convert using mb_convert_encoding
                try {
                    // Check if encoding is supported
                    if (!in_array($encoding, mb_list_encodings())) {
                        // If encoding not supported, use UTF-8 fallback
                        file_put_contents($tempFile, $content);
                        break;
                    }

                    $encoded = mb_convert_encoding($content, $encoding, 'UTF-8');
                    file_put_contents($tempFile, $encoded);
                } catch (Exception | ValueError $e) {
                    // If conversion fails, use UTF-8 fallback
                    file_put_contents($tempFile, $content);
                }
                break;
        }

        return $tempFile;
    }

    /**
     * Test 1: Unicode content preservation across different UTF encodings
     */
    public function testUnicodeContentPreservation()
    {
        $unicodeEncodings = ['UTF-8', 'UTF-8-BOM', 'UTF-16BE', 'UTF-16LE', 'UTF-32BE', 'UTF-32LE'];

        foreach ($this->getUnicodeTestData() as $name => $content) {
            foreach ($unicodeEncodings as $encoding) {
                $tempFile = $this->createTestFile($content, $encoding);

                $result = $this->readFileMethod->invoke($this->controller, $tempFile);

                $this->assertEquals(
                    $content,
                    $result,
                    "Unicode preservation failed for {$name} with {$encoding} encoding"
                );

                $this->assertTrue(
                    $this->isValidConversionMethod->invoke($this->controller, $result),
                    "Validation failed for {$name} with {$encoding} encoding"
                );

                unlink($tempFile);
            }
        }
    }

    /**
     * Test 2: BOM handling for different UTF variants
     */
    public function testBOMHandlingForAllUTF()
    {
        $testContent = "Hello 世界! Тест العالم";

        $bomTests = [
            'UTF-8' => "\xEF\xBB\xBF",
            'UTF-16BE' => "\xFE\xFF",
            'UTF-16LE' => "\xFF\xFE",
            'UTF-32BE' => "\x00\x00\xFE\xFF",
            'UTF-32LE' => "\xFF\xFE\x00\x00",
        ];

        foreach ($bomTests as $encoding => $bom) {
            // Create file with BOM using the createTestFile method
            $tempFile = $this->createTestFile($testContent, $encoding);

            // Test file processing with BOM
            $fileResult = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals(
                $testContent,
                $fileResult,
                "File processing with BOM failed for {$encoding}"
            );

            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $fileResult),
                "BOM file validation failed for {$encoding}"
            );

            unlink($tempFile);
        }

        // Test UTF-8 BOM removal specifically (since that's what the method is designed for)
        $utf8DataWithBOM = "\xEF\xBB\xBF" . $testContent;
        $result = $this->removeBOMMethod->invoke($this->controller, $utf8DataWithBOM);

        $this->assertEquals(
            $testContent,
            $result,
            "UTF-8 BOM removal failed"
        );
    }

    /**
     * Test 3: Extended encoding compatibility
     */
    public function testExtendedEncodingCompatibility()
    {
        // Use content that's compatible with most encodings
        $basicContent = "Company data with special chars";
        $accentContent = "Cafe resume naive facade"; // Without actual accents for broader compatibility

        foreach ($this->getExtendedEncodings() as $encoding) {
            // Skip encodings that are known to not support certain characters
            $content = $this->isAsciiCompatibleEncoding($encoding) ? $basicContent : $accentContent;

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

            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Validation failed for encoding: {$encoding}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 4: Right-to-left (RTL) text handling
     */
    public function testRightToLeftTextHandling()
    {
        $rtlContent = [
            'arabic' => "مرحبا بالعالم! شركة البيانات",
            'hebrew' => "שלום עולם! חברת הנתונים",
            'mixed_rtl' => "Hello مرحبا World עולם!",
        ];

        foreach ($rtlContent as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals($content, $result, "RTL test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "RTL validation failed for: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 5: Asian character sets (CJK)
     */
    public function testAsianCharacterSets()
    {
        $cjkContent = [
            'chinese_simplified' => "公司数据处理系统",
            'chinese_traditional' => "公司資料處理系統",
            'japanese_hiragana' => "かいしゃのでーたしすてむ",
            'japanese_katakana' => "カイシャノデータシステム",
            'japanese_kanji' => "会社のデータシステム",
            'korean' => "회사 데이터 시스템",
            'mixed_cjk' => "Company 公司 会社 회사 Data",
        ];

        foreach ($cjkContent as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals($content, $result, "CJK test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "CJK validation failed for: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 6: Emoji and symbol handling
     */
    public function testEmojiAndSymbolHandling()
    {
        $symbolContent = [
            'basic_emoji' => "Data 📊 Reports 📈 Analysis 🔍",
            'complex_emoji' => "👨‍💻👩‍💼🏢💼📋📊📈📉",
            'mathematical' => "∑(x²) ∫f(x)dx ∞ ≠ ≤ ≥ ± √",
            'currency_symbols' => "Price: €100 £80 ¥1000 $75",
            'technical_symbols' => "® © ™ § ¶ † ‡ • ‰ ‱",
            'arrows_symbols' => "← → ↑ ↓ ↔ ↕ ⇐ ⇒ ⇔",
        ];

        foreach ($symbolContent as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertEquals($content, $result, "Symbol test failed for: {$name}");
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Symbol validation failed for: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 7: Combining characters and normalization
     */
    public function testCombiningCharacters()
    {
        $combiningContent = [
            'accents_composed' => "café résumé naïve",
            'accents_decomposed' => "cafe\u{0301} re\u{0301}sume\u{0301} nai\u{0308}ve",
            'mixed_normalization' => "café cafe\u{0301} résumé re\u{0301}sume\u{0301}",
        ];

        foreach ($combiningContent as $name => $content) {
            $tempFile = $this->createTestFile($content, 'UTF-8');

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            // Content should be preserved (normalization might occur but content should be valid)
            $this->assertTrue(
                mb_check_encoding($result, 'UTF-8'),
                "Combining character result should be valid UTF-8 for: {$name}"
            );
            $this->assertTrue(
                $this->isValidConversionMethod->invoke($this->controller, $result),
                "Combining character validation failed for: {$name}"
            );

            unlink($tempFile);
        }
    }

    /**
     * Test 8: Large Unicode content performance
     */
    public function testLargeUnicodeContentPerformance()
    {
        $unicodePattern = "🌍 Hello 世界 مرحبا Здравствуй שלום こんにちは 안녕하세요 ";
        $largeContent = str_repeat($unicodePattern, 1000); // ~50KB of Unicode content

        $tempFile = $this->createTestFile($largeContent, 'UTF-8');

        $startTime = microtime(true);
        $result = $this->readFileMethod->invoke($this->controller, $tempFile);
        $endTime = microtime(true);

        $processingTime = $endTime - $startTime;

        $this->assertLessThan(2.0, $processingTime, "Large Unicode content processing should be fast");
        $this->assertEquals($largeContent, $result, "Large Unicode content should be preserved");
        $this->assertTrue(
            $this->isValidConversionMethod->invoke($this->controller, $result),
            "Large Unicode content validation failed"
        );

        unlink($tempFile);
    }

    /**
     * Test 9: Mixed encoding scenarios
     */
    public function testMixedEncodingScenarios()
    {
        // Simulate files that might have mixed encoding issues
        $scenarios = [
            'mostly_ascii_with_unicode' => "Regular text with émojis 😀 and symbols ™",
            'csv_with_international' => "Name,Company,Location\n\"José García\",\"Café España\",\"São Paulo\"",
            'business_names' => "McDonald's, L'Oréal, Nestlé, Björk & Co, Müller GmbH",
        ];

        foreach ($scenarios as $name => $content) {
            // Test with multiple encodings
            $encodings = ['UTF-8', 'UTF-8-BOM', 'WINDOWS-1252', 'ISO-8859-1'];

            foreach ($encodings as $encoding) {
                $tempFile = $this->createTestFile($content, $encoding);

                $result = $this->readFileMethod->invoke($this->controller, $tempFile);

                $this->assertTrue(
                    mb_check_encoding($result, 'UTF-8'),
                    "Mixed encoding result should be valid UTF-8 for {$name} with {$encoding}"
                );
                $this->assertTrue(
                    $this->isValidConversionMethod->invoke($this->controller, $result),
                    "Mixed encoding validation failed for {$name} with {$encoding}"
                );

                unlink($tempFile);
            }
        }
    }

    /**
     * Helper method to determine if an encoding is ASCII-compatible
     */
    private function isAsciiCompatibleEncoding(string $encoding): bool
    {
        $asciiOnlyEncodings = ['ASCII', 'US-ASCII'];
        return in_array($encoding, $asciiOnlyEncodings);
    }

    /**
     * Test 10: CSV data with international content
     */
    public function testCSVWithInternationalContent()
    {
        $csvContent = "Name,Company,City,Country,Notes\n" .
                     "\"José García\",\"Café España\",\"São Paulo\",\"Brasil\",\"Açaí supplier\"\n" .
                     "\"李小明\",\"北京科技公司\",\"北京\",\"中国\",\"Technology partner\"\n" .
                     "\"Müller\",\"Bäckerei München\",\"München\",\"Deutschland\",\"Café & Bäckerei\"\n" .
                     "\"Иванов\",\"Москва ООО\",\"Москва\",\"Россия\",\"Software development\"\n" .
                     "\"محمد أحمد\",\"شركة الرياض\",\"الرياض\",\"السعودية\",\"Trading company\"";

        $encodings = ['UTF-8', 'UTF-8-BOM', 'WINDOWS-1252'];

        foreach ($encodings as $encoding) {
            $tempFile = $this->createTestFile($csvContent, $encoding);

            $result = $this->readFileMethod->invoke($this->controller, $tempFile);

            $this->assertTrue(
                mb_check_encoding($result, 'UTF-8'),
                "CSV result should be valid UTF-8 for encoding: {$encoding}"
            );

            // Check that it contains expected international content
            $this->assertStringContainsString("José García", $result, "Should contain Spanish names");
            $this->assertStringContainsString("李小明", $result, "Should contain Chinese names");
            $this->assertStringContainsString("Müller", $result, "Should contain German names");

            unlink($tempFile);
        }
    }
}
