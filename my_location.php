<?php
/** File purpose: My Location handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/address.php';
require_once __DIR__.'/includes/geo.php';
require_role(['donor','seeker']);
$pdo=db();$user=current_user();$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        $address=address_input($_POST);
        $point=bangladesh_point($_POST['latitude']??null,$_POST['longitude']??null);
        $consent=isset($_POST['location_consent']);
        if($consent && !$point) throw new DomainException('Add a map point before enabling nearby matching.');
        bb_exec($pdo,'UPDATE users SET location=?,latitude=?,longitude=?,location_consent=?,location_updated_at=NOW() WHERE id=?',[$address,$consent?$point[0]:null,$consent?$point[1]:null,$consent?1:0,$user['id']]);
        audit_log($pdo,(int)$user['id'],$consent?'Nearby location enabled':'Nearby location removed','User',(int)$user['id']);
        flash('success','Address saved. Nearby visibility expires in 30 days unless you refresh it.');redirect('my_location.php');
    }catch(Throwable $e){$errors[]=safe_error($e);}
}
$profile=bb_one($pdo,'SELECT * FROM users WHERE id=?',[$user['id']]);
$pageTitle='My location';$enableMap=true;require __DIR__.'/includes/header.php';
?>
<section class="page-heading"><div><h1>My location</h1><p>Choose your address and control nearby donor matching.</p></div></section>
<?php foreach($errors as $error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endforeach; ?>
<section class="content-card form-card"><form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<?php render_address_picker($profile['location']);render_map_picker($profile['latitude']!==null?[(float)$profile['latitude'],(float)$profile['longitude']]:null); ?>
<label class="checkbox-label form-wide"><input type="checkbox" name="location_consent" <?= $profile['location_consent']?'checked':'' ?>><span>Allow my approximate area to appear to verified request owners when I am available. Untick and save to delete my saved coordinates.</span></label>
<p class="form-wide">The map uses approximate markers. One kilometre means straight-line distance from the saved point, not road distance or a live location. Last saved: <?= e($profile['location_updated_at']??'Never') ?>.</p>
<div class="form-wide"><button class="button button-primary">Save location preferences</button></div></form></section>
<?php require __DIR__.'/includes/footer.php'; ?>
