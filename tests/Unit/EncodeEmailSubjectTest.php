<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Utils\Encode;

class EncodeEmailSubjectTest extends TestCase
{
    /**
     * Test the exact example provided by the user
     */
    public function testUserSpecificExample()
    {
        $originalSubject = "Rappel facture impayée (\$invoice) 🚀";
        $convertedSubject = Encode::convert($originalSubject);

        // Should return unchanged - already valid UTF-8
        $this->assertEquals($originalSubject, $convertedSubject);
        $this->assertTrue(mb_check_encoding($convertedSubject, 'UTF-8'));

        // Verify emoji is preserved
        $this->assertStringContainsString('🚀', $convertedSubject);
        $this->assertStringContainsString('é', $convertedSubject);
        // Verify accented characters are preserved
        $this->assertStringContainsString('impayée', $convertedSubject);

        // Verify the string length is correct (emojis are multi-byte)
        $this->assertEquals(mb_strlen($originalSubject, 'UTF-8'), mb_strlen($convertedSubject, 'UTF-8'));
    }

    /**
     * Test various email subject scenarios with emojis
     */
    public function testEmojiEmailSubjects()
    {
        $testCases = [
            // Single emoji
            "Invoice Ready 📧" => "Invoice Ready 📧",

            // Multiple emojis
            "Payment Received ✅ 🎉" => "Payment Received ✅ 🎉",

            // Emoji at start
            "🚨 Urgent: Payment Overdue" => "🚨 Urgent: Payment Overdue",

            // Emoji at end
            "Welcome to our service! 🎯" => "Welcome to our service! 🎯",

            // Complex emojis (family, skin tones, etc.)
            "Team meeting 👨‍💻👩‍💻" => "Team meeting 👨‍💻👩‍💻",

            // Mixed flags and symbols
            "Conference in Paris 🇫🇷 ✈️" => "Conference in Paris 🇫🇷 ✈️",
            "Nouvelle facture de Réact" => "Nouvelle facture de Réact"
        ];

        foreach ($testCases as $input => $expected) {
            $result = Encode::convert($input);

            $this->assertEquals($expected, $result, "Failed for emoji test: {$input}");
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'), "Not valid UTF-8: {$input}");
        }
    }

    /**
     * Test accented characters common in email subjects
     */
    public function testAccentedCharacters()
    {
        $testCases = [
            // French
            "Café résumé naïve façade" => "Café résumé naïve façade",

            // Spanish
            "Niño piñata mañana" => "Niño piñata mañana",

            // German
            "Größe Weiß Mädchen" => "Größe Weiß Mädchen",

            // Portuguese
            "Coração São Paulo" => "Coração São Paulo",

            // Mixed languages
            "Café & Niño résumé" => "Café & Niño résumé",
            "Nouvelle facture de Réact" => "Nouvelle facture de Réact"
        ];

        foreach ($testCases as $input => $expected) {
            $result = Encode::convert($input);

            $this->assertEquals($expected, $result, "Failed for accent test: {$input}");
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'), "Not valid UTF-8: {$input}");
        }
    }

    /**
     * Test special symbols commonly used in email subjects
     */
    public function testSpecialSymbols()
    {
        $testCases = [
            // Currency symbols
            "Invoice €50.00 £25.99 ¥1000" => "Invoice €50.00 £25.99 ¥1000",

            // Smart quotes and dashes
            "Company's \"quoted\" text—dash…ellipsis" => "Company's \"quoted\" text—dash…ellipsis",

            // Copyright and trademark
            "Product™ Service© Brand®" => "Product™ Service© Brand®",

            // Mathematical symbols
            "Discount ≥ 20% ± 5%" => "Discount ≥ 20% ± 5%",

            // Arrows and symbols
            "Process → Complete ✓" => "Process → Complete ✓",
            "Nouvelle facture de Réact" => "Nouvelle facture de Réact"
        ];

        foreach ($testCases as $input => $expected) {
            $result = Encode::convert($input);

            $this->assertEquals($expected, $result, "Failed for symbol test: {$input}");
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'), "Not valid UTF-8: {$input}");
        }
    }

    /**
     * Test email subjects with mixed content (the most realistic scenario)
     */
    public function testMixedContentEmailSubjects()
    {
        $testCases = [
            // User's exact example
            "Rappel facture impayée (\$invoice) 🚀" => "Rappel facture impayée (\$invoice) 🚀",

            // Invoice with currency and emoji
            "Facture #123 - €150.00 💰" => "Facture #123 - €150.00 💰",

            // Reminder with accents and emoji
            "Relance: paiement en retard 📅 ⚠️" => "Relance: paiement en retard 📅 ⚠️",

            // Welcome message
            "Bienvenue chez Café ☕ 🥐" => "Bienvenue chez Café ☕ 🥐",

            // Complex business scenario
            "Réunion équipe → 15h30 📊 🎯" => "Réunion équipe → 15h30 📊 🎯",
            "Nouvelle facture de Réact" => "Nouvelle facture de Réact"
        ];

        foreach ($testCases as $input => $expected) {
            $result = Encode::convert($input);

            $this->assertEquals($expected, $result, "Failed for mixed content test: {$input}");
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'), "Not valid UTF-8: {$input}");

            // Verify character count is preserved (important for emojis)
            $this->assertEquals(
                mb_strlen($expected, 'UTF-8'),
                mb_strlen($result, 'UTF-8'),
                "Character count mismatch for: {$input}"
            );
        }
    }

