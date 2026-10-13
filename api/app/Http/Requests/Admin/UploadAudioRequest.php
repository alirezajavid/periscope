<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav', 'max:307200']];
    }
}
