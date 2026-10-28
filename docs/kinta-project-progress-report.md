# KINTA Project Progress Report

**Prepared:** 28 October 2026  
**Projects:** CI4 backend at `C:\xampp\htdocs\kinta`; Flutter application at `C:\flutter_projects\kinta_intern_justin`  
**Purpose:** Explain the work completed so far, how it was implemented, why the design was chosen, what the current result is, and what remains before production use.

## Executive summary

KINTA is being developed as two separate applications: a Flutter frontend/PWA and a CodeIgniter 4 (CI4) JSON API backed by MySQL. The CI4 project is the new backend; it is not an extension of the unrelated `inspection-defix` project. The FlutterFlow-exported application remains the frontend and has not been converted into CI4 views.

The foundation has been built and exercised: CI4 endpoints provide master data and process operations; migrations define the master and process schema; Flutter can load and cache master data, keep process work locally, and send queued work to CI4. The user has reported successful tests of the main process flow, database persistence, offline use after initial data was cached, and synchronization. Authentication is currently a prototype: CI4 verifies passwords online, while Flutter also has a local PBKDF2 password verifier for offline sign-in. The newer proposed signed offline-grant design has only been documented and compared; it has not been implemented.

This is a development checkpoint, not a production-ready release. In particular, server-side authentication and authorization of process and sync APIs, a signed offline grant, comprehensive error/recovery behavior, and end-to-end security decisions remain outstanding.

## 1. Architecture and boundaries

```text
FlutterFlow-exported Flutter application
  - UI and navigation
  - HTTP/JSON client
  - IndexedDB-backed local storage for Flutter Web
  - offline drafts and sync queue
             |
             | HTTP / JSON
             v
CodeIgniter 4 API (PHP)
  - authentication endpoints
  - master-data endpoints
  - process and synchronization endpoints
             |
             v
MySQL: kinta_db
```

The frontend and backend stay in separate project folders. Flutter calls CI4 over HTTP; CI4 is the service that reads and writes MySQL. The database is not accessed directly by the browser. This keeps database credentials on the backend and allows one API contract to serve the Flutter client.

## 2. CI4 project and local database foundation

### What was done

- Worked in the new CI4 App Starter project at `C:\xampp\htdocs\kinta`.
- Inspected the project setup, dependency declarations, environment file, database settings, routes, and migrations during the setup work.
- Configured the local default database connection in `.env` for MySQLi, database `kinta_db`, host `localhost`, and user `root` with a blank password as the local XAMPP default.
- Confirmed the project could run its local API work before adding application features.
- Used CI4 migrations to define tables rather than asking phpMyAdmin to create the schema manually. phpMyAdmin is used to inspect database contents.

The current dependency declaration specifies PHP `^8.2` and CodeIgniter framework `^4.7` in `composer.json`. The exact installed framework patch version and current live database connectivity were not re-probed specifically for this report.

### Why migrations were used

A migration records the schema change as source code. `php spark migrate` can apply those changes in a repeatable order on another development machine or deployment. This is safer to review and reproduce than undocumented manual table creation. Existing migration files should be treated as schema history: deleting a migration file does not itself undo tables already created in MySQL, and can make another environment unable to reproduce the same schema.

### Migration-created schema

The KINTA schema is split into master/reference tables and process history tables:

| Group | Tables | Purpose |
|---|---|---|
| Master/reference | `users`, `machines`, `sectors`, `activities`, `sales_orders`, `problems` | Personnel, available production options, orders, and problem codes used by process work. |
| Process/transaction | `processes`, `process_operators`, `qc_inspections`, `process_problems`, `process_pic_history` | Process records and the people, inspections, problems, and supervisor changes associated with them. |
| Sync support | `process_sync_receipts` | Records client action keys already accepted by the server so retrying a queued request does not repeat the same operation. |
| Early API test | `ci4_test_records` | Isolated test table used during the initial CI4/Flutter connectivity and CRUD checks. |

The master and process migrations use unsigned integer IDs, indexes for lookup/foreign-key columns, InnoDB, and foreign keys to preserve relationships. Activities belong to sectors. A process references its machine, sector, activity, optional sales order, and current supervisor. Operator assignments, QC inspections, problem links, and PIC history reference a process; user/problem references point back to their master records.

