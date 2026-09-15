<?php
/**
 * CodeVault private customer receipt and license portal.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'config.php';
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow, noarchive');

$publicId=strtoupper(safe_text($_GET['order']??'',32));$stmt=$db->prepare('SELECT * FROM orders WHERE public_id=?');$stmt->execute([$publicId]);$order=$stmt->fetch();
$access=$order?current_order_access($publicId):'';
if(!$order||!order_access_valid($order,$access)){event_record('order_access',client_hash(),false,['order_hash'=>hash('sha256',$publicId)]);http_response_code(404);exit('Not found.');}
remember_order_access($publicId,$access);event_record('order_access',client_hash(),true,['order_id'=>(int)$order['id']]);
if($order['status']==='paid')fulfill_order((int)$order['id']);
$items=order_items((int)$order['id']);$licenses=$order['status']==='paid'?order_licenses((int)$order['id']):[];$downloads=[];
foreach($licenses as $license){$file=license_file($license);$token=$file&&$license['status']==='active'?create_download_grant((int)$license['id']):'';if($file&&$license['status']==='active')$downloads[(int)$license['id']]=['token'=>$token,'file'=>$file];}
$pageTitle=t('portal.title');require 'partials/header.php';
?>
<section class="receipt-shell"><header class="receipt-head"><div class="result-icon <?= $order['status']==='paid'?'ok':'wait' ?>"><?= $order['status']==='paid'?'✓':'⌁' ?></div><div><span class="kicker"><?= e(t('result.order')) ?> #<?= e($publicId) ?></span><h1><?= e($order['status']==='paid'?t('portal.ready'):t('portal.pending')) ?></h1><p><?= e(t('portal.private')) ?></p></div><em class="status large"><?= e(order_status_label((string)$order['status'])) ?></em></header>
<div class="receipt-grid"><section class="panel"><div class="panel-head"><h2><?= e(t('portal.products')) ?></h2><span><?= count($items) ?></span></div><?php foreach($items as $item): ?><div class="license-item"><div class="mini-art">&lt;/&gt;</div><div><h3><?= e($item['title_snapshot']) ?></h3><p>v<?= e($item['version']??'1.0.0') ?> · <?= money((float)$item['price_snapshot']) ?></p><?php $license=array_values(array_filter($licenses,fn(array $entry):bool=>(int)$entry['order_item_id']===(int)$item['id']))[0]??null;if($license): ?><code><?= e($license['public_key']) ?></code><?php endif; ?></div><?php if($license&&isset($downloads[(int)$license['id']])&&$downloads[(int)$license['id']]['token']!==''):$download=$downloads[(int)$license['id']]; ?><a class="button download-button" href="download.php?token=<?= e(rawurlencode($download['token'])) ?>"><?= e(t('portal.download')) ?> ↓<small><?= e(number_format((int)$download['file']['size_bytes']/1048576,1)) ?> MB</small></a><?php elseif($order['status']==='paid'): ?><span class="unavailable"><?= e($license&&(int)$license['download_count']>=(int)$license['download_limit']?t('portal.limit_reached'):t('portal.no_file')) ?></span><?php endif; ?></div><?php endforeach; ?></section>
<aside class="panel receipt-meta"><h2><?= e(t('portal.details')) ?></h2><dl><div><dt><?= e(t('form.email')) ?></dt><dd><?= e($order['customer_email']) ?></dd></div><div><dt><?= e(t('admin.payments')) ?></dt><dd><?= e(ucfirst((string)$order['payment_method'])) ?></dd></div><div><dt><?= e(t('order.total')) ?></dt><dd><?= money((float)$order['amount']) ?></dd></div><div><dt><?= e(t('admin.status')) ?></dt><dd><?= e(order_status_label((string)$order['status'])) ?></dd></div></dl><?php if($order['status']!=='paid'&&$order['payment_method']==='manual'):?><div class="manual-instructions"><b><?= e(t('payment.manual')) ?></b><p><?= nl2br(e(setting('manual_instructions','Use the order number as your payment reference.'))) ?></p><small><?= e(t('result.order')) ?>: <?= e($publicId) ?></small></div><?php endif;?><div class="secure-box">◈ <span><b><?= e(t('portal.protected')) ?></b><small><?= e(t('portal.expiry')) ?></small></span></div><?php if($order['status']!=='paid'): ?><a class="button ghost wide" href="<?= e($order['payment_method']==='btcpay'?'btcpay_return.php?order='.rawurlencode($publicId):'order.php?order='.rawurlencode($publicId)) ?>"><?= e(t('result.refresh')) ?></a><?php endif; ?></aside></div></section>
<?php require 'partials/footer.php'; ?>
