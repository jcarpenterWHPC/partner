<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? config('App')->brandName) ?></title>
    <meta name="csrf-field" content="<?= csrf_token() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="base-url" content="<?= base_url() ?>">
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
<body class="pp-body">
<header class="pp-topbar">
    <a class="pp-topbar__brand" href="<?= base_url('/') ?>">
        <img src="<?= asset('images/brand/whpc-logo-white.svg') ?>" alt="Wisconsin Housing Preservation Corp.">
        <span>Partner Portal</span>
    </a>
    <?php if ($u = session('user')): ?>
    <nav class="pp-topbar__user" aria-label="Account">
        <span class="pp-topbar__name"><?= esc($u['name'] ?: $u['email']) ?></span>
        <?php if ($u['company'] !== ''): ?><span class="pp-topbar__company"><?= esc($u['company']) ?></span><?php endif; ?>
        <form method="post" action="<?= base_url('logout') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="pp-btn pp-btn--ghost">Sign out</button>
        </form>
    </nav>
    <?php endif; ?>
</header>
<main class="pp-main">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="whpc-alert whpc-alert--error" role="alert"><i class="bx bx-error-circle"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('info')): ?>
        <div class="whpc-alert whpc-alert--info" role="status"><i class="bx bx-info-circle"></i><span><?= esc(session()->getFlashdata('info')) ?></span></div>
    <?php endif; ?>
    <?= $this->renderSection('content') ?>
</main>
<footer class="pp-footer">&copy; <?= date('Y') ?> Wisconsin Housing Preservation Corp.</footer>
<?= $this->renderSection('scripts') ?>
</body>
</html>
