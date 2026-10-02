<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('phone');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // le journal survit à la suppression du compte : on garde le nom et le rôle de l'auteur
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role');
            $table->string('action');
            $table->text('description');
            $table->timestamps();
            $table->index(['actor_role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('suspended_at'));
    }
};