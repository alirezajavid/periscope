<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personalized_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path')->nullable();
            $table->enum('status', ['building', 'ready', 'failed'])->default('building');
            $table->text('error')->nullable();
            $table->timestamp('mirrored_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalized_files');
    }
};
