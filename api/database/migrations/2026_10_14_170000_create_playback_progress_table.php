<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playback_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('last_position')->default(0);
            $table->unsignedInteger('max_position')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('last_played_at');
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['post_id', 'completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_progress');
    }
};
