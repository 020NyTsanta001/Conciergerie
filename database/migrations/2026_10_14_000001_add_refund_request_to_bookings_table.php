<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('refund_status')->nullable()->after('balance_method');          // requested | declined | accepted
            $table->text('refund_reason')->nullable()->after('refund_status');             // cause écrite par le voyageur
            $table->text('refund_decline_reason')->nullable()->after('refund_reason');     // cause écrite par l'hôte s'il refuse
            $table->timestamp('refund_requested_at')->nullable()->after('refund_decline_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['refund_status', 'refund_reason', 'refund_decline_reason', 'refund_requested_at']);
        });
    }
};
