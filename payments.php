<?php
/**
 * CodeVault payment provider integrations.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require_once __DIR__.'/config.php';

function api_request(string $url,string $method,array $headers=[],?array $body=null,?array $basic=null): array {
    if(!function_exists('curl_init'))throw new RuntimeException('cURL unavailable.');
    $scheme=parse_url($url,PHP_URL_SCHEME);if(!in_array($scheme,['https','http'],true))throw new RuntimeException('Invalid provider URL.');
    $ch=curl_init($url);$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];
    if($basic)$options[CURLOPT_USERPWD]=$basic[0].':'.$basic[1];if($body!==null)$options[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);curl_setopt_array($ch,$options);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
    if($raw===false){error_log('Payment network error: '.$error);throw new RuntimeException('Provider connection failed.');}
    if(strlen((string)$raw)>2000000)throw new RuntimeException('Provider response too large.');
    $data=json_decode((string)$raw,true);if($status<200||$status>=300){error_log('Payment provider HTTP '.$status);throw new RuntimeException('Provider rejected the request.');}
    return is_array($data)?$data:[];
}
function provider_redirect(string $url,string $provider): never {
    if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Unsafe payment redirect.');
    $host=strtolower((string)parse_url($url,PHP_URL_HOST));
    if($provider==='paypal'&&!($host==='paypal.com'||str_ends_with($host,'.paypal.com')))throw new RuntimeException('Unexpected PayPal host.');
    if($provider==='btcpay'&&!hash_equals(strtolower((string)parse_url(btcpay_host(),PHP_URL_HOST)),$host))throw new RuntimeException('Unexpected BTCPay host.');
    header('Location: '.$url,true,303);exit;
}
function paypal_base(): string { return setting('paypal_mode','sandbox')==='live'?'https://api-m.paypal.com':'https://api-m.sandbox.paypal.com'; }
function paypal_token(): string {
    $id=setting('paypal_client_id');$secret=setting('paypal_secret','',true);if(!$id||!$secret)throw new RuntimeException('PayPal is incomplete.');
    if(!function_exists('curl_init'))throw new RuntimeException('cURL unavailable.');$ch=curl_init(paypal_base().'/v1/oauth2/token');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'grant_type=client_credentials',CURLOPT_USERPWD=>$id.':'.$secret,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$data=json_decode((string)$raw,true);if($status!==200||empty($data['access_token']))throw new RuntimeException('PayPal credentials rejected.');return (string)$data['access_token'];
}
function paypal_create(array $order,array $product): array {
    $access=rawurlencode((string)($order['access_token']??''));$return=site_url().'/paypal_return.php?order='.rawurlencode($order['public_id']).'&access='.$access;$cancel=site_url().'/checkout.php?cancelled=1';
    $title=(string)($product['_payment_title']??product_copy($product,'title'));$payload=['intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>$order['public_id'],'custom_id'=>$order['public_id'],'description'=>safe_text($title,120),'amount'=>['currency_code'=>'EUR','value'=>number_format((float)$order['amount'],2,'.','')]]],'payment_source'=>['paypal'=>['experience_context'=>['return_url'=>$return,'cancel_url'=>$cancel,'user_action'=>'PAY_NOW','shipping_preference'=>'NO_SHIPPING']]]];
    $data=api_request(paypal_base().'/v2/checkout/orders','POST',['Authorization: Bearer '.paypal_token(),'Content-Type: application/json','PayPal-Request-Id: cv-'.$order['public_id']],$payload);$approval='';foreach($data['links']??[] as $link)if(in_array($link['rel']??'',['approve','payer-action'],true))$approval=$link['href']??'';if(empty($data['id'])||!$approval)throw new RuntimeException('PayPal approval unavailable.');return ['id'=>(string)$data['id'],'url'=>(string)$approval];
}
function paypal_capture(string $paypalId): array { return api_request(paypal_base().'/v2/checkout/orders/'.rawurlencode($paypalId).'/capture','POST',['Authorization: Bearer '.paypal_token(),'Content-Type: application/json','PayPal-Request-Id: cap-'.bin2hex(random_bytes(8))],[]); }
function btcpay_host(): string {
    $url=rtrim(setting('btcpay_url'),'/');$valid=filter_var($url,FILTER_VALIDATE_URL);$scheme=parse_url($url,PHP_URL_SCHEME);$host=parse_url($url,PHP_URL_HOST);$local=in_array($host,['localhost','127.0.0.1','::1'],true);if(!$valid||($scheme!=='https'&&!$local))throw new RuntimeException('Invalid BTCPay URL.');return $url;
}
function btcpay_headers(): array { $key=setting('btcpay_api_key','',true);if(!$key)throw new RuntimeException('BTCPay key missing.');return ['Authorization: token '.$key,'Content-Type: application/json']; }
function btcpay_create(array $order,array $product): array {
    $store=setting('btcpay_store_id');if(!$store)throw new RuntimeException('BTCPay store missing.');$title=(string)($product['_payment_title']??product_copy($product,'title'));$payload=['amount'=>number_format((float)$order['amount'],2,'.',''),'currency'=>'EUR','metadata'=>['orderId'=>$order['public_id'],'itemDesc'=>$title],'checkout'=>['redirectURL'=>site_url().'/btcpay_return.php?order='.rawurlencode($order['public_id']).'&access='.rawurlencode((string)($order['access_token']??''))]];$data=api_request(btcpay_host().'/api/v1/stores/'.rawurlencode($store).'/invoices','POST',btcpay_headers(),$payload);if(empty($data['id'])||empty($data['checkoutLink']))throw new RuntimeException('BTCPay invoice unavailable.');return ['id'=>(string)$data['id'],'url'=>(string)$data['checkoutLink']];
}
function btcpay_invoice(string $invoiceId): array { return api_request(btcpay_host().'/api/v1/invoices/'.rawurlencode($invoiceId),'GET',btcpay_headers()); }
