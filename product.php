<?php
/**
 * CodeVault product detail.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'config.php';
$stmt=$db->prepare('SELECT * FROM products WHERE id=? AND active=1');$stmt->execute([(int)($_GET['id']??0)]);$product=$stmt->fetch();
if(!$product){http_response_code(404);exit('Not found.');}
$title=product_copy($product,'title');$pageTitle=$title;require 'partials/header.php';
$file=product_file((int)$product['id']);$inCart=in_array((int)$product['id'],cart_ids(),true);
?>
<section class="product"><div class="product-art">&lt;<?= e(strtoupper(substr($title,0,2))) ?> /&gt;</div><div class="product-info"><a class="back" href="index.php#products">← <?= e(t('product.back')) ?></a><span class="kicker"><?= e($product['category']) ?></span><h1><?= e($title) ?></h1><p><?= e(product_copy($product,'description')) ?></p><div class="tech"><?= e($product['tech']) ?></div><ul><li>✓ <?= e(t('product.source')) ?></li><li>✓ <?= e(t('product.docs')) ?></li><li>✓ <?= e(t('product.license')) ?></li><li>✓ <?= e(t('product.updates')) ?></li></ul><div class="product-facts"><span><?= e(t('product.version')) ?> <b>v<?= e($product['version']??'1.0.0') ?></b></span><span><?= e(t('product.file')) ?> <b><?= $file?e(number_format((int)$file['size_bytes']/1048576,1).' MB'):e(t('product.no_file')) ?></b></span></div><div class="buy"><b><?= money((float)$product['price']) ?></b><div class="buy-actions"><form method="post" action="cart.php"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><input type="hidden" name="next" value="checkout"><button class="button" type="submit" <?= $inCart?'disabled':'' ?>><?= e($inCart?t('catalog.in_cart'):t('product.cart')) ?> <?= $inCart?'✓':'+' ?></button></form><a class="text-link" href="checkout.php?id=<?= (int)$product['id'] ?>"><?= e(t('product.buy')) ?> ↗</a></div></div></div></section>
<?php require 'partials/footer.php'; ?>