    /**
     * Test corrupted Windows-1252 content that needs conversion
     */
    public function testCorruptedEncodingConversion()
    {
        // Simulate content that was incorrectly encoded as Windows-1252
        $windows1252Input = mb_convert_encoding("Café résumé", 'WINDOWS-1252', 'UTF-8');
        $result = Encode::convert($windows1252Input);

        $this->assertEquals("Café résumé", $result);
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    /**
     * Test Gmail-specific email subject requirements
     */
    public function testGmailCompatibility()
    {
        $testCases = [
            // Long subject with emojis (Gmail truncates at ~70 chars in preview)
            "This is a long email subject with emojis 🚀 that might get truncated by Gmail 📧",

            // Subject with only emojis
            "🚀📧🎉✅⚠️💰",

            // Subject with special characters Gmail handles
            "Re: Fw: [URGENT] Company's \"Project\" Status—Update ✓",

            // International content
            "国际业务 🌍 Négociation €500K 💼",
            "Nouvelle facture de Réact" => "Nouvelle facture de Réact"
        ];

        foreach ($testCases as $input) {
            $result = Encode::convert($input);

            // Should be valid UTF-8 (Gmail requirement)
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'), "Gmail compatibility failed for: {$input}");

            // Should not contain replacement characters
            $this->assertStringNotContainsString("\xEF\xBF\xBD", $result, "Contains replacement characters: {$input}");
            $this->assertStringNotContainsString('ï¿½', $result, "Contains double-encoded replacement: {$input}");

            // Should preserve original content for valid UTF-8
            $this->assertEquals($input, $result, "Content changed unnecessarily: {$input}");
        }
    }

    /**
     * Test edge cases that might break email clients
     */
    public function testEmailClientEdgeCases()
    {
        $testCases = [
            // Empty string
            "" => "",

            // Only spaces
            "   " => "   ",

            // Only special characters
            "€£¥" => "€£¥",

            // Only emojis
            "🚀🎉📧" => "🚀🎉📧",

            // Mixed spaces and emojis
            " 🚀 📧 🎉 " => " 🚀 📧 🎉 ",

            // Newlines and tabs (should be preserved)
            "Line 1\nLine 2\tTabbed" => "Line 1\nLine 2\tTabbed",
            "Nouvelle facture de Réact" => "Nouvelle facture de Réact"
        ];

        foreach ($testCases as $input => $expected) {
            $result = Encode::convert($input);

            $this->assertEquals($expected, $result, "Edge case failed: " . var_export($input, true));
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'), "Not valid UTF-8: " . var_export($input, true));
        }
    }


    /**
     * Test performance with typical email subject lengths
     */
    public function testPerformanceWithTypicalSubjects2()
    {
        $baseSubject = "Nouvelle facture de Réact";

        // Test with different subject lengths
        $subjects = [
            $baseSubject, // ~40 chars
            str_repeat($baseSubject . " ", 2), // ~80 chars
            str_repeat($baseSubject . " ", 5), // ~200 chars
        ];

        foreach ($subjects as $subject) {
            $startTime = microtime(true);
            $result = Encode::convert($subject);
            $endTime = microtime(true);

            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            // Should complete quickly (under 10ms for email subjects)
            $this->assertLessThan(10, $executionTime, "Too slow for subject: " . strlen($subject) . " chars");
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        }
    }
    /**
     * Test performance with typical email subject lengths
     */
    public function testPerformanceWithTypicalSubjects()
    {
        $baseSubject = "Rappel facture impayée (\$invoice) 🚀";

        // Test with different subject lengths
        $subjects = [
            $baseSubject, // ~40 chars
            str_repeat($baseSubject . " ", 2), // ~80 chars
            str_repeat($baseSubject . " ", 5), // ~200 chars
        ];

        foreach ($subjects as $subject) {
            $startTime = microtime(true);
            $result = Encode::convert($subject);
            $endTime = microtime(true);

            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            // Should complete quickly (under 10ms for email subjects)
            $this->assertLessThan(10, $executionTime, "Too slow for subject: " . strlen($subject) . " chars");
            $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        }
    }

    /**
     * Test that the method is safe to call multiple times
     */
    public function testIdempotency()
    {
        $original = "Rappel facture impayée (\$invoice) 🚀";

        $first = Encode::convert($original);
        $second = Encode::convert($first);
        $third = Encode::convert($second);

        // Should be identical after multiple conversions
        $this->assertEquals($original, $first);
        $this->assertEquals($first, $second);
        $this->assertEquals($second, $third);


        $original = "Nouvelle facture de Réact";

        $first = Encode::convert($original);
        $second = Encode::convert($first);
        $third = Encode::convert($second);

        // Should be identical after multiple conversions
        $this->assertEquals($original, $first);
        $this->assertEquals($first, $second);
        $this->assertEquals($second, $third);
    }
}
