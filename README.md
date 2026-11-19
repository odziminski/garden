# Bloomify

Bloomify to projekt inżynierski do zarządzania pielęgnacją roślin domowych. Po zalogowaniu można prowadzić własny ogród, zapisywać podlewanie i nawożenie, dodawać zdjęcia oraz otrzymywać przypomnienia e-mail. Opcjonalna integracja z Plant.id identyfikuje rośliny na podstawie zdjęć.

## Uruchomienie lokalne

Wymagane są PHP 8.3+, Composer 2, Node.js 22+ i rozszerzenie PHP PDO SQLite. Do pracy z MySQL potrzebne jest rozszerzenie PDO MySQL.

W PowerShellu, w katalogu projektu:

    composer install
    Copy-Item .env.example .env
    php artisan key:generate
    php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
    php artisan migrate
    npm ci
    npm run build
    php artisan serve

Otwórz http://localhost:8000 i załóż konto. Opcjonalnie uruchom `php artisan db:seed`, aby utworzyć demonstracyjną paproć. Dane konta demonstracyjnego w środowisku lokalnym to `demo@example.test` i `bloomify-demo`.

## Integracje i przypomnienia

- `CLOUDINARY_URL` włącza przesyłanie zdjęć z pliku lub kamery. Zdjęcia są przechowywane pod adresami Cloudinary. Kamera wymaga HTTPS lub `localhost`.
- `PLANTID_API_KEY` włącza rozpoznawanie roślin na podstawie przesłanych zdjęć. Ogród działa również bez kluczy integracji.
- Przy `MAIL_MAILER=log` wiadomości trafiają do logu aplikacji. Skonfiguruj SMTP i `MAIL_FROM_ADDRESS`, aby wysyłać je do użytkowników.
- Uruchom `php artisan schedule:work` lokalnie, aby sprawdzać terminy co minutę. Scheduler wysyła przypomnienia codziennie o 10:10 według strefy czasu aplikacji. Na serwerze wywołuj `php artisan schedule:run` raz na minutę.

## Testy

    php vendor/bin/phpunit
    composer audit --locked
    npm audit

Testy używają świeżej bazy SQLite w pamięci. Testy integracji z Cloudinary i Plant.id korzystają z symulowanych odpowiedzi.

## Aktualizacja istniejącej instalacji

Zainstaluj PHP 8.3 lub nowsze i wykonaj kopię bazy przed wdrożeniem. Zachowaj istniejący `APP_KEY`, ustaw `APP_ENV=production`, `APP_DEBUG=false` i `SESSION_SECURE_COOKIE=true`, ustaw `APP_URL` na publiczny adres HTTPS aplikacji, a jako katalog publiczny serwera wskaż `public/`. Istniejące połączenie MySQL może używać `DB_URL`, `DATABASE_URL` albo `CLEARDB_DATABASE_URL`. Można też ustawić `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` i `DB_PASSWORD`.
