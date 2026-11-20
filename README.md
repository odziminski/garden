# Bloomify

Bloomify is an engineering degree project for managing houseplant care. Sign in to check upcoming tasks, track care and photos, and receive reminders by email or browser notification. The optional Plant.id integration identifies plants from uploaded photos.

## Local setup

You need PHP 8.3+, Composer 2, Node.js 22+, and the PDO SQLite extension for PHP. If you use MySQL, install the PDO MySQL extension. The PHP build also needs cURL and OpenSSL.

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

- After login, **Today** shows due and overdue care. Complete a task, snooze it until tomorrow, or undo an accidental completion. When you're caught up, open or complete the next scheduled task early.
- On a plant's page, log watering or fertilizing with a date and an optional note. You can backdate, correct, or undo an entry. The history keeps corrected entries marked, and the schedule uses the active records. Changing your time zone keeps the dates you entered.
- New plants start with a seven-day watering interval and no scheduled fertilizing. Expand **Seasonal options** to choose the hemisphere and set different intervals. The Northern Hemisphere uses April through September as summer, and the Southern Hemisphere uses October through March. Due tasks carry over at the season boundary.
- Add dated photos and optional notes to a plant's photo journal. Journal photos require Cloudinary and are accessible through your account.
- Under **Profile**, choose a time and time zone for your daily care email or turn reminders off.

## Shared gardens

Open **Account → Garden sharing**, choose a plant or the entire garden, and enter the email the invitee uses to sign in. Bloomify emails the link when a delivery transport is configured. You can also copy it from the confirmation screen. The link expires in seven days and can be accepted once by the account with the matching verified email. Invitees can create and verify an account before accepting. A new invitation can replace an existing member's access when they accept it.

Members see shared plants in **Your garden** and their due tasks in **Today**. They can complete care tasks and record dated entries. The log shows who recorded each new entry. Task dates follow the owner's time zone, and two people completing the same due task write one history entry.

The owner manages plants, care schedules, private photo journals, and access. Owners can assign custom tasks to members, who receive their assigned due tasks in reminders when enabled. Revoking a member's access clears their assignments and preserves care history. Members can also leave through **Garden sharing**.

## Custom care and observations

Open a plant you own and use **Custom care tasks** to add a one-time or recurring task. The owner can edit the task, change its due date, assign it, or archive it. Every completion records who did the work. Repeating tasks calculate their next due date from the day you complete them.

Use **Observations** on a plant page to record a pest check, leaf change, repotting, or other update. Repotting entries can record the container size and substrate. The owner can link a photo from the private journal. Members can edit their own observations, and the owner can edit observations for their plants.

## Monthly plant report

Open **Monthly report** from **Your garden** or from a plant you own. Choose a plant and a month to see its care entries, completed custom tasks, observations, and journal photos in one timeline. The watering chart shows calendar days between recorded watering dates. Multiple entries on the same day count once for the chart, and the earliest interval may start before the selected month.

The report is available for your own plants and includes private journal photos.

## Integrations and reminders

- Set `CLOUDINARY_URL` to enable image uploads from files or a camera. Bloomify stores photos in Cloudinary. Browser camera access requires HTTPS or `localhost`. For uploads up to 5 MB, set PHP's `upload_max_filesize` to at least `5M` and `post_max_size` to at least `8M`.
- Set `PLANTID_API_KEY` to identify new cover photos with Plant.id API v3. The scheduler sends the cover image to Plant.id in the background. The plant page shows the status, and an unavailable service leaves any earlier identification intact. You can manage your garden without either integration key.
- With `MAIL_MAILER=log`, email goes to the application log. Configure SMTP and `MAIL_FROM_ADDRESS` to deliver email to users.
- Run `php artisan schedule:work` locally to enqueue and deliver reminders. Only verified accounts receive reminders. Bloomify checks for new reminders every five minutes and processes the delivery queue every minute. `php artisan email:care` runs both steps immediately. `php artisan care:refresh` still refreshes the stored watering and fertilizing flags on demand.

