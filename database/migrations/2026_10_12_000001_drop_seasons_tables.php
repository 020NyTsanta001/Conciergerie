<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Les tarifs saisonniers n'ont jamais été gérés côté hôte : on retire les deux tables. */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('villa_season');
        Schema::dropIfExists('seasons');
    }

    public function down(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        Schema::create('villa_season', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_per_night', 10, 2);
            $table->unique(['villa_id', 'season_id']);
        });
    }
};
