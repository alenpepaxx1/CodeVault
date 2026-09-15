<?php
/**
 * CodeVault one-time secure administrator setup.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
require 'config.php';
if(admin_count()>0)redirect('admin.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $email=filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL);
    $password=is_string($_POST['password']??null)?$_POST['password']:'';
    $confirm=is_string($_POST['confirm_password']??null)?$_POST['confirm_password']:'';
    $blocked=rate_limited('setup',client_hash(),10,1800);
    $human=captcha_verify($_POST['captcha']??'');
    $honeypot=trim((string)($_POST['website']??''));
    if($blocked)$error=t('error.rate');
    elseif(!$human||$honeypot!=='')$error=t('error.captcha');
    elseif(!$email||$password!==$confirm||!password_strong($password))$error=t('admin.password_mismatch');
    else{
        try{
            $db->exec('BEGIN IMMEDIATE');
            if((int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn()>0){$db->rollBack();redirect('admin.php');}
            $stmt=$db->prepare('INSERT INTO admin_users(email,password_hash) VALUES(?,?)');
            $stmt->execute([strtolower((string)$email),password_hash($password,password_algo())]);
            $adminId=(int)$db->lastInsertId();$db->commit();
            session_regenerate_id(true);$_SESSION['admin_id']=$adminId;$_SESSION['admin_last']=time();$_SESSION['admin_ua']=user_agent_hash();
            event_record('setup',client_hash(),true);audit('admin_created','admin',(string)$_SESSION['admin_id']);redirect('admin.php');
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Setup error: '.$e->getMessage());$error=t('admin.invalid');}
    }
    event_record('setup',client_hash(),false);
}
$captchaQuestion=captcha_new();$pageTitle=t('admin.setup');require 'partials/header.php';
?>
<section class="login setup-page"><div class="login-card"><div class="admin-mark">CV</div><span class="kicker"><?= e(t('admin.control')) ?></span><h1><?= e(t('admin.setup')) ?></h1><p><?= e(t('admin.setup_text')) ?></p>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><label class="hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
<label><?= e(t('form.email')) ?><input type="email" name="email" maxlength="190" required autocomplete="username"></label>
<label><?= e(t('form.password')) ?><input type="password" name="password" minlength="12" maxlength="200" required autocomplete="new-password"><small><?= e(t('admin.password_rules')) ?></small></label>
<label><?= e(t('admin.confirm_password')) ?><input type="password" name="confirm_password" minlength="12" maxlength="200" required autocomplete="new-password"></label>
<label><?= e(t('form.captcha')) ?> · <b><?= e($captchaQuestion) ?></b><input name="captcha" inputmode="numeric" maxlength="4" required placeholder="<?= e(t('form.answer')) ?>"></label>
<?php if($error):?><p class="error" role="alert"><?= e($error) ?></p><?php endif;?><button class="button wide" type="submit"><?= e(t('admin.create')) ?><span>→</span></button></form></div></section>
<?php require 'partials/footer.php'; ?>
