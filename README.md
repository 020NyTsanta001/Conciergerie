# Maison Élite — API REST de conciergerie immobilière de luxe

Laravel 13 (API REST + Sanctum) · React 19 (SPA) · Tailwind CSS 4 · Vite (assets compilés avec `npm run build`, aucun CDN).

## Installation (fichiers à superposer sur un projet Laravel neuf)

```bash
composer create-project laravel/laravel conciergerie
cd conciergerie
php artisan install:api            # Sanctum + routes/api.php

# copier le contenu de ce dossier PAR-DESSUS le projet (accepter d'écraser)

# base MySQL (XAMPP) : créer la base `conciergerie`, puis dans .env :
#   DB_CONNECTION=mysql  DB_DATABASE=conciergerie  DB_USERNAME=root  DB_PASSWORD=
#   QUEUE_CONNECTION=sync   SESSION_DRIVER=file

npm install react react-dom react-router-dom @fontsource-variable/manrope
npm install -D @vitejs/plugin-react

php artisan migrate:fresh --seed
php artisan test                   # anti double-booking + cycle de vie complet
npm run build                      # production (assets dans public/build)
php artisan serve                  # http://127.0.0.1:8000
```
En développement : `npm run dev` dans un second terminal.

Comptes de démo (mot de passe `password`) : `client@luxe.test`, `host@luxe.test`, `admin@luxe.test`.

## Endpoints (préfixe `/api/v1`)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/register`, `/login` | public |
| GET | `/stats`, `/properties?city=&price_min=&price_max=&guests=&has_pool=true&featured=true&available_from=&available_to=` | public |
| GET | `/properties/{id}`, `/services`, `/seasons` | public |
| POST | `/bookings/quote`, `/bookings` | connecté |
| GET | `/bookings`, `/notifications`, `/me` | connecté |
| PATCH | `/bookings/{id}/status` `{action: validate\|start\|close\|cancel}` | hôte / admin (annulation : client aussi) |
| POST | `/bookings/{id}/pay` `{type: deposit\|balance}` | client (paiement simulé) |
| GET/POST | `/host/properties`, PUT `/host/properties/{id}/seasons` | hôte |
| GET/PATCH | `/admin/properties`, `/admin/properties/{id}/moderate` | admin |

## Correspondance UML (pour la soutenance)

- **Cas d'utilisation** : Voyageur / Propriétaire / Concierge-Admin (rôles + `EnsureRole`).
- **Séquence** : `POST /bookings` → `BookingService::create` (transaction + `lockForUpdate` + contrôle de chevauchement) → `BookingSubmitted` → `HandleBookingSubmitted` → statut `pending_host` + notifications base de données (hôte + admins).
- **Classes** : `User`, `Villa`, `Season` (many-to-many avec attribut `price_per_night` via `villa_season`), `Service`, `Booking` (pivot `booking_service` avec quantité/prix), `Image` (relation **polymorphique** : Villa, Service, User).
- **États-transitions** : `App\Enums\BookingStatus` — submitted → pending_host → validated → deposit_paid → balance_paid → in_progress → closed (cancelled possible avant le séjour).
- **Activités** : `PricingService::quote` (prix par nuit selon saison + services, acompte 30 %).

## Pistes d'évolution (à citer en perspectives)
Spatie MediaLibrary pour l'upload d'images, WebSockets (Reverb) pour le temps réel, vrai provider de paiement (Stripe), avis polymorphiques.
