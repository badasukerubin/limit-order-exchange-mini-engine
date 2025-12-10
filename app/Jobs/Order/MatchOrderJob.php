<?php

namespace App\Jobs\Order;

use App\Enums\StatusType;
use App\Http\Services\Order\MatchOrderService;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MatchOrderJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $orderId) {}

    /**
     * Execute the job.
     */
    public function handle(MatchOrderService $matchOrderService): void
    {
        $order = Order::find($this->orderId);

        if ($order && $order->status === StatusType::Open) {
            $matchOrderService->handle($order);
        }
    }
}