`process_operators` intentionally has one row per `(process_id, user_id)` assignment. If two operators take part in one process, the expected representation is two assignment rows with the same `process_id` and separate `user_id` values. That is a many-to-many relationship, not a single comma-separated operator field and not two process records. The table name remains `process_operators` as requested.

### Development seed data

CI4 seeders were added for isolated API test records and the master rows needed to exercise process setup choices. These are development fixtures so dropdowns can be populated while building the flow; they are not production business data. IDs may have gaps or differ from the visual order after rows have been inserted and deleted because MySQL auto-increment values are not normally reset by deleting rows.

## 3. API work

### Initial connectivity and CRUD checks

The first API checkpoint introduced a small GET endpoint at `/api/flutter-test` to prove that the Flutter test client could reach CI4 and receive JSON. A database test endpoint then exercised the CI4-to-MySQL connection. An isolated CRUD test endpoint was used to exercise create/read/update/delete requests without mixing connectivity testing into the KINTA process schema.

These endpoints were stepping stones: they verified the HTTP/JSON and database path before the real process flow depended on it.

### Master-data API

`MasterDataController` exposes lists for users, machines, sectors, activities, sales orders, and problems. The user endpoint can filter by role, and the activities endpoint can filter by sector. The Flutter process setup can therefore request active reference records rather than embedding fixed dropdown entries in UI code.

Representative routes in `app/Config/Routes.php`:

```php
$routes->get('api/master-data/machines', 'Api\\MasterDataController::machines');
$routes->get('api/master-data/sectors', 'Api\\MasterDataController::sectors');
$routes->get('api/master-data/activities', 'Api\\MasterDataController::activities');
$routes->get('api/master-data/users', 'Api\\MasterDataController::users');
```

The controller reads rows from MySQL, applies active/role/filter rules where relevant, and returns JSON. This gives the Flutter client current choices when connected. Flutter caches successful responses for later offline access.

### Process API

The process API supports the process lifecycle and related records: starting and ending processes, viewing active/specific processes, recording QC inspections, attaching problems, changing the person in charge (PIC), and reviewing/updating operator assignments. These operations are routed through CI4 so the backend can validate input and persist the related rows to MySQL.

Process start stores one selected machine, sector, activity, supervisor, and optional order on a process record. Multiple operators are saved as separate association rows under the same process. PIC changes create history rows rather than erasing who was previously responsible. QC inspection results, process problems, start/end values, and remarks are persisted in their related process tables.

### Offline sync API and receipts

The sync endpoint accepts locally saved process work. It handles the process start and the queued associated actions (such as operator assignments, QC inspections, PIC changes, process end/results, and problems) and writes them as a database transaction. A transaction groups related writes so a partial sync is not reported as a successful complete process save.

`process_sync_receipts` stores a unique client action key with its server process and action type. On a retry, CI4 can recognize a key already applied and avoid creating duplicate rows. This matters when the client loses the response after the server committed: the client can retry safely instead of assuming it knows whether the write succeeded. A receipt is sync bookkeeping, not an additional business process.

## 4. Flutter integration and offline groundwork

### Normal application preserved

The normal FlutterFlow-exported `lib/main.dart` remains the application entry point. During early connectivity work, separate CI4 test entry points/pages were used so the generated main flow did not need to be replaced just to test HTTP requests. The actual application integration was then added alongside the generated screens and app state.

### API client

`lib/kinta_api_service.dart` centralizes HTTP/JSON calls to the CI4 API. Instead of each screen independently constructing a URL and interpreting JSON, screens can use shared GET/POST/PUT helpers. This reduces duplicated request logic and gives the app a common place to interpret API success and error responses.

The local base URL is development-specific. `localhost` works only when the browser and backend resolve to the same machine and the CI4 server is served at that path. A different device, emulator, or production deployment needs its reachable backend host and HTTPS configuration.

### Local database and cache

`lib/offline/kinta_local_db.dart` opens a browser database through Sembast Web, which uses IndexedDB in the browser. `lib/offline/kinta_local_repository.dart` keeps local records in distinct stores, including master-data cache, local process records/drafts, and the current offline credential data.

