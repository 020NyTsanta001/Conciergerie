<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder ADDITIF « host2 » : ajoute un second propriétaire avec 5 villas approuvées,
 * dont 3 avec un yacht, avec les fonctionnalités récentes :
 *   - exactement 3 photos différentes par villa ;
 *   - prix des services fixés par le propriétaire, villa par villa (chef, chauffeur, yacht) ;
 *     un prix vide (null) veut dire « service non proposé » ;
 *   - yacht complet : nom, photo, description, 1 ou 2 lieux de sortie, prix.
 *
 * Il ne modifie ni ne supprime aucune donnée existante et peut être relancé sans doublon.
 * DatabaseSeeder n'est pas touché.
 *
 * Lancer :  php artisan db:seed --class=Host2Seeder
 * Mot de passe : SEED_HOST2_PASSWORD dans .env, sinon généré et affiché UNE SEULE FOIS.
 */
class Host2Seeder extends Seeder
{
    public const EMAIL = 'host2@luxe.test';

    public function run(): void
    {
        DB::transaction(function () {
            $this->ensureServiceCatalogue();

            $host = User::where('email', self::EMAIL)->first();
            if (! $host) {
                $host = User::create([
                    'name' => 'Nirina Andriamihaja',
                    'email' => self::EMAIL,
                    'password' => $this->password(),
                    'role' => 'host',
                ]);
            }

            $created = 0;
            foreach ($this->villas() as $i => $v) {
                if (Villa::where('owner_id', $host->id)->where('title', $v['title'])->exists()) {
                    continue; // déjà présente : on ne touche à rien
                }

                $yacht = $v['yacht'];
                $villa = Villa::create([
                    'owner_id' => $host->id,
                    'title' => $v['title'],
                    'description' => "Une résidence d'exception à {$v['city']}, entre confort contemporain, vue sur la mer et service de conciergerie sur mesure.",
                    'city' => $v['city'],
                    'country' => 'Madagascar',
                    'price_per_night' => $v['price'],
                    'bedrooms' => $v['bedrooms'],
                    'capacity' => $v['capacity'],
                    'has_pool' => $v['pool'],
                    'is_featured' => $v['featured'],
                    'status' => 'approved',

                    // prix des services de CETTE villa (Ar, entiers) ; null = non proposé
                    'chef_price' => $v['chef'],
                    'chauffeur_price' => $v['chauffeur'],

                    // yacht (tout ou rien : sans yacht, aucune information de yacht)
                    'has_yacht' => $yacht !== null,
                    'yacht_image' => $yacht ? "https://picsum.photos/seed/host2-yacht{$i}/1000/1250" : null,
                    'yacht_name' => $yacht['name'] ?? null,
                    'yacht_description' => $yacht['description'] ?? null,
                    'yacht_places' => $yacht['places'] ?? null,
                    'yacht_price' => $yacht['price'] ?? null,
                ]);

                foreach ([0, 1, 2] as $p) {
                    $villa->images()->create(['url' => "https://picsum.photos/seed/host2-villa{$i}-{$p}/1200/800", 'position' => $p]);
                }
                $created++;
            }

            $this->command?->info("Host2 : {$created} villa(s) ajoutée(s) pour ".self::EMAIL.' (5 au total, dont 3 avec yacht).');
        });
    }

    /** Le catalogue de services n'est créé que s'il manque : les lignes existantes ne sont jamais modifiées. */
    private function ensureServiceCatalogue(): void
    {
        $catalogue = [
            'chef' => ['Chef à domicile', 'Dîner gastronomique pour toute la villa', 100000],
            'chauffeur' => ['Chauffeur privé', 'Berline avec chauffeur, à la journée', 500000],
            'yacht' => ['Sortie en yacht', 'Demi-journée en mer avec équipage', 1000000],
        ];

        foreach ($catalogue as $type => [$name, $description, $price]) {
            Service::firstOrCreate(
                ['type' => $type],
                ['name' => $name, 'description' => $description, 'price' => $price, 'is_active' => true]
            );
        }
    }

    /** @return array<int, array<string, mixed>> 5 villas, dont 3 avec yacht (0, 1 et 3). */
    private function villas(): array
    {
        return [
            [
                'title' => 'Villa Ylang', 'city' => 'Nosy Be', 'price' => 450000, 'bedrooms' => 4, 'capacity' => 8,
                'pool' => true, 'featured' => true, 'chef' => 120000, 'chauffeur' => 80000,
                'yacht' => [
                    'name' => 'Ylang Voyager',
                    'description' => 'Catamaran de 14 m avec skipper pour une journée entre Nosy Komba et Nosy Tanikely.',
                    'places' => ['Nosy Komba', 'Nosy Tanikely'],
                    'price' => 900000,
                ],
            ],
            [
                'title' => 'Villa Lokobe', 'city' => 'Nosy Be', 'price' => 380000, 'bedrooms' => 3, 'capacity' => 6,
                'pool' => true, 'featured' => false, 'chef' => 100000, 'chauffeur' => null, // pas de chauffeur
                'yacht' => [
                    'name' => 'Lokobe Spirit',
                    'description' => 'Vedette rapide de 11 m, sortie en mer avec déjeuner de poissons grillés.',
                    'places' => ['Réserve de Lokobe'],
                    'price' => 750000,
                ],
            ],
            [
                'title' => 'Villa Baobab Bay', 'city' => 'Morondava', 'price' => 300000, 'bedrooms' => 3, 'capacity' => 6,
                'pool' => false, 'featured' => false, 'chef' => 90000, 'chauffeur' => 70000,
                'yacht' => null,
            ],
            [
                'title' => 'Villa Sainte-Marie', 'city' => 'Île Sainte-Marie', 'price' => 520000, 'bedrooms' => 5, 'capacity' => 10,
                'pool' => true, 'featured' => true, 'chef' => 130000, 'chauffeur' => null,
                'yacht' => [
                    'name' => 'Baleine Bleue',
                    'description' => "Bateau de 12 m pour observer les baleines à bosse (juillet à septembre) et nager au large de l'Île aux Nattes.",
                    'places' => ['Île aux Nattes', 'Baie des Forbans'],
                    'price' => 1100000,
                ],
            ],
            [
                'title' => 'Villa Diego Bleu', 'city' => 'Antsiranana', 'price' => 280000, 'bedrooms' => 2, 'capacity' => 4,
                'pool' => false, 'featured' => false, 'chef' => null, 'chauffeur' => 60000, // pas de chef
                'yacht' => null,
            ],
        ];
    }

    /** Mot de passe lu dans .env, sinon généré au hasard et affiché une seule fois. */
    private function password(): string
    {
        $value = env('SEED_HOST2_PASSWORD');
        if ($value) {
            return $value;
        }

        $value = Str::password(16, symbols: false);
        $this->command?->warn("Mot de passe host2 généré (à noter, il ne sera plus affiché) : {$value}");

        return $value;
    }
}
