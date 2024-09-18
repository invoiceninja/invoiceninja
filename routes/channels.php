<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

use App\Models\User;

// Broadcast::channel('App.User.{id}', function ($user, $id) {
//    nlog($id);
   
//     return false;
//     // return (int) $user->id === (int) $id;
// });

Broadcast::channel('company-{company_key}', function (User $user, string $company_key) {
    return $user->company()->company_key === $company_key;
});

Broadcast::channel('company-${company.company_key}.{roomId}', function (User $user, int $roomId) {
    if ($user->canJoinRoom($roomId)) {
        return ['id' => $user->id, 'name' => $user->name];
    }
});

Broadcast::channel('company-{company_key}.invoices.{invoice_id}', function (User $user, string $company_key, string $invoice_id) {
    // @todo

    return true;
});
