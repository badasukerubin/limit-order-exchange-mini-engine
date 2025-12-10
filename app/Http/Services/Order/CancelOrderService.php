<?php

namespace App\Http\Services\Order;

use App\Enums\SideType;
use App\Enums\StatusType;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CancelOrderService
{
    public function handle(Order $order): Order
    {
        if ($order->status !== StatusType::Open) {
            throw new UnprocessableEntityHttpException('Only open orders can be cancelled');
        }

        if ($order->user_id !== auth()->id()) {
            throw new UnprocessableEntityHttpException('You can only cancel your own orders');
        }

        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            $lockedOrder->status = StatusType::Cancelled;
            $lockedOrder->save();

            match ($lockedOrder->side) {
                SideType::Buy => $this->handleCancelBuyOrder($lockedOrder),
                SideType::Sell => $this->handleCancelSellOrder($lockedOrder),
                default => throw new UnprocessableEntityHttpException('Invalid order side'),
            };

            return $order->fresh();
        });
    }

    private function handleCancelBuyOrder(Order $order): void
    {
        $user = User::where('id', $order->user_id)
            ->lockForUpdate()
            ->first();
        $lockedAmount = $order->locked_amount;

        $user->balance = bcadd($user->balance, $lockedAmount, 8);
        $user->save();
    }

    private function handleCancelSellOrder(Order $order): void
    {
        $asset = Asset::where('user_id', $order->user_id)
            ->where('symbol', $order->symbol)
            ->lockForUpdate()
            ->first();

        $asset->amount = bcadd($asset->amount, $order->locked_amount, 8);
        $asset->locked_amount = bcsub($asset->locked_amount, $order->locked_amount, 8);
        $asset->save();
    }
}
