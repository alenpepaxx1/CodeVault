<?php
/**
 * CodeVault BTCPay return verification.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'payments.php';
$publicId=strtoupper(safe_text($_GET['order']??'',32));$access=safe_text($_GET['access']??'',100);$stmt=$db->prepare('SELECT * FROM orders WHERE public_id=? AND payment_method="btcpay"');$stmt->execute([$publicId]);$order=$stmt->fetch();$ok=false;$message=t('result.btc_wait');
if($order&&$access&&order_access_valid($order,$access))remember_order_access($publicId,$access);
if($order&&!empty($order['payment_reference']))try{$invoice=$order['status']==='paid'?['status'=>'Settled']:btcpay_invoice((string)$order['payment_reference']);$status=$invoice['status']??'';$invoiceAmount=(string)($invoice['amount']??'');$invoiceCurrency=(string)($invoice['currency']??'');$metadataOrder=(string)($invoice['metadata']['orderId']??'');$matches=$order['status']==='paid'||($invoiceAmount!==''&&$invoiceCurrency==='EUR'&&$metadataOrder===$publicId&&number_format((float)$invoiceAmount,2,'.','')===number_format((float)$order['amount'],2,'.',''));if(in_array($status,['Settled','Confirmed'],true)&&$matches){$db->prepare('UPDATE orders SET status="paid",updated_at=CURRENT_TIMESTAMP WHERE id=? AND status IN ("paying","paid")')->execute([$order['id']]);fulfill_order((int)$order['id']);$ok=true;$message=t('result.btc_paid');}}catch(Throwable $e){error_log('BTCPay verification error: '.$e->getMessage());}
$portalAccess=current_order_access($publicId);$pageTitle=t('result.btc_wait');require 'partials/header.php';$portalUrl='order.php?order='.rawurlencode($publicId).($portalAccess!==''?'&access='.rawurlencode($portalAccess):'');?><section class="payment-result"><div class="result-icon <?= $ok?'ok':'wait' ?>"><?= $ok?'✓':'₿' ?></div><span class="kicker"><?= e(t('result.order')) ?> #<?= e($publicId) ?></span><h1><?= e($ok?t('result.btc_paid'):t('result.btc_wait')) ?></h1><p><?= e($message) ?></p><div class="actions"><a class="button" href="<?= e($portalUrl) ?>"><?= e(t('portal.title')) ?> ↗</a><a class="text-link" href="index.php"><?= e(t('result.store')) ?></a></div></section><?php require 'partials/footer.php'; ?>