The order matters for a first offline use: the browser must first connect and download reference data and any required bootstrap information. If a master list has never been successfully fetched or otherwise seeded into local storage, turning Apache off leaves the client with no local copy to display. After a successful fetch and cache write, the app can reuse that copy while disconnected. Offline availability therefore depends on completing the initial online bootstrap on each device/browser profile.

### Process drafts and sync queue

Process data can be saved locally before it is sent to CI4. A local draft preserves the work across reloads; the sync service reads unsynchronized work and posts it to the API when connectivity is available or when the user starts sync. On a successful server response the local record can be marked synchronized/associated with the server ID. A failed sync remains available for retry or error handling.

The user reported that they completed a process and synchronized it using the in-app Sync button, and then found the records in phpMyAdmin. They also reported that the process could be completed while online and offline and later synchronized. These are user-observed manual test results, not a claim that an automated end-to-end test suite was run.

### Process values and UI corrections discussed

- Quantity passed and quantity rejected entered at process end were carried forward into the later process setup submission fields so the user did not have to re-enter them.
- Process result values remained fixed in the confirmation/result view after confirmation, matching the intended history display.
- Master-data loading errors were traced to the Flutter client being unable to fetch its CI4 URL (for example, the browser error `Failed to fetch`). This is a reachability/server/CORS/URL problem, not evidence that the dropdown requires a database schema change. The user later reported successful operation after resolving the test setup issue.
- Multiple selected operators are stored as separate `process_operators` records for one process. This was an explicit user requirement. Individual rows in phpMyAdmin are expected; the Flutter process summary is the appropriate place to display all operator names together for a reader.
- The user identified several normal-app gaps still needing product work: sales order and team information, QC selection/content, PIC change content, cancel behavior for process start/end/submit, and adding process problems. Some backend endpoints exist, but endpoint existence alone does not establish that every corresponding normal FlutterFlow screen is fully integrated and accepted.

## 5. Authentication work and the newer proposal

### Current prototype

CI4 exposes registration and login endpoints in `AuthController`. Registration validates the submitted fields, hashes the password with PHP `password_hash()` and `PASSWORD_DEFAULT`, and assigns the default `operator` role. Login looks up an active account and checks the submitted password with `password_verify()`. The API returns a user profile without returning the stored password hash.

Flutter uses the existing login interface and calls the CI4 authentication endpoints online. The current offline prototype derives a salted PBKDF2-HMAC-SHA256 verifier in Flutter and stores the salt, verifier, and cached profile locally; it does not store the plaintext password. When the network request fails in the supported network-error path, the local verifier can be checked to allow returning-device sign-in.

This makes offline re-entry possible, but it also means the browser has a second password-checking mechanism. A copied browser profile can be subjected to offline password guessing, and the local verifier does not learn that CI4 later disabled the account or changed its permissions. The current implementation is not the final offline authentication design.

### New signed-grant recommendation

The new prompt and the comparison report recommend that first registration/login requires internet access; CI4 verifies the credentials and account state; and only then does CI4 issue a short-lived offline access grant. Flutter also downloads the required offline data and marks itself ready only after that bootstrap succeeds. Offline access requires both a valid grant and the cached working set. Logout removes the local grant. On reconnect, CI4 must check the current user, status, permissions, and each queued action before applying it.

For a Flutter Web/PWA, the comparison recommends an asymmetric signature: the private signing key stays on CI4 and the client has only the public key for verification. The grant duration and behavior for queued work after expiry still require a business decision. **The signed-grant flow, server session/token, role-based authorization enforcement, and API protection have not been implemented yet.** The comparison is in `docs/authentication-design-comparison.md`.

## 6. What has been verified and what that means

The following outcomes were reported by the user during manual testing:

- the isolated Flutter test app could be launched and reach the backend;
- seeded reference choices became selectable in process setup;
- process start, PIC change, process end, and process submission worked in the tested flow;
- process and related rows appeared in MySQL/phpMyAdmin;
- cached reference selections survived a reload and could be used while Apache was stopped, once data had first been cached;
- process work completed offline was later synchronized through the app, and server rows appeared in phpMyAdmin;
- a previously reported QC quantity/error screen was retested and the user said it was working.

