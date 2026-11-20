# Bloomify

Bloomify is an engineering degree project for managing houseplant care. Sign in to see which plants need attention, track your care and photos, and receive a daily email summary. The optional Plant.id integration identifies plants from uploaded photos.

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

## Caring for your plants

- Open **Today** to see all watering and fertilizing tasks due today or overdue. Mark tasks complete from the list.
- On a plant's page, log watering or fertilizing with a date and an optional note. You can enter a past date to fill in your care history. Changing your time zone keeps the dates you entered.
- Set seasonal watering and fertilizing intervals when adding or editing a plant. Summer runs from April through September, and winter from October through March. An empty seasonal field uses the plant's regular interval.
- Add dated photos and optional notes to a plant's photo journal. Journal photos require Cloudinary and are accessible through your account.
- Under **Profile**, choose a time and time zone for your daily care email or turn reminders off.

## Shared gardens

Open **Account → Garden sharing** and enter the email the invitee uses to sign in. Copy the invitation link and send it privately. The link expires in seven days and can be accepted once by an account with the matching email. Invitees who need an account can register from the sign-in screen and then return to the invitation.

Members see the owner's plants in **Your garden** and their due tasks in **Today**. They can complete care tasks and record dated entries. The log shows who recorded each new entry. Task dates follow the owner's time zone, and two people completing the same due task write one history entry.

The owner manages plants, care schedules, private photo journals, and access. The owner can revoke a pending link or remove a member. Members can leave on their **Garden sharing** page. Removing access preserves the care history.

## Custom care and observations

Open a plant you own and use **Custom care tasks** to add a one-time task or repeat it every few days. The form suggests names for checks such as repotting and cleaning the leaves. Due tasks appear in **Today** and the owner's daily reminder. Every completion records who did the work. Repeating tasks calculate their next due date from the day you complete them. The owner can archive a task and keep its completion history.

Use **Observations** on a plant page to record a pest check, leaf change, repotting, or other update. Repotting entries can record the container size and substrate. The owner can link a photo from the private journal or schedule a one-time follow-up task. Shared garden members can add observations and complete tasks. Private journal photos and task management stay with the owner.

## Monthly plant report

Open **Monthly report** from **Your garden** or from a plant you own. Choose a plant and a month to see its care entries, completed custom tasks, observations, and journal photos in one timeline. The watering chart shows calendar days between recorded watering dates. Multiple entries on the same day count once for the chart, and the earliest interval may start before the selected month.

The report is available for your own plants and includes private journal photos.

## Integrations and reminders

- Set `CLOUDINARY_URL` to enable image uploads from files or a camera. Bloomify stores photos in Cloudinary. Browser camera access requires HTTPS or `localhost`. For uploads up to 5 MB, set PHP's `upload_max_filesize` to at least `5M` and `post_max_size` to at least `8M`.
- Set `PLANTID_API_KEY` to identify plants from uploaded photos. You can manage your garden without either integration key.
- With `MAIL_MAILER=log`, email goes to the application log. Configure SMTP and `MAIL_FROM_ADDRESS` to deliver email to users.
- Run `php artisan schedule:work` locally to refresh care tasks and check the reminder schedule every minute. The scheduler sends at most one digest per user per local day when plants need care. On the server, run `php artisan schedule:run` once per minute.

## Tests

    php vendor/bin/phpunit
    composer audit --locked
    npm audit

The tests use a fresh in-memory SQLite database. The Cloudinary and Plant.id integration tests use simulated responses.

## Upgrading an existing installation

Install PHP 8.3 or later and back up the database before deploying. Run `php artisan migrate` to apply the care history, seasonal schedules, photo journal, reminder, garden sharing, custom task, and observation migrations. Keep the existing `APP_KEY` and set `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`. Set `APP_URL` to the application's public HTTPS URL and point the web server's document root at `public/`.

Configure MySQL with `DB_URL`, `DATABASE_URL`, or `CLEARDB_DATABASE_URL`. You can also set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
