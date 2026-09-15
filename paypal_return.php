<?php
/**
 * CodeVault PayPal capture callback.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'payments.php';
$publicId=strtoupper(safe_text($_GET['order']??'',32));$token=safe_text($_GET['token']??'',120);$access=safe_text($_GET['access']??'',100);$stmt=$db->prepare('SELECT * FROM orders WHERE public_id=? AND payment_method="paypal"');$stmt->execute([$publicId]);$order=$stmt->fetch();$ok=false;$message=t('result.checking');
if($order&&$access&&order_access_valid($order,$access))remember_order_access($publicId,$access);
if($order&&$token&&hash_equals((string)$order['payment_reference'],$token))try{$result=$order['status']==='paid'?['status'=>'COMPLETED']:paypal_capture($token);$capture=$result['purchase_units'][0]['payments']['captures'][0]??[];$captureAmount=(string)($capture['amount']['value']??'');$currency=(string)($capture['amount']['currency_code']??'');$amountMatches=$captureAmount!==''&&$currency==='EUR'&&hash_equals(number_format((float)$order['amount'],2,'.',''),number_format((float)$captureAmount,2,'.',''));if($order['status']==='paid'||(($result['status']??'')==='COMPLETED'&&$amountMatches)){$db->prepare('UPDATE orders SET status="paid",updated_at=CURRENT_TIMESTAMP WHERE id=? AND status IN ("paying","paid")')->execute([$order['id']]);fulfill_order((int)$order['id']);$ok=true;$message=t('result.paid');}}catch(Throwable $e){error_log('PayPal capture error: '.$e->getMessage());}
$portalAccess=current_order_access($publicId);$pageTitle=t('result.paid');require 'partials/header.php';$portalUrl='order.php?order='.rawurlencode($publicId).($portalAccess!==''?'&access='.rawurlencode($portalAccess):'');?><section class="payment-result"><div class="result-icon <?= $ok?'ok':'wait' ?>"><?= $ok?'✓':'!' ?></div><span class="kicker"><?= e(t('result.order')) ?> #<?= e($publicId) ?></span><h1><?= e($ok?t('result.paid'):t('result.checking')) ?></h1><p><?= e($message) ?></p><div class="actions"><a class="button" href="<?= e($portalUrl) ?>"><?= e(t('portal.title')) ?> ↗</a><a class="text-link" href="index.php"><?= e(t('result.store')) ?></a></div></section><?php require 'partials/footer.php'; ?>
