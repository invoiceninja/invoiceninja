<?php

namespace Tests\Unit;

use App\Factory\UserFactory;
use App\Transformers\UserTransformer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class UserLastLoginTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testAUserWhoHasNeverLoggedInIsCreatedWithANullLastLogin(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');

        $user = UserFactory::create(1);

        $this->assertNull($user->last_login);
    }

    public function testTransformerRepresentsANullLastLoginAsZero(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');
        $user = UserFactory::create(1);
        $user->last_login = null;
        $user->setRelation('passkey_credentials', new EloquentCollection());

        $payload = (new UserTransformer())->transform($user);

        $this->assertSame(0, $payload['last_login']);
    }
}
