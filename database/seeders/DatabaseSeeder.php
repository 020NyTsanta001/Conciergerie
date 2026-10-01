<?php

namespace Database\Seeders;

use App\Models\Season;
use App\Models\Service;
use App\Models\User;
use App\Models\Villa;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create(['name' => 'Concierge Admin', 'email' => 'admin@luxe.test', 'password' => 'password', 'role' => 'admin']);
        $host = User::create(['name' => 'Hélène Marchand', 'email' => 'host@luxe.test', 'password' => 'password', 'role' => 'host']);
        User::create(['name' => 'Victor Client', 'email' => 'client@luxe.test', 'password' => 'password', 'role' => 'client']);

        $winter = Season::create(['name' => 'Fêtes de fin d\'année', 'start_date' => '2026-12-15', 'end_date' => '2027-01-05']);
        $summer = Season::create(['name' => 'Haute saison été', 'start_date' => '2027-06-15', 'end_date' => '2027-09-15']);

        Service::insert([
            ['name' => 'Chef à domicile', 'type' => 'chef', 'description' => 'Dîner gastronomique pour toute la villa', 'price' => 450, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chauffeur privé', 'type' => 'chauffeur', 'description' => 'Berline avec chauffeur, à la journée', 'price' => 300, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sortie en yacht', 'type' => 'yacht', 'description' => 'Demi-journée en mer avec équipage', 'price' => 1800, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $villas = [
            ['Villa Horizon', 'Cannes', 'France', 1200, 5, 10, true, true],
            ['Willow Estate', 'Marbella', 'Espagne', 950, 4, 8, true, true],
            ['Oakridge Residence', 'Saint-Tropez', 'France', 1600, 6, 12, true, true],
            ['Casa Blanca', 'Mykonos', 'Grèce', 780, 3, 6, true, false],
            ['Chalet Aurore', 'Courchevel', 'France', 1400, 5, 10, false, true],
            ['Maison du Lac', 'Côme', 'Italie', 890, 4, 8, true, false],
        ];

        foreach ($villas as $i => [$title, $city, $country, $price, $beds, $cap, $pool, $featured]) {
            $villa = Villa::create([
                'owner_id' => $host->id, 'title' => $title, 'city' => $city, 'country' => $country,
                'description' => "Une résidence d'exception à {$city}, alliant architecture contemporaine, confort absolu et service de conciergerie sur mesure.",
                'price_per_night' => $price, 'bedrooms' => $beds, 'capacity' => $cap,
                'has_pool' => $pool, 'is_featured' => $featured, 'status' => 'approved',
            ]);
            foreach ([1, 2, 3] as $p) {
                $villa->images()->create(['url' => "https://picsum.photos/seed/villa{$i}-{$p}/1200/800", 'position' => $p]);
            }
            $villa->seasons()->attach([$winter->id => ['price_per_night' => $price * 1.5], $summer->id => ['price_per_night' => $price * 1.25]]);
        }
    }
}
