<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->string('yacht_name', 80)->nullable()->after('yacht_image');
            $table->string('yacht_description', 300)->nullable()->after('yacht_name');
            $table->json('yacht_places')->nullable()->after('yacht_description'); // 1 ou 2 lieux de sortie
        });
    }

    public function down(): void
    {
        Schema::table('villas', fn (Blueprint $t) => $t->dropColumn(['yacht_name', 'yacht_description', 'yacht_places']));
    }
};