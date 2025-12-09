<?php

use App\Enums\SymbolType;
use App\Models\Asset;
use App\Models\User;

it('prevents access to profiles for unauthenticated users', function () {
    $this->getJson(route('api.v1.profile.index'))
        ->assertStatus(401);
})->only();

it('returns user balance and asset holdings', function () {
    $user = User::factory()->create([
        'balance' => 1234.5678,
    ]);

    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::BTC->value,
        'amount' => 0.5,
        'locked_amount' => 0.1,
    ]);

    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => SymbolType::ETH->value,
        'amount' => 2,
        'locked_amount' => 0,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson(route('api.v1.profile.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'balance',
                'assets' => [
                    [
                        'id',
                        'symbol',
                        'amount',
                        'locked_amount',
                        'total_amount',
                    ],
                ],
                'created_at',
                'updated_at',
                'two_factor',
            ],
        ])
        ->assertJsonFragment([
            'balance' => '1234.56780000',
            'amount' => '0.50000000',
            'locked_amount' => '0.10000000',
            'total_amount' => '0.60000000',
        ]);
})->only();
