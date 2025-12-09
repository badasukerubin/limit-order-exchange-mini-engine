<?php

use App\Enums\SideType;
use App\Enums\StatusType;
use App\Enums\SymbolType;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;

it('prevents access to cancelling orders for unauthenticated users', function () {
    $this->postJson(route('api.v1.orders.cancel'))
        ->assertStatus(401);
});

it('cancels a BUY order and refunds user balance', function () {
    $user = User::factory()->create([
        'balance' => 10000,
    ]);

    $cost = bcmul(0.1, 20000, 8);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'side' => 'buy',
        'symbol' => 'BTC',
        'price' => 20000,
        'amount' => 0.1,
        'locked_amount' => $cost,
        'status' => StatusType::Open->value,
    ]);

    $user->decrement('balance', $cost);

    $this->actingAs($user)
        ->postJson(route('api.v1.orders.cancel', ['order' => $order->id]))
        ->assertOk()
        ->assertJson([
            'data' => [
                'status' => StatusType::Cancelled->value,
            ],
        ]);

    // Balance should be refunded
    expect($user->fresh()->balance)->toEqual(10000);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => StatusType::Cancelled->value,
    ]);
});

it('cancels a SELL order and unlocks user asset', function () {
    $user = User::factory()->create();
    $amountToRelease = 0.5;

    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 1.0,
        'locked_amount' => $amountToRelease,
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'side' => SideType::Sell->value,
        'symbol' => SymbolType::BTC->value,
        'price' => 30000,
        'amount' => $amountToRelease,
        'locked_amount' => $amountToRelease,
        'status' => StatusType::Open->value,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.v1.orders.cancel', ['order' => $order->id]))
        ->assertOk()
        ->assertJson([
            'data' => [
                'status' => StatusType::Cancelled->value,
            ],
        ]);

    // Asset locked amount should be released
    expect($asset->fresh()->amount)->toEqual(bcadd(1.0, $amountToRelease, 8));
    expect($asset->fresh()->locked_amount)->toEqual(0);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => StatusType::Cancelled->value,
    ]);
});

it('prevents cancelling an order that is not open', function () {
    $user = User::factory()->create();

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => StatusType::Filled->value,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.v1.orders.cancel', ['order' => $order->id]))
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Only open orders can be cancelled',
        ]);
});

it('prevents cancelling an order that belongs to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $order = Order::factory()->create([
        'user_id' => $otherUser->id,
        'status' => StatusType::Open->value,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.v1.orders.cancel', ['order' => $order->id]))
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'You can only cancel your own orders',
        ]);
});

it('prevents cancelling a filled order', function () {
    $user = User::factory()->create();

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => StatusType::Filled->value,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.v1.orders.cancel', ['order' => $order->id]))
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Only open orders can be cancelled',
        ]);
});
