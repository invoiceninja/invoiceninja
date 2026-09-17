<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Modules\Admin\Http\Controllers\SpamFiltersController;
use Tests\TestCase;

class SpamFiltersControllerTest extends TestCase
{
    private const CACHE_KEYS = [
        'banned_user_emails',
        'company_names',
        'spam_subject_keywords',
        'spam_body_keywords',
        'spam_username_keywords',
        'spam_emails',
        'spam_domains',
        'outbound_recipient_domains',
        'invalid_phone_codes',
        'docuninja_beta',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(SpamFiltersController::class)) {
            $this->markTestSkipped('Admin spam filters are not installed.');
        }

        $this->withoutMiddleware();
        Storage::fake('backup');

        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }

        parent::tearDown();
    }

    public function testBodyKeywordsAreNormalizedCachedAndPersisted(): void
    {
        $response = $this->post(route('admin.filters.store'), [
            'spam_body_keywords' => "  prize  \nYour subscription has expired.\nPRIZE\n\n",
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Spam filters updated successfully.');

        $this->assertSame(
            ['prize', 'Your subscription has expired.'],
            Cache::get('spam_body_keywords')
        );

        Storage::disk('backup')->assertExists('spam-filters.json');

        $persisted_filters = json_decode(
            Storage::disk('backup')->get('spam-filters.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            ['prize', 'Your subscription has expired.'],
            $persisted_filters['spam_body_keywords']
        );
    }

    public function testLegacyPersistenceHydratesMissingBodyKeywordsAsAnEmptyList(): void
    {
        Storage::disk('backup')->put('spam-filters.json', json_encode([
            'spam_subject_keywords' => ['legacy subject'],
        ], JSON_THROW_ON_ERROR));

        Cache::put('spam_body_keywords', ['stale body phrase']);

        $response = $this->post(route('admin.filters.refresh'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame([], Cache::get('spam_body_keywords'));
        $this->assertSame([], Cache::get('invalid_phone_codes'));
        $this->assertSame(['legacy subject'], Cache::get('spam_subject_keywords'));
    }

    public function testInvalidPhoneCodesAreNormalizedCachedAndPersisted(): void
    {
        $response = $this->post(route('admin.filters.store'), [
            'invalid_phone_codes' => "  +61  \n+4470\n+61\n\n",
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Spam filters updated successfully.');

        $this->assertSame(['+61', '+4470'], Cache::get('invalid_phone_codes'));

        $persisted_filters = json_decode(
            Storage::disk('backup')->get('spam-filters.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertSame(['+61', '+4470'], $persisted_filters['invalid_phone_codes']);
    }

    public function testSpamFiltersViewDisplaysBodyKeywordsField(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::put($key, []);
        }

        Cache::put('spam_body_keywords', ['Confirm your account now.']);
        Cache::put('invalid_phone_codes', ['+61']);

        $this->withoutVite();
        view()->share('errors', new ViewErrorBag());
        $this->actingAs(User::factory()->make([
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]));

        $response = $this->get(route('admin.filters.index'));

        $response->assertOk();
        $response->assertSee('Spam body keywords / sentences');
        $response->assertSee('name="spam_body_keywords"', false);
        $response->assertSee('class="flex flex-row flex-wrap my-6"', false);
        $response->assertSee('class="w-1/3"', false);
        $response->assertDontSee('md:grid-cols-2', false);
        $response->assertSee('Confirm your account now.');
        $response->assertSee('Blocked phone codes');
        $response->assertSee('name="invalid_phone_codes"', false);
        $response->assertSee('+61');
    }

    public function testSpamFilterListsMustBeText(): void
    {
        $response = $this->post(route('admin.filters.store'), [
            'spam_body_keywords' => ['not', 'text'],
            'invalid_phone_codes' => ['not', 'text'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['spam_body_keywords', 'invalid_phone_codes']);
        Storage::disk('backup')->assertMissing('spam-filters.json');
    }
}
