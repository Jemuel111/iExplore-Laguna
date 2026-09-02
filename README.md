# iExplore Laguna

A trip-planning platform for Laguna province, Philippines. Helps tourists discover
tourist spots, plan routes with real road-following directions and fare estimates,
build day-by-day itineraries, and book hotels or order from local shops. Also
includes verification/dashboard tools for hotel and shop owners, and an admin
panel for managing listings, transport fares, and site content.

## Tech stack

- **Backend:** Plain PHP (no framework) — PHP 8.2+ recommended
- **Database:** MySQL / MariaDB
- **Frontend:** Server-rendered PHP pages + vanilla JavaScript (no build step, no
  npm required) + Bootstrap 5 for layout/components
- **Maps:** Leaflet.js with OpenStreetMap tiles
- **Routing:** TomTom Routing API (primary), OpenRouteService (automatic fallback)
- **Live traffic overlay:** TomTom Traffic Flow tiles (optional — see below)

There is deliberately no framework, build pipeline, or Node.js dependency on the
backend. Any PHP-capable host (shared hosting, VPS, etc.) can run this without
special tooling.

## Folder structure

```
├── index.php              Homepage
├── includes/              Shared PHP: config, DB connection, header/footer,
│                          helper functions, small reusable components
│   ├── config.php         App constants, loads .env, error handling setup
│   ├── db.php             MySQL connection + db_fetch_all()/db_fetch_one()/db_execute()
│   ├── env.php            Minimal dependency-free .env file loader
│   └── helpers.php        ~59 shared functions (auth, CSRF, formatting, etc.)
├── pages/                 One file per page, accessed directly by URL
│   (e.g. /pages/planner.php, /pages/explore.php, /pages/hotel.php)
│   Includes: trip planner, explore/route picker, spot/hotel/shop listings +
│   detail pages, registration flows, hotel/shop owner dashboards, admin
│   dashboard + sub-pages, budget estimator, saved itineraries, orders/bookings
├── api/                   JSON endpoints called via fetch() from page JS.
│   Each file handles one resource (routes, spots, shops, budget, orders,
│   bookings, itineraries, notifications, etc.) via ?action=... routing
├── assets/
│   ├── css/app.css        All site styling — theme colors are CSS custom
│   │                      properties (see Theming below)
│   └── js/app.js          Shared client-side utilities (IExploreApp: toast
│                          notifications, loading states, formatting helpers)
├── uploads/               User-uploaded images (spots, hotels, shops, packages)
└── logs/                  PHP error log (auto-created, gitignored)
```

**Routing note:** there is no front controller — every page is a real `.php`
file accessed directly (e.g. `yoursite.com/pages/planner.php?origin=1&destination=2`).
No `.htaccess` rewriting is required for the app to function.

## Setup

### Requirements
- PHP 8.2+ with `curl` and `pdo_mysql` extensions enabled
- MySQL/MariaDB
- A webserver pointed at the project root (Apache/Nginx/PHP built-in server all work)

### 1. Database
**There is currently no `schema.sql` in this repository** — the database was
built up interactively during development. Before handing this off or deploying
somewhere new, export the current schema (and optionally seed data) from
phpMyAdmin: select the database → Export tab → format "SQL" → include structure
+ data → Go. Save that file as `schema.sql` in the project root so a fresh
environment can be set up with one import instead of reverse-engineering the
schema from the PHP queries.

Tables currently in use (inferred from the codebase): `users`, `cities`,
`tourist_spots`, `spot_photos`, `spot_reviews`, `spot_amenities`, `spot_checkins`,
`spot_views`, `hotels`, `hotel_photos`, `hotel_rooms`, `hotel_amenities`,
`hotel_reviews`, `shops`, `shop_products`, `shop_reviews`, `routes`,
`itineraries`, `bookings`, `orders`, `order_items`, `packages`, `package_spots`,
`package_bookings`, `payments`, `budget_estimates`, `notifications`,
`review_photos`, `site_settings`.

