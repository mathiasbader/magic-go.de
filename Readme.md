# Magic Go

A personal Magic: The Gathering collection manager. Tracks owned cards, artists,
and AI-suggested decks built from your collection.

## Architecture

- **`src/Magic/`** — domain code under the `Magic\` namespace, autoloaded via
  `Magic\Bootstrap::init()`.
- **`src/Service/`** — `DbService` (PDO + migration runner) and `AuthService`
  (cookie-based login).
- **`migrations/`** — sequential numbered SQL files, applied on every request
  via `DbService::runMigrations()`.
- **`public/`** — Apache document root.
  - `index.php` redirects to `/cards/`.
  - `cards/` — collection browser, deck list, artist list, set list.
  - `cards/assets/` — extracted CSS and JS modules.
  - `img/cache.php` — Scryfall image proxy with disk cache + corporate-MITM
    hardening (uses `CURLSSLOPT_NATIVE_CA`, validates content-type and magic
    bytes before caching).
  - `api/scryfall.php` — server-side Scryfall API proxy.
  - `index.php` doubles as the login form when the user isn't authenticated;
    once logged in it redirects to `/cards/`.
  - `logout.php` clears the auth cookie and redirects home.

## Setup

1. **Apache vhost** — add a stanza to `httpd-vhosts.conf` pointing
   `magic-go.local` (or whatever you want to call it locally) at `public/`.
2. **Database** — `config/db.php` defaults to the existing `mathiasbader.de`
   MySQL database, so all data carries over. To use a fresh DB, copy
   `config/db_example.php` over `config/db.php` and update credentials.
3. **Migrations** — run automatically on each request via
   `DbService::runMigrations()`. On the shared DB they're tracked by filename
   in the existing `migrations` table, so already-applied ones are skipped.

## URLs

| Path                      | Renders                                    |
| ------------------------- | ------------------------------------------ |
| `/`                       | redirects to `/cards/`                     |
| `/cards/`                 | main collection (cards / decks / artists / sets tabs) |
| `/cards/{id}`             | single-card detail page                    |
| `/cards/imports`          | CSV import                                 |
| `/cards/artists/{id}`     | artist detail page                         |
| `/decks/{id}`             | single-deck detail page                    |
| `/img/cache?url=...`      | Scryfall image proxy                       |
| `/api/scryfall?path=...`  | Scryfall API proxy                         |
| `/sal/`                   | login / dashboard                          |
| `/logout`                 | clears the auth cookie                     |
