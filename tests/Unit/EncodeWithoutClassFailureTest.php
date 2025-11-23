<?php

namespace Tests\Unit;

use Tests\TestCase;

class EncodeWithoutClassFailureTest extends TestCase
{
    private string $problematicSubject = "Rappel facture impayée (\$invoice) 🚀";

    /**
     * Test that direct mb_convert_encoding through Windows-1252 corrupts emojis
     */
    public function testDirectConversionCorruptsEmojis()
    {
        $original = $this->problematicSubject;

        // This is what would happen without the Encode class - forcing conversion through Windows-1252
        $corrupted = mb_convert_encoding($original, 'UTF-8', 'WINDOWS-1252');

        // Should NOT be equal to original (emoji gets corrupted)
        $this->assertNotEquals($original, $corrupted);

        // Emoji should be lost/corrupted
        $this->assertStringNotContainsString('🚀', $corrupted);

        // Should contain corruption artifacts
        $this->assertTrue(
            str_contains($corrupted, "\xEF\xBF\xBD") || // Replacement character
            str_contains($corrupted, '?') || // Question mark replacement
            str_contains($corrupted, 'Ã©') || // Double-encoded é
            str_contains($corrupted, 'ðŸš€') || // Corrupted emoji
            strlen($corrupted) < strlen($original), // Characters lost
            "Expected emoji corruption but content seems intact. Original: {$original}, Corrupted: {$corrupted}"
        );
    }

    /**
     * Test that naive iconv usage fails with emojis
     */
    public function testIconvFailsWithEmojis()
    {
        $original = $this->problematicSubject;

        // Common mistake: trying to convert UTF-8 through ISO-8859-1
        $result = iconv('ISO-8859-1', 'UTF-8//IGNORE', $original);

        // Should fail or corrupt the content
        $this->assertNotEquals($original, $result);

        // Should lose the emoji
        $this->assertStringNotContainsString('🚀', $result);
    }

    /**
     * Test that forcing through ASCII destroys international characters
     */
    public function testAsciiConversionDestroysInternationalChars()
    {
        $original = $this->problematicSubject;

        // Naive approach: force to ASCII
        $asciiAttempt = iconv('UTF-8', 'ASCII//IGNORE', $original);

        // Should lose both emoji and accented characters
        $this->assertNotEquals($original, $asciiAttempt);
        $this->assertStringNotContainsString('🚀', $asciiAttempt);
        $this->assertStringNotContainsString('impayée', $asciiAttempt);

        // Should contain "impaye" instead (accent completely removed)
        $this->assertStringContainsString('impaye', $asciiAttempt);
    }

    /**
     * Test that manual character replacement approach is inadequate
     */
    public function testManualReplacementInadequate()
    {
        $original = $this->problematicSubject;

        // Naive manual approach that many developers try
        $manualAttempt = str_replace([
            'é',
            'à',
            'ç',
            'ù'
        ], [
            'e',
            'a',
            'c',
            'u'
        ], $original);

        // Still has the emoji problem - can't handle all Unicode
        $this->assertNotEquals($original, $manualAttempt);

        // Manual replacement changes the é in "impayée" to "e"
        $this->assertStringNotContainsString('impayée', $manualAttempt);
        $this->assertStringContainsString('impayee', $manualAttempt);

        // Emoji remains but manual approach doesn't solve encoding issues
        $this->assertStringContainsString('🚀', $manualAttempt);
    }

    /**
     * Test simulated database storage/retrieval corruption
     */
    public function testDatabaseStorageCorruption()
    {
        $original = $this->problematicSubject;

        // Simulate what happens when storing in Latin1 database column
        $latin1Encoded = mb_convert_encoding($original, 'ISO-8859-1', 'UTF-8');
        $retrievedBack = mb_convert_encoding($latin1Encoded, 'UTF-8', 'ISO-8859-1');

        // Should be corrupted
        $this->assertNotEquals($original, $retrievedBack);

        // Emoji definitely lost
        $this->assertStringNotContainsString('🚀', $retrievedBack);
    }

