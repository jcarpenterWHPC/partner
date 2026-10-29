<?php
/**
 * @var string $supportMail
 */
$req = service('request');
$notice = $req->getGet('expired') ? 'Your session has expired. Please sign in again.'
    : ($req->getGet('signed_out') ? 'You have been signed out.'
    : (session()->getFlashdata('info') ?: null));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · WHPC Partner Portal</title>
    <link rel="icon" href="<?= asset('images/favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= asset('images/favicon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= asset('images/apple-touch-icon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="<?= asset('css/whpc-brand.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/partner.css') ?>" rel="stylesheet">
</head>
<body class="whpc-login-body">
    <div class="whpc-login">
        <aside class="whpc-login__brand">
            <img class="whpc-login__keyhole" src="<?= asset('images/brand/keyhole-white.svg') ?>" alt="" aria-hidden="true">
            <img class="whpc-login__brand-logo" src="<?= asset('images/brand/whpc-logo-white.svg') ?>" alt="Wisconsin Housing Preservation Corp.">
            <div class="whpc-login__brand-copy">
                <span class="whpc-login__eyebrow"><i class="bx bx-buildings"></i> Partner Portal</span>
                <h1 class="whpc-login__headline">Preserve, provide&nbsp;and protect affordable housing.</h1>
                <p class="whpc-login__mission">For the property management companies who look after WHPC's communities.</p>
            </div>
            <div class="whpc-login__brand-footer">&copy; <?= date('Y') ?> Wisconsin Housing Preservation Corp.</div>
        </aside>
        <main class="whpc-login__panel">
            <div class="whpc-login__card">
                <img class="whpc-login__card-logo" src="<?= asset('images/brand/whpc-logo.svg') ?>" alt="WHPC">
                <h2 class="whpc-login__title">Sign in</h2>
                <p class="whpc-login__subtitle">Enter your work email and we'll send you a one-time code.</p>
                <?php if ($notice): ?>
                    <div class="whpc-alert whpc-alert--info" role="status"><i class="bx bx-time-five"></i><span><?= esc($notice) ?></span></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
                <?php endif; ?>
                <form method="post" action="<?= base_url('login') ?>" class="pp-form" id="loginForm">
                    <?= csrf_field() ?>
                    <label class="pp-label" for="email">Work email</label>
                    <input class="pp-input" type="email" id="email" name="email" value="<?= esc(old('email', ''), 'attr') ?>" autocomplete="email" inputmode="email" required autofocus>
                    <button type="submit" class="pp-btn pp-btn--primary pp-btn--block">Email me a code</button>
                </form>
                <div class="whpc-login__help">
                    Trouble signing in? Contact <a href="mailto:<?= esc($supportMail, 'attr') ?>"><?= esc($supportMail) ?></a>.
                </div>
            </div>
        </main>
    </div>
</body>
</html>
