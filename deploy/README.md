# Deploying the Partner Portal

Built 2026-10-29 in the same shape as home.whpc.com and capex.whpc.com.

## What exists (us-east-1, account 122610509618)

| piece | prod | test |
|---|---|---|
| hostname | partner.whpc.com | partner-test.whpc.com |
| CloudFront | `ECXRW3HJHEY1K` → d1zhynbya8ln4r.cloudfront.net | `E18XFNI6D61D7X` → d3dknj9tg7bmi1.cloudfront.net |
| ALB rule (WHPC, :80 and :443) | priority 6 host-header → TG `partner-Website` | priority 7 → TG `partner-TEST` |
| target group health check | `GET /healthcheck.html` → 200 | same |
| EC2 | `Partner` t3a.medium, key pair `Home` | `Partner TEST` t3a.medium, key pair `WHPCtest` |
| IAM role / instance profile | `whpc_partner_Production` | `whpc_partner_test` |
| role policies | `whpc_secretsManager_partner`, `whpc_SES_noreply`, `AmazonSSMManagedInstanceCore` | `whpc_secretsManager_partner_test`, same two |
| secret | `partner/app-secrets` | `partner_TEST/app-secrets` |
| database (Aurora `whpc-main`) | `partner`, user `partner_app@<box private IP>` | `partner_TEST`, user `partner_test_app@<box private IP>` |
| DNS (GoDaddy, by hand) | CNAME partner → d1zhynbya8ln4r.cloudfront.net | CNAME partner-test → d3dknj9tg7bmi1.cloudfront.net |

Security group `WHPC` (sg-0275a9de0fbfcc743), subnet us-east-1a, IMDSv2 required, 30 GB
gp3 encrypted root. The AWS Backup plans select every instance, so both are backed up like
the others. Both boxes are reachable with Session Manager (`aws ssm start-session`).

## A box's first deploy

The user-data script installed Apache + PHP 8.3 + Composer + the AWS CLI, wrote the vhost,
generated a deploy key and left notes in `/root/WHPC-DEPLOY-NEXT-STEPS.txt`.

1. Add the box's deploy key (printed in that file) to the GitHub repo.
2. `sudo whpc-deploy` — clones into /var/www, installs dependencies, stops at "no .env".
3. `sudo -u ubuntu php /var/www/spark secrets:refresh` — builds `.env` from the box's secret.
   `.env` is `ubuntu:www-data` 0640; ubuntu is in `www-data` so a refresh keeps it readable by Apache.
4. `sudo whpc-deploy` — migrates, fixes permissions, restarts Apache.
5. `curl -sI http://localhost/` → 302 to /login; `curl -s http://localhost/healthcheck.html` → ok.
6. Admins: put them in the secret as `partner.admins` (`Joe Carpenter <jcarpenter@whpc.com>, …`),
   run `secrets:refresh`, and sign in; the account is made on first sign-in. PMC accounts:
   `/admin/users` or `php spark partner:user add jane@pmc.com "Jane Doe" --pmc "Company"`.

## After the first deploy

```sh
sudo -u ubuntu php /var/www/spark partner:sync-properties     # PMCs + properties from Assets
sudo install -m 644 /var/www/deploy/whpc-partner.cron /etc/cron.d/whpc-partner   # PROD only: nightly sync
```

## Later deploys

```sh
sudo whpc-deploy            # main (production)
sudo whpc-deploy <branch>   # TEST box: serve a branch
```

A setting change is a secret change followed by `php spark secrets:refresh` (no restart
needed for `.env`; `whpc-deploy` restarts Apache anyway because mod_php caches opcodes).

## Changing a DB user's host

The app users are locked to the boxes' private IPs. If a box is replaced, rename the user
on the cluster: `RENAME USER 'partner_app'@'<old ip>' TO 'partner_app'@'<new ip>';`
