# ULS HDC Cumulative Record System

Web system for the **Human Development Center (HDC) of the University of La Salette, Santiago City**.
It replaces the paper forms filled out during enrollment (6,000+ students):

- **HDC Form 5, Student Cumulative Record**: filled out online by each student, with data privacy consent.
- **Entrance Test Result (SATA)**: encoded by HDC staff.
- **HDC Form 8, Intake Interview**: filled out by a counselor during an interview. Highly sensitive.

Staff can track who has submitted, search and filter records by college and year level, get notified about new
submissions, and view **aggregate reports** (counts only, small groups shown as "<5").

Built with Laravel 13, MySQL 8, Blade, Tailwind CSS, Alpine.js and Chart.js.

## Contents

- [Roles](#roles)
- [Local setup (Windows and Mac)](#local-setup-windows-and-mac)
- [Test accounts (local only)](#test-accounts-local-only)
- [Running the tests](#running-the-tests)
- [Deploying to shared hosting (Hostinger)](#deploying-to-shared-hosting-hostinger)
- [Cron and queue](#cron-and-queue)
- [Importing students and SATA results](#importing-students-and-sata-results)
- [Backups and restore (FR-11)](#backups-and-restore-fr-11)
- [Security](#security)
- [Deployment checklist](#deployment-checklist)

## Roles

| Role | Can do | Cannot do |
| --- | --- | --- |
| **Student** | Register, fill out and edit their own Form 5 until staff mark it reviewed. | See anyone else's record, SATA results, Form 8 or reports. |
| **Counselor** | For students in their **assigned colleges only**: view Form 5, mark reviewed, encode SATA, fill out Form 8 intake interviews, view reports. | See students outside their colleges; edit a finalized intake interview. |
| **Director** | Everything a counselor can do, for **all colleges**; create and deactivate counselor accounts and assign colleges; manage colleges and programs; edit finalized intake interviews (logged). | |

Every view of a full record, SATA result or intake interview, and every report view or export, is written to the
`access_logs` table (who, what, when, IP).

## Local setup (Windows and Mac)

### What you need

| | Windows | Mac |
| --- | --- | --- |
| PHP 8.3+ with `pdo_mysql`, `zip`, `intl`, `mbstring`, `fileinfo` | [Laravel Herd](https://herd.laravel.com/windows) or [Laragon](https://laragon.org) | [Laravel Herd](https://herd.laravel.com) or `brew install php` |
| Composer 2 | Included with Herd/Laragon | Included with Herd, or `brew install composer` |
| MySQL 8 | Laragon's MySQL, or [MySQL Installer](https://dev.mysql.com/downloads/installer/) | DBngin, or `brew install mysql` |
| Node.js 20+ and npm | [nodejs.org](https://nodejs.org) | `brew install node` |
| Git | [git-scm.com](https://git-scm.com) | Included with Xcode Command Line Tools |

### Steps

The commands are the same on both systems. Use **PowerShell** or **Git Bash** on Windows (in PowerShell, use
`copy` instead of `cp`).

```bash
git clone <repository-url> uls-hdc-system
cd uls-hdc-system

composer install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database (for example `uls_hdc_system`), then edit `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uls_hdc_system
DB_USERNAME=root
DB_PASSWORD=your-local-mysql-password

DIRECTOR_EMAIL=director@uls.test
DIRECTOR_PASSWORD=Local-dev-2026!     # 10+ characters: upper and lower case, a number and a symbol
```

Create the tables and the starting data (colleges and programs, the director, and a sample counselor), then build the
front end:

```bash
php artisan migrate --seed
npm install
npm run build
```

Start the app:

```bash
composer run dev        # web server + Vite with live reload, at http://localhost:8000
# or, without live reload:
php artisan serve
```

> **Herd users:** the site is also available at `http://uls-hdc-system.test` once the folder is in Herd's sites
> directory. Set `APP_URL` to match.

## Test accounts (local only)

| Role | E-mail | Password |
| --- | --- | --- |
| Director | the `DIRECTOR_EMAIL` in your `.env` | the `DIRECTOR_PASSWORD` in your `.env` |
| Counselor (CIT) | `counselor@uls.test` | `password` |
| Student | register at `/register` | your choice |

The sample counselor is created by `SampleCounselorSeeder`, which **refuses to run in production** (even when called
directly). Never create demo accounts on the live server.

## Running the tests

The tests use an in-memory SQLite database, so they never touch your MySQL data.

```bash
php artisan test                                  # everything
php artisan test --compact tests/Feature/ReportTest.php
php artisan test --filter=intake                  # by name
vendor/bin/pint --dirty                           # code style, after editing PHP files
npm run build                                     # must succeed before deploying
```

## Deploying to shared hosting (Hostinger)

These steps assume a Hostinger plan with **SSH access**, PHP 8.3+, MySQL 8 and a domain or subdomain such as
`hdc.example.edu.ph`. Other cPanel/hPanel hosts work the same way. Read the
[Deployment checklist](#deployment-checklist) first: the university's data protection officer must approve the hosting.

### 1. Prepare the hosting panel (hPanel)

1. **PHP version:** *Advanced → PHP Configuration* → PHP 8.3 or newer. Make sure `pdo_mysql`, `zip`, `mbstring`,
   `intl` and `fileinfo` are enabled.
2. **Database:** *Databases → MySQL Databases* → create a database and a user with a strong password. Note the names
   (Hostinger prefixes them, e.g. `u123456789_hdc`).
3. **SSL:** *Security → SSL* → install the free certificate for the domain. The app redirects HTTP to HTTPS and sends
   HSTS, so the certificate must work before you go live.
4. **SSH:** *Advanced → SSH Access* → enable it and note the host, port and user.

### 2. Upload the code

Keep the application **outside** `public_html`; only the `public/` folder may be reachable from the web.

```bash
ssh -p PORT USER@HOST
cd ~
git clone <repository-url> hdc
cd hdc
composer install --no-dev --optimize-autoloader
```

Then make the domain serve `~/hdc/public`, using **one** of these:

- **Subdomain or addon domain:** in hPanel, set the document root to `hdc/public` (if the panel allows it), or
- **Main domain:** replace `public_html` with a link to the app's `public` folder:

  ```bash
  mv ~/domains/hdc.example.edu.ph/public_html ~/domains/hdc.example.edu.ph/public_html.old
  ln -s ~/hdc/public ~/domains/hdc.example.edu.ph/public_html
  ```

  (The exact path of `public_html` is shown in hPanel's File Manager.)

### 3. Build the front end

Shared hosting usually has no Node.js. Build on your own computer and upload the result:

```bash
# on your computer, in the project folder
npm ci
npm run build
scp -P PORT -r public/build USER@HOST:~/hdc/public/
```

Repeat this every time you deploy changes to CSS or JavaScript.

### 4. Configure `.env`

```bash
cd ~/hdc
cp .env.production.example .env
nano .env                       # fill in every value marked CHANGE
php artisan key:generate --force
```

Important values (all explained in `.env.production.example`):

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `FORCE_HTTPS=true`
- `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `SESSION_LIFETIME=60`
- `DB_*` from step 1; `MAIL_*` for password-reset and backup-failure e-mails
- `DIRECTOR_EMAIL` / `DIRECTOR_PASSWORD`: at least 10 characters with upper- and lowercase letters, a number and a symbol (the seeder refuses
  anything weaker, and refuses the placeholder)
- `BACKUP_ARCHIVE_PASSWORD`: a long random password, **stored in the HDC password manager, not only on the server**

> Keep a copy of `APP_KEY` and `BACKUP_ARCHIVE_PASSWORD` somewhere safe outside the server. `APP_KEY` encrypts the
> Form 8 counselor notes; without it they cannot be read, even from a backup.

### 5. Storage permissions

```bash
chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

Uploaded 2x2 photos live in `storage/app/private` and backups in `storage/app/backups`. Both are outside `public/`
and are only served through logged-in, permission-checked routes. **Do not run `php artisan storage:link`**; the
app does not use the public disk.

### 6. Database and caches

```bash
php artisan migrate --force          # create/update tables
php artisan db:seed --force          # colleges/programs + the director (no demo data in production)
php artisan optimize                 # caches config, routes, views and events
```

Log in as the director at `https://your-domain/login`, change the password, then create the counselor accounts and
assign their colleges.

### 7. Cron (required)

See [Cron and queue](#cron-and-queue). Without the cron entry, **no backups are made**.

### 8. Check the deployment

```bash
php artisan about                     # Environment: production, Debug Mode: OFF, URL: https://...
php artisan backup:run                # one manual backup; should end with "Backup completed!"
php artisan backup:list
curl -sI http://your-domain/login | head -3     # 301 to https://
curl -sI https://your-domain/login | grep -iE "strict-transport|x-frame|x-content-type|referrer"
```

### Updating the live site

```bash
cd ~/hdc
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

Upload a fresh `public/build` (step 3) if the CSS or JavaScript changed.

## Cron and queue

### Scheduler (one cron entry)

All scheduled work runs from Laravel's scheduler (`routes/console.php`), in Philippine time:

| Time | Task |
| --- | --- |
| 01:30 | `backup:clean`: delete backups older than 30 days |
| 02:00 | `backup:run`: database + private uploads, encrypted |
| 02:30 | `auth:clear-resets`: remove expired password-reset tokens |
| hourly | remove leftover temporary export files and import previews older than a day |
| 07:00 | `backup:monitor`: e-mail if the newest backup is missing or too old |
| weekly | `queue:prune-failed`: forget failed jobs older than 30 days |

Add **one** cron job that runs every minute. In hPanel: *Advanced → Cron Jobs → Custom*:

```
* * * * * cd /home/USER/hdc && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP binary of the version you selected (run `which php` over SSH). Check the tasks with
`php artisan schedule:list`.

### Queue worker

The app uses the `database` queue. Nothing is queued today (notifications are sent immediately), but a worker keeps
future e-mail and background jobs moving. Choose one:

- **Shared hosting (no Supervisor):** set `QUEUE_WORKER_VIA_SCHEDULER=true` in `.env`. The cron entry above then also
  starts a short-lived worker every minute (`queue:work --stop-when-empty --max-time=55`).
- **VPS with Supervisor:** set `QUEUE_WORKER_VIA_SCHEDULER=false` and add `/etc/supervisor/conf.d/hdc-worker.conf`:

  ```ini
  [program:hdc-worker]
  command=php /var/www/hdc/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  user=www-data
  numprocs=1
  autostart=true
  autorestart=true
  stopwaitsecs=3600
  redirect_stderr=true
  stdout_logfile=/var/www/hdc/storage/logs/worker.log
  ```

  Then `sudo supervisorctl reread && sudo supervisorctl update`. Run `php artisan queue:restart` after each deploy.

## Importing students and SATA results

**Import** in the staff menu (director: all colleges; counselors: only their assigned colleges, checked row by row):

1. Download the **Excel** or **CSV template** for *Student roster* or *SATA results*. The Excel template has an
   *Instructions* sheet and dropdowns for program codes (only your programs), year levels and descriptors. Row 2 is an
   example; its student number starts with `EXAMPLE`, so it is never imported.
2. Upload the filled-in file (.xlsx or .csv, up to 5 MB and 2,000 rows).
3. Check the **preview**: every row is marked *Ready*, *Skipped* (already exists, duplicate in the file, or the example
   row) or *Error*, with the reason under each wrong cell. Nothing is saved yet.
4. Click **Import** and confirm. Only the ready rows are saved, all in one database transaction. The summary shows how
   many were imported, skipped and wrong, with a **problem report (CSV)** to fix and re-upload.

Rows use the same rules as the forms (names, student number format, e-mail, program code in your colleges, year level
1–5; SATA scores and descriptors from `config/sata.php`, exam date not in the future). New students get their
**student number as a temporary password** and must change it on first login. Every import is recorded in the access
log (`import`, with the counts).

## Backups and restore (FR-11)

Backups use [spatie/laravel-backup](https://spatie.be/docs/laravel-backup) (`config/backup.php`):

- **What:** the MySQL database (gzipped `mysqldump`) and `storage/app/private` (student photos). The code is not
  backed up; it comes from Git.
- **When:** every night at 2:00 AM Philippine time (needs the cron entry), or any time with **Backups → Back up now**.
- **Where:** `storage/app/backups/uls-hdc/` (change with `BACKUP_PATH`).
- **Protection:** each archive is a ZIP encrypted with **AES-256** using `BACKUP_ARCHIVE_PASSWORD`, and checked after
  it is written. In production, `backup:run` refuses to run without a password.
- **Kept:** 30 days (`BACKUP_KEEP_DAYS`), then deleted automatically. The newest backup is never deleted.
- **Alerts:** an e-mail to `BACKUP_NOTIFICATION_EMAIL` (or the director) when a backup fails or none was made in the
  last day.
- **Shared-hosting friendly dump:** `--no-tablespaces` and no GTID statements, so the dump works without the
  PROCESS/SUPER privileges that shared-hosting database users do not have.

> **A backup on the same server is not enough.** If the hosting account is lost, so are its backups. Download the
> latest archive regularly (for example weekly) to university-controlled storage, or configure a second backup disk.
> Where off-site copies are kept is an HDC decision (see the checklist).

**Backups page (director only):** *Backups* in the menu lists every backup with its date and size, and has
**Back up now**, **Download** and **Delete** (each asks for confirmation and is recorded in the access log). The director
dashboard shows the date of the last successful backup and turns red when it is more than 2 days old, which usually
means the cron job is not running.

Useful commands:

```bash
php artisan backup:run            # make a backup now
php artisan backup:list           # list backups and their health
php artisan backup:monitor        # check health now
```

### How to restore

Practice this on a **separate test database** at least once per semester, so you know the backups work.

1. **Get the archive.** As the director, open **Backups** and click **Download** next to the backup you need (or copy
   it from `storage/app/backups/uls-hdc/` with the hPanel File Manager or `scp`).
2. **Open it with the backup password.** The archive is AES-256 encrypted, which the built-in macOS and Windows
   unzip tools cannot open. Use **7-Zip** (Windows), **Keka** or `7zz` (Mac, `brew install sevenzip`), or `7z` (Linux):

   ```bash
   7z x 2026-10-20-02-00-00.zip        # asks for BACKUP_ARCHIVE_PASSWORD
   ```

   Inside are `db-dumps/mysql-<database>.sql.gz` and `private/` (the photos).
3. **Put the site in maintenance mode:** `php artisan down`.
4. **Restore the database** into an empty database:
   - **phpMyAdmin** (hPanel): select the database → *Import* → choose the `.sql.gz` file directly, or
   - **SSH:**

     ```bash
     gunzip -c db-dumps/mysql-u123_hdc.sql.gz | mysql -u DB_USER -p DB_NAME
     ```

5. **Restore the photos:** copy the extracted `private/` folder over `~/hdc/storage/app/private/`.
6. **Use the same `APP_KEY`** as when the backup was made (from the password manager); otherwise the Form 8 notes
   cannot be decrypted.
7. **Finish:** `php artisan optimize:clear && php artisan optimize && php artisan up`, then log in and open a few
   records, a photo and an intake interview to confirm.

## Security

What the system enforces (covered by the tests in `tests/Feature`):

- **Access:** every staff route requires login and the counselor/director role; every record, SATA result and intake
  interview goes through a policy that checks the student's college against the user's assigned colleges. Students
  get 403 on all staff and report routes.
- **Accounts:** the role can never be set from a form (it is not mass-assignable). Passwords need at least
  10 characters with upper- and lowercase letters, a number and a symbol, must differ from the current one, and
  must not appear in known data breaches (checked online when reachable). Changing it signs out the user's other
  sessions. Login is limited to 5 attempts per minute per e-mail and IP; registration
  and password-reset requests are rate limited too. Deactivated accounts are signed out on their next request.
- **Data:** Form 8 counselor notes and "specify" fields, and Form 5 family notes, are encrypted in the database.
  Photos are stored privately (JPG/PNG/WebP up to 2 MB) and served only to authorized users. Reports show counts
  only, with small groups shown as "<5".
- **Web:** CSRF protection on every form, no unescaped output in templates, HTTPS forced in production with HSTS,
  `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, and no browser caching of signed-in
  pages (important on shared computers).
- **Audit:** views of records, SATA results and intake interviews, edits of finalized interviews, and report
  views/exports are written to `access_logs`.

## Deployment checklist

### Technical (before go-live)

- [ ] Hosting approved by the university's data protection officer (see below).
- [ ] HTTPS certificate installed; `http://` redirects to `https://`.
- [ ] `.env` from `.env.production.example`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
      `SESSION_ENCRYPT=true`.
- [ ] `APP_KEY` and `BACKUP_ARCHIVE_PASSWORD` stored in the HDC password manager, not only on the server.
- [ ] `php artisan migrate --force` and `php artisan db:seed --force` run; director password changed after first login.
- [ ] No demo accounts exist (`counselor@uls.test` must not be in the live database).
- [ ] Cron entry added; `php artisan schedule:list` shows the backup tasks.
- [ ] `php artisan backup:run` succeeds and a **test restore** into a separate database works.
- [ ] Mail works (test a password reset); backup alerts go to the right person.
- [ ] Counselor accounts created and their colleges assigned.
- [ ] `php artisan optimize` run after the last change.

### Decisions HDC still needs to make

These are open questions from `docs/needs-assessment.md` (section 13) and from building the system. Each has a
working default so the system runs, but HDC should confirm them before real student data is entered.

| Decision | Current default | Where to change |
| --- | --- | --- |
| **Semester dates** used by the report filters for intake interviews and SATA results | 1st: Jun 1–Oct 31, 2nd: Nov 1–Mar 31, Summer: Apr 1–May 31 (school year starts in June) | `config/hdc.php` → `semesters` |
| **Small-group privacy rule** for reports | Counts 1–4 shown as "<5"; averages from fewer than 5 students hidden. Not yet handled: a hidden cell can sometimes be worked out from a total or by comparing two filters, and a 0 in a very small filtered group still says something about everyone in it. HDC may want a second hidden cell next to each hidden one ("secondary suppression") or a minimum group size before a report is shown at all. | `HDC_MIN_GROUP_SIZE`; report code in `app/Support/Reports` |
| **Follow-up triggers** (Form 8 answers that flag "Needs follow-up" and notify the director) | Attempted suicide, rape or sexual assault, substance abuse | `config/hdc.php` → `follow_up_concerns` |
| **Final form fields** for Form 5 and Form 8 | Taken from the scanned paper forms (`docs/form-fields.md`) | Needs code changes; confirm before go-live |
| **Data retention period**: how long to keep Form 5 records, intake interviews, SATA results and access logs after a student leaves | Kept indefinitely (nothing is deleted automatically) | To be built once decided |
| **Backup retention and off-site copies** | 30 days, on the same server | `BACKUP_KEEP_DAYS`; off-site location to be decided |
| **Hosting approval** by the university's **data protection officer**, under the Data Privacy Act of 2012 (including where the server is and who can access it) | Not yet approved | Before go-live |
| **Who may self-register**: today anyone can register as a student with any unused student number, and e-mail addresses are not verified | Open registration | Options: check against the registrar's list, require a school e-mail, or have HDC create accounts |
| **Counselor-to-college assignments**, and whether any counselor needs all colleges | Director assigns them in the app | Director → Counselors |
| **Session timeout** on shared computers | Signed out after 60 idle minutes in production | `SESSION_LIFETIME` |
| **Temporary password for imported students** | Their student number (they must change it at first login). Student numbers are easy to guess, so someone who knows a classmate's number could log in first. Safer: e-mail a one-time link, or a random password printed for each student | `app/Support/Imports/RosterImport.php` |
| **Phone numbers**: Form 5 and Form 8 accept only Philippine mobile numbers (09XXXXXXXXX / +639XXXXXXXXX). The paper form says "Cell phone / landline" | Mobile only | `App\Support\Validation` |
| **Who may export what**: counselors can export a student's full Form 5 (and Form 8 for their colleges); the director can export lists with contact details | As described | Export controllers |
| **Time zone** for dates shown in the app | UTC (times show 8 hours behind Philippine time) | `config/app.php` → `timezone`; best decided **before** real data is entered, because existing timestamps are stored in that zone |
