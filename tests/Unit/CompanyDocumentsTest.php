<?php

namespace Tests\Unit;

use App\Jobs\Util\UploadFile;
use App\Models\Document;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

class CompanyDocumentsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }


    public function testCompanyDocumentExists()
    {
        $original_count = Document::whereCompanyId($this->company->id)->count();

        $image = UploadedFile::fake()->image('avatar.jpg');

        $document = UploadFile::dispatchNow(
            $image, UploadFile::IMAGE, $this->user, $this->company, $this->invoice
        );

        $this->assertNotNull($document);

        $this->assertGreaterThan($original_count, Document::whereCompanyId($this->company->id)->count());

        $company_key = $this->company->company_key;

        $this->company->delete();

        $this->assertEquals(0, Document::whereCompanyId($this->company->id)->count());

        $path = sprintf('%s/%s', storage_path('app/public'), $company_key);

        $this->assertFalse(file_exists($path));
    }
}
