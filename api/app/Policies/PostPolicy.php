<?php

namespace App\Policies;

use App\Enums\PostAccess;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Who gets the full content (body, embed, audio). Everyone else sees only the preview:
     * title, cover and excerpt. Free posts are open to anonymous visitors too.
     */
    public function viewContent(?User $user, Post $post): bool
    {
        if ($post->access === PostAccess::Free) {
            return true;
        }

        return $user?->canReadPremium() ?? false;
    }
}
