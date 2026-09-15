<?php
/**
 * CodeVault signed BTCPay webhook receiver.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'config.php';
header('Content-Type: text/plain; charset=utf-8');
$raw=(string)file_get_contents('php://input');$secret=setting('btcpay_webhook_secret','',true);$signature=$_SERVER['HTTP_BTCPAY_SIG']??'';
if(!$secret||strlen($raw)>1000000||!hash_equals('sha256='.hash_hmac('sha256',$raw,$secret),(string)$signature)){http_response_code(401);exit('Invalid signature');}
$event=json_decode($raw,true);$invoiceId=is_array($event)?safe_text($event['invoiceId']??'',180):'';$type=is_array($event)?safe_text($event['type']??'',80):'';
if(!$invoiceId){http_response_code(400);exit('Invalid payload');}
if(in_array($type,['InvoiceSettled','InvoicePaymentSettled'],true)){$stmt=$db->prepare('SELECT id FROM orders WHERE payment_method="btcpay" AND payment_reference=? AND status IN ("paying","paid")');$stmt->execute([$invoiceId]);$orderIds=$stmt->fetchAll(PDO::FETCH_COLUMN);$db->prepare('UPDATE orders SET status="paid",updated_at=CURRENT_TIMESTAMP WHERE payment_method="btcpay" AND payment_reference=? AND status IN ("paying","paid")')->execute([$invoiceId]);foreach($orderIds as $orderId)fulfill_order((int)$orderId);}
elseif(in_array($type,['InvoiceExpired','InvoiceInvalid'],true)){$db->prepare('UPDATE orders SET status="cancelled",updated_at=CURRENT_TIMESTAMP WHERE payment_method="btcpay" AND payment_reference=? AND status!="paid"')->execute([$invoiceId]);}
event_record('btcpay_webhook',$invoiceId,true,['type'=>$type]);http_response_code(200);echo 'OK';
