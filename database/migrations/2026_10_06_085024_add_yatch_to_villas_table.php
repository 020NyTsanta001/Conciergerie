<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('villas', function (Blueprint $table) {
            $table->boolean('has_yacht')->default(false)->after('has_pool');
            $table->string('yacht_image', 500)->nullable()->after('has_yacht');
        });
    }

    public function down(): void
    {
        Schema::table('villas', fn (Blueprint $t) => $t->dropColumn(['has_yacht', 'yacht_image']));
    }
};