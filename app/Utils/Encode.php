<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

class Encode
{

    /**
     * Convert string content to UTF-8
     * Safe for emojis, file content, and any encoding issues
     */
    public static function convert(string $contents): string
    {
 
        // Check for different UTF BOMs and handle accordingly
        $bomResult = self::detectAndHandleUTFEncoding($contents);
        if ($bomResult !== null) {
            return $bomResult;
        }

        // Remove BOM if present (for UTF-8 BOM)
        $contents = self::removeBOM($contents);

        // Check if it's clean UTF-8 first (no conversion needed)
        // This handles emojis, accented characters, and any valid UTF-8 content
        if (mb_check_encoding($contents, 'UTF-8') && self::isValidConversion($contents)) {
            return $contents;
        }

        // Method 1: Try Windows-1252 conversion               
        $contextContents = $contents;
        if ($contextContents !== false) {
            $contextContents = self::removeBOM($contextContents);
            $converted = mb_convert_encoding($contextContents, 'UTF-8', 'WINDOWS-1252');
            if (self::isValidConversion($converted)) {
                return $converted;
            }
        }

        // Method 2: Binary conversion
        $binaryContents = $contents;
        
        $binaryContents = self::removeBOM($binaryContents);
        
        // Check if this looks like Windows-1252 by looking for problem bytes
        if (self::containsWindows1252Bytes($binaryContents)) {
            $converted = mb_convert_encoding($binaryContents, 'UTF-8', 'WINDOWS-1252');
            if (self::isValidConversion($converted)) {
                return $converted;
            }
        }

        // Method 3: Fix corrupted UTF-8 replacement characters
        if ($contents !== false) {
            $fixed = self::fixCorruptedWindows1252($contents);
            if (self::isValidConversion($fixed)) {
                return $fixed;
            }
        }

        // Method 4: Try different encoding auto-detection with broader list
        if ($contents !== false) {
            $encodings = ['WINDOWS-1252', 'ISO-8859-1', 'ISO-8859-15', 'CP1252'];
            foreach ($encodings as $encoding) {
                $converted = mb_convert_encoding($contents, 'UTF-8', $encoding);
                if (self::isValidConversion($converted)) {
                    return $converted;
                }
            }
        }

        // Fallback: return original contents
        return $contents ?: '';
    }

    /**
     * Detect and handle UTF-16 and UTF-32 encodings based on BOM
     */
    private static function detectAndHandleUTFEncoding(string $data): ?string
    {
        // UTF-32 BE BOM
        if (substr($data, 0, 4) === "\x00\x00\xFE\xFF") {
            $withoutBOM = substr($data, 4);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-32BE');
        }
        
        // UTF-32 LE BOM
        if (substr($data, 0, 4) === "\xFF\xFE\x00\x00") {
            $withoutBOM = substr($data, 4);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-32LE');
        }
        
        // UTF-16 BE BOM
        if (substr($data, 0, 2) === "\xFE\xFF") {
            $withoutBOM = substr($data, 2);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-16BE');
        }
        
        // UTF-16 LE BOM
        if (substr($data, 0, 2) === "\xFF\xFE") {
            $withoutBOM = substr($data, 2);
            return mb_convert_encoding($withoutBOM, 'UTF-8', 'UTF-16LE');
        }
        
        // Try to detect UTF-16/32 without BOM (heuristic approach)
        $length = strlen($data);
        
        // UTF-32 detection (every 4th byte pattern)
        if ($length >= 8 && $length % 4 === 0) {
            $nullCount = 0;
            for ($i = 0; $i < min(100, $length); $i += 4) {
                if ($data[$i] === "\x00" && $data[$i + 1] === "\x00" && $data[$i + 2] === "\x00") {
                    $nullCount++;
                }
            }
            if ($nullCount > 5) { // Likely UTF-32LE
                return mb_convert_encoding($data, 'UTF-8', 'UTF-32LE');
            }
        }
        
        // UTF-16 detection (every 2nd byte pattern)
        if ($length >= 4 && $length % 2 === 0) {
            $nullCount = 0;
            for ($i = 0; $i < min(100, $length); $i += 2) {
                if ($data[$i + 1] === "\x00") {
                    $nullCount++;
                }
            }
            if ($nullCount > 10) { // Likely UTF-16LE
                return mb_convert_encoding($data, 'UTF-8', 'UTF-16LE');
            }
            
            // Check for UTF-16BE
            $nullCount = 0;
            for ($i = 0; $i < min(100, $length); $i += 2) {
                if ($data[$i] === "\x00") {
                    $nullCount++;
                }
            }
            if ($nullCount > 10) { // Likely UTF-16BE
                return mb_convert_encoding($data, 'UTF-8', 'UTF-16BE');
            }
        }
        
        return null;
    }

    /**
     * Remove BOM (Byte Order Mark) from the beginning of a string
     */
    private static function removeBOM(string $data): string
    {
        // UTF-8 BOM
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            return substr($data, 3);
        }
        
        // UTF-16 BE BOM
        if (substr($data, 0, 2) === "\xFE\xFF") {
            return substr($data, 2);
        }
        
        // UTF-16 LE BOM
        if (substr($data, 0, 2) === "\xFF\xFE") {
            return substr($data, 2);
        }
        
        // UTF-32 BE BOM
        if (substr($data, 0, 4) === "\x00\x00\xFE\xFF") {
            return substr($data, 4);
        }
        
        // UTF-32 LE BOM
        if (substr($data, 0, 4) === "\xFF\xFE\x00\x00") {
            return substr($data, 4);
        }
        
        return $data;
    }

    private static function containsWindows1252Bytes(string $data): bool
    {
        // Check for Windows-1252 specific bytes in 0x80-0x9F range
        $windows1252Bytes = [0x80, 0x82, 0x83, 0x84, 0x85, 0x86, 0x87, 0x88, 0x89, 0x8A, 0x8B, 0x8C, 0x8E, 0x91, 0x92, 0x93, 0x94, 0x95, 0x96, 0x97, 0x98, 0x99, 0x9A, 0x9B, 0x9C, 0x9E, 0x9F];
        
        foreach ($windows1252Bytes as $byte) {
            if (strpos($data, chr($byte)) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function fixCorruptedWindows1252(string $data): string
    {
        // Map of UTF-8 replacement sequences back to proper characters
        $replacements = [
            "\xEF\xBF\xBD" => "\u{2019}", // Most common: right single quote (0x92) - use smart quote
            // Add more mappings as needed based on your data
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $data);
    }

    private static function isValidConversion(string $data): bool
    {
        // Check if conversion was successful:
        // 1. Must be valid UTF-8
        // 2. Must NOT contain replacement characters (indicating corruption)
        // 3. Additional check for double-encoded replacement
        return mb_check_encoding($data, 'UTF-8') && 
               !str_contains($data, "\xEF\xBF\xBD") &&  // UTF-8 replacement character bytes
               !str_contains($data, 'ï¿½'); // Double-encoded replacement character
    }

}