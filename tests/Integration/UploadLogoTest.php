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

use App\Models\Company;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class UploadLogoTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use MakesHash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Company::reguard();
    }

    public function testLogoUploadWorks()
    {
        Storage::fake('avatars');

        $data = [
            'company_logo' => UploadedFile::fake()->image('avatar.jpg'),
            'name' => 'TestCompany',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $data);

        $response->assertStatus(200);

        $acc = $response->json();

        $logo = $acc['data']['settings']['company_logo'];

        $logo_file = Storage::url($logo);

        $this->assertNotNull($logo_file);
    }

    public function testLogoUploadfailure()
    {
        Storage::fake('avatars');

        $data = [
            'company_logo' => '',
            'name' => 'TestCompany',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $data);

        //$acc = $response->json();

        $response->assertStatus(302);
    }

    public function testLogoUploadNoAttribute()
    {
        Storage::fake('avatars');

        $data = [
            'name' => 'TestCompany',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $data);

        $response->assertStatus(200);
    }
}
