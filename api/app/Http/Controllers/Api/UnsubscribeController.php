<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UnsubscribeController extends Controller
{
    /** Target of the signed link in every new-episode email (works without logging in). */
    public function __invoke(User $user): RedirectResponse
    {
        $user->forceFill(['notify_new_episode' => false])->save();

        return redirect(config('periscope.app_url').'/#/unsubscribed');
    }
}
