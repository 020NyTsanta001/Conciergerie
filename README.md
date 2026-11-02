# Hoxen — Conciergerie immobilière & expériences de luxe

Site de réservation de villas de luxe avec services à la carte (chef, chauffeur, yacht).
Trois types d'utilisateurs : **voyageur**, **propriétaire** (hôte) et **admin** (le concierge).

| Partie | Technologie |
|---|---|
| Back-end (API REST) | Laravel 13, Sanctum (authentification par token) |
| Front-end (site) | React 19, React Router, Tailwind CSS 4, Vite |
| Base de données | PostgreSQL |

Le front et l'API sont dans le même projet : Laravel sert le site React pour toute URL qui ne commence pas par `/api`.

---

## Démarrer en 5 minutes

Prérequis : PHP 8.3+, Composer, Node 20+, PostgreSQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Dans `.env`, renseignez votre base PostgreSQL :

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=conciergerie
DB_USERNAME=...
DB_PASSWORD=...
SEED_ADMIN_PASSWORD=...     # mot de passe du compte admin de démo
SEED_HOST_PASSWORD=...      # mot de passe du compte propriétaire de démo
```

Puis :

```bash
php artisan migrate:fresh --seed   # crée les tables + données de démo
npm run build                      # compile le front (ou « npm run dev » en développement)
php artisan serve                  # http://127.0.0.1:8000
php artisan test                   # lance les tests
```

Comptes de démo : `admin@luxe.test` et `host@luxe.test`.
Si vous ne définissez pas de mots de passe dans `.env`, ils sont générés et affichés **une seule fois** dans le terminal.
Pour tester en voyageur, créez un compte depuis la page *Connexion → Inscription*.

---

## Comment ça marche

1. Le **propriétaire** publie une villa (prix par nuit, services proposés avec leur prix).
2. L'**admin** la modère (validation ou refus) avant qu'elle soit visible.
3. Le **voyageur** choisit des dates et des services, obtient un devis, puis réserve.
4. Le propriétaire valide, le voyageur paie, le séjour a lieu, puis la réservation est clôturée.

### Cycle de vie d'une réservation

```
submitted → pending_host → validated → deposit_paid → balance_paid → in_progress → closed
                  └─────────────┴────────────┴──── cancelled (annulation possible avant le séjour)
```

Chaque changement d'état est contrôlé côté serveur (`app/Enums/BookingStatus.php`) : on ne peut pas sauter d'étape.
Un contrôle empêche aussi deux réservations sur les mêmes dates (anti double-réservation).

### Argent

- Les montants sont stockés en **Ariary (MGA)**, en nombres entiers. L'affichage peut passer en dollars (1 $ = 5 000 Ar).
- Acompte de **30 %** après validation par le propriétaire, solde ensuite.
- Commission de la plateforme : **15 %**.
- Le paiement est **simulé** par un portefeuille (wallet) interne. Les bonus de démo (liens de la section Contact, jeu Pac-Man) alimentent ce portefeuille.
- Tous ces réglages sont dans `config/money.php`.

---

## L'API en bref

Toutes les routes commencent par `/api/v1`. Les réponses sont en JSON.

**Comment s'authentifier :**
1. `POST /login` avec `{ "email": "...", "password": "..." }` renvoie un `token`.
2. Pour toutes les routes « connecté », envoyez l'en-tête `Authorization: Bearer <token>`.

Exemple :

```bash
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@luxe.test","password":"VOTRE_MOT_DE_PASSE"}'

curl http://127.0.0.1:8000/api/v1/me \
  -H "Accept: application/json" -H "Authorization: Bearer <token>"
```

### Public (sans compte)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/register` · `/login` | Créer un compte · se connecter |
| GET | `/properties` | Liste des villas (filtres : `city`, `price_min`, `price_max`, `guests`, `has_pool`, `featured`, `available_from`, `available_to`) |
| GET | `/properties/{id}` · `/properties/{id}/reviews` | Détail d'une villa · ses avis |
| GET | `/services` · `/yachts` · `/stats` | Services, yachts, chiffres de la page d'accueil |
| POST | `/contact` | Envoyer un message de contact |

### Connecté (tout compte)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/me` · POST `/logout` | Mon profil · déconnexion |
| PATCH | `/account/name` · `/account/password` · `/account/currency` | Modifier mon compte |
| DELETE | `/account` | Supprimer mon compte |
| POST | `/bookings/quote` | Calculer un devis (sans réserver) |
| POST · GET | `/bookings` | Réserver · lister mes réservations |
| PATCH | `/bookings/{id}/status` | Changer l'état (`action` : `validate`, `start`, `close`, `cancel`, `request_refund`…) selon le rôle |
| POST | `/bookings/{id}/pay` | Payer l'acompte ou le solde (`type` : `deposit` ou `balance`) |
| POST · PUT · DELETE | `/bookings/{id}/review` · `/reviews/{id}` | Laisser, modifier ou supprimer un avis |
| GET · POST | `/notifications` · `/notifications/dots` · `/notifications/read` | Notifications et pastilles |
| GET · POST | `/wallet` · `/wallet/claim` | Solde et bonus |
| POST | `/games/pacman/start` · `/games/pacman/{session}/finish` | Mini-jeu Pac-Man (bonus) |

### Propriétaire (`/host/...`)

| Méthode | Route | Rôle |
|---|---|---|
| GET · POST | `/host/properties` | Mes villas · en créer une |
| PUT · DELETE | `/host/properties/{id}` | Modifier · supprimer |
| GET | `/host/payouts` | Mes versements |

### Admin (`/admin/...`)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/admin/properties` | Toutes les villas |
| PATCH | `/admin/properties/{id}/moderate` | Valider ou refuser une villa |
| DELETE | `/admin/properties/{id}` | Supprimer une villa |
| GET · PATCH | `/admin/users` · `/admin/users/{id}/suspend` | Utilisateurs · suspendre |
| GET | `/admin/activity` · `/admin/payouts` | Journal d'activité · versements |
| GET · PATCH | `/admin/messages` · `/admin/messages/{id}/read` | Messages de contact |

Erreurs courantes : `401` non connecté · `403` mauvais rôle · `422` données invalides · `429` trop de requêtes.
Des limites de requêtes protègent la connexion, l'inscription, le contact et le jeu.

---

## Où trouver quoi

```
routes/api.php             toutes les routes de l'API
app/Http/Controllers/      un contrôleur par thème (réservations, villas, admin…)
app/Services/              logique métier (réservation, prix, portefeuille)
app/Enums/BookingStatus.php  états d'une réservation
app/Models/                tables de la base
config/money.php           prix, commission, bonus, devises
database/migrations/       structure de la base
resources/js/pages/        pages du site (Home, Villas, Bookings, Admin…)
resources/js/components/   composants (Layout, Preloader, HeroScroll, Contact…)
tests/Feature/             tests automatiques
public/images/             images et séquence du hero
```

## Déploiement

- `npm run build` génère les fichiers du site dans `public/build`.
- Utilisez une base PostgreSQL gérée (le disque d'un hébergement serverless n'est pas durable).
- Définissez `APP_ENV=production`, `APP_DEBUG=false` et les variables `DB_*` dans l'environnement de l'hébergeur.
- Lancez `php artisan migrate --force` à chaque mise à jour de la structure.
