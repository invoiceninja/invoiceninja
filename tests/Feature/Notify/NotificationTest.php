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

namespace Tests\Feature\Notify;

use Tests\TestCase;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Product;
use Tests\MockAccountData;
use App\Models\CompanyUser;
use Illuminate\Support\Str;
use App\Models\CompanyToken;
use App\Models\InvoiceInvitation;
use App\DataMapper\CompanySettings;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 * @covers App\Utils\Traits\Notifications\UserNotifies
 */
class NotificationTest extends TestCase
{
    use UserNotifies;
    use MockAccountData;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }


    public function testEntityViewedNotificationWithEntityLate()
    {
        // ['all_notifications', 'all_user_notifications', 'invoice_created_user', 'invoice_sent_user', 'invoice_viewed_user', 'invoice_late_user'];
        $u = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->safeEmail(),
            'confirmation_code' => uniqid("st", true),
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = $u->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $u->companies()->attach($this->company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $company_user = CompanyUser::where('user_id', $u->id)->where('company_id', $this->company->id)->first();

        $notifications = new \stdClass();
        $notifications->email = ["invoice_late_user","quote_approved_user"];
        $company_user->update(['notifications' => (array)$notifications]);

        $i = Invoice::factory()->create([
            'user_id' => $u->id,
            'company_id' => $this->company->id,
            'number' => uniqid("st", true),
            'client_id' => $this->client->id,
        ]);

        $invitation = InvoiceInvitation::factory()->create([
            'user_id' => $u->id,
            'company_id' => $this->company->id,
            'invoice_id' => $i->id,
            'client_contact_id' => $this->client->contacts->first()->id,
        ]);

        $methods = $this->findUserNotificationTypes($invitation, $company_user, 'invoice', ['all_notifications', 'invoice_late_user']);

        $this->assertCount(1, $methods);

        $methods = $this->findUserNotificationTypes($invitation, $company_user, 'invoice', ['all_notifications', 'invoice_viewed', 'invoice_viewed_all']);

        $this->assertCount(0, $methods);
    }


    public function testNotificationFound()
    {
        $notifications = new \stdClass();
        $notifications->email = ["inventory_all"];

        $this->user->company_users()->where('company_id', $this->company->id)->update(['notifications' => (array)$notifications]);

        $this->assertTrue(property_exists($this->cu->notifications, 'email'));

        $p = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id
        ]);

        $notification_users = $this->filterUsersByPermissions($this->company->company_users, $p, ['inventory_all']);
        $this->assertCount(1, $notification_users->toArray());

        $notification_users = $this->filterUsersByPermissions($this->company->company_users, $p, ['inventory_user']);
        $this->assertCount(0, $notification_users->toArray());

        $notification_users = $this->filterUsersByPermissions($this->company->company_users, $p, ['inventory_user','invalid notification']);
        $this->assertCount(0, $notification_users->toArray());
    }

    public function testAllNotificationsFires()
    {
        $notifications = new \stdClass();
        $notifications->email = ["all_notifications"];

        $p = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id
        ]);

        $this->user->company_users()->where('company_id', $this->company->id)->update(['notifications' => (array)$notifications]);

        $notification_users = $this->filterUsersByPermissions($this->company->company_users, $p, ['inventory_all']);
        $this->assertCount(1, $notification_users->toArray());
    }

    public function testAllNotificationsFiresForUser()
    {
        $notifications = new \stdClass();
        $notifications->email = ["all_user_notifications"];

        $p = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id
        ]);

        $this->user->company_users()->where('company_id', $this->company->id)->update(['notifications' => (array)$notifications]);

        $notification_users = $this->filterUsersByPermissions($this->company->company_users, $p, ['all_user_notifications']);
        $this->assertCount(1, $notification_users->toArray());
    }

    public function testAllNotificationsDoesNotFiresForUser()
    {
        $u = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->safeEmail(),
            'confirmation_code' => uniqid("st", true),
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = $u->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $u->companies()->attach($this->company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $p = Product::factory()->create([
            'user_id' => $u->id,
            'company_id' => $this->company->id
        ]);


        $notifications = new \stdClass();
        $notifications->email = ["all_user_notifications"];
        $this->user->company_users()->where('company_id', $this->company->id)->update(['notifications' => (array)$notifications]);

        $methods = $this->findUserEntityNotificationType($p, $this->cu, ['all_user_notifications']);
        $this->assertCount(0, $methods);

        $methods = $this->findUserEntityNotificationType($p, $this->cu, ['all_notifications']);
        $this->assertCount(0, $methods);

        $notifications = [];
        $notifications['email'] = ["all_notifications"];

        $cu = CompanyUser::where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        $cu->notifications = $notifications;
        $cu->save();

        $methods = $this->findUserEntityNotificationType($p, $cu, ["all_notifications"]);

        $this->assertCount(1, $methods);

        $notifications = [];
        $notifications['email'] = ["inventory_user"];

        $cu = CompanyUser::where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        $cu->notifications = $notifications;
        $cu->save();

        $methods = $this->findUserEntityNotificationType($p, $cu, ["all_notifications"]);
        $this->assertCount(0, $methods);

        $p = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id
        ]);

        $methods = $this->findUserEntityNotificationType($p, $cu, ['inventory_user']);

        // nlog($methods);

        $this->assertCount(1, $methods);

        $this->assertTrue($this->checkNotificationExists($cu, $p, ['inventory_all', 'inventory_user']));
    }
}
