<?php

use App\Enums\SideType;
use App\Enums\StatusType;
use App\Enums\SymbolType;
use App\Events\Orders\OrderMatchedEvent;
use App\Http\Services\Order\MatchOrderService;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Event;

use function App\Helpers\format_currency;

beforeEach(function () {
    Event::fake();
});

it('matches a valid BUY and SELL order fully', function () {
    $buyer = User::factory()->create(['balance' => 10000]);
    $seller = User::factory()->create(['balance' => 0]);

    // Seller has BTC
    $sellerAsset = Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 1.0,
        'locked_amount' => 0,
    ]);

    // Buy order - locks USD
    $buy = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy,
        'price' => 20000,
        'amount' => 0.1,
        'locked_amount' => 2000,
        'status' => StatusType::Open,
    ]);

    // Sell order - locks asset
    $sell = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell,
        'price' => 19000,
        'amount' => 0.1,
        'status' => StatusType::Open,
    ]);

    $sellerAsset->update([
        'locked_amount' => 0.1,
        'amount' => 0.9,
    ]);

    $matchOrderService = app(MatchOrderService::class);
    $matchOrderService->handle($buy);

    $buy->refresh();
    $sell->refresh();
    $buyer->refresh();
    $seller->refresh();
    $sellerAsset->refresh();

    expect($buy->status)->toEqual(StatusType::Filled);
    expect($sell->status)->toEqual(StatusType::Filled);

    // Buyer should recieve BTC
    $this->assertDatabaseHas('assets', [
        'user_id' => $buyer->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => format_currency(0.1),
        'locked_amount' => format_currency(0),
    ]);

    // Seller should have BTC unlocked and USD balance increased
    $this->assertDatabaseHas('assets', [
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => format_currency(0.9),
        'locked_amount' => format_currency(0),
    ]);

    // Seller recieves USD minus fee
    $expectedTotal = bcmul('19000', '0.1', 8);
    $expectedFee = bcmul($expectedTotal, '0.015', 8);
    $expectedNet = bcsub($expectedTotal, $expectedFee, 8);
    expect($seller->balance)->toEqual($expectedNet);

    // A trade record is created
    $this->assertDatabaseHas('trades', [
        'symbol' => SymbolType::BTC->value,
        'price' => format_currency(20000),
        'amount' => format_currency(0.1),
        'fee' => format_currency($expectedFee),
        'total' => format_currency($expectedTotal),
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
    ]);

    // OrderMatchedEvent is dispatched
    Event::assertDispatched(OrderMatchedEvent::class, function ($event) use ($buy, $sell, $expectedTotal, $expectedFee) {
        $trade = $event->trade;

        return $trade->buyer_id === $buy->user_id
            && $trade->seller_id === $sell->user_id
            && $trade->symbol === $buy->symbol
            && bccomp($trade->price, $buy->price, 8) === 0
            && bccomp($trade->amount, $buy->amount, 8) === 0
            && bccomp($trade->total, $expectedTotal, 8) === 0
            && bccomp($trade->fee, $expectedFee, 8) === 0;
    });
})->only();

it('does not match if amounts differ (no partials allowed)', function () {
    $buyer = User::factory()->create(['balance' => 10000]);
    $seller = User::factory()->create(['balance' => 0]);

    // Seller has BTC
    $sellerAsset = Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 1.0,
        'locked_amount' => 0,
    ]);

    // Buy order - locks USD
    $buy = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy,
        'price' => 20000,
        'amount' => 0.2,
        'locked_amount' => 4000,
        'status' => StatusType::Open,
    ]);

    // Sell order - locks asset
    $sell = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell,
        'price' => 18000,
        'amount' => 0.1,
        'status' => StatusType::Open,
    ]);

    $sellerAsset->update([
        'locked_amount' => 0.1,
        'amount' => 0.9,
    ]);

    $matchOrderService = app(MatchOrderService::class);
    $matchOrderService->handle($buy);

    $buy->refresh();
    $sell->refresh();

    expect($buy->status)->toEqual(StatusType::Open);
    expect($sell->status)->toEqual(StatusType::Open);

    // No trade should be created
    $this->assertDatabaseMissing('trades', [
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
    ]);

    // No event dispatched
    Event::assertNotDispatched(OrderMatchedEvent::class);
})->only();

