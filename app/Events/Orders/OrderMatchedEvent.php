<?php

namespace App\Events\Orders;

use App\Enums\StatusType;
use App\Models\Trade;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatchedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Trade $trade) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->trade->buyer_id),
            new PrivateChannel('user.'.$this->trade->seller_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * The data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $trade = $this->trade->with(['buyer.assets', 'seller.assets'])->first();

        $data = [
            'trade' => $trade->toArray(),
            'updates' => [],
        ];

        foreach ([$trade->buyer, $trade->seller] as $user) {
            $openOrders = $user->orders()
                ->where('status', StatusType::Open->value)
                ->get(['id', 'symbol', 'side', 'price', 'amount', 'status'])
                ->toArray();

            $data['updates'][$user->id] = [
                'balance' => $user->balance,
                'assets' => $user->assets->pluck('amount', 'symbol')->toArray(),
                'open_orders' => $openOrders,
            ];
        }

        return $data;
    }
}
