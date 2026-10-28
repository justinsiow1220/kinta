# KINTA Authentication Design Comparison

**Purpose:** Compare the latest short-lived offline access grant prompt with the earlier server-authentication/trusted-device prompt and the salted PBKDF2 offline sign-in currently implemented in Flutter.

**Scope:** This is a design report only. No application code, database rows, API routes, or Flutter screens were changed to prepare this report.

## Recommendation

Use CI4 as the authority for online authentication, then issue a short-lived, server-signed offline access grant only after CI4 verifies the account and its offline permissions. Flutter verifies that grant locally and combines it with an `offline_ready` state that is set only after the required data bootstrap completes. On reconnect, CI4 rechecks the account, role, permissions, and each queued operation before writing to MySQL.

For a Flutter Web/PWA client, use an asymmetric signature: CI4 keeps the private signing key; Flutter contains only the public verification key. Do not embed a shared HMAC secret in Flutter Web. Use a maintained JWS/JWT implementation rather than writing signature parsing or verification code by hand. The grant payload is signed, not encrypted, and browser verification is an offline-use control rather than a defense against a user who can modify the client application.

The grant lifetime remains configurable and unconfirmed. The new prompt suggests a workday or shift as an example. The final duration and behavior after expiry should be agreed before implementation.

## 1. Three designs being compared

### A. Latest prompt: short-lived signed offline grant

```text
Flutter login
    -> CI4 verifies credentials, account status, role, and offline permission
    -> CI4 issues a signed grant with identity, role/permissions, issued_at, expires_at
    -> Flutter stores the grant and bootstraps required data
    -> Offline access requires both a valid grant and offline_ready data
    -> Reconnect sends pending actions through CI4 for revalidation
```

This is the most specific design in the latest prompt. It replaces a simple local `authenticated = true` flag with a grant whose fields cannot be silently edited without invalidating its signature.

### B. Earlier prompt: CI4 authentication plus trusted local device state

```text
Flutter login
    -> CI4 verifies credentials and creates an authenticated session/token
    -> Flutter bootstraps required data
    -> Previously authenticated device may continue offline
    -> Reconnect refreshes server state and syncs through CI4
```

This correctly kept password authentication on CI4 and prohibited a custom local password verifier. It left the offline device state, its tamper resistance, and grant lifetime underspecified. The latest prompt improves it by explicitly requiring a signed, expiring grant and logout invalidation.

### C. Current Flutter method: local PBKDF2 verifier

```text
Online registration/login
    -> CI4 checks credentials
    -> Flutter derives a salted PBKDF2-HMAC-SHA256 verifier
    -> Sembast/IndexedDB stores the profile, salt, and verifier
    -> If CI4 cannot be reached later, Flutter checks the entered password locally
```

The current Flutter code uses 120,000 PBKDF2-HMAC-SHA256 iterations. It does not store the plaintext password or copy the CI4 password hash. It creates a second, device-local password-checking mechanism so that users can type a password and sign in while offline.

## 2. Pros and restrictions

| Design | Pros | Restrictions |
|---|---|---|
| **Signed offline grant** | CI4 remains the password and account authority. The grant can carry an expiry and role/permission claims. Asymmetric signing lets the Flutter app verify offline without receiving CI4’s private key. Logout can remove the local grant. | A disabled account cannot be discovered while the device is disconnected; the grant remains usable until expiry. A signed payload is readable, not encrypted. Client-side checks can be bypassed by someone who modifies the PWA, so CI4 must still protect online APIs and revalidate sync operations. Public-key rotation and expiry policy must be planned. |
| **Trusted local session/device marker** | Simple and avoids building a second password verifier. Fits the requirement that the user first authenticate online. | A plain editable flag is easy to change and has no integrity or expiry protection. If used, it should be replaced by or backed by a signed grant and readiness metadata. |
| **Salted PBKDF2 verifier** | Allows a returning user to enter a password and unlock the app without contacting CI4. A salt prevents reuse of precomputed tables; repeated derivation raises the cost of password guesses. The current implementation does not save the raw password. | Adds a custom credential check to Flutter and stores a verifier that can be attacked offline if the browser profile is copied. It can remain valid after the server account is disabled or the password changes. The current verifier has no expiry. The current logout clears only the in-memory user and leaves the verifier available, contrary to the latest prompt’s logout rule. It also does not prove to CI4 that a user was authenticated. |

OWASP currently recommends Argon2id for password storage where available, or 600,000 iterations for PBKDF2-HMAC-SHA256 when PBKDF2 is required. The KINTA local verifier uses 120,000 iterations; raising the count would still not resolve the larger design issue that the browser is maintaining a separate offline password authority. OWASP also cautions that IndexedDB is modifiable and accessible to code running in the same origin, so it should not be treated as a secure credential vault. [OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html) · [OWASP HTML5 Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/HTML5_Security_Cheat_Sheet.html)

