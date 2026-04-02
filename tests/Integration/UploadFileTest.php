<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration;

use App\Jobs\Util\UploadFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *   App\Jobs\Util\UploadFile
 */
class UploadFileTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testFileUploadWorks()
    {
        $image = UploadedFile::fake()->image('avatar.jpg');

        $document = (new UploadFile(
            $image,
            UploadFile::IMAGE,
            $this->invoice->user,
            $this->invoice->company,
            $this->invoice
        ))->handle();

        $this->assertNotNull($document);
    }

    public function testSpecialCharactersAreSanitized()
    {
        $image = UploadedFile::fake()->create('file<name>with:illegal|chars?.pdf', 100, 'application/pdf');

        $document = (new UploadFile(
            $image,
            UploadFile::DOCUMENT,
            $this->invoice->user,
            $this->invoice->company,
            $this->invoice
        ))->handle();

        $this->assertNotNull($document);
        $this->assertStringNotContainsString('<', $document->name);
        $this->assertStringNotContainsString('>', $document->name);
        $this->assertStringNotContainsString(':', $document->name);
        $this->assertStringNotContainsString('|', $document->name);
        $this->assertStringNotContainsString('?', $document->name);
        $this->assertEquals('file_name_with_illegal_chars_.pdf', $document->name);
    }

    public function testQuotesAndAsterisksAreSanitized()
    {
        $image = UploadedFile::fake()->create('file"name*here.pdf', 100, 'application/pdf');

        $document = (new UploadFile(
            $image,
            UploadFile::DOCUMENT,
            $this->invoice->user,
            $this->invoice->company,
            $this->invoice
        ))->handle();

        $this->assertNotNull($document);
        $this->assertStringNotContainsString('"', $document->name);
        $this->assertStringNotContainsString('*', $document->name);
        $this->assertEquals('file_name_here.pdf', $document->name);
    }

    public function testDoubleDotSequenceIsSanitized()
    {
        $image = UploadedFile::fake()->create('file..name.pdf', 100, 'application/pdf');

        $document = (new UploadFile(
            $image,
            UploadFile::DOCUMENT,
            $this->invoice->user,
            $this->invoice->company,
            $this->invoice
        ))->handle();

        $this->assertNotNull($document);
        $this->assertStringNotContainsString('..', $document->name);
        $this->assertEquals('file_name.pdf', $document->name);
    }

    public function testCleanFileNameIsUnchanged()
    {
        $image = UploadedFile::fake()->create('my-document_v2 (final).pdf', 100, 'application/pdf');

        $document = (new UploadFile(
            $image,
            UploadFile::DOCUMENT,
            $this->invoice->user,
            $this->invoice->company,
            $this->invoice
        ))->handle();

        $this->assertNotNull($document);
        $this->assertEquals('my-document_v2 (final).pdf', $document->name);
    }
}
