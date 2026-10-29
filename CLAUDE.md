# CLAUDE.md

Guidance for Claude Code (and contributors) working in this repository.

## What this is

The **WHPC Partner Portal** — https://partner.whpc.com — the site Wisconsin Housing
Preservation Corp.'s property management companies (PMCs) use. Built on **CodeIgniter 4**
(PHP 8.3). First phase: the entry point to the CapEx site, and the process by which PMCs
record personnel changes (site managers, maintenance staff, …), which will later update the
Assets records on the intranet ("home").

This project is **independent**: its own repository, its own servers, its own database
(`partner` on the shared Aurora cluster). It is not the intranet and not CapEx. One app =
one repo.

## Servers

| | host | EC2 | role | secret | database |
|---|---|---|---|---|---|
| prod | partner.whpc.com | `Partner` | `whpc_partner_Production` | `partner/app-secrets` | `partner` |
| test | partner-test.whpc.com | `Partner TEST` | `whpc_partner_test` | `partner_TEST/app-secrets` | `partner_TEST` |

Both sit behind the shared ALB (host-header rules) and CloudFront (`*.whpc.com` certificate,
WHPC WAF). TLS ends at CloudFront; the boxes serve plain HTTP on :80 and read
`X-Forwarded-Proto`. Provisioning: `deploy/ec2-user-data.template.sh`; runbook: `deploy/README.md`.

## Settings and secrets

- **`.env` is generated**, never hand-edited: `php spark secrets:refresh` reads the box's
  secret (chosen by its instance role) and rewrites `.env`. To change a setting, change the
  secret, then run it again.
- Secret keys use CodeIgniter's `.env` names (`app.baseURL`, `database.default.*`,
  `encryption.key`, `email.fromEmail`, `capex.url`, `partner.*`).
- Never commit `.env`; never print or log a secret value. Mail goes out through the SES
  API as the instance role (From `noreply@whpc.com`); there are no SMTP credentials.

## What is in it

- **Sign-in** (`Auth`): PMC users get an emailed one-time code + one-click link; WHPC
  people sign in with Microsoft (Entra, `App\Libraries\MicrosoftSignIn`) once it is
  configured. PMC accounts are made by WHPC admins (`/admin/users`, or
  `php spark partner:user`); a PMC user belongs to one PMC. The WHPC admins are the
  addresses in `Config\Partner::$admins` (secret `partner.admins`) and nobody else at
  WHPC can sign in; their accounts appear on first sign-in (`App\Libraries\Accounts`).
- **Staff roster** (`RosterController`, `App\Libraries\Roster`): each PMC keeps its own
  people — name, position (Config\Partner::$positions, named as Assets' contact roles),
  title, email, phones, the properties they cover, start and end date. Nothing is deleted:
  leaving is an end date, and every change is a row in `staff_changes` (field diffs), which
  is what will later update Assets' contacts. A user only ever reaches their own PMC's
  people; admins pick a PMC.
- **Properties** come from the intranet: `partner:sync-properties` reads `home.assets`
  through the read-only `home` database group (user `partner_home_ro`, SELECT only) and
  fills `pmcs` + `properties`. Nightly on prod (`deploy/whpc-partner.cron`) and from the
  admin page. A property that disappears is retired, not deleted.
- **Admin** (`AdminController`, filter `admin`): accounts, management companies + sync,
  recent changes.

## Sign-in

Passwordless. `App\Libraries\LoginCode` emails a numeric code and a one-click link, stores
only digests, enforces the TTL / attempt / resend limits in `Config\Partner`. Whether an
address is registered is never revealed. PMC accounts: `php spark partner:user …`.

Microsoft sign-in is OpenID Connect (authorization code + PKCE) against Entra; the
id_token is verified in `MicrosoftSignIn` (signature against Microsoft's keys, issuer,
audience, lifetime, nonce). It is on when `partner.microsoftTenant`, `…ClientId` and
`…ClientSecret` are in the secret, and then every `@whpc.com` address is sent to
Microsoft instead of being emailed a code. The app registration is multi-tenant
(`partner.microsoftAuthority = organizations`) so PMC tenants can be enrolled later;
today `Accounts::forMicrosoft` admits WHPC's tenant only.

## Git workflow

- Remote `origin` = `git@github.com:jcarpenterWHPC/partner.git` (private; per-box deploy keys).
- `main` is the release line: production is always a checkout of `origin/main`, so **`main`
  must always be deployable**. Work on short-lived, one-word branches off `main`; merge to
  `main` only when ready to go live; delete the branch after.
- The TEST box serves whatever branch is checked out there (`sudo whpc-deploy <branch>`).
- Migrations on production are a deliberate step with Joe's go-ahead, never automatic.
- Every commit carries `Co-Authored-By: Claude <noreply@anthropic.com>`.

## Before committing

```sh
./vendor/bin/phpunit          # unit + database (SQLite in memory)
```

PHP app code is 2-space Allman; views are 4-space. Keep comments to what the code cannot
say. No explanatory prose in the UI.

## Branding

Slate-blue `#4E738A`, navy `#243746`, orange `#FF8300`, Source Sans 3. Tokens and the
sign-in layout live in `public/assets/css/whpc-brand.css` (shared with the intranet);
the portal shell in `public/assets/css/partner.css`.