it('does nothing when counter order does not meet price rule', function () {
    $buyer = User::factory()->create(['balance' => 5000]);
    $seller = User::factory()->create(['balance' => 0]);

    // Seller has BTC
    $sellerAsset = Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 10,
        'locked_amount' => 0,
    ]);

    // Buy order - locks USD
    $buy = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy,
        'price' => 1000, // buyer wants to buy at 1000
        'amount' => 1,
        'locked_amount' => 1000,
        'status' => StatusType::Open,
    ]);

    // Sell order - locks asset
    $sell = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell,
        'price' => 1200, // seller wants to sell at 1200
        'amount' => 1,
        'status' => StatusType::Open,
    ]);

    $sellerAsset->update([
        'locked_amount' => 1,
        'amount' => 9,
    ]);

    $matchOrderService = app(MatchOrderService::class);
    $matchOrderService->handle($buy);

    $buy->refresh();
    $sell->refresh();

    expect($buy->status)->toEqual(StatusType::Open);
    expect($sell->status)->toEqual(StatusType::Open);

    // No trade should be created
    $this->assertDatabaseMissing('trades', [
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
    ]);

    // No event dispatched
    Event::assertNotDispatched(OrderMatchedEvent::class);
})->only();

it('is idempotent and safe to run multiple times', function () {
    $buyer = User::factory()->create(['balance' => 5000]);
    $seller = User::factory()->create(['balance' => 0]);

    // Seller has BTC
    $sellerAsset = Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 1.0,
        'locked_amount' => 0,
    ]);

    // Buy order - locks USD
    $buy = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Buy,
        'price' => 25000,
        'amount' => 0.1,
        'locked_amount' => 2500,
        'status' => StatusType::Open,
    ]);

    // Sell order - locks asset
    $sell = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'side' => SideType::Sell,
        'price' => 20000,
        'amount' => 0.1,
        'status' => StatusType::Open,
    ]);

    $sellerAsset->update([
        'locked_amount' => 0.1,
        'amount' => 0.9,
    ]);

    $matchOrderService = app(MatchOrderService::class);

    // First match
    $matchOrderService->handle($buy);

    // Attempt to match again
    $matchOrderService->handle($buy);

    $buy->refresh();
    $sell->refresh();
    $buyer->refresh();
    $seller->refresh();
    $sellerAsset->refresh();

    expect($buy->status)->toEqual(StatusType::Filled);
    expect($sell->status)->toEqual(StatusType::Filled);

    // Buyer should recieve BTC
    $this->assertDatabaseHas('assets', [
        'user_id' => $buyer->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => format_currency(0.1),
        'locked_amount' => format_currency(0),
    ]);

    // Seller should have BTC unlocked and USD balance increased
    $this->assertDatabaseHas('assets', [
        'user_id' => $seller->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => format_currency(0.9),
        'locked_amount' => format_currency(0),
    ]);

    // Seller recieves USD minus fee
    $expectedTotal = bcmul('20000', '0.1', 8);
    $expectedFee = bcmul($expectedTotal, '0.015', 8);
    $expectedNet = bcsub($expectedTotal, $expectedFee, 8);
    expect($seller->balance)->toEqual($expectedNet);

    // A trade record is created
    $this->assertDatabaseHas('trades', [
        'symbol' => SymbolType::BTC->value,
        'price' => format_currency(25000),
        'amount' => format_currency(0.1),
        'fee' => format_currency($expectedFee),
        'total' => format_currency($expectedTotal),
        'buyer_id' => $buyer->id,
        'seller_id' => $seller->id,
    ]);

    // OrderMatchedEvent is dispatched only once
    Event::assertDispatched(OrderMatchedEvent::class, 1);
})->only();
