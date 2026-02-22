<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Utils\Encode;

/**
 * Direct comparison showing why the Encode class is necessary
 * for email subject lines with emojis and accented characters
 */
class EncodeClassComparisonTest extends TestCase
{
    private string $problematicSubject = "Rappel facture impayée (\$invoice) 🚀";

    /**
     * Demonstrate the difference: WITH Encode class vs WITHOUT
     */
    public function testWithVsWithoutEncodeClass()
    {
        $original = $this->problematicSubject;

        // ✅ WITH Encode class - CORRECT approach
        $withEncodeClass = Encode::convert($original);

        // ❌ WITHOUT Encode class - Common mistake (forcing through Windows-1252)
        $withoutEncodeClass = mb_convert_encoding($original, 'UTF-8', 'WINDOWS-1252');

        // Results comparison
        $this->assertEquals($original, $withEncodeClass, "Encode class should preserve original");
        $this->assertNotEquals($original, $withoutEncodeClass, "Direct conversion should corrupt content");

        // Emoji preservation
        $this->assertStringContainsString('🚀', $withEncodeClass, "Encode class preserves emoji");
        $this->assertStringNotContainsString('🚀', $withoutEncodeClass, "Direct conversion corrupts emoji");

        // Accented character preservation
        $this->assertStringContainsString('impayée', $withEncodeClass, "Encode class preserves accents");
        $this->assertStringNotContainsString('impayée', $withoutEncodeClass, "Direct conversion corrupts accents");

        // Show the actual corruption
        $this->assertStringContainsString('ðŸš€', $withoutEncodeClass, "Should contain corrupted emoji");
        $this->assertStringContainsString('Ã©', $withoutEncodeClass, "Should contain corrupted accent");

        // UTF-8 validity
        $this->assertTrue(mb_check_encoding($withEncodeClass, 'UTF-8'), "Encode class result is valid UTF-8");
        $this->assertTrue(mb_check_encoding($withoutEncodeClass, 'UTF-8'), "Corrupted result is still UTF-8 but wrong");
    }

    /**
     * Show multiple common failure approaches vs the Encode class
     */
    public function testMultipleFailureApproachesVsEncodeClass()
    {
        $original = $this->problematicSubject;

        // ✅ CORRECT: Using Encode class
        $correct = Encode::convert($original);

        // ❌ WRONG: Common developer mistakes
        $commonMistakes = [
            'force_windows1252' => mb_convert_encoding($original, 'UTF-8', 'WINDOWS-1252'),
            'force_ascii' => iconv('UTF-8', 'ASCII//IGNORE', $original),
            'manual_replace' => str_replace(['é'], ['e'], $original), // Simplistic approach
            'regex_strip' => preg_replace('/[^\x20-\x7E]/', '?', $original),
            'sanitize_filter' => @filter_var($original, FILTER_FLAG_STRIP_HIGH) ?: 'FILTER_FAILED',
        ];

        // The Encode class should preserve the original
        $this->assertEquals($original, $correct);

        // All other approaches should fail
        foreach ($commonMistakes as $method => $result) {
            $this->assertNotEquals($original, $result, "Method '{$method}' should fail to preserve original");

            // Most should lose the emoji (except manual_replace which only changes accents)
            if ($result !== 'FILTER_FAILED' && $method !== 'manual_replace') {
                $this->assertStringNotContainsString('🚀', $result, "Method '{$method}' should lose emoji");
            }
        }
    }

    /**
     * Gmail email header compatibility test
     */
    public function testGmailHeaderCompatibility()
    {
        $original = $this->problematicSubject;

        // ✅ CORRECT: Encode class makes it Gmail-compatible
        $encodedSubject = Encode::convert($original);

        // Create a proper email header (RFC 2047 encoding would be done by email library)
        $properHeader = "Subject: " . $encodedSubject;

        // ❌ WRONG: Direct use without encoding
        $corruptedSubject = mb_convert_encoding($original, 'UTF-8', 'WINDOWS-1252');
        $badHeader = "Subject: " . $corruptedSubject;

        // Proper header should contain correct characters
        $this->assertStringContainsString('🚀', $properHeader);
        $this->assertStringContainsString('impayée', $properHeader);

        // Bad header should contain corruption
        $this->assertStringNotContainsString('🚀', $badHeader);
        $this->assertStringNotContainsString('impayée', $badHeader);
        $this->assertStringContainsString('ðŸš€', $badHeader);
        $this->assertStringContainsString('Ã©', $badHeader);
    }

    /**
     * Performance comparison: Encode class vs naive approaches
     */
    public function testPerformanceComparison()
    {
        $original = $this->problematicSubject;

        // Time the Encode class
        $start = microtime(true);
        $result = Encode::convert($original);
        $encodeClassTime = microtime(true) - $start;

        // Time a naive approach
        $start = microtime(true);
        $naiveResult = mb_convert_encoding($original, 'UTF-8', 'WINDOWS-1252');
        $naiveTime = microtime(true) - $start;

        // Both should be fast (under 10ms)
        $this->assertLessThan(0.01, $encodeClassTime, "Encode class should be fast");
        $this->assertLessThan(0.01, $naiveTime, "Naive approach should also be fast");

        // But only Encode class preserves content
        $this->assertEquals($original, $result);
        $this->assertNotEquals($original, $naiveResult);
    }

    /**
     * Real-world email scenario test
     */
    public function testRealWorldEmailScenario()
    {
        // Simulate various real-world email subjects that would fail without Encode class
        $realWorldSubjects = [
            $this->problematicSubject,
            "Café Newsletter 📧 March 2024",
            "Paiement reçu ✅ Facture #123",
            "Señor García - Cotización €1,500 💼",
            "Müller GmbH → Status Update 🎯",
        ];

        foreach ($realWorldSubjects as $subject) {
            // ✅ With Encode class
            $safe = Encode::convert($subject);

            // ❌ Without Encode class (common mistake)
            $unsafe = mb_convert_encoding($subject, 'UTF-8', 'WINDOWS-1252');

            // Encode class should preserve everything
            $this->assertEquals($subject, $safe, "Encode class failed for: {$subject}");

            // Direct conversion should corrupt emojis/accents
            $this->assertNotEquals($subject, $unsafe, "Direct conversion should fail for: {$subject}");

            // Should be valid UTF-8
            $this->assertTrue(mb_check_encoding($safe, 'UTF-8'));
        }
    }

    /**
     * Test what happens with edge cases
     */
    public function testEdgeCaseComparison()
    {
        $edgeCases = [
            // Only emoji
            "🚀",
            // Only accents
            "impayée",
            // Mixed complex
            "🇫🇷 François & José 💼 €500",
            // Empty
            "",
            // ASCII only
            "Invoice 123",
        ];

        foreach ($edgeCases as $testCase) {
            $encoded = Encode::convert($testCase);
            $naive = mb_convert_encoding($testCase, 'UTF-8', 'WINDOWS-1252');

            // For ASCII-only content, both should work
            if (mb_check_encoding($testCase, 'ASCII')) {
                $this->assertEquals($testCase, $encoded);
                // Naive might still work for ASCII
            } else {
                // For Unicode content, only Encode class should work correctly
                $this->assertEquals($testCase, $encoded, "Encode class should handle: {$testCase}");
                $this->assertNotEquals($testCase, $naive, "Naive approach should fail: {$testCase}");
            }
        }
    }
}
