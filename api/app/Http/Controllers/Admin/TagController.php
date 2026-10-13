<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /** All hashtags, for the editor's autocomplete. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => Tag::orderBy('name')->pluck('name')]);
    }
}
