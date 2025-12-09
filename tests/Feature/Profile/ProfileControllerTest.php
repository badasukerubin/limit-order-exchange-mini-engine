<?php

it('prevents access to transactions for unauthenticated users', function () {
    $this->getJson(route('api.v1.transactions.index'))
        ->assertStatus(401);
})->only();
