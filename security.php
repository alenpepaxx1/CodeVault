<?php
/**
 * CodeVault security controls.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);

function e(string|int|float|null $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function money(float $value): string { return '€'.number_format($value,2,'.',','); }
function security_headers(): void {
    if(headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 0');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    $privatePages=['admin.php','setup.php','checkout.php','cart.php','order.php','download.php','paypal_return.php','btcpay_return.php'];
    if(in_array(basename($_SERVER['SCRIPT_NAME']??''),$privatePages,true))header('Cache-Control: no-store, private');
}
security_headers();

function app_key(): string {
    $env=getenv('CODEVAULT_APP_KEY');
    if($env) return hash('sha256',$env,true);
    $path=(getenv('CODEVAULT_DATA_DIR') ?: __DIR__.'/data').'/.appkey';
    if(!is_file($path)){
        $key=bin2hex(random_bytes(32));
        if(file_put_contents($path,$key,LOCK_EX)===false) throw new RuntimeException('Encryption key unavailable.');
        @chmod($path,0600);
    }
    return hash('sha256',(string)file_get_contents($path),true);
}
function protect(string $value): string {
    if($value==='') return '';
    $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false) throw new RuntimeException('Encryption failed.');
    return 'enc:'.base64_encode($iv.$tag.$cipher);
}
function unprotect(string $value): string {
    if($value===''||!str_starts_with($value,'enc:')) return $value;
    $raw=base64_decode(substr($value,4),true);if($raw===false||strlen($raw)<29) return '';
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
    return $plain===false?'':$plain;
}
function setting(string $key,string $default='',bool $secret=false): string {
    global $db;$stmt=$db->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$stmt->execute([$key]);$value=$stmt->fetchColumn();
    if($value===false)return $default;return $secret?unprotect((string)$value):(string)$value;
}
function save_setting(string $key,string $value,bool $secret=false): void {
    global $db;$value=$secret?protect($value):$value;$stmt=$db->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value');$stmt->execute([$key,$value]);
}
function enabled(string $key,bool $default=false): bool { return hash_equals('1',setting($key,$default?'1':'0')); }
function site_url(): string { return rtrim(setting('site_url','http://localhost:8000'),'/'); }
function redirect(string $url): never { header('Location: '.$url,true,303);exit; }
function csrf(): string { if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf']; }
function verify_csrf(): void {
    $provided=is_string($_POST['csrf']??null)?$_POST['csrf']:'';
    if(!hash_equals($_SESSION['csrf']??'',$provided)){http_response_code(419);exit('Invalid request token.');}
    unset($_SESSION['csrf']);
}
function client_hash(): string { return hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',app_key()); }
function user_agent_hash(): string { return hash('sha256',substr($_SERVER['HTTP_USER_AGENT']??'unknown',0,500)); }
function admin_count(): int { global $db;return (int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(); }
function admin(): bool {
    if(empty($_SESSION['admin_id'])||empty($_SESSION['admin_last'])||!hash_equals($_SESSION['admin_ua']??'',user_agent_hash()))return false;
    if(time()-(int)$_SESSION['admin_last']>1800){unset($_SESSION['admin_id'],$_SESSION['admin_last'],$_SESSION['admin_ua']);return false;}
    $_SESSION['admin_last']=time();return true;
}
function require_admin(): void { if(!admin())redirect('admin.php'); }
function admin_logout(): void { $_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);}session_destroy(); }
function password_strong(string $password): bool { return strlen($password)>=12&&preg_match('/[a-z]/',$password)&&preg_match('/[A-Z]/',$password)&&preg_match('/\d/',$password)&&preg_match('/[^a-zA-Z0-9]/',$password); }
function password_algo(): string|int|null { return defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT; }
function event_record(string $type,string $identifier,bool $success,array $metadata=[]): void { global $db;$stmt=$db->prepare('INSERT INTO security_events(event_type,identifier_hash,success,metadata,created_at) VALUES(?,?,?,?,?)');$stmt->execute([$type,hash_hmac('sha256',$identifier,app_key()),$success?1:0,json_encode($metadata),time()]); }
function rate_limited(string $type,string $identifier,int $max=5,int $window=900): bool { global $db;$hash=hash_hmac('sha256',$identifier,app_key());$stmt=$db->prepare('SELECT COUNT(*) FROM security_events WHERE event_type=? AND identifier_hash=? AND success=0 AND created_at>=?');$stmt->execute([$type,$hash,time()-$window]);return (int)$stmt->fetchColumn()>=$max; }
function captcha_new(): string {
    $a=random_int(2,12);$b=random_int(2,9);$op=random_int(0,1);$answer=$op?$a+$b:$a*$b;
    $_SESSION['captcha']=['hash'=>hash_hmac('sha256',(string)$answer,app_key()),'expires'=>time()+300];
    $prefix=['sq'=>'Sa bëjnë','en'=>'What is','de'=>'Wie viel ist'][current_lang()];return "$prefix $a ".($op?'+':'×')." $b?";
}
function captcha_verify(mixed $answer): bool {
    $challenge=$_SESSION['captcha']??null;unset($_SESSION['captcha']);
    if(!is_array($challenge)||($challenge['expires']??0)<time()||!is_scalar($answer))return false;
    return hash_equals((string)$challenge['hash'],hash_hmac('sha256',trim((string)$answer),app_key()));
}
function audit(string $action,string $objectType='',string $objectId=''): void { global $db;$stmt=$db->prepare('INSERT INTO audit_log(admin_id,action,object_type,object_id,ip_hash) VALUES(?,?,?,?,?)');$stmt->execute([$_SESSION['admin_id']??null,$action,$objectType,$objectId,client_hash()]); }
function order_status_label(string $status): string { return t('status.'.$status); }
function safe_text(mixed $value,int $max=255): string { if(!is_string($value))return '';$clean=trim(strip_tags($value));return function_exists('mb_substr')?mb_substr($clean,0,$max):substr($clean,0,$max); }

function complete_admin_login(array $user,bool $mfaVerified=false): void {
    global $db;
    session_regenerate_id(true);
    $_SESSION['admin_id']=(int)$user['id'];
    $_SESSION['admin_last']=time();
    $_SESSION['admin_ua']=user_agent_hash();
    $_SESSION['admin_mfa']=$mfaVerified;
    unset($_SESSION['csrf'],$_SESSION['mfa_pending']);
    $db->prepare('UPDATE admin_users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$user['id']]);
    audit('login','admin',(string)$user['id']);
}
function admin_mfa_enabled(?int $adminId=null): bool {
    global $db;
    $adminId=$adminId??(int)($_SESSION['admin_id']??0);if($adminId<1)return false;
    $stmt=$db->prepare('SELECT mfa_enabled FROM admin_users WHERE id=?');$stmt->execute([$adminId]);
    return (int)$stmt->fetchColumn()===1;
}
function require_admin_mfa(): void {
    if(!admin()||!admin_mfa_enabled()||empty($_SESSION['admin_mfa'])){http_response_code(403);exit('Multi-factor authentication is required.');}
}
function base32_encode_secret(string $bytes): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';$output='';
    foreach(str_split($bytes) as $byte)$bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT);
    foreach(str_split($bits,5) as $chunk){if(strlen($chunk)<5)$chunk=str_pad($chunk,5,'0');$output.=$alphabet[bindec($chunk)];}
    return $output;
}
function base32_decode_secret(string $encoded): string|false {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$encoded=strtoupper(preg_replace('/[^A-Z2-7]/i','',$encoded)??'');if($encoded==='')return false;
    $bits='';foreach(str_split($encoded) as $char){$position=strpos($alphabet,$char);if($position===false)return false;$bits.=str_pad(decbin($position),5,'0',STR_PAD_LEFT);}
    $output='';foreach(str_split($bits,8) as $chunk){if(strlen($chunk)===8)$output.=chr(bindec($chunk));}return $output;
}
function totp_code(string $secret,int $step): string {
    $decoded=base32_decode_secret($secret);if($decoded===false)return '';
    $counter=pack('N2',0,$step);$hash=hash_hmac('sha1',$counter,$decoded,true);$offset=ord($hash[19])&0x0f;
    $number=(unpack('N',substr($hash,$offset,4))[1]&0x7fffffff)%1000000;
    return str_pad((string)$number,6,'0',STR_PAD_LEFT);
}
function totp_verify_step(string $secret,string $code,?int $lastStep=null): int|false {
    $code=preg_replace('/\D/','',$code)??'';if(strlen($code)!==6)return false;$current=(int)floor(time()/30);
    for($offset=-1;$offset<=1;$offset++){$step=$current+$offset;if($lastStep!==null&&$step<=$lastStep)continue;if(hash_equals(totp_code($secret,$step),$code))return $step;}
    return false;
}
function recovery_codes_new(int $count=10): array {
    $codes=[];for($i=0;$i<$count;$i++){$raw=strtoupper(substr(bin2hex(random_bytes(6)),0,12));$codes[]=substr($raw,0,4).'-'.substr($raw,4,4).'-'.substr($raw,8,4);}return $codes;
}
function verify_admin_second_factor(array $user,string $code): bool {
    global $db;
    $secret=unprotect((string)($user['totp_secret']??''));$step=$secret!==''?totp_verify_step($secret,$code,isset($user['totp_last_step'])?(int)$user['totp_last_step']:null):false;
    if($step!==false){$stmt=$db->prepare('UPDATE admin_users SET totp_last_step=? WHERE id=? AND (totp_last_step IS NULL OR totp_last_step<?)');$stmt->execute([$step,$user['id'],$step]);return $stmt->rowCount()===1;}
    $normalized=strtoupper(preg_replace('/[^A-Z0-9]/','',$code)??'');if(strlen($normalized)!==12)return false;
    $stmt=$db->prepare('SELECT id,code_hash FROM admin_recovery_codes WHERE admin_id=? AND used_at IS NULL');$stmt->execute([$user['id']]);
    foreach($stmt->fetchAll() as $recovery){if(password_verify($normalized,(string)$recovery['code_hash'])){$use=$db->prepare('UPDATE admin_recovery_codes SET used_at=CURRENT_TIMESTAMP WHERE id=? AND used_at IS NULL');$use->execute([$recovery['id']]);return $use->rowCount()===1;}}
    return false;
}

function cart_ids(): array {
    $ids=$_SESSION['cart']??[];if(!is_array($ids))return [];$clean=[];
    foreach($ids as $id){$value=(int)$id;if($value>0&&!in_array($value,$clean,true))$clean[]=$value;if(count($clean)>=20)break;}
    $_SESSION['cart']=$clean;return $clean;
}
function cart_count(): int { return count(cart_ids()); }
function cart_add(int $productId): void { $ids=cart_ids();if($productId>0&&!in_array($productId,$ids,true)&&count($ids)<20)$ids[]=$productId;$_SESSION['cart']=$ids; }
function cart_remove(int $productId): void { $_SESSION['cart']=array_values(array_filter(cart_ids(),fn(int $id):bool=>$id!==$productId)); }
function cart_clear(): void { unset($_SESSION['cart'],$_SESSION['checkout_ids']); }
function products_by_ids(array $ids): array {
    global $db;$ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn(int $id):bool=>$id>0)));if(!$ids)return [];
    $placeholders=implode(',',array_fill(0,count($ids),'?'));$stmt=$db->prepare("SELECT * FROM products WHERE active=1 AND id IN ($placeholders)");$stmt->execute($ids);$found=[];foreach($stmt->fetchAll() as $product)$found[(int)$product['id']]=$product;
    $ordered=[];foreach($ids as $id)if(isset($found[$id]))$ordered[]=$found[$id];return $ordered;
}
function package_directory(): string { return (getenv('CODEVAULT_DATA_DIR') ?: __DIR__.'/data').'/packages'; }
function product_file(int $productId): array|false {
    global $db;$stmt=$db->prepare('SELECT * FROM product_files WHERE product_id=? AND active=1 ORDER BY id DESC LIMIT 1');$stmt->execute([$productId]);return $stmt->fetch();
}
function license_file(array $license): array|false {
    global $db;$fileId=(int)($license['product_file_id']??0);if($fileId>0){$stmt=$db->prepare('SELECT * FROM product_files WHERE id=?');$stmt->execute([$fileId]);$file=$stmt->fetch();if($file)return $file;}return product_file((int)$license['product_id']);
}
function secure_product_upload(int $productId,array $upload,string $version): array {
    global $db;
    if(!class_exists('ZipArchive'))throw new RuntimeException('PHP ZipArchive extension is required.');
    $error=(int)($upload['error']??UPLOAD_ERR_NO_FILE);if($error!==UPLOAD_ERR_OK)throw new RuntimeException('Package upload failed.');
    $tmp=is_string($upload['tmp_name']??null)?$upload['tmp_name']:'';$original=safe_text($upload['name']??'',180);$size=(int)($upload['size']??0);
    if($tmp===''||!is_uploaded_file($tmp)||$size<1||$size>50*1024*1024||strtolower(pathinfo($original,PATHINFO_EXTENSION))!=='zip')throw new RuntimeException('Only ZIP packages up to 50 MB are accepted.');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);if(!in_array($mime,['application/zip','application/x-zip-compressed','application/octet-stream'],true))throw new RuntimeException('The uploaded file is not a valid ZIP package.');
    $zip=new ZipArchive();if($zip->open($tmp)!==true)throw new RuntimeException('The ZIP package is damaged or unsupported.');
    $total=0;$unsafe=false;if($zip->numFiles<1||$zip->numFiles>5000)$unsafe=true;
    for($i=0;!$unsafe&&$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$name=(string)($stat['name']??'');$entrySize=(int)($stat['size']??0);$compressed=(int)($stat['comp_size']??0);$total+=$entrySize;
        if($name===''||strlen($name)>500||str_contains($name,"\0")||str_contains($name,'\\')||str_starts_with($name,'/')||preg_match('#(^|/)\.\.(/|$)#',$name)||$total>250*1024*1024||($entrySize>1024*1024&&$compressed>0&&$entrySize/$compressed>200))$unsafe=true;
        $opsys=0;$attributes=0;if(method_exists($zip,'getExternalAttributesIndex')&&$zip->getExternalAttributesIndex($i,$opsys,$attributes)&&(($attributes>>16)&0170000)===0120000)$unsafe=true;
    }
    $zip->close();if($unsafe)throw new RuntimeException('The ZIP package contains unsafe paths, links, or excessive compressed data.');
    $storage=bin2hex(random_bytes(24)).'.zip';$destination=package_directory().DIRECTORY_SEPARATOR.$storage;
    if(!move_uploaded_file($tmp,$destination))throw new RuntimeException('The package could not be moved to private storage.');@chmod($destination,0640);
    $version=safe_text($version,30);if($version===''||!preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,29}$/',$version)){$version='1.0.0';}
    try{$db->prepare('UPDATE product_files SET active=0 WHERE product_id=?')->execute([$productId]);$stmt=$db->prepare('INSERT INTO product_files(product_id,storage_name,original_name,mime_type,size_bytes,sha256,version) VALUES(?,?,?,?,?,?,?)');$stmt->execute([$productId,$storage,$original,$mime,$size,hash_file('sha256',$destination),$version]);$db->prepare('UPDATE products SET version=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$version,$productId]);return ['id'=>(int)$db->lastInsertId(),'name'=>$original,'size'=>$size];}
    catch(Throwable $e){@unlink($destination);throw $e;}
}

function order_access_hash(string $token): string { return hash_hmac('sha256',$token,app_key()); }
function order_access_valid(array $order,string $token): bool { $expected=(string)($order['access_token_hash']??'');return $expected!==''&&strlen($token)>=32&&hash_equals($expected,order_access_hash($token)); }
function remember_order_access(string $publicId,string $token): void { if(!is_array($_SESSION['order_access']??null))$_SESSION['order_access']=[];$_SESSION['order_access'][$publicId]=$token;if(count($_SESSION['order_access'])>10)$_SESSION['order_access']=array_slice($_SESSION['order_access'],-10,null,true); }
function current_order_access(string $publicId): string { $query=is_string($_GET['access']??null)?$_GET['access']:'';$sessionAccess=is_array($_SESSION['order_access']??null)?($_SESSION['order_access'][$publicId]??''):'';return $query!==''?$query:(string)$sessionAccess; }
function order_items(int $orderId): array { global $db;$stmt=$db->prepare('SELECT order_items.*,products.version FROM order_items LEFT JOIN products ON products.id=order_items.product_id WHERE order_id=? ORDER BY order_items.id');$stmt->execute([$orderId]);return $stmt->fetchAll(); }
function fulfill_order(int $orderId): void {
    global $db;$ownTransaction=!$db->inTransaction();if($ownTransaction)$db->beginTransaction();
    try{$stmt=$db->prepare('SELECT * FROM orders WHERE id=?');$stmt->execute([$orderId]);$order=$stmt->fetch();if(!$order||$order['status']!=='paid'){if($ownTransaction)$db->commit();return;}
        $items=order_items($orderId);$insert=$db->prepare('INSERT OR IGNORE INTO licenses(public_key,order_id,order_item_id,product_id,product_file_id,customer_email) VALUES(?,?,?,?,?,?)');$fileLookup=$db->prepare('SELECT id FROM product_files WHERE product_id=? AND active=1 ORDER BY id DESC LIMIT 1');
        foreach($items as $item){$fileLookup->execute([(int)$item['product_id']]);$fileId=$fileLookup->fetchColumn();$key='CV-'.strtoupper(substr(bin2hex(random_bytes(12)),0,8)).'-'.strtoupper(substr(bin2hex(random_bytes(12)),0,8)).'-'.strtoupper(substr(bin2hex(random_bytes(12)),0,8));$insert->execute([$key,$orderId,$item['id'],$item['product_id'],$fileId===false?null:(int)$fileId,$order['customer_email']]);}
        if($ownTransaction)$db->commit();
    }catch(Throwable $e){if($ownTransaction&&$db->inTransaction())$db->rollBack();throw $e;}
}
function order_licenses(int $orderId): array { global $db;$stmt=$db->prepare('SELECT licenses.*,order_items.title_snapshot,products.version,product_files.version AS file_version FROM licenses JOIN order_items ON order_items.id=licenses.order_item_id LEFT JOIN products ON products.id=licenses.product_id LEFT JOIN product_files ON product_files.id=licenses.product_file_id WHERE licenses.order_id=? ORDER BY licenses.id');$stmt->execute([$orderId]);return $stmt->fetchAll(); }
function create_download_grant(int $licenseId): string {
    global $db;$stmt=$db->prepare('SELECT download_count,download_limit FROM licenses WHERE id=? AND status="active"');$stmt->execute([$licenseId]);$license=$stmt->fetch();if(!$license||(int)$license['download_count']>=(int)$license['download_limit'])return '';
    $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$hash=hash_hmac('sha256',$token,app_key());$db->prepare('DELETE FROM download_grants WHERE license_id=?')->execute([$licenseId]);$stmt=$db->prepare('INSERT INTO download_grants(license_id,token_hash,expires_at,max_downloads,created_at) VALUES(?,?,?,?,?)');$stmt->execute([$licenseId,$hash,time()+86400,3,time()]);return $token;
}
function download_event(?int $licenseId,?int $fileId,bool $success,string $reason): void { global $db;$stmt=$db->prepare('INSERT INTO download_events(license_id,product_file_id,ip_hash,success,reason,created_at) VALUES(?,?,?,?,?,?)');$stmt->execute([$licenseId,$fileId,client_hash(),$success?1:0,safe_text($reason,80),time()]); }
