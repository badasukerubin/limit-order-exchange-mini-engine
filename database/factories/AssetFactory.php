<?php

namespace Database\Factories;

use App\Enums\SymbolType;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $symbols = array_map(fn ($c) => $c->value, SymbolType::cases());

        $amount = $this->faker->randomFloat(8, 0, 10_000);
        $locked = $this->faker->randomFloat(8, 0, (float) $amount);

        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement($symbols),
            'amount' => number_format($amount, 8, '.', ''),
            'locked_amount' => number_format($locked, 8, '.', ''),
        ];
    }
}
