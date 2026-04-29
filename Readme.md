# Magic Go

A personal Magic: The Gathering collection manager. Tracks owned cards, artists,
and AI-suggested decks built from your collection.

## Local development

```
ddev start
```

Then open https://magic-go.local or https://www.magic-go.local.

## Configuration

Copy `config/db_example.php` to `config/db.php` (or `config/db_prod.php` for
production) and fill in the database credentials. The DDEV setup uses
`config/db_ddev.php`.

## Architecture

- **`composer.json`** — declares the autoload mappings (`Magic\` → `src/Magic/`,
  classmap for `src/Service/`). Run `composer install` to generate `vendor/`.
- **`phpstan.neon`** — static analysis at level 5. Run `composer stan` (or
  `vendor/bin/phpstan analyse`) before committing; the baseline is 0 errors.
- **`src/Magic/`** — domain code under the `Magic\` namespace, autoloaded by
  Composer's PSR-4. Entry points start with
  `require_once __DIR__ . '/.../vendor/autoload.php';` followed by
  `use Magic\Bootstrap; $boot = Bootstrap::init();`.
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
