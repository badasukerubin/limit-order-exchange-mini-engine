<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Services\Order\CancelOrderService;
use App\Models\Order;

class CancelOrderController extends Controller
{
    public function __construct(private readonly CancelOrderService $cancelOrderService) {}

    public function __invoke(Order $order)
    {
        $order = $this->cancelOrderService->handle($order);

        return new OrderResource($order);
    }
}
