<?php

namespace Database\Seeders;

use App\Enums\StatusType;
use App\Enums\SymbolType;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'balance' => 20000,
            ]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'demo-two@example.com'],
            [
                'name' => 'Demo User Two',
                'password' => Hash::make('password'),
                'balance' => 15000,
            ]
        );

        Asset::firstOrCreate(
            ['user_id' => $user->id, 'symbol' => SymbolType::BTC->value],
            ['amount' => 2, 'locked_amount' => 0]
        );

        Asset::firstOrCreate(
            ['user_id' => $user->id, 'symbol' => SymbolType::ETH->value],
            ['amount' => 10, 'locked_amount' => 0]
        );

        Asset::firstOrCreate(
            ['user_id' => $user2->id, 'symbol' => SymbolType::BTC->value],
            ['amount' => 1, 'locked_amount' => 0]
        );

        Asset::firstOrCreate(
            ['user_id' => $user2->id, 'symbol' => SymbolType::ETH->value],
            ['amount' => 5, 'locked_amount' => 0]
        );

        // Counter order for testing
        // Sell 1 BTC at 15000
        $user2->orders()->create([
            'symbol' => SymbolType::BTC->value,
            'side' => 'sell',
            'price' => '15000.00000000',
            'amount' => '1.00000000',
            'status' => StatusType::Open->value,
            'locked_amount' => '1.00000000',
        ]);
    }
}
