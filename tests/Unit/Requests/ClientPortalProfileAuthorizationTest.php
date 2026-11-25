<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ClientPortal\UpdateClientRequest;
use App\Http\Requests\ClientPortal\UpdateContactRequest;
use App\Http\Requests\Request;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientPortalProfileAuthorizationTest extends TestCase
{
    #[DataProvider('profileRequests')]
    public function testAuthorizesBoundContactWithStaffDefaultGuard(string $request_class): void
    {
        $contact = (new ClientContact())->forceFill(['id' => 202]);
        $this->actingAs($contact, 'contact');
        $this->actingAs((new User())->forceFill(['id' => 101]), 'user');

        $request = $this->profileRequest($request_class, clone $contact);

        $this->assertTrue($request->authorize());
    }

    #[DataProvider('profileRequests')]
    public function testRejectsContactWhoseIdMatchesStaffUser(string $request_class): void
    {
        $this->actingAs((new ClientContact())->forceFill(['id' => 202]), 'contact');
        $this->actingAs((new User())->forceFill(['id' => 101]), 'user');

        $request = $this->profileRequest($request_class, (new ClientContact())->forceFill(['id' => 101]));

        $this->assertFalse($request->authorize());
    }

    #[DataProvider('profileRequests')]
    public function testRejectsStaffWithoutAuthenticatedContact(string $request_class): void
    {
        $this->actingAs((new User())->forceFill(['id' => 101]), 'user');

        $request = $this->profileRequest($request_class, (new ClientContact())->forceFill(['id' => 101]));

        $this->assertFalse($request->authorize());
    }

    #[DataProvider('profileRequests')]
    public function testRejectsUnauthenticatedRequest(string $request_class): void
    {
        $request = $this->profileRequest($request_class, (new ClientContact())->forceFill(['id' => 202]));

        $this->assertFalse($request->authorize());
    }

    public static function profileRequests(): array
    {
        return [
            'contact profile' => [UpdateContactRequest::class],
            'client profile' => [UpdateClientRequest::class],
        ];
    }

    private function profileRequest(string $request_class, ClientContact $contact): Request
    {
        // Ownership must not depend on the contact's position in the URL.
        $route = new Route('PUT', 'nested/client/profile/{client_contact}/edit', static fn () => null);
        $route->bind(HttpRequest::create('/nested/client/profile/unused/edit', 'PUT'));
        $route->setParameter('client_contact', $contact);

        $request = new $request_class();
        $request->setRouteResolver(static fn () => $route);
        $request->setUserResolver(static fn ($guard = null) => auth()->guard($guard)->user());

        return $request;
    }
}
