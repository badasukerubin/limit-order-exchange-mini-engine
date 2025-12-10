<?php

namespace App\Http\Services\Order;

use App\Enums\SideType;
use App\Enums\StatusType;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PlaceOrderService
{
    /**
     * @param  array{symbol:string,side:string,price:float,amount:float}  $data
     */
    public function handle(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $order = match ($data['side']) {
                SideType::Buy->value => $this->handlePlaceBuyOrder($user, $data),
                SideType::Sell->value => $this->handlePlaceSellOrder($user, $data),
                default => throw new UnprocessableEntityHttpException('Invalid order side'),
            };

            return Order::create([
                'user_id' => $user->id,
                'symbol' => $data['symbol'],
                'side' => $data['side'],
                'price' => $data['price'],
                'amount' => $data['amount'],
                'status' => StatusType::Open->value,
                'locked_amount' => $order['locked_amount'],
            ]);
        });
    }

    /**
     * @param  array{symbol:string,side:string,price:float,amount:float}  $data
     * @return array{user:User,locked_amount:string}
     */
    private function handlePlaceBuyOrder(User $user, array $data): array
    {
        $cost = bcmul($data['price'], $data['amount'], 8);

        if ($user->balance < $cost) {
            throw new UnprocessableEntityHttpException('Insufficient balance');
        }

        // Lock user row for update
        $user = User::where('id', $user->id)->lockForUpdate()->first();
        $user->balance = bcsub($user->balance, $cost, 8);
        $user->save();

        return [
            'user' => $user,
            'locked_amount' => $cost,
        ];
    }

    /**
     * @param  array{symbol:string,side:string,price:float,amount:float}  $data
     * @return array{user:User,locked_amount:string}
     */
    private function handlePlaceSellOrder(User $user, array $data): array
    {
        $asset = Asset::where('user_id', $user->id)
            ->where('symbol', $data['symbol'])
            ->lockForUpdate()
            ->first();

        if (! $asset || $asset->amount < $data['amount']) {
            throw new UnprocessableEntityHttpException('Insufficient asset amount');
        }

        $asset->amount = bcsub($asset->amount, $data['amount'], 8);
        $asset->locked_amount = bcadd($asset->locked_amount, $data['amount'], 8);
        $asset->save();

        return [
            'user' => $user,
            'locked_amount' => $data['amount'],
        ];
    }
}
