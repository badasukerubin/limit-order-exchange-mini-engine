<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Services\Order\PlaceOrderService;
use App\Jobs\Order\MatchOrderJob;

class PlaceOrderController extends Controller
{
    public function __construct(private readonly PlaceOrderService $placeOrderService) {}

    public function __invoke(PlaceOrderRequest $request)
    {
        $order = $this->placeOrderService->handle($request->user(), $request->validated());

        MatchOrderJob::dispatch($order->id);

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}
