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

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('loan-chat.thread.{threadId}', function ($user, $threadId) {
    return ! empty($user);
});

Broadcast::channel('loan-chat.customer.{customerId}', function ($user, $customerId) {
    return ! empty($user) && ((int) $user->id === (int) $customerId || method_exists($user, 'can'));
});

Broadcast::channel('loan-chat.staff.{staffId}', function ($user, $staffId) {
    return ! empty($user) && ((int) $user->id === (int) $staffId || (method_exists($user, 'can') && $user->can('loan_management.chat.admin')));
});
