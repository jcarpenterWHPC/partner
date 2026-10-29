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

## Sign-in

Passwordless. `App\Libraries\LoginCode` emails a numeric code and a one-click link, stores
only digests, enforces the TTL / attempt / resend limits in `Config\Partner`. Whether an
address is registered is never revealed. Accounts: `php spark partner:user …`.

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
