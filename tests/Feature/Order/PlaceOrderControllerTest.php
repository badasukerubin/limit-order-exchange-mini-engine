<?php

use App\Enums\SideType;
use App\Enums\StatusType;
use App\Enums\SymbolType;
use App\Jobs\Order\MatchOrderJob;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function App\Helpers\format_currency;

beforeEach(function () {
    Queue::fake();
});

it('prevents access to placing orders for unauthenticated users', function () {
    $this->postJson(route('api.v1.orders.place'))
        ->assertStatus(401);

    Queue::assertNothingPushed();
});

it('creates a BUY order and reserves USD balance', function () {
    $user = User::factory()->create(['balance' => 10000]);
    $price = 50000;
    $amount = 0.1;

    $payload = [
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy->value,
        'price' => $price,
        'amount' => $amount,
    ];

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.orders.place'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', StatusType::Open->value);

    $user->refresh();
    expect($user->balance)->toEqual(5000.00);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy->value,
        'price' => format_currency($price),
        'amount' => format_currency($amount),
        'locked_amount' => format_currency($price * $amount),
        'status' => StatusType::Open->value,
    ]);

    $orderId = $response->json('data.id');

    Queue::assertPushed(MatchOrderJob::class, function ($job) use ($orderId) {
        return $job->orderId === $orderId;
    });
});

it('fails creating BUY order if insufficient USD balance', function () {
    $user = User::factory()->create(['balance' => 1000]);

    $payload = [
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy->value,
        'price' => 50000,
        'amount' => 0.1,
    ];

    $this->actingAs($user)
        ->postJson(route('api.v1.orders.place'), $payload)
        ->assertStatus(422);

    expect($user->balance)->toEqual(1000);

    $this->assertDatabaseMissing('orders', [
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy->value,
        'price' => format_currency(50000),
        'amount' => format_currency(0.1),
    ]);

    Queue::assertNothingPushed();
});

it('creates SELL order and locks assets', function () {
    $user = User::factory()->create(['balance' => 0]);

    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 1.0,
        'locked_amount' => 0,
    ]);

    $payload = [
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell->value,
        'price' => 50000,
        'amount' => 0.5,
    ];

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.orders.place'), $payload)
        ->assertCreated();

    $asset->refresh();
    expect($user->balance)->toEqual(0);

    $this->assertDatabaseHas('assets', [
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 0.5,
        'locked_amount' => 0.5,
    ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell->value,
        'price' => format_currency(50000),
        'amount' => format_currency(0.5),
        'locked_amount' => format_currency(0.5),
        'status' => StatusType::Open->value,
    ]);

    $orderId = $response->json('data.id');

    Queue::assertPushed(MatchOrderJob::class, function ($job) use ($orderId) {
        return $job->orderId === $orderId;
    });
});

it('fails creating SELL order if insufficient asset amount', function () {
    $user = User::factory()->create();

    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 0.1,
    ]);

    $payload = [
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell->value,
        'price' => 50000,
        'amount' => 1,
    ];

    $this->actingAs($user)
        ->postJson(route('api.v1.orders.place'), $payload)
        ->assertStatus(422);

    $this->assertDatabaseMissing('orders', [
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell->value,
        'price' => format_currency(50000),
        'amount' => format_currency(1),
    ]);

    Queue::assertNothingPushed();
});
