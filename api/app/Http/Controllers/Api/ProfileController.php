<?php

namespace App\Http\Controllers\Api;

use App\Enums\MirrorServer;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /** Account settings: name, new-episode emails on/off, which server streams the audio. */
    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'notify_new_episode' => ['sometimes', 'boolean'],
            'preferred_mirror' => ['sometimes', Rule::enum(MirrorServer::class)],
        ]);

        $request->user()->update($data);

        return new UserResource($request->user());
    }
}
