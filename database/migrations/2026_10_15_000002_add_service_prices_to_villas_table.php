<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // prix en Ariary, fixés par le propriétaire de chaque villa ; null = service non proposé
        Schema::table('villas', function (Blueprint $table) {
            $table->unsignedBigInteger('chef_price')->nullable()->after('yacht_places');
            $table->unsignedBigInteger('chauffeur_price')->nullable()->after('chef_price');
            $table->unsignedBigInteger('yacht_price')->nullable()->after('chauffeur_price');
        });

        // Villas déjà en ligne : elles gardent les services qu'elles proposaient jusqu'ici, au prix du catalogue
        // (le yacht seulement si la villa en a un). Le propriétaire peut ensuite changer ou retirer chaque prix.
        // Pour qu'elles démarrent sans aucun service, supprime ce bloc avant de migrer.
        foreach (['chef', 'chauffeur', 'yacht'] as $type) {
            $price = DB::table('services')->where('type', $type)->value('price');
            if ($price === null) {
                continue;
            }
            $villas = DB::table('villas');
            if ($type === 'yacht') {
                $villas->where('has_yacht', true);
            }
            $villas->update([$type.'_price' => (int) round((float) $price)]);
        }
    }

    public function down(): void
    {
        Schema::table('villas', fn (Blueprint $t) => $t->dropColumn(['chef_price', 'chauffeur_price', 'yacht_price']));
    }
};
