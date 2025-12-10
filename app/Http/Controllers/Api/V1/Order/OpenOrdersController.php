<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Enums\StatusType;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OpenOrdersController extends Controller
{
    public function __invoke(Request $request)
    {
        $symbol = $request->query('symbol');

        if (! $symbol) {
            throw new UnprocessableEntityHttpException('The symbol query parameter is required.');
        }

        $orders = $request->user()->orders()
            ->where('symbol', $symbol)
            ->where('status', StatusType::Open->value)
            ->orderBy('created_at', 'desc')
            ->get();

        return OrderResource::collection($orders);
    }
}