## 3. Signing choices for the grant

| Choice | How it works | Fit for KINTA PWA |
|---|---|---|
| **HMAC/shared secret** | CI4 and Flutter both need the same secret to sign/verify. | **Do not use.** A secret embedded in Flutter Web can be extracted, allowing a modified client to mint grants. |
| **Asymmetric signature / JWS** | CI4 signs with a private key; Flutter verifies with a public key. | **Recommended.** The private key remains server-side, and offline verification does not need a shared secret. Use a vetted library, pin allowed algorithms, check issuer/audience/expiry, and support key IDs for rotation. PHP OpenSSL provides signing and verification primitives; JWS defines a standard signed representation. [PHP `openssl_sign`](https://www.php.net/manual/en/function.openssl-sign.php) · [PHP `openssl_verify`](https://www.php.net/manual/en/function.openssl-verify.php) · [IETF RFC 7515: JWS](https://www.rfc-editor.org/rfc/rfc7515.html) |
| **Opaque server session only** | CI4 stores session state and gives Flutter an opaque identifier; CI4 must be contacted to validate it. | Good for online session management, but by itself it cannot establish offline validity. Pair it with a separately signed, short-lived offline grant if offline use is required. |

A signed grant should include only the claims needed for offline decisions, such as `user_id`, permitted capabilities, `issued_at`, `expires_at`, issuer, audience, and a key ID. It should not contain secrets. A signature protects the claims from undetected editing; it does not encrypt them or make a locally modified Flutter app trustworthy.

## 4. Authentication and authorization flows

### Online registration/login

1. Flutter sends username and password to CI4 over HTTPS.
2. CI4 checks the account, password hash, active status, role, and offline-access permission.
3. CI4 creates the normal online session and issues a signed offline grant only if allowed.
4. Flutter verifies the grant and stores it with local user/role information.
5. Flutter downloads the required offline working set.
6. Flutter marks `offline_ready` only when both the grant and required cached data are valid.

### Offline access

1. Flutter verifies the grant signature using the pinned public key.
2. Flutter checks issuer, audience, role/capabilities, and expiry.
3. Flutter confirms `offline_ready` and the required cached data are present.
4. The UI enables only actions permitted by the grant.
5. Offline actions are queued locally; no direct MySQL writes occur.

### Logout

Logout clears the local grant and in-memory/session state. If online, CI4 should also invalidate the server session. Deleting the local grant cannot revoke a copied grant on a disconnected device; its expiry limits that exposure.

### Reconnect and sync

CI4 revalidates the current user, account status, authorization, payload, and business rules for each queued operation. It writes valid actions to MySQL and rejects invalid actions with an error for review. If the account was disabled while the device was offline, the device cannot know until reconnect; CI4 becomes authoritative at that point and the local grant should be invalidated or refreshed.

## 5. Important limits

- A signed grant is not a replacement for server authorization. Process, master-data, and sync APIs must enforce authenticated sessions and permissions whenever they receive requests.
- Offline role claims can become stale. Keep grants short-lived and revalidate on reconnect.
- A PWA cannot prevent its device owner from modifying the client or its local data. A signature detects edited grant fields in the intended client; it does not make IndexedDB confidential or protect against a modified client.
- Offline grant expiry and behavior when a grant expires while work is pending need a business decision. The latest prompt says deny new offline access after expiry and revalidate queued work on reconnect; the exact expiry duration remains unchosen.
- Logout while offline can remove the local grant, but cannot notify CI4 until connectivity returns.

## 6. Current KINTA implementation state

The current prototype has:

- CI4 registration and login endpoints using PHP `password_hash()` / `password_verify()`.
- Registration assigns the `operator` role; login checks that the account is active.
- Flutter stores a PBKDF2-derived verifier and user profile in Sembast/IndexedDB.
- Flutter falls back to that verifier when the login request fails with a network `ClientException`.
- The router gates screens using an in-memory Flutter authentication flag.

It does **not** yet have a server session/token, signed offline grant, grant expiry/invalidation, offline bootstrap/readiness metadata, role-based UI authorization, or authenticated API protection. The sync endpoint also needs server-side authenticated-user and permission checks before this design is complete.

## 7. Decision summary

The latest signed-grant design is stronger and better aligned with KINTA than the current PBKDF2 fallback. CI4 should remain authoritative for passwords, account status, and server-side authorization. A CI4-signed asymmetric grant is the recommended offline credential; a simple boolean and a client-side PBKDF2 verifier should not be treated as proof of server authentication.

Before implementation, confirm the offline grant duration and what should happen to pending work if the grant expires before reconnection. The prompt proposes a configurable workday/shift duration but does not make the final policy decision.

