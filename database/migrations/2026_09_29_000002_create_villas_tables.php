<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('villas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('city');
            $table->string('country');
            $table->decimal('price_per_night', 10, 2);
            $table->unsignedSmallInteger('bedrooms')->default(1);
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->boolean('has_pool')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->timestamps();
            $table->index(['status', 'city']);
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        // Many-to-Many avec attributs : tarif saisonnier propre à chaque villa
        Schema::create('villa_season', function (Blueprint $table) {
            $table->id();
            $table->foreignId('villa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_per_night', 10, 2);
            $table->unique(['villa_id', 'season_id']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // chef | chauffeur | yacht
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Relation polymorphique : une image appartient à une Villa, un Service, un User...
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->morphs('imageable');
            $table->string('url');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['images', 'services', 'villa_season', 'seasons', 'villas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
