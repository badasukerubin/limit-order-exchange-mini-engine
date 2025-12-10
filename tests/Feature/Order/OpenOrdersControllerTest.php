<?php

use App\Enums\StatusType;
use App\Enums\SymbolType;
use App\Models\Order;
use App\Models\User;

it('prevents access to orders for unauthenticated users', function () {
    $this->getJson(route('api.v1.orders.index'))
        ->assertStatus(401);
});

it('returns only open orders for a symbol', function () {
    $user = User::factory()->create();

    $openOrder1 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'status' => StatusType::Open->value,
    ]);

    $openOrder2 = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::ETH->value,
        'status' => StatusType::Open->value,
    ]);

    $closedOrder = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'status' => StatusType::Cancelled->value,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.v1.orders.index', ['symbol' => SymbolType::BTC->value]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['id' => $openOrder1->id])
        ->assertJsonMissing(['id' => $closedOrder->id]);
});

it('returns both buy and sell orders', function () {
    $user = User::factory()->create();

    $buyOrder = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'side' => 'buy',
        'status' => StatusType::Open->value,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'side' => 'sell',
        'status' => StatusType::Open->value,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.v1.orders.index', ['symbol' => SymbolType::BTC->value]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $buyOrder->id])
        ->assertJsonFragment(['id' => $sellOrder->id]);
});

it('returns validation error if symbol is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.orders.index'))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'The symbol query parameter is required.']);
});
