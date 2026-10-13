<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leak_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['audio', 'text']);
            $table->string('subject', 255);
            $table->foreignId('found_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('result', ['found', 'ambiguous', 'not_found']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leak_checks');
    }
};
