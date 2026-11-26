# shipments

A web application that takes a signed-in user from entering shipment details through to a
quote, a choice of service, a sandbox shipment and its printable label, against the
Shiplogic sandbox API that Fastway Couriers South Africa runs on.

Built with PHP 8.5, MariaDB 11.4, Apache, Bootstrap 5.3 and Guzzle, in Docker with a 
a small, explicit structure described under [Architecture](#architecture) built using Claude.

- [Requirements](#requirements) · [Setup](#setup) · [Configuration](#configuration) · [Running the tests](#running-the-tests)
- [Using the app](#using-the-app) · [Operating procedures](#operating-procedures)
- [Architecture](#architecture) · [Security](#security) · [Design decisions and trade-offs](#design-decisions-and-trade-offs)
- [Test plan](#test-plan) · [Limitations and future work](#limitations-and-future-work)
- [Libraries and licences](#libraries-and-licences) · [Use of AI tools](#use-of-ai-tools) · [Candidate checklist](#candidate-checklist)

## Requirements

- Docker with Compose v2 (Docker Desktop on macOS or Windows, or Docker Engine on Linux)
- Git
- A Shiplogic **sandbox** API key (supplied separately for the assessment; see
  [Configuration](#configuration)). Everything except quoting, booking and labels works
  without one.

Nothing else is needed on the host: PHP, Composer and MariaDB all run in containers.

## Setup

From a fresh clone:

```bash
git clone https://github.com/mohaurammala/shipments.git
cd shipments
cp .env.example .env
```

Edit `.env`: set `DB_PASSWORD` and `DB_ROOT_PASSWORD` to passwords of your choice, and
`SHIPLOGIC_API_KEY` to your sandbox key. If ports 8080 or 8081 are taken on your machine,
change `APP_PORT` or `PHPMYADMIN_PORT`.

Build and start the app and the database:

```bash
docker compose up -d --build
```

Install the PHP dependencies inside the container (the project folder is mounted into it):

```bash
docker compose exec -e COMPOSER_ALLOW_SUPERUSER=1 app composer install
```

Create the tables in the app database and in the test database:

```bash
docker compose exec app php bin/migrate.php
docker compose exec app php bin/migrate.php --database=test
```

Create the first admin account (the password prompt is hidden; at least 12 characters):

```bash
docker compose exec app php bin/create-admin.php you@example.com
```

Open **http://localhost:8080** (or your `APP_PORT`) and sign in. `http://localhost:8080/health`
answers `ok` when the app can reach its database.

Optional: phpMyAdmin, only on your machine, at http://localhost:8081:

```bash
docker compose --profile tools up -d
```

Stop everything with `docker compose --profile tools down`. Add `-v` to also delete the
database volume (all data).

## Configuration

All configuration comes from environment variables, read from `.env`.
Values set by Docker take precedence over the file.

| Variable | Default | Meaning |
|---|---|---|
| `APP_ENV` | `sandbox` | `sandbox` marks every shipment "SANDBOX TEST ONLY - DO NOT COLLECT / DELIVER". `production` disables the demo password reset. |
| `APP_DEBUG` | `false` | `true` shows error details on error pages. Keep `false` anywhere but your own machine. |
| `APP_HTTPS` | `false` | `true` when served over HTTPS: secure session cookie and HSTS. |
| `APP_PORT`, `PHPMYADMIN_PORT` | `8080`, `8081` | Host ports, bound to 127.0.0.1 only. |
| `DB_*` | | Database connection. `DB_HOST` is the Compose service name `db`, not `localhost`. `DB_TEST_DATABASE` must differ from `DB_DATABASE`. |
| `SHIPLOGIC_BASE_URL` | `https://api.shiplogic.com` | The same URL serves sandbox and production; the key decides which. |
| `SHIPLOGIC_API_KEY` | | A sandbox key from the portal: **Integrations > API Keys**. Used only server-side. |
| `SHIPLOGIC_CONNECT_TIMEOUT`, `SHIPLOGIC_TIMEOUT` | `5`, `20` | Seconds. |
| `QUOTE_TTL_MINUTES` | `30` | How long a quote's prices may be used before a new quote is needed. |
| `ADMIN_RESET_KEY` | empty | Demo only: enables `/reset-password` (see below). At least 32 characters; generate with `openssl rand -hex 32`. Leave empty to disable. |

## Running the tests

```bash
docker compose exec app vendor/bin/phpunit
```

372 tests: unit tests, and integration tests that run against `DB_TEST_DATABASE`, each inside
a transaction that is rolled back. Shiplogic is simulated with Guzzle's `MockHandler`; the
tests never call the real API. Run one suite with `--testsuite unit` or
`--testsuite integration`.

## Using the app

1. **Bookings** (`/`) lists your bookings (admins see everyone's). **New booking** opens the
   shipment form: collection and delivery address and contact, one or more parcels
   (add and remove rows), declared value and instructions. In sandbox mode the instructions
   are prefilled with the required test markers.
2. **Save draft.** Validation errord are listed at the top and shown next to each field; everything
   entered is kept.
3. **Get a quote.** Each service the courier offers is shown with its estimated delivery and
   its total **including VAT**, with the amount excluding VAT. If there are no services,
   the app says so and offers nothing else.
4. **Choose this service**, review, then **Book shipment**. The confirmation shows the
   shipment id and tracking reference.
5. **Open or print label** opens the label PDF in a new tab, to print from the browser.
6. **Cancel shipment** (after a confirmation) cancels a booked shipment with the courier.
   The booking keeps its shipment id and tracking reference, shows as Cancelled, and has no
   label any more.

The progress bar at the top of each booking shows the step reached. Editing details after a
quote clears the chosen service and needs a new quote; a booked shipment can no longer be
edited.

Admins also have **Users**: add accounts (there is no public sign-up), change roles,
deactivate and reactivate. At least one active admin always remains.

## Operating procedures

**Booking a shipment.** Enter details → Save draft → Get a quote → Choose this service →
Book shipment → Open or print label. Quotes hold for `QUOTE_TTL_MINUTES`; after that the page
asks for a new quote before booking.

**A booking says "Waiting for confirmation" (outcome unknown).** This happens when the request
to create the shipment may have reached the courier but no clear answer came back: a timeout,
a server error, or an unreadable success. The app cannot know whether the shipment exists, so
it never sends the booking again by itself, and nobody can book it again from the page. To
resolve it, an admin:

1. Opens the booking and copies the booking reference shown there.
2. Signs in to the Shiplogic portal and searches for a shipment whose customer reference is
   that booking reference (every shipment is sent with it).
3. If one exists: enters its shipment id (and tracking reference) and chooses **It was
   booked**. The label then works as normal.
4. If none exists: chooses **No shipment exists: allow booking again**. The owner sees why and
   can book again (with a fresh quote if the old one has expired).

**A label will not open.** The booking page shows the reason; use **Open or print label**
again. This only ever asks for the existing shipment's label and can never create a second
shipment. Label links expire, so each click fetches a fresh one.

**A booking was rejected ("Booking not made").** The courier refused it, so no shipment was
created. The page says why (for example, a parcel over the weight limit). Edit the details if
needed, get a new quote and book again.

**Someone forgot their password.** An admin can create a new account for them. In this demo,
if `ADMIN_RESET_KEY` is set, **Forgotten your password?** on the sign-in page sets a new
password for any account to whoever knows that key; it is off by default and always off when
`APP_ENV=production`.

**Logs.** `docker compose logs app` shows one JSON line per event. An error page shows a
six-character reference; search the logs for it to find the details.

## Architecture

```
public/            index.php (front controller), .htaccess (routing), assets/
src/
  bootstrap.php    composition root: builds every object and returns the Kernel
  routes.php       every URL, its controller method and its middleware
  Http/            Request, Response, Router, Kernel, ErrorHandler, Pages, Flash, CsrfToken,
                   Middleware/ (RequireLogin, RequireRole, VerifyCsrfToken, SecurityHeaders)
  Controllers/     thin: read the request, call a service, render or redirect
  Services/        the rules: BookingDraftService, QuoteService, BookingService, LabelService,
                   LoginService, Authenticator, UserService, ...
  Shiplogic/       the only code that talks to the API: client, request mappers, response
                   parsing, failure types, user-facing messages
  Repositories/    the only code that writes SQL
  Domain/          plain values and enums: ShipmentInput, Address, Parcel, Money, Rate,
                   Booking, BookingStatus, ...
  Validation/      ShipmentValidator, ParcelValidator, UserValidator
  Core/            Config, Database, Logger, View, Migrator, sessions, clock
views/             plain PHP templates; every value printed through escape()
database/migrations/  numbered SQL files, applied once each by bin/migrate.php
bin/               migrate.php, create-admin.php
docker/            Dockerfile, Apache and PHP configuration, database init script
tests/             Unit/ and Integration/
```

A request goes `.htaccess` → `index.php` → `Kernel` → security headers → `Router` → route
middleware (sign-in, role, CSRF) → controller → service → repository / Shiplogic client →
`Response`. Anything thrown on the way becomes a safe error page in one place.


Every move is a conditional `UPDATE … WHERE status IN (…)`, so two requests can never move the
same booking at once. The database also enforces the invariants with `CHECK` constraints
(a CREATED or CANCELLED booking has a shipment id; one shipment id can belong to one booking only).

## Security

- **Credentials stay on the server.** The Shiplogic key is read from the environment by one
  class, sent only in the `Authorization` header, never logged, never in browser code, never
  committed (`.env` is git-ignored; `.env.example` holds placeholders).
- **Sign-in:** Argon2id password hashes (PHP 8.5's bcrypt ignores bytes past the 72nd; old
  hashes upgrade on sign-in), the same message and timing for a wrong email or password,
  throttling (5 failures per account and address, 20 per address, 15 minutes), a new session
  id on sign-in, HttpOnly and SameSite cookies, 30-minute idle and 8-hour absolute timeouts.
  A deactivated user is signed out on their next click.
- **Authorisation:** every page but sign-in and `/health` needs a signed-in user; admin pages
  need the admin role. Bookings are always loaded with the owner check in the SQL query, so
  another user's booking is a 404.
- **Requests:** a CSRF token on every form that changes something; input normalised in one
  place (trimmed, valid UTF-8, size-limited, no unexpected arrays), validated per field,
  escaped on output; SQL only through prepared statements.
- **Responses:** Content-Security-Policy limited to this site (plus `data:` images for
  Bootstrap's icons), `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`;
  `Cache-Control: no-store` on signed-in pages; server and PHP versions hidden.
- **Errors and logs:** users see plain messages and a reference; details go to the log only.
  No Shiplogic response text is shown, and no request bodies, label links or contact details
  are logged.
- **Label links** are signed URLs to customer data: stored only in the label history, shown
  only to the booking's owner or an admin, and redirected to only if they are https on
  Shiplogic's domain.

## Design decisions and trade-offs

**Preventing duplicate bookings, and what happens after a timeout.** Three layers:

1. *Browser:* the Book button locks on the first click, and every form ends in a redirect, so
   neither a double click nor a refresh sends it twice. Helpful, but a browser can be bypassed.
2. *Database:* booking first **claims** the booking with one conditional
   `UPDATE … SET status='CREATING' WHERE status IN ('SELECTED','FAILED') AND …`. However many
   requests arrive at once (two tabs, a replayed request), exactly one changes the row; only
   that one calls Shiplogic. The method is marked PHP 8.5 `#[\NoDiscard]`, so code that
   ignored its answer would raise a warning (which the error handler turns into a failure).
   A test fires two requests at the same booking and asserts one `POST /shipments`.
3. *No automatic retries of `POST /shipments`*, and failures are classified by what they prove.
   A 4xx, or a connection that was never established (Guzzle 8's `ConnectException`), means
   nothing was created: the booking is FAILED and may be booked again. A timeout after
   sending, a 5xx, or a success without a shipment id means the shipment **may** exist: the
   booking becomes OUTCOME_UNKNOWN and cannot be booked again until an admin checks the
   portal using the booking reference sent as `customer_reference` (see the procedure above).
   A request that dies mid-booking leaves the booking in CREATING; once it is older than the
   longest possible request, it is treated as unknown too.

The cost: an uncertain outcome needs a person to resolve it. The alternative, retrying, risks
two shipments and two charges, which is worse. If the account may set
`custom_tracking_reference`, Shiplogic itself would reject a duplicate; that is listed under
future work because the sandbox account's permission was not confirmed.

**Quotes belong to exact inputs.** The normalised shipment is hashed (SHA-256 of canonical
JSON). A quote, a selection and a booking are only valid while the hash matches, so editing
anything that was quoted clears the selection, and reformatting (a phone number with spaces,
`2.50` vs `2.5`) does not. Quotes also expire after `QUOTE_TTL_MINUTES`.

**VAT and money.** Shiplogic's `rate` is the total including VAT and insurance; the page shows
it with the VAT percentage and the amount excluding VAT. Amounts are integer cents in PHP and
`DECIMAL` in MariaDB, rounded once when they arrive, never floats.

**Labels.** "Open or print label" is a GET link to `/bookings/{id}/label` in a new tab. It
fetches a fresh signed link each time (they expire) and redirects to the PDF, whose viewer
prints it. A GET suits it because nothing about the booking changes, and a POST that
redirects off-site would be blocked by the `form-action` CSP rule.

**Cancelling.** `POST /shipments/cancel` with the booking's tracking reference. The booking
moves CREATED → CANCELLED only when the courier's answer says the shipment is `cancelled`.
Unlike booking, it needs no claim and no unknown state: cancelling twice cannot create
anything, so after a refusal, a timeout or an unreadable answer the booking simply stays
CREATED and the user can try again. A booking without a tracking reference (possible after an
admin resolves an unknown outcome without one) is cancelled in the Shiplogic portal instead.

**Retries.** Reading rates and labels is retried once after a network failure or a 5xx; it
changes nothing, so it is safe. Creating a shipment never is.

**No framework.** The brief allows plain PHP; a framework would hide the parts being assessed
(routing, request handling, error handling, sessions). The code keeps one job per class,
dependencies passed in through constructors from one composition root, and no globals, so each
part is tested on its own.

**Containers.** Apache and PHP in one container (simplest to run), MariaDB with a named
volume and a health check, phpMyAdmin only behind a profile. The code is bind-mounted for
development; a production image would copy the code and run `composer install --no-dev` at
build time instead.

## Test plan

Automated (`vendor/bin/phpunit`, 400 tests):

- **Validation:** every rule (missing or invalid fields, non-positive dimensions or weight,
  invalid declared values, 1–20 parcels, South African mobiles and postcodes, different
  addresses), each reported against its own field.
- **Domain:** all 64 status transitions; money in exact cents; the input hash ignores field
  order and formatting but not real changes.
- **Database rules:** status and invariant `CHECK`s, unique email (case- and
  accent-insensitive), unique shipment id, foreign keys.
- **Shiplogic client:** request shape and headers, retries for reads only, every failure type
  (refused connection, timeout before or after headers, 4xx with message, 5xx, non-JSON,
  missing fields), calls recorded without bodies, and a failed recording never losing a result.
- **Booking:** the race between two requests, every outcome, rebooking blocked after an
  unknown outcome, stuck bookings, admin resolution, the `#[\NoDiscard]` warning.
- **Labels:** retries never create a shipment; failures are recorded; a cancelled shipment has none.
- **Cancelling:** sent by tracking reference; never sent twice for a cancelled booking; a
  refusal, a timeout or an answer that does not say `cancelled` leaves the shipment booked.
- **Sign-in and access:** throttling, timeouts, deactivation, CSRF, roles, ownership (404).

Manual, end to end (done during development against a stand-in Shiplogic server, and to be
repeated against the real sandbox): book a shipment through every step and print its label;
an invalid form keeps every value; no rates; a rejected booking then fixed; a timeout and a
server error each resolved by an admin; two simultaneous Book clicks make one shipment; the
label failing then retried; every page at 375 px and 1280 px wide with no console errors.

## Limitations and future work

- Resolving an unknown outcome is manual: the documentation seen has no shipment lookup by
  reference. Nothing built in to look into detail except repsone codes and api logs
- Password reset by email, maybe two-factor sign-in, an audit trail of admin actions, and address autocomplete were left out of scope.
- Sessions use PHP's file store in the container; several app servers would need a shared
  store. Behind a reverse proxy, the client address used for throttling needs a trusted-proxy
  setting.
- Shipment fetch in the case of cancel status failing with 'cannot cancel shipments that have already been collected',
  the confirmation can be confirmed with a check of the status field from https://api.shiplogic.com/shipments?tracking_reference=

## Libraries and licences

| Library | Licence | Use |
|---|---|---|
| [guzzlehttp/guzzle](https://github.com/guzzle/guzzle) 8.6 (with guzzlehttp/psr7, promises) | MIT | HTTP client for Shiplogic |
| [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) 5.8 | BSD-3-Clause | Reads `.env` on hosts without Docker |
| [phpunit/phpunit](https://github.com/sebastianbergmann/phpunit) 13.4 (development only) | BSD-3-Clause | Tests |
| [Bootstrap](https://getbootstrap.com) 5.3.9, in `public/assets/vendor/bootstrap/` with its licence | MIT | Layout and components |

Docker images: `php:8.5.13-apache`, `mariadb:11.4.13`, `phpmyadmin:5.2.3` (official images).
This project is licensed under Apache 2.0 (see `LICENSE`).

## Use of AI tools

This project was built through AI assisted development using **Claude** (Anthropic), used through Claude Code, as the brief
allows with disclosure. It was used to plan(from the spec I gave) and review the design, to explain unfamiliar parts
(Docker) through step-by-step tutorials, and to write code at my request.

- Written by me, following those tutorials and with Claude's reviews: the Docker setup
  (Dockerfile, Compose file, Apache and PHP configuration, database init script), the
  configuration, database and logging classes, the shipment domain model and its validation,
  and the later restructuring of routes and enums.
- Written by Claude at my request and reviewed by me: the remaining application code
  (HTTP layer, authentication, user management, the booking workflow, the Shiplogic
  integration and labels), the tests and this README(Edited by me).

All design decisions were discussed and approved by me, and I can explain and justify the code
submitted.

## Candidate checklist

| Task | Completed |
|---|---|
| Shipment and contact details form | ✅ |
| Multiple parcel entry and declared value | ✅ |
| Quotes and service selection | ✅ |
| Shipment creation and confirmation | ✅ |
| Printable PDF label | ✅ |
| Validation and error handling | ✅ |
| Responsive Bootstrap interface | ✅ |
| Credentials kept server side | ✅ |
| Tests, README, SOP and design answers | ✅ |
