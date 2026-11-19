# Bloomify

Bloomify is an engineering degree project for managing houseplant care. Sign in to manage your garden, log watering and fertilizing, add photos, and receive email reminders. The optional Plant.id integration identifies plants from uploaded photos.

## Local setup

You need PHP 8.3+, Composer 2, Node.js 22+, and the PDO SQLite extension for PHP. If you use MySQL, install the PDO MySQL extension.

Run the following commands in PowerShell from the project directory.

    composer install
    Copy-Item .env.example .env
    php artisan key:generate
    php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
    php artisan migrate
    npm ci
    npm run build
    php artisan serve

Open [Bloomify](http://localhost:8000) and create an account. To try the app with a sample fern, run `php artisan db:seed` and sign in locally with `demo@example.test` and `bloomify-demo`.

## Integrations and reminders

- Set `CLOUDINARY_URL` to enable image uploads from files or a camera. Bloomify stores photos in Cloudinary. Browser camera access requires HTTPS or `localhost`.
- Set `PLANTID_API_KEY` to identify plants from uploaded photos. You can manage your garden without either integration key.
- With `MAIL_MAILER=log`, email goes to the application log. Configure SMTP and `MAIL_FROM_ADDRESS` to deliver email to users.
- Run `php artisan schedule:work` locally to check watering and fertilizing schedules every minute. The scheduler sends reminders daily at 10:10 in the application's time zone. On the server, run `php artisan schedule:run` once per minute.

## Tests

    php vendor/bin/phpunit
    composer audit --locked
    npm audit

The tests use a fresh in-memory SQLite database. The Cloudinary and Plant.id integration tests use simulated responses.

## Upgrading an existing installation

Install PHP 8.3 or later and back up the database before deploying. Keep the existing `APP_KEY` and set `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`. Set `APP_URL` to the application's public HTTPS URL and point the web server's document root at `public/`.

Configure MySQL with `DB_URL`, `DATABASE_URL`, or `CLEARDB_DATABASE_URL`. You can also set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
