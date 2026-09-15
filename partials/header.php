<?php
/**
 * CodeVault shared header.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
$pageTitle=$pageTitle??APP_NAME;
?>
<!doctype html><html lang="<?= e(current_lang()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> — <?= APP_NAME ?></title><meta name="description" content="<?= e(t('meta.description')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><link rel="stylesheet" href="assets/style.css"><noscript><link rel="stylesheet" href="assets/nojs.css"></noscript><script defer src="assets/app.js"></script></head>
<body class="<?= !empty($adminShell)?'admin-body ':'' ?>is-loading">
<?php $loadingText=['sq'=>'Po përgatitet eksperienca','en'=>'Preparing your experience','de'=>'Dein Erlebnis wird vorbereitet']; ?>
<div class="app-loader" id="app-loader" role="status" aria-live="polite" aria-label="<?= e($loadingText[current_lang()]) ?>"><div class="loader-grid" aria-hidden="true"></div><div class="loader-glow" aria-hidden="true"></div><div class="loader-shell"><div class="loader-orbit"><i></i><span>&lt;/&gt;</span></div><div class="loader-brand">CodeVault<small>SECURE CODE MARKETPLACE</small></div><p><?= e($loadingText[current_lang()]) ?></p><div class="loader-track" aria-hidden="true"><i></i></div><div class="loader-meta"><span><b></b> ENCRYPTED SESSION</span><strong data-loader-percent>0%</strong></div></div><div class="loader-copyright">© 2026 ALEN PEPA</div></div>
<?php if(empty($adminShell)): ?><header class="nav"><a class="brand" href="index.php"><span>&lt;/&gt;</span> CodeVault</a><button class="menu" type="button" aria-label="Menu" aria-expanded="false">☰</button><nav>
<a href="index.php#products"><?= e(t('nav.products')) ?></a><a href="index.php#benefits"><?= e(t('nav.why')) ?></a><a href="cart.php" class="cart-link">◎ <?= e(t('cart.label')) ?><b><?= cart_count() ?></b></a><a href="admin.php"><?= e(t('nav.admin')) ?></a>
<div class="language" aria-label="<?= e(t('nav.language')) ?>"><?php foreach(['sq'=>'SQ','en'=>'EN','de'=>'DE'] as $code=>$label):?><a class="<?= current_lang()===$code?'active':'' ?>" href="<?= e(language_url($code)) ?>"><?= $label ?></a><?php endforeach;?></div>
<a class="button small" href="index.php#products"><?= e(t('nav.browse')) ?></a></nav></header><?php endif; ?><main>
