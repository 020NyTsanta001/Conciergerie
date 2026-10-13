<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Données de départ : 1 administrateur, 1 propriétaire, 10 villas (dont 4 avec yacht), aucun voyageur.
 * Les voyageurs s'inscrivent eux-mêmes sur le site.
 *
 * Tous les montants sont en Ariary (entiers), comme le reste de l'application.
 *
 * Mots de passe : à définir dans .env (SEED_ADMIN_PASSWORD et SEED_HOST_PASSWORD).
 * S'ils sont absents, un mot de passe aléatoire est généré et affiché UNE SEULE FOIS dans le terminal.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = $this->password('SEED_ADMIN_PASSWORD', 'administrateur');
        $hostPassword = $this->password('SEED_HOST_PASSWORD', 'propriétaire');

        User::create(['name' => 'Concierge Admin', 'email' => 'admin@luxe.test', 'password' => $adminPassword, 'role' => 'admin']);

        $host = User::create(['name' => 'Hélène Marchand', 'email' => 'host@luxe.test', 'password' => $hostPassword, 'role' => 'host']);

        Service::insert([
            ['name' => 'Chef à domicile', 'type' => 'chef', 'description' => 'Dîner gastronomique pour toute la villa', 'price' => 100000, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chauffeur privé', 'type' => 'chauffeur', 'description' => 'Berline avec chauffeur, à la journée', 'price' => 500000, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sortie en yacht', 'type' => 'yacht', 'description' => 'Demi-journée en mer avec équipage', 'price' => 1000000, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // [titre, ville, pays, prix/nuit (Ar), chambres, capacité, piscine, mise en avant, yacht|null]
        $villas = [
            ['Villa Horizon', 'Cannes', 'France', 1000000, 5, 10, true, true, [
                'Azimut Horizon 72', "Yacht de 22 m avec équipage, pont solarium et cuisine de bord pour une journée en mer.", ['Îles de Lérins', 'Baie de la Napoule'],
            ]],
            ['Willow Estate', 'Marbella', 'Espagne', 470000, 4, 8, true, true, null],
            ['Oakridge Residence', 'Saint-Tropez', 'France', 800000, 6, 12, true, true, [
                'Sunseeker Oakridge', "Yacht rapide de 20 m, idéal pour déjeuner en mer face aux plages de la presqu'île.", ['Plage de Pampelonne', 'Baie des Canebiers'],
            ]],
            ['Chalet Aurore', 'Courchevel', 'France', 1500000, 5, 10, false, true, null],
            ['Maison du Lac', 'Côme', 'Italie', 440000, 4, 8, true, false, null],
            ['Villa Caldera', 'Santorin', 'Grèce', 500000, 4, 8, true, false, null],
            ['Casa Ibiza', 'Ibiza', 'Espagne', 350000, 3, 6, true, false, null],

            ['Casa Blanca', 'Mykonos', 'Grèce', 390000, 3, 6, true, false, [
                'Aegean Spirit', "Voilier-yacht de 18 m pour explorer les criques des Cyclades, avec skipper et hôtesse.", ['Île de Délos', 'Plage de Paradise'],
            ]],
            ['Villa Amalfi', 'Amalfi', 'Italie', 1500000, 5, 10, true, true, [
                'Costiera Dream', "Yacht de 17 m au départ de la côte amalfitaine, apéritif à bord et baignade en pleine mer.", ['Positano', 'Île de Capri'],
            ]],
            ['Villa Baobab', 'Nosy Be', 'Madagascar', 200000, 3, 6, true, false, null],
        ];

        foreach ($villas as $i => [$title, $city, $country, $price, $beds, $cap, $pool, $featured, $yacht]) {
            $villa = Villa::create([
                'owner_id' => $host->id,
                'title' => $title, 'city' => $city, 'country' => $country,
                'description' => "Une résidence d'exception à {$city}, alliant architecture contemporaine, confort absolu et service de conciergerie sur mesure.",
                'price_per_night' => $price, 'bedrooms' => $beds, 'capacity' => $cap,
                'has_pool' => $pool, 'is_featured' => $featured, 'status' => 'approved',
                'has_yacht' => $yacht !== null,
                'yacht_image' => $yacht ? 'https://picsum.photos/seed/yacht'.$i.'/1000/1250' : null,
                'yacht_name' => $yacht[0] ?? null,
                'yacht_description' => $yacht[1] ?? null,
                'yacht_places' => $yacht[2] ?? null,
            ]);

            foreach ([0, 1, 2] as $p) {
                $villa->images()->create(['url' => "https://picsum.photos/seed/villa{$i}-{$p}/1200/800", 'position' => $p]);
            }
        }

        $this->command?->info('Données créées : 1 admin, 1 propriétaire (10 villas dont 4 avec yacht), 0 voyageur.');
    }

    /** Mot de passe lu dans .env, sinon généré au hasard et affiché une seule fois. */
    private function password(string $envKey, string $label): string
    {
        $value = env($envKey);
        if ($value) {
            return $value;
        }

        $value = Str::password(16, symbols: false);
        $this->command?->warn("Mot de passe {$label} généré (à noter, il ne sera plus affiché) : {$value}");

        return $value;
    }
}