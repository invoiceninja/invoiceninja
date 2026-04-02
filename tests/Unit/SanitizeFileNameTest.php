<?php

namespace Tests\Unit;

use App\Jobs\Util\UploadFile;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SanitizeFileNameTest extends TestCase
{
    private function sanitize(string $name): string
    {
        $method = new ReflectionMethod(UploadFile::class, 'sanitizeFileName');

        $instance = (new \ReflectionClass(UploadFile::class))
            ->newInstanceWithoutConstructor();

        return $method->invoke($instance, $name);
    }

    public function testForwardSlashesAreReplaced(): void
    {
        $this->assertEquals('path_to_file.pdf', $this->sanitize('path/to/file.pdf'));
    }

    public function testBackslashesAreReplaced(): void
    {
        $this->assertEquals('path_to_file.pdf', $this->sanitize('path\to\file.pdf'));
    }

    public function testDirectoryTraversalIsNeutralized(): void
    {
        $result = $this->sanitize('../../etc/passwd.pdf');

        $this->assertStringNotContainsString('..', $result);
        $this->assertStringNotContainsString('/', $result);
    }

    public function testAngleBracketsAreReplaced(): void
    {
        $this->assertEquals('file_name_.pdf', $this->sanitize('file<name>.pdf'));
    }

    public function testColonIsReplaced(): void
    {
        $this->assertEquals('file_name.pdf', $this->sanitize('file:name.pdf'));
    }

    public function testPipeIsReplaced(): void
    {
        $this->assertEquals('file_name.pdf', $this->sanitize('file|name.pdf'));
    }

    public function testQuestionMarkIsReplaced(): void
    {
        $this->assertEquals('file_name.pdf', $this->sanitize('file?name.pdf'));
    }

    public function testAsteriskIsReplaced(): void
    {
        $this->assertEquals('file_name.pdf', $this->sanitize('file*name.pdf'));
    }

    public function testDoubleQuotesAreReplaced(): void
    {
        $this->assertEquals('file_name.pdf', $this->sanitize('file"name.pdf'));
    }

    public function testControlCharactersAreReplaced(): void
    {
        $this->assertEquals('file_name.pdf', $this->sanitize("file\x00name.pdf"));
        $this->assertEquals('file_name.pdf', $this->sanitize("file\x0Aname.pdf"));
        $this->assertEquals('file_name.pdf', $this->sanitize("file\x1Fname.pdf"));
    }

    public function testLeadingDotsAreTrimmed(): void
    {
        $this->assertEquals('hidden.pdf', $this->sanitize('.hidden.pdf'));
    }

    public function testTrailingDotsAndSpacesAreTrimmed(): void
    {
        $this->assertEquals('file.pdf', $this->sanitize('file.pdf. '));
    }

    public function testCleanFileNameIsUnchanged(): void
    {
        $this->assertEquals('my-document_v2 (final).pdf', $this->sanitize('my-document_v2 (final).pdf'));
    }

    public function testUnicodeCharactersArePreserved(): void
    {
        $this->assertEquals('Rechnung-München.pdf', $this->sanitize('Rechnung-München.pdf'));
    }

    public function testMultipleIllegalCharactersCombined(): void
    {
        $this->assertEquals('file_name_with_illegal_chars_.pdf', $this->sanitize('file<name>with:illegal|chars?.pdf'));
    }

    public function testDoubleDotWithoutSlashes(): void
    {
        $result = $this->sanitize('file..name.pdf');

        $this->assertStringNotContainsString('..', $result);
    }
}
