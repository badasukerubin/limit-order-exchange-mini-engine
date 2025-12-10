<?php

namespace App\Http\Services\Order;

use App\Enums\SideType;
use App\Enums\StatusType;
use App\Events\Orders\OrderMatchedEvent;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MatchOrderService
{
    private const COMMISSION_RATE = 0.015;

    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (! $lockedOrder || $lockedOrder->status !== StatusType::Open) {
                return $order->fresh();
            }

            $side = $lockedOrder->side;
            $counterSide = $side === SideType::Buy ? SideType::Sell : SideType::Buy;
            $operator = $side === SideType::Buy ? '<=' : '>=';
            $orderBy = $side === SideType::Buy ? 'asc' : 'desc';
            $symbol = $lockedOrder->symbol;

            $counterOrder = Order::where('symbol', $symbol)
                ->where('side', $counterSide)
                ->where('status', StatusType::Open)
                ->where('price', $operator, $lockedOrder->price)
                ->orderBy('price', $orderBy)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $counterOrder || bccomp($lockedOrder->amount, $counterOrder->amount, 8) !== 0) {
                return $order->fresh();
            }

            $this->handleMatch($lockedOrder, $counterOrder);

            return $lockedOrder->fresh();
        });
    }

    public function handleMatch(Order $order, Order $counterOrder): void
    {
        $buyerOrder = $order->side === SideType::Buy ? $order : $counterOrder;
        $sellerOrder = $order->side === SideType::Sell ? $order : $counterOrder;

        /** @var array{amount:string,fee:string,total:string} $orderData */
        $orderData = [];
        $price = $counterOrder->price;
        $orderData['amount'] = $counterOrder->amount;
        $orderData['total'] = bcmul($price, $orderData['amount'], 8);
        $orderData['fee'] = bcmul($orderData['total'], self::COMMISSION_RATE, 8);

        DB::transaction(function () use ($buyerOrder, $sellerOrder, $orderData) {
            $buyer = $buyerOrder->user()->lockForUpdate()->first();
            $seller = $sellerOrder->user()->lockForUpdate()->first();

            $buyerAsset = Asset::firstOrCreate(
                ['user_id' => $buyer->id, 'symbol' => $buyerOrder->symbol],
                ['amount' => 0, 'locked_amount' => 0]
            );

            $sellerAsset = Asset::where('user_id', $seller->id)
                ->where('symbol', $sellerOrder->symbol)
                ->lockForUpdate()
                ->first();

            $trade = $this->handleTrade($buyer, $seller, $buyerAsset, $sellerAsset, $buyerOrder, $orderData);

            $buyerOrder->lockForUpdate()->update(['status' => StatusType::Filled]);
            $sellerOrder->lockForUpdate()->update(['status' => StatusType::Filled]);

            event(new OrderMatchedEvent($trade));
        });
    }

    /**
     * @param  array{amount:string,fee:string,total:string}  $orderData
     */
    public function handleTrade(User $buyer, User $seller, Asset $buyerAsset, Asset $sellerAsset, Order $buyerOrder, array $orderData): Trade
    {
        $netTotal = bcsub($orderData['total'], $orderData['fee'], 8);

        $seller->balance = bcadd($seller->balance, $netTotal, 8);
        $seller->save();

        $sellerAsset->locked_amount = bcsub($sellerAsset->locked_amount, $orderData['amount'], 8);
        $sellerAsset->save();

        $lockedAmount = $buyerOrder->locked_amount;
        $refundAmount = bcsub($lockedAmount, $orderData['total'], 8);

        if (bccomp($refundAmount, '0', 8) === 1) {
            $buyer->balance = bcadd($buyer->balance, $refundAmount, 8);
            $buyer->save();
        }

        $buyerAsset->amount = bcadd($buyerAsset->amount, $orderData['amount'], 8);
        $buyerAsset->save();

        return Trade::create([
            'symbol' => $buyerOrder->symbol,
            'price' => $buyerOrder->price,
            'amount' => $orderData['amount'],
            'fee' => $orderData['fee'],
            'total' => $orderData['total'],
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
    }
}
