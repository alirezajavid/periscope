<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedSmallInteger('episode_number')->nullable()->unique();
            $table->text('excerpt')->nullable();
            $table->mediumText('body')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedBigInteger('audio_size')->nullable();
            $table->unsignedInteger('audio_duration')->nullable();
            $table->timestamp('audio_mirrored_at')->nullable();
            $table->enum('access', ['free', 'premium'])->default('premium');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('sponsor_name')->nullable();
            $table->string('sponsor_url')->nullable();
            $table->text('embed_code')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
