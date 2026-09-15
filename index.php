<?php
/**
 * CodeVault storefront.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'config.php';
$products=$db->query('SELECT * FROM products WHERE active=1 ORDER BY featured DESC,id DESC')->fetchAll();$cartIds=cart_ids();
$pageTitle=t('hero.title2');require 'partials/header.php';
?>
<section class="hero"><div class="hero-copy"><div class="eyebrow"><i></i><?= e(t('hero.eyebrow')) ?></div><h1><?= e(t('hero.title1')) ?><br><em><?= e(t('hero.title2')) ?></em></h1><p><?= e(t('hero.text')) ?></p><div class="actions"><a class="button" href="#products"><?= e(t('hero.explore')) ?> <span>↗</span></a><a class="text-link" href="#benefits"><?= e(t('hero.how')) ?> →</a></div><div class="trust"><div><b><?= count($products) ?>+</b><span><?= e(t('hero.products')) ?></span></div><div><b>1.2k</b><span><?= e(t('hero.downloads')) ?></span></div><div><b>4.9/5</b><span><?= e(t('hero.ratings')) ?></span></div></div></div>
<div class="code-window"><div class="window-top"><span></span><span></span><span></span><small>secure-checkout.php</small></div><pre><code><i>&lt;?php</i>

<b>require</b> <u>'CodeVault.php'</u>;

$project = <b>new</b> Project([
  <u>'quality'</u> =&gt; <em>'premium'</em>,
  <u>'security'</u> =&gt; <em>'hardened'</em>,
  <u>'support'</u> =&gt; <em>true</em>
]);

$project-&gt;<strong>ship</strong>();

<i>// Copyright © Alen Pepa</i></code></pre><div class="terminal"><span>✓</span> <?= e(t('hero.ready')) ?> <small><?= e(t('hero.errors')) ?></small></div></div></section>
<section class="logos"><span><?= e(t('built')) ?></span><b>PHP 8</b><b>SQLite</b><b>AES-256-GCM</b><b>PayPal</b><b>BTCPay</b></section>
<section id="products" class="section"><div class="section-head"><div><span class="kicker"><?= e(t('catalog.label')) ?></span><h2><?= e(t('catalog.title')) ?></h2></div><p><?= e(t('catalog.text')) ?></p></div><div class="catalog-toolbar"><label class="catalog-search"><span>⌕</span><input type="search" data-catalog-search placeholder="<?= e(t('catalog.search')) ?>" maxlength="80"></label><label class="catalog-sort"><span><?= e(t('catalog.sort')) ?></span><select data-catalog-sort><option value="newest"><?= e(t('catalog.newest')) ?></option><option value="low"><?= e(t('catalog.price_low')) ?></option><option value="high"><?= e(t('catalog.price_high')) ?></option></select></label></div><div class="filters"><button class="active" type="button" data-filter="all"><?= e(t('catalog.all')) ?></button><?php foreach(array_unique(array_column($products,'category')) as $category):?><button type="button" data-filter="<?= e($category) ?>"><?= e($category) ?></button><?php endforeach;?></div>
<div class="catalog-status" data-catalog-status data-label="<?= e(t('catalog.results')) ?>"><?= count($products) ?> <?= e(t('catalog.results')) ?></div><div class="grid" data-catalog-grid><?php foreach($products as $product):$title=product_copy($product,'title');$description=product_copy($product,'description');$inCart=in_array((int)$product['id'],$cartIds,true);?><article class="card" data-category="<?= e($product['category']) ?>" data-search="<?= e(strtolower($title.' '.$description.' '.$product['tech'].' '.$product['category'])) ?>" data-price="<?= e((string)$product['price']) ?>" data-created="<?= e($product['created_at']) ?>"><div class="card-art"><span><?= $product['featured']?e(t('catalog.featured')):e(t('catalog.ready')) ?></span><div>&lt;<?= e(strtoupper(substr($title,0,2))) ?> /&gt;</div></div><div class="card-body"><div class="meta"><span><?= e($product['category']) ?></span><span>★ 4.9</span></div><h3><?= e($title) ?></h3><p><?= e($description) ?></p><small><?= e($product['tech']) ?></small><div class="card-foot"><b><?= money((float)$product['price']) ?></b><a href="product.php?id=<?= (int)$product['id'] ?>"><?= e(t('catalog.details')) ?> →</a></div><form method="post" action="cart.php" class="card-cart"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><button class="button tiny" type="submit" <?= $inCart?'disabled':'' ?>><?= e($inCart?t('catalog.in_cart'):t('catalog.add')) ?> <?= $inCart?'✓':'+' ?></button></form></div></article><?php endforeach;?><div class="catalog-empty" data-catalog-empty hidden><div>⌕</div><h3><?= e(t('catalog.no_results')) ?></h3></div></div></section>
<section id="benefits" class="benefits"><div><span class="kicker"><?= e(t('why.label')) ?></span><h2><?= e(t('why.title')) ?></h2></div><div class="benefit-grid"><article><b>01</b><h3><?= e(t('why.clean')) ?></h3><p><?= e(t('why.clean_text')) ?></p></article><article><b>02</b><h3><?= e(t('why.license')) ?></h3><p><?= e(t('why.license_text')) ?></p></article><article><b>03</b><h3><?= e(t('why.support')) ?></h3><p><?= e(t('why.support_text')) ?></p></article></div></section>
<section class="cta"><span><?= e(t('cta.label')) ?></span><h2><?= e(t('cta.one')) ?><br><em><?= e(t('cta.two')) ?></em></h2><a class="button light" href="#products"><?= e(t('cta.button')) ?> ↗</a></section>
<?php require 'partials/footer.php'; ?>
