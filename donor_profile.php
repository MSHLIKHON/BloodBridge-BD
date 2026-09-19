<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_role(['donor','seeker']);

$userId = (int) current_user()['id'];
$load = db()->prepare(
    'SELECT id, full_name, email, blood_group, location, phone, last_donation_date, total_donations,
            is_available, screening_status, has_medical_condition, medical_conditions,
            current_medications, verified_by_hospital, screening_notes
     FROM users WHERE id = ? AND role IN (\'donor\',\'seeker\')'
);
$load->execute([$userId]);
$donor = $load->fetch();
if (!$donor) {
    http_response_code(404);
    exit('Donor profile not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $lastDonationDate = trim((string) ($_POST['last_donation_date'] ?? ''));
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;
    $hasCondition = (string) ($_POST['has_medical_condition'] ?? '0') === '1' ? 1 : 0;
    $conditions = trim((string) ($_POST['medical_conditions'] ?? ''));
    $medications = trim((string) ($_POST['current_medications'] ?? ''));

    if ($lastDonationDate !== '') {
        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $lastDonationDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $lastDonationDate || $lastDonationDate > date('Y-m-d')) {
            $errors[] = 'Enter a valid last donation date. Future dates are not allowed.';
        }
    }
    if ($hasCondition && $conditions === '') $errors[] = 'Briefly describe the medical condition.';
    if (strlen($conditions) > 1000) $errors[] = 'Medical condition details must be within 1000 characters.';
    if (strlen($medications) > 1000) $errors[] = 'Medication details must be within 1000 characters.';

    if (!$errors) {
        try {
            bb_transaction(db(),function()use($userId,$lastDonationDate,$isAvailable,$hasCondition,$conditions,$medications):void {
                $pdo=db();
                bb_one($pdo,'SELECT id FROM users WHERE id=? FOR UPDATE',[$userId]);
                $latest=bb_one($pdo,'SELECT MAX(donation_date) AS latest FROM donation_history WHERE donor_id=?',[$userId]);
                if ($latest['latest'] && ($lastDonationDate==='' || $lastDonationDate<$latest['latest'])) throw new DomainException('Last donation cannot be earlier than a recorded completed donation.');
                bb_exec($pdo,"UPDATE users SET last_donation_date=?,is_available=?,has_medical_condition=?,medical_conditions=?,current_medications=?,screening_status='Pending',verified_by_hospital=0,profile_updated_at=NOW() WHERE id=?",[$lastDonationDate?:null,$isAvailable,$hasCondition,$hasCondition?$conditions:null,$medications?:null,$userId]);
                audit_log($pdo,$userId,'Update donor declaration','User',$userId,'Self-reported; rescreening required');
            });
            flash('success','Donor profile updated. Hospital verification is now pending.');redirect('donor_profile.php');
        } catch(Throwable $e) { $errors[]=safe_error($e); }
    }

    $donor = array_merge($donor, [
        'last_donation_date' => $lastDonationDate,
        'is_available' => $isAvailable,
        'has_medical_condition' => $hasCondition,
        'medical_conditions' => $conditions,
        'current_medications' => $medications,
    ]);
}

$effectiveStatus = donor_effective_status($donor);
$eligibleDate = next_eligible_date($donor['last_donation_date']);
$pageTitle = 'My Donor Profile';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><a href="my_location.php">Update address / map location</a><span class="eyebrow">Private donor information</span><h1>My donor profile</h1><p>Keep your donation and health information accurate.</p></div>
    <span class="badge <?= donor_status_class($effectiveStatus) ?>"><?= e($effectiveStatus) ?></span>
</section>

<div class="detail-grid">
    <section class="content-card">
        <div class="section-heading"><div><span class="eyebrow">Eligibility summary</span><h2><?= e($donor['blood_group'] ?: 'Blood group missing') ?></h2></div></div>
        <dl class="detail-list">
            <div><dt>Last donation</dt><dd><?= $donor['last_donation_date'] ? e(date('d M Y', strtotime($donor['last_donation_date']))) : 'Not provided' ?></dd></div>
            <div><dt>Next interval date</dt><dd><?= $eligibleDate ? e(date('d M Y', strtotime($eligibleDate))) : 'Requires donation history' ?></dd></div>
            <div><dt>Total donations</dt><dd><?= (int) $donor['total_donations'] ?></dd></div>
            <div><dt>Hospital verification</dt><dd><?= $donor['verified_by_hospital'] ? 'Verified' : 'Pending' ?></dd></div>
            <div><dt>Screening note</dt><dd><?= e($donor['screening_notes'] ?: 'No screening note') ?></dd></div>
        </dl>
    </section>
    <section class="content-card privacy-card">
        <span class="eyebrow">Privacy</span><h2>Your medical details are protected</h2>
        <p>Medical details are visible only to you and hospital staff you authorize in <a href="health_records.php">Health Records</a>. Seekers and club coordinators cannot see them.</p>
        <p class="muted">A hospital must complete the final health screening before every donation.</p>
    </section>
</div>

<section class="content-card form-card top-gap">
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="section-heading"><div><span class="eyebrow">Health declaration</span><h2>Update donation information</h2></div></div>
    <form method="post" class="form-grid" data-health-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Last donation date</span><input type="date" name="last_donation_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($donor['last_donation_date']) ?>"></label>
        <label><span>Verified total donations</span><input type="number" value="<?= (int) $donor['total_donations'] ?>" readonly><small>Updated automatically after a completed donation.</small></label>
        <label><span>Any current illness or medical condition?</span><select name="has_medical_condition" data-condition-select><option value="0" <?= !$donor['has_medical_condition'] ? 'selected' : '' ?>>No</option><option value="1" <?= $donor['has_medical_condition'] ? 'selected' : '' ?>>Yes</option></select></label>
        <label class="checkbox-label"><input type="checkbox" name="is_available" value="1" <?= $donor['is_available'] ? 'checked' : '' ?>><span>I am currently available for donation requests</span></label>
        <label class="form-wide" data-condition-details><span>Medical condition details</span><textarea name="medical_conditions" rows="3" maxlength="1000" placeholder="Give a short description for authorized screening staff"><?= e($donor['medical_conditions']) ?></textarea></label>
        <label class="form-wide"><span>Current medications (if any)</span><textarea name="current_medications" rows="3" maxlength="1000" placeholder="Medicine name or write None"><?= e($donor['current_medications']) ?></textarea></label>
        <div class="form-wide form-actions"><button class="button button-primary" type="submit">Save Donor Profile</button></div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
