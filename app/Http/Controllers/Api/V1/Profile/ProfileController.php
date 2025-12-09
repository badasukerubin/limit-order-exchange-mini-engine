<?php

namespace App\Http\Controllers\Api\V1\PRofile;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load('assets');

        return new UserResource($user);
    }
}
