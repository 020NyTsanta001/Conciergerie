<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('balance')->default(0)->after('phone');   // solde du compte, en Ar
            $table->string('currency', 3)->default('MGA')->after('balance'); // monnaie d'affichage
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('balance_method')->nullable()->after('paid_amount'); // wallet | on_site
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                  // reward | payment | receipt | commission
            $table->bigInteger('amount');            // + crédit, - débit (en Ar)
            $table->bigInteger('balance_after');
            $table->string('description');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reward_key')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'reward_key']); // un bonus par bouton et par compte
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('balance_method'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['balance', 'currency']));
    }
};