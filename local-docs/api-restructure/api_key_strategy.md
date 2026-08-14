# Concise API Token Strategy

To prevent leaked keys from becoming permanent backdoors without ruining developer experience, adopt a **Three-Tier Token Model**.

## 1. The Three-Tier Model

Add a `token_type` column to your tokens table (`login`, `developer`, `integration`) and enforce different rules for each:

| Type                | Audience                                   | Max Expiry              | Renewal Method            |
| :------------------ | :----------------------------------------- | :---------------------- | :------------------------ |
| **Login Token**     | Mobile apps, CLI tools                     | 30 days                 | Re-login                  |
| **Developer PAT**   | Individual developers (scripts, local dev) | 1 Year (90-day default) | Manual (create new token) |
| **Integration Key** | Enterprise (server-to-server, CI/CD)       | 1 Year                  | **Auto-rolling** (on use) |

### Auto-Rolling Integration Keys (The Enterprise Solution)

Don't make integration keys live forever. Give them a 1-year expiry. However, **every time the token is used** (if it's within 30 days of expiring), automatically extend the expiry by another year.

- **Benefit:** Active integrations never break. Abandoned integrations naturally die.

## 2. Expiration Notifications (Critical)

Mandatory expiration only works if you warn users before their tokens die.

- **Email schedule:** Send alerts 30, 14, 7, and 1 day before expiry.
- **Grace period:** Don't hard-cut tokens at midnight. Add a 7-day grace period where the token still works, but API responses include an `X-DayWright-Token-Expiring: true` header.

## 3. Zero-Downtime Rotation (For Strict Enterprises)

When a security team demands manual token rotation, use the **Overlap Pattern**:

1. User generates a new token.
2. The old token is marked as "retiring" and will auto-expire in 48 hours.
3. The user has 48 hours to swap the keys in their production systems without any downtime.

## 4. Compromised Token Strategy (Incident Response)

If a third-party integration is hacked or a token is leaked, you need a strategy to minimize damage and recover quickly:

- **Blast Radius Reduction (Scopes):** Because tokens use `tokenAbility:scope`, a leaked token can only access what it was explicitly granted. A `projects:read` token cannot delete users or change billing. (You already have this architecture in place!)
- **Immediate Revocation (Kill Switch):** The web dashboard must allow users to delete tokens instantly. Since API requests validate tokens against the database on every request, deleting it is an immediate kill switch.
- **Automated Secret Scanning:** Because you are using the token prefix `dw_live_` (configured in `sanctum.php`), you can partner with GitHub Secret Scanning. If a developer accidentally pushes a DayWright token to a public repo, GitHub will notify your webhook, and you can instantly and automatically revoke it.
- **Audit Logs:** Ensure you are logging the IP address and User-Agent of API requests so you can help users understand exactly what data a compromised token accessed.

## 5. What to Avoid

- ❌ **OAuth2 Refresh Tokens:** Too complex for simple API access. Stick to Personal Access Tokens (PATs).
- ❌ **Infinite Tokens:** Never allow a token to be created without an `expires_at` date.
- ❌ **Per-Scope Expirations:** Keep expirations tied to the token, not the individual scopes it possesses.

## Quickest Win (Do This Today)

Update `ApiTokenService::createForUser()` to strictly enforce a 1-year maximum expiration for all new tokens. This instantly stops the bleeding of permanent tokens being issued.

```php
$maxExpiry = now()->addYear();
$expiresAt = $expiresAt ? min($expiresAt, $maxExpiry) : now()->addDays(90);
```
