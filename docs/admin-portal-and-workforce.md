# KINTA Admin Portal and Workforce

## Access and roles

- The administrator portal is served by CI4 at `/admin` and uses a server-side session.
- Admin accounts do not receive Flutter offline grants. The Flutter API accepts active `supervisor` accounts only.
- Operators and QC personnel are selectable workforce records without login credentials.
- The portal creates supervisor, operator, and QC records. It does not provide a way to create another administrator; administrators are provisioned manually.
- Every workforce record receives a server-generated `SID` plus six digits. The development reset provisions the first admin as `SID000001`; the next inserted workforce row receives its database sequence number. IDs are not reused after deactivation.

## Portal pages

- **Workforce:** create/edit/deactivate/reactivate supervisors, operators, and QC personnel. Only supervisors have a username and password.
- **Master Data:** maintain machines, sectors, activities, sales orders, and problems. Deactivation preserves history and removes the choice from active API responses.
- **Operation reports:** search and paginate read-only process records; filter by date, machine, and supervisor; open a record for operators, QC inspections, problems, and PIC history.

Forms use CSRF protection. Every protected portal request also checks that the signed-in workforce row remains an active admin.

## Development reset

The approved local cleanup has already been run against `kinta_db`. It emptied test processes, workforce rows, and the seeded test master choices, reset their sequences, then created the first admin. CI4 stores the password as a one-way PHP password hash. The temporary `KINTA_INITIAL_ADMIN_PASSWORD` environment value was removed after seeding.

The migration that renames `users` to `workforce` and renames personnel foreign-key columns is reversible. Apply future migrations with:

```powershell
php spark migrate
```

Do not rerun `ResetKintaDevelopmentSeeder` unless you intend to clear those local tables again. It requires `KINTA_INITIAL_ADMIN_PASSWORD` to be set temporarily in the local environment and clears test process/master data before provisioning the initial admin.

## Deferred item

Forgot-password links are not operational yet. The portal and PWA display a message to contact the administrator until Gmail/Google Workspace SMTP, single-use 30-minute reset tokens, and email delivery are implemented and configured.
