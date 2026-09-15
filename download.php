<?php
/**
 * CodeVault authorized private package streaming endpoint.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'config.php';
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow, noarchive');

$token=is_string($_GET['token']??null)?$_GET['token']:'';$licenseId=null;$fileId=null;
if(strlen($token)<40||strlen($token)>100||rate_limited('download_denied',client_hash(),30,600)){event_record('download_denied',client_hash(),false);http_response_code(429);exit('Download unavailable.');}
$hash=hash_hmac('sha256',$token,app_key());$stmt=$db->prepare('SELECT download_grants.*,licenses.status AS license_status,licenses.id AS license_id,licenses.download_count,licenses.download_limit,orders.status AS order_status,product_files.id AS file_id,product_files.storage_name,product_files.original_name,product_files.mime_type,product_files.size_bytes,product_files.sha256 FROM download_grants JOIN licenses ON licenses.id=download_grants.license_id JOIN orders ON orders.id=licenses.order_id JOIN product_files ON (product_files.id=licenses.product_file_id OR (licenses.product_file_id IS NULL AND product_files.product_id=licenses.product_id AND product_files.active=1)) WHERE download_grants.token_hash=? LIMIT 1');$stmt->execute([$hash]);$grant=$stmt->fetch();
if($grant){$licenseId=(int)$grant['license_id'];$fileId=(int)$grant['file_id'];}
$valid=$grant&&$grant['license_status']==='active'&&$grant['order_status']==='paid'&&(int)$grant['expires_at']>=time()&&(int)$grant['download_count']<(int)$grant['max_downloads']&&(int)$grant['download_count']<(int)$grant['download_limit'];
$base=realpath(package_directory());$path=$grant?realpath(package_directory().DIRECTORY_SEPARATOR.basename((string)$grant["storage_name"])):false;$integrity=$grant&&$path!==false&&is_file($path)&&hash_equals(strtolower((string)$grant["sha256"]),strtolower((string)hash_file("sha256",$path)));
if(!$valid||$base===false||$path===false||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)||!$integrity){download_event($licenseId,$fileId,false,"authorization_denied");event_record("download_denied",client_hash(),false);http_response_code(404);exit("Download unavailable.");}
$db->beginTransaction();try{$consume=$db->prepare('UPDATE download_grants SET download_count=download_count+1,last_download_at=? WHERE id=? AND expires_at>=? AND download_count<max_downloads');$consume->execute([time(),$grant['id'],time()]);if($consume->rowCount()!==1)throw new RuntimeException('Grant already consumed.');$licenseConsume=$db->prepare('UPDATE licenses SET download_count=download_count+1 WHERE id=? AND status="active" AND download_count<download_limit');$licenseConsume->execute([$licenseId]);if($licenseConsume->rowCount()!==1)throw new RuntimeException('License download limit reached.');download_event($licenseId,$fileId,true,'stream_started');$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();event_record('download_denied',client_hash(),false);http_response_code(409);exit('Download unavailable.');}
$filename=preg_replace('/[^A-Za-z0-9._-]/','_',basename((string)$grant['original_name']))??'package.zip';if(!str_ends_with(strtolower($filename),'.zip'))$filename.='.zip';
while(ob_get_level()>0)ob_end_clean();header('Content-Type: application/zip');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($path));header('Accept-Ranges: none');readfile($path);exit;
