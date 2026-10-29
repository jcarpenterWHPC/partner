# WHPC Partner Portal

The portal for the property management companies that look after WHPC's communities —
https://partner.whpc.com (TEST: https://partner-test.whpc.com). CodeIgniter 4, PHP 8.3.

- Sign-in is passwordless: a one-time code and a one-click link are emailed together.
- Accounts are created with `php spark partner:user add <email> "<Name>" --company "<PMC>"`.
- Settings come from AWS Secrets Manager, never a hand-kept `.env`: `php spark secrets:refresh`.
- Deploy on a box: `sudo whpc-deploy` (see `deploy/README.md`).

See `CLAUDE.md` for the working rules.
