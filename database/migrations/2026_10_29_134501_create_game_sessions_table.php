<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game')->default('pacman');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('seconds')->nullable();
            $table->boolean('paid');                       // fait partie des parties rémunérées
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'game']);
        });
    }

    public function down(): void { Schema::dropIfExists('game_sessions'); }
};