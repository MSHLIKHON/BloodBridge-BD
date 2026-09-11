<?php
declare(strict_types=1);

// Donor Details & Screening
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash('error', 'Invalid donor ID.');
    redirect('search.php');
}

$load = db()->prepare(
    'SELECT id, full_name, email, blood_group, location, phone, account_status, last_donation_date, total_donations,
            is_available, screening_status, has_medical_condition, medical_conditions,
            current_medications, verified_by_hospital, screened_by_user_id, screened_at,
            screening_notes, profile_updated_at
     FROM users WHERE id = ? AND role = \'donor\''
);
$load->execute([$id]);
$donor = $load->fetch();
if (!$donor) {
    http_response_code(404);
    exit('Donor not found.');
}

$viewer = current_user();
$acceptedConnection = false;
if ($viewer['role'] === 'seeker') {
    $connection = db()->prepare("SELECT COUNT(*) FROM blood_requests WHERE seeker_id = ? AND accepted_by = ? AND status IN ('Accepted', 'Completed')");
    $connection->execute([(int) $viewer['id'], (int) $donor['id']]);
    $acceptedConnection = (int) $connection->fetchColumn() > 0;
}
$canViewPrivate = (int) $viewer['id'] === (int) $donor['id'] || in_array($viewer['role'], ['admin', 'hospital'], true) || $acceptedConnection;
$canVerify = in_array($viewer['role'], ['admin', 'hospital'], true);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$canVerify) {
        http_response_code(403);
        exit('You do not have permission to update screening information.');
    }
    $status = (string) ($_POST['screening_status'] ?? '');
    $verified = isset($_POST['verified_by_hospital']) ? 1 : 0;
    $notes = trim((string) ($_POST['screening_notes'] ?? ''));
    if (!in_array($status, valid_screening_statuses(), true)) $errors[] = 'Choose a valid screening status.';
    if (strlen($notes) > 500) $errors[] = 'Screening note must be within 500 characters.';

    if (!$errors) {
        $update = db()->prepare('UPDATE users SET screening_status = ?, verified_by_hospital = ?, screened_by_user_id = ?, screened_at = NOW(), screening_notes = ?, profile_updated_at = NOW() WHERE id = ?');
        $update->execute([$status, $verified, (int) $viewer['id'], $notes !== '' ? $notes : null, $id]);
        create_notification(db(), (int) $id, 'Donor screening updated', 'Your screening status is now ' . $status . '.', 'donor_profile.php', 'Screening');
        audit_log(db(), (int) $viewer['id'], 'Update donor screening', 'User', (int) $id, $status);
        flash('success', 'Donor screening information updated.');
        redirect('donor_details.php?id=' . $id);
    }
}

$effectiveStatus = donor_effective_status($donor);
$eligibleDate = next_eligible_date($donor['last_donation_date']);
$pageTitle = 'Donor Profile';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">Donor information</span><h1><?= e($donor['full_name']) ?></h1><p><?= e($donor['blood_group']) ?> donor from <?= e($donor['location']) ?></p></div>
    <span class="badge <?= donor_status_class($effectiveStatus) ?>"><?= e($effectiveStatus) ?></span>
</section>

<div class="detail-grid">
    <section class="content-card">
        <div class="section-heading"><div><span class="eyebrow">Public information</span><h2>Donation eligibility</h2></div></div>
        <dl class="detail-list">
            <div><dt>Blood group</dt><dd><?= e($donor['blood_group']) ?></dd></div>
            <div><dt>Location</dt><dd><?= e($donor['location']) ?></dd></div>
            <div><dt>Last donation</dt><dd><?= $donor['last_donation_date'] ? e(date('d M Y', strtotime($donor['last_donation_date']))) : 'Not provided' ?></dd></div>
            <div><dt>Next eligible</dt><dd><?= $eligibleDate ? e(date('d M Y', strtotime($eligibleDate))) : 'Requires screening' ?></dd></div>
            <div><dt>Total donations</dt><dd><?= (int) $donor['total_donations'] ?></dd></div>
            <div><dt>Hospital verification</dt><dd><?= $donor['verified_by_hospital'] ? 'Verified' : 'Pending' ?></dd></div>
            <div><dt>Screened at</dt><dd><?= $donor['screened_at'] ? e(date('d M Y, h:i A', strtotime($donor['screened_at']))) : 'Not screened' ?></dd></div>
            <div><dt>Availability</dt><dd><?= $donor['is_available'] ? 'Available' : 'Unavailable' ?></dd></div>
            <div><dt>Contact</dt><dd><?= $canViewPrivate ? e($donor['phone']) : 'Shared after request acceptance' ?></dd></div>
        </dl>
    </section>

    <?php if ($canViewPrivate): ?>
    <section class="content-card private-medical-card">
        <div class="section-heading"><div><span class="eyebrow">Restricted information</span><h2>Medical declaration</h2></div></div>
        <dl class="detail-list">
            <div><dt>Health condition</dt><dd><?= $donor['has_medical_condition'] ? 'Declared' : 'None declared' ?></dd></div>
            <div><dt>Condition details</dt><dd><?= e($donor['medical_conditions'] ?: 'None provided') ?></dd></div>
            <div><dt>Medications</dt><dd><?= e($donor['current_medications'] ?: 'None provided') ?></dd></div>
            <div><dt>Screening note</dt><dd><?= e($donor['screening_notes'] ?: 'No note') ?></dd></div>
        </dl>
    </section>
    <?php else: ?>
    <section class="content-card privacy-card"><span class="eyebrow">Privacy protected</span><h2>Medical details are confidential</h2><p>You can see the donor's eligibility and hospital-verification status. Specific medical information is restricted.</p></section>
    <?php endif; ?>
</div>

<?php if (in_array($viewer['role'], ['donor', 'hospital', 'admin'], true)): ?><p class="top-gap"><a class="button button-secondary" href="donation_history.php?donor_id=<?= (int) $donor['id'] ?>">View Donation History</a></p><?php endif; ?>

<?php if ($canVerify): ?>
<section class="content-card form-card top-gap">
    <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="section-heading"><div><span class="eyebrow">Authorized screening</span><h2>Update verification status</h2></div></div>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Screening status</span><select name="screening_status"><?php foreach (valid_screening_statuses() as $status): ?><option value="<?= e($status) ?>" <?= $donor['screening_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
        <label class="checkbox-label"><input type="checkbox" name="verified_by_hospital" value="1" <?= $donor['verified_by_hospital'] ? 'checked' : '' ?>><span>Verified by authorized staff</span></label>
        <label class="form-wide"><span>Screening note</span><textarea name="screening_notes" rows="3" maxlength="500"><?= e($donor['screening_notes']) ?></textarea></label>
        <div class="form-wide"><button class="button button-primary" type="submit">Save Verification</button></div>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