### 2. Environment variables
Copy `.env.example` to `.env` in the project root and fill in real values:

```
DB_HOST=localhost
DB_NAME=iexplore_laguna
DB_USER=your_db_user
DB_PASS=your_db_password

ORS_API_KEY=            # https://openrouteservice.org/dev/#/signup (free tier: 2,000 req/day)
TOMTOM_API_KEY=         # https://developer.tomtom.com/ — used for routing AND the optional
                         # live traffic overlay. Leave blank to disable the Traffic button.
```

`.env` is gitignored and must be created manually on every environment
(local, staging, production) — it is never committed.

### 3. Update `APP_URL`
`includes/config.php` currently hardcodes:
```php
define('APP_URL', 'http://localhost/iexplore-laguna');
```
**This must be changed to the real domain before going live** (e.g.
`https://iexplorelaguna.com`), or generated links, API calls, and asset paths
will all point at localhost.

### 4. Turn off debug mode for production
Also in `includes/config.php`:
```php
define('DEBUG_MODE', false);
```
This is already set correctly for production (errors get logged to
`logs/php-error.log` instead of being shown to visitors). Only flip it to
`true` temporarily while actively debugging locally.

## Theming

All brand colors are defined once as CSS custom properties at the top of
`assets/css/app.css`:

```css
--maroon-dark, --maroon-mid, --maroon-light, --maroon-pale
```

Every page references these via `var(--maroon-dark)` etc. instead of hardcoded
hex values — **to re-theme the entire site, change these four values in one
place** rather than searching through individual pages.

## Key subsystems worth understanding before making changes

- **Trip Planner (`pages/planner.php`)** — the most complex page. Fetches a
  real road route (TomTom → ORS fallback → straight-line fallback), filters
  candidate spots/cities to a corridor around that route, auto-schedules a
  day-by-day itinerary with real inter-city fares (from the `routes` table),
  walk/tricycle "last-mile" suggestions, and location-aware lunch breaks
  (nearest real food shop from the `shops` table).
- **Explore (`pages/explore.php`)** — lets a tourist pick a start/destination,
  see every town actually along that route, hand-pick spots/hotels/shops into
  a cart, and generate an itinerary. Its itinerary-building logic mirrors the
  Trip Planner's (fares, last-mile, lunch) but is a separate implementation in
  this file (functions suffixed `GI`) rather than a shared module — a
  reasonable target for future refactoring into a shared JS file if the two
  ever need to be kept in sync again.
- **Admin Dashboard (`pages/admin-dashboard.php` + `admin-*.php`)** — Site
  Settings, Transport & Fares (with search/pagination), Shops, Hotels tabs.
  The `routes` table has a `UNIQUE(origin_city_id, dest_city_id, transport_type)`
  constraint at the database level to prevent duplicate fare entries.
- **CSRF protection** — every state-changing POST request must include the
  token from `window.CSRF_TOKEN` (set in `includes/header.php` from the
  session). Check existing `fetch()` calls for the pattern before adding new
  ones — a couple of these were previously missing the header and silently
  failing with a 403.

## Known technical debt

- No `schema.sql` (see Setup step 1) — highest-priority gap for onboarding a
  new environment or developer.
- Itinerary-building logic (fare lookups, last-mile suggestions, lunch
  suggestions) is implemented separately in `planner.php` and `explore.php`
  rather than shared — functional, but a maintenance risk if one gets updated
  without the other.
- No automated tests. Changes should be manually verified against the Trip
  Planner, Explore, and Admin Dashboard flows before deploying.

## Security notes for whoever maintains this next

- API keys (`ORS_API_KEY`, `TOMTOM_API_KEY`) live in `.env`, never in code —
  keep it that way, and never commit it.
- If this repository's git history predates the `.env` migration, check
  whether old commits still contain hardcoded keys (GitHub/GitGuardian-style
  secret scanning will flag this) — rotate any exposed keys in their
  respective developer portals if so.
- Passwords are hashed with bcrypt (`BCRYPT_COST` in `config.php`) — never
  store or log plaintext passwords.
