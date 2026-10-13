<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\LoginMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        event(new Registered($user)); // sends the verification email

        UserSession::signIn($request, $user, LoginMethod::Email);

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
