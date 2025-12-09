<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Services\Order\PlaceOrderService;

class PlaceOrderController extends Controller
{
    public function __construct(private readonly PlaceOrderService $placeOrderService) {}

    public function __invoke(PlaceOrderRequest $request)
    {
        $order = $this->placeOrderService->handle($request->user(), $request->validated());

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}
