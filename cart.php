<?php
/**
 * CodeVault server-side shopping cart.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'config.php';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();$action=is_string($_POST['action']??null)?$_POST['action']:'';$productId=(int)($_POST['product_id']??0);
    if($action==='add'){$products=products_by_ids([$productId]);if($products)cart_add($productId);}
    elseif($action==='remove')cart_remove($productId);
    elseif($action==='clear')cart_clear();
    redirect($action==='add'&&($_POST['next']??'')==='checkout'?'checkout.php':'cart.php');
}

$products=products_by_ids(cart_ids());$total=array_reduce($products,fn(float $sum,array $product):float=>$sum+(float)$product['price'],0.0);
$pageTitle=t('cart.title');require 'partials/header.php';
?>
<section class="cart-shell">
    <div class="section-head compact"><div><span class="kicker"><?= e(t('cart.label')) ?></span><h1><?= e(t('cart.title')) ?></h1></div><p><?= e(t('cart.text')) ?></p></div>
    <?php if(!$products): ?>
        <div class="empty-state"><div>&lt;/&gt;</div><h2><?= e(t('cart.empty')) ?></h2><p><?= e(t('cart.empty_text')) ?></p><a class="button" href="index.php#products"><?= e(t('cart.browse')) ?> ↗</a></div>
    <?php else: ?>
        <div class="cart-layout"><div class="cart-list">
            <?php foreach($products as $product):$title=product_copy($product,'title'); ?>
                <article class="cart-item"><div class="cart-art">&lt;<?= e(strtoupper(substr($title,0,2))) ?> /&gt;</div><div><span><?= e($product['category']) ?></span><h2><a href="product.php?id=<?= (int)$product['id'] ?>"><?= e($title) ?></a></h2><p><?= e($product['tech']) ?> · v<?= e($product['version']??'1.0.0') ?></p></div><strong><?= money((float)$product['price']) ?></strong><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><button class="icon-button" aria-label="<?= e(t('cart.remove')) ?>">×</button></form></article>
            <?php endforeach; ?>
            <form method="post" class="clear-cart" data-confirm="clear-cart"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="clear"><button><?= e(t('cart.clear')) ?></button></form>
        </div><aside class="cart-total"><span><?= e(t('cart.summary')) ?></span><div><b><?= e(t('cart.items')) ?></b><strong><?= count($products) ?></strong></div><div><b><?= e(t('order.license')) ?></b><strong><?= e(t('order.included')) ?></strong></div><div class="grand-total"><b><?= e(t('order.total')) ?></b><strong><?= money($total) ?></strong></div><a class="button wide" href="checkout.php"><?= e(t('cart.checkout')) ?> <span>→</span></a><small>⌁ <?= e(t('cart.secure')) ?></small></aside></div>
    <?php endif; ?>
</section>
<?php require 'partials/footer.php'; ?>
