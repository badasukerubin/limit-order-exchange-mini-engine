<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    ds($user->id, $id);

    return (int) $user->id === (int) $id;
});
