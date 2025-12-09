<?php

namespace Database\Factories;

use App\Enums\SideType;
use App\Enums\StatusType;
use App\Enums\SymbolType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $sides = array_map(fn ($c) => $c->value, SideType::cases());
        $statuses = array_map(fn ($c) => $c->value, StatusType::cases());
        $symbols = array_map(fn ($c) => $c->value, SymbolType::cases());

        $amount = $this->faker->randomFloat(8, 0.00000001, 1_000);
        $price = $this->faker->randomFloat(8, 0.00000001, 100_000);
        $locked = $this->faker->randomFloat(8, 0, (float) $amount);

        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement($symbols),
            'side' => $this->faker->randomElement($sides),
            'price' => number_format($price, 8, '.', ''),
            'amount' => number_format($amount, 8, '.', ''),
            'locked_amount' => number_format($locked, 8, '.', ''),
            'status' => $this->faker->randomElement($statuses),
        ];
    }
}
