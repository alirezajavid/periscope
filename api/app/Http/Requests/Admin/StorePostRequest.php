<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostAccess;
use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'episode_number' => ['nullable', 'integer', 'min:1', 'max:65535', Rule::unique('posts', 'episode_number')->ignore($this->route('post'))],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:500000'],
            'access' => ['required', Rule::enum(PostAccess::class)],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'sponsor_name' => ['nullable', 'string', 'max:255'],
            'sponsor_url' => ['nullable', 'url:http,https', 'max:500'],
            'embed_code' => ['nullable', 'string', 'max:2000'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'min:1', 'max:50', 'distinct'],
        ];
    }
}