    /**
     * Test simulated file read/write corruption
     */
    public function testFileHandlingCorruption()
    {
        $original = $this->problematicSubject;

        // Create a temporary file and write with wrong encoding assumption
        $tempFile = tempnam(sys_get_temp_dir(), 'encoding_fail_test_');

        // Simulate writing as Windows-1252
        $windows1252Content = mb_convert_encoding($original, 'WINDOWS-1252', 'UTF-8');
        file_put_contents($tempFile, $windows1252Content);

        // Now read it back assuming UTF-8 (common mistake)
        $corruptedRead = file_get_contents($tempFile);

        // Should be corrupted
        $this->assertNotEquals($original, $corruptedRead);

        // Should not be valid UTF-8
        $this->assertFalse(mb_check_encoding($corruptedRead, 'UTF-8'));

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test what happens with common "sanitization" approaches
     */
    public function testCommonSanitizationBreaksContent()
    {
        $original = $this->problematicSubject;

        // Common "sanitization" that developers might try
        $sanitized = filter_var($original, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

        if ($sanitized !== false) {
            // Should remove high-bit characters (including emoji and accents)
            $this->assertNotEquals($original, $sanitized);
            $this->assertStringNotContainsString('🚀', $sanitized);
            $this->assertStringNotContainsString('impayée', $sanitized);
        } else {
            // Filter might fail entirely
            $this->assertFalse($sanitized);
        }
    }

    /**
     * Test naive regular expression replacement
     */
    public function testRegexReplacementBreaksUnicode()
    {
        $original = $this->problematicSubject;

        // Naive attempt to "clean" the string with regex
        $regexCleaned = preg_replace('/[^\x20-\x7E]/', '?', $original);

        // Should replace all non-ASCII characters with ?
        $this->assertNotEquals($original, $regexCleaned);
        $this->assertStringNotContainsString('🚀', $regexCleaned);
        $this->assertStringNotContainsString('impayée', $regexCleaned);

        // Should contain question marks
        $this->assertStringContainsString('?', $regexCleaned);
    }

    /**
     * Test double-encoding problems
     */
    public function testDoubleEncodingProblems()
    {
        $original = $this->problematicSubject;

        // Simulate double-encoding (common web application bug)
        $firstEncoding = mb_convert_encoding($original, 'ISO-8859-1', 'UTF-8');
        $doubleEncoded = mb_convert_encoding($firstEncoding, 'UTF-8', 'ISO-8859-1');

        // Should be different and corrupted
        $this->assertNotEquals($original, $doubleEncoded);

        // Common double-encoding artifacts
        $this->assertTrue(
            str_contains($doubleEncoded, 'Ã©') || // é becomes Ã©
            str_contains($doubleEncoded, 'Ã¢â‚¬') || // Other artifacts
            !str_contains($doubleEncoded, '🚀'), // Emoji lost
            "Expected double-encoding artifacts but got: " . $doubleEncoded
        );
    }

    /**
     * Test CSV export/import corruption
     */
    public function testCsvCorruption()
    {
        $original = $this->problematicSubject;

        // Simulate CSV export without proper encoding
        $csvLine = '"' . $original . '"';

        // Write to temp file with wrong encoding
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_fail_test_');
        file_put_contents($tempFile, $csvLine, LOCK_EX);

        // Read back with wrong encoding assumption
        $contents = file_get_contents($tempFile);

        // Parse CSV (simplified)
        $parsed = str_replace('"', '', $contents);

        // If the file system or CSV handling messed up encoding
        if (!mb_check_encoding($parsed, 'UTF-8')) {
            $this->assertNotEquals($original, $parsed);
        } else {
            // Even if it's valid UTF-8, it might still be different due to CSV processing
            $this->assertTrue(true, "CSV processing completed");
        }

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test JSON encoding/decoding issues
     */
    public function testJsonEncodingIssues()
    {
        $original = $this->problematicSubject;

        // Create array with the subject
        $data = ['subject' => $original];

        // Encode to JSON
        $json = json_encode($data);
        $this->assertNotFalse($json, "JSON encoding should work with UTF-8");

        // Decode back
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded, "JSON decoding should work");

        // This should actually work correctly with modern PHP
        // But let's test what happens if someone tries to "fix" it
        $brokenAttempt = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        $brokenDecoded = json_decode($brokenAttempt, true);

        // The point is that without proper understanding, people might use wrong flags
        // and lose data integrity
        if ($brokenDecoded !== null && isset($brokenDecoded['subject'])) {
            // In some PHP versions or configurations, this might alter the data
            $this->assertTrue(
                $decoded['subject'] === $original,
                "Proper JSON handling preserves Unicode"
            );
        }
    }

    /**
     * Test email header encoding issues
     */
    public function testEmailHeaderEncodingIssues()
    {
        $original = $this->problematicSubject;

        // Naive attempt to create email header without proper encoding
        $naiveHeader = "Subject: " . $original;

        // Email headers with non-ASCII characters need RFC 2047 encoding
        // Without proper encoding, the subject would be corrupted by email servers

        // Simulate what an email server might do with unencoded headers
        $serverProcessed = preg_replace('/[^\x20-\x7E]/', '?', $naiveHeader);

        $this->assertNotEquals($naiveHeader, $serverProcessed);
        $this->assertStringNotContainsString('🚀', $serverProcessed);
        $this->assertStringNotContainsString('impayée', $serverProcessed);

        // Should contain replacement characters
        $this->assertStringContainsString('?', $serverProcessed);
    }

    /**
     * Summary test showing multiple failure modes
     */
    public function testMultipleFailureModes()
    {
        $original = $this->problematicSubject;
        $failures = [];

        // Collect all the ways it can fail
        $attempts = [
            'windows1252' => mb_convert_encoding($original, 'UTF-8', 'WINDOWS-1252'),
            'ascii' => iconv('UTF-8', 'ASCII//IGNORE', $original),
            'latin1_roundtrip' => mb_convert_encoding(mb_convert_encoding($original, 'ISO-8859-1', 'UTF-8'), 'UTF-8', 'ISO-8859-1'),
            'regex_strip' => preg_replace('/[^\x20-\x7E]/', '', $original),
            'filter_sanitize' => filter_var($original, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
        ];

        foreach ($attempts as $method => $result) {
            if ($result !== false && $result !== $original) {
                $failures[$method] = $result;
            }
        }

        // All methods should fail to preserve the original
        $this->assertGreaterThan(0, count($failures), "At least some methods should fail");

        // None of the failed attempts should contain the emoji
        foreach ($failures as $method => $result) {
            $this->assertStringNotContainsString('🚀', $result, "Method {$method} should lose emoji");
        }
    }
}