These statements describe the scenarios the user said they tested. They do not certify all data combinations, multiple-device behavior, concurrency conflicts, account revocation, or production deployment security. The source was inspected to prepare this report; no fresh application test or database cleanup was performed for this report.

## 7. Important decisions and constraints to carry forward

1. Keep Flutter and CI4 separate. Do not move Flutter sources into CI4 or convert the app to server-rendered views.
2. Preserve FlutterFlow-generated code outside the requested feature scope; use isolated test entry points for integration checks where practical.
3. Keep schema changes in migrations and test/reference rows in seeders. Avoid manually creating the application schema in phpMyAdmin.
4. A process has one machine, sector, and activity in the current schema. Multiple operators are represented by multiple `process_operators` rows tied to the same process.
5. Keep test/business transaction data separate from master/reference data. The prior cleanup request was scoped to transaction/test rows only; the database contents were not checked as part of this report.
6. No offline/service-worker feature should be considered complete merely because application data is stored in IndexedDB. A PWA service worker caches and serves web assets; IndexedDB is where structured offline application data and pending actions are stored. These are different responsibilities.
7. Do not treat a local flag or client-side role claim as server authorization. CI4 must protect every write and sync route before production use.

## 8. Remaining work, in a sensible order

### Before production authentication

- Agree the offline grant lifetime (for example, a configured shift/workday) and the policy for pending work when it expires.
- Replace or retire the PBKDF2 offline verifier according to the approved design; implement grant issuance, verification, expiry, logout, and refresh behavior.
- Add CI4 session/token handling and server-side authentication/authorization to process, master-data, and sync APIs. Revalidate account status and permissions when queued work reaches the server.
- Decide online credential/session transport and deployment HTTPS/origin settings; do not ship the local development URL as a production endpoint.

### Before relying on offline workflows

- Define exactly which reference data is required for each role and make the first-login bootstrap visibly complete or fail with a recoverable explanation.
- Define retry, duplicate, conflict, partial failure, logout, storage clearing, and device/browser replacement behavior.
- Test offline work across reloads, browser closure, reconnects, repeated sync attempts, and rejected actions. Add automated tests where appropriate.
- Integrate and review the remaining identified normal-app screens/actions: sales order/team, QC, PIC content, cancellation behavior, and problem selection/submission.

### Later PWA/product work

- Implement service-worker asset caching and installability only after the online app flow and offline data rules are stable.
- Perform offline-to-online end-to-end testing, including stale permissions, grant expiry, data conflicts, and network interruption during sync.
- Review database backup/restore, deployment configuration, logging, and production error handling.

## 9. Files worth knowing

| Area | Main files |
|---|---|
| CI4 route map | `app/Config/Routes.php` |
| API controllers | `app/Controllers/Api/AuthController.php`, `MasterDataController.php`, `ProcessController.php`, `ProcessSyncController.php` |
| Schema history | `app/Database/Migrations/2026-10-27-070000_CreateKintaMasterTables.php`, `2026-10-27-070100_CreateKintaProcessTables.php`, `2026-10-28-080000_CreateProcessSyncReceipts.php` |
| Development fixtures | `app/Database/Seeds/ProcessStartTestSeeder.php`, `Ci4TestRecordsSeeder.php` |
| Flutter API client | `lib/kinta_api_service.dart` |
| Flutter local persistence | `lib/offline/kinta_local_db.dart`, `lib/offline/kinta_local_repository.dart` |
| Flutter process draft/sync | `lib/kinta_process_draft.dart`, `lib/kinta_sync_service.dart` |
| Flutter authentication prototype | `lib/auth/kinta_auth_service.dart` |
| Authentication design comparison | `docs/authentication-design-comparison.md` |

## Conclusion

The project has moved from an empty-backend connectivity check to a working development path for master data, process capture, local drafts, and sync to MySQL. The user has manually confirmed the core tested flow, including an offline completion followed by sync. The next substantial milestone is to settle and implement the safer authentication model and enforce authorization on the backend before treating offline sync or the application as production-ready.