Each reminder channel has one queue entry per account and local date. Failed deliveries retry after 5, 15, 30, and 60 minutes within the same local day, for up to five attempts. The next run can reclaim an interrupted attempt after its five-minute lease expires. Delivery checks the current reminder settings and care state. If a provider accepted a message before a network failure or interruption prevented Bloomify from recording success, a retry may generate a duplicate. A push retry after partial delivery may also repeat a notification on a device that already received it.

### Browser reminders

Run `php artisan push:generate-keys` once and store the resulting `WEB_PUSH_PUBLIC_KEY` and `WEB_PUSH_PRIVATE_KEY` in the server environment. Set `WEB_PUSH_SUBJECT` to an operator contact such as `mailto:admin@example.com`. Use the same keys on web and scheduler instances. On Windows, PHP may require `OPENSSL_CONF` to point to its OpenSSL configuration file.

Serve the application over HTTPS. After verifying your email, open **Profile → Browser reminders** and enable the device. On iPhone and iPad, open Bloomify from its Home Screen icon first. Each device can opt in or out independently. Tapping the notification opens **Today**. The lock-screen message contains no plant names or account information.

### Account data

Use **Profile → Your data** to download a JSON export of your owned plants and their records. Download any photos you want to keep while your account is active. The export includes authenticated photo links. Deleting your account requires your current password and queues your photos for deletion from Cloudinary.

## Tests

    php vendor/bin/phpunit
    npm run build
    npx playwright install chromium
    npm run test:browser
    composer audit --locked
    npm audit

PHPUnit uses in-memory SQLite by default. The browser suite creates a temporary SQLite database and starts a dedicated loopback server. Tests simulate Cloudinary, Plant.id, email, and push providers.

## Upgrading an existing installation

Install PHP 8.3 or later and back up the database before deploying. Run `php artisan migrate` to apply the care history, seasonal schedules, photo journal, reminder, garden sharing, custom task, and observation migrations. Keep the existing `APP_KEY` and set `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`. Set `APP_URL` to the application's public HTTPS URL and point the web server's document root at `public/`.

Configure MySQL with `DB_URL`, `DATABASE_URL`, or `CLEARDB_DATABASE_URL`. You can also set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

After migrating, run `php artisan photos:privatize-covers` with the original `CLOUDINARY_URL` to copy recognizable old covers to authenticated storage. Run `php artisan photos:cleanup` or let the scheduler remove and invalidate the old public copies. Until converted, old cover URLs display a placeholder. Review reported assets separately, especially covers hosted outside the configured Cloudinary account.

If the migration reports duplicate plant metadata or identification records without a corresponding plant, reconcile those records from a backup before rerunning it.

### Scheduler and production checks

- Run `php artisan schedule:run` every minute from the release directory, or run one supervised `php artisan schedule:work` process. The Heroku `scheduler` process in `Procfile` can be scaled to one dyno. Use one scheduler instance per deployment. On multiple web hosts, use a shared session store, and use a persistent shared database for scheduler and web processes.
- Monitor `php artisan care:status` from an independent job. Its failure exit code reports missing or stale discovery and delivery heartbeats, more than 10 minutes of overdue queued work, or exhausted retries in the preceding 24 hours. Review application logs for attempt failures and inspect the `care_reminder_deliveries` table for terminal failures after correcting the underlying error.
- Keep HTTP and CLI processes on the same release and environment. Run the production migrations before starting the scheduler and restart the supervised worker on release. Run `php artisan schedule:list` to verify the scheduled commands and `php artisan care:status` once the first discovery and delivery runs have finished.
- Use a persistent database and back it up before every release. For SQLite, configure a persistent volume and keep the scheduler and web process on the same host. Before switching to a new domain or database backend, test migrations and restore a backup in staging.

CI builds the browser assets and runs PHPUnit and Chromium tests against SQLite. The MySQL 8.4 job exercises fresh migrations, scheduler commands, and the reminder suite. CI also checks the Composer and npm lockfiles for known advisories.
