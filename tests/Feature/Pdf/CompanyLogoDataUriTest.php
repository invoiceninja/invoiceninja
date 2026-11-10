<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Pdf;

use App\Models\Company;
use App\Services\Pdf\ImageFetcher;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * On a Docker (or LOCAL_DOWNLOAD) install, `$company.logo` is the logo read
 * from storage and inlined by CompanyPresenter::logoDocker(). The JSON (block)
 * designer prints a logo block through ImageFetcher::inline(), which only
 * accepts a well-formed `data:image/...;base64,<payload>` URI — so the logo
 * must come out in that exact shape, or the block prints an empty <img>.
 */
class CompanyLogoDataUriTest extends TestCase
{
    private const JPEG = "\xFF\xD8\xFF\xE0" . "\x00\x10JFIF";

    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.is_docker' => true]);
        Storage::fake(config('filesystems.default'));
    }

    private function companyWithStoredLogo(): array
    {
        $company = new Company();
        $company->company_key = 'testcompanykey';

        Storage::put('testcompanykey/logo.jpg', self::JPEG);

        $settings = new \stdClass();
        $settings->company_logo = 'https://example.com/storage/testcompanykey/logo.jpg';

        return [$company, $settings];
    }

    public function test_docker_logo_is_a_well_formed_data_uri(): void
    {
        [$company, $settings] = $this->companyWithStoredLogo();

        $logo = $company->present()->logo_base64($settings);

        $this->assertSame('data:image/png;base64,' . base64_encode(self::JPEG), $logo);
    }

    public function test_docker_logo_is_printed_by_the_block_designer(): void
    {
        [$company, $settings] = $this->companyWithStoredLogo();

        $logo = $company->present()->logo_base64($settings);

        // What JsonToSectionsAdapter::convertImageBlock() puts in the logo
        // block's src; null there prints `src=""`.
        $this->assertNotNull((new ImageFetcher())->inline($logo));
    }
}
