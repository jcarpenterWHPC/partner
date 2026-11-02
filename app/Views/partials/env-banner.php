<?php
/**
 * The TEST banner: an orange frame and a "Test · host" tab on every page of a box that
 * is not production, keyed off the environment, never the hostname. Production renders
 * nothing. Same look as the intranet's test sites (home-test, 2026-11-02).
 *
 * @var string|null $env  override for tests; defaults to ENVIRONMENT
 */
$env = $env ?? (defined('ENVIRONMENT') ? ENVIRONMENT : 'production');
if ($env === 'production') {
    return;
}
$host = (string)parse_url(base_url(), PHP_URL_HOST);
?>
<style>
  .env-frame{position:fixed;inset:0;z-index:2147483000;pointer-events:none;box-shadow:inset 0 0 0 4px #FF8300}
  .env-tab{position:fixed;top:0;left:50%;transform:translateX(-50%);z-index:2147483001;pointer-events:none;background:#FF8300;color:#243746;font:700 12px/1 Inter,system-ui,sans-serif;letter-spacing:.08em;text-transform:uppercase;padding:5px 14px 6px;border-radius:0 0 8px 8px;box-shadow:0 2px 6px rgba(0,0,0,.25);white-space:nowrap}
  @media print{.env-frame,.env-tab{display:none}}
</style>
<div class="env-frame" aria-hidden="true"></div>
<div class="env-tab" role="status">Test &middot; <?= esc($host) ?></div>
