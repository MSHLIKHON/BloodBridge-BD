<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/locations.php';
require_role(['seeker', 'admin']);

$errors = [];
$values = [
    'blood_group' => $_POST['blood_group'] ?? 'B+',
    'location' => $_POST['location'] ?? (current_user()['location'] ?? ''),
    'units' => $_POST['units'] ?? '1',
    'urgency' => $_POST['urgency'] ?? 'Urgent',
    'source_type' => $_POST['source_type'] ?? 'Donor',
    'note' => $_POST['note'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $bloodGroup = (string) $values['blood_group'];
    $location = trim((string) $values['location']);
    $units = filter_var($values['units'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
    $urgency = (string) $values['urgency'];
    $sourceType = (string) $values['source_type'];
    $note = trim((string) $values['note']);

    if (!in_array($bloodGroup, valid_blood_groups(), true)) {
        $errors[] = 'Choose a valid blood group.';
    }
    if ($location === '' || strlen($location) > 120) {
        $errors[] = 'Enter a location within 120 characters.';
    }
    if ($units === false || $units === null) {
        $errors[] = 'Units must be between 1 and 10.';
    }
    if (!in_array($urgency, ['Normal', 'Urgent', 'Emergency'], true)) {
        $errors[] = 'Choose a valid urgency.';
    }
    if (!in_array($sourceType, ['Donor', 'Blood Bank'], true)) {
        $errors[] = 'Choose a valid blood source.';
    }
    if (strlen($note) > 500) {
        $errors[] = 'Note must be within 500 characters.';
    }

    if (!$errors) {
        $statement = db()->prepare(
            'INSERT INTO blood_requests (seeker_id, blood_group, location, units, urgency, source_type, note)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            (int) current_user()['id'],
            $bloodGroup,
            $location,
            $units,
            $urgency,
            $sourceType,
            $note !== '' ? $note : null,
        ]);

        flash('success', 'Blood request created successfully.');
        redirect('requests.php');
    }
}

$pageTitle = 'Create Blood Request';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><span class="eyebrow">Blood request</span><h1>Create blood request</h1><p>Enter the required blood group, location and preferred source.</p></div>
    <a class="button button-secondary" href="requests.php">Back to Requests</a>
</section>

<section class="content-card form-card">
    <?php if ($errors): ?>
        <div class="alert alert-error"><strong>Please fix:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Blood group</span><select name="blood_group" required><?php foreach (valid_blood_groups() as $group): ?><option value="<?= e($group) ?>" <?= $values['blood_group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?></select></label>
        <label><span>Location</span><input type="text" name="location" list="request-locations" maxlength="120" value="<?= e((string) $values['location']) ?>" placeholder="Start typing a location" autocomplete="off" required><?php render_location_datalist('request-locations'); ?></label>
        <label><span>Units needed</span><input type="number" name="units" min="1" max="10" value="<?= e((string) $values['units']) ?>" required></label>
        <label><span>Urgency</span><select name="urgency"><option <?= $values['urgency'] === 'Normal' ? 'selected' : '' ?>>Normal</option><option <?= $values['urgency'] === 'Urgent' ? 'selected' : '' ?>>Urgent</option><option <?= $values['urgency'] === 'Emergency' ? 'selected' : '' ?>>Emergency</option></select></label>
        <label><span>Blood source</span><select name="source_type"><option <?= $values['source_type'] === 'Donor' ? 'selected' : '' ?>>Donor</option><option <?= $values['source_type'] === 'Blood Bank' ? 'selected' : '' ?>>Blood Bank</option></select></label>
        <label class="form-wide"><span>Patient note (optional)</span><textarea name="note" maxlength="500" rows="4" placeholder="Hospital name, contact person or special instruction"><?= e((string) $values['note']) ?></textarea></label>
        <div class="form-wide form-actions"><button class="button button-primary" type="submit">Create Request</button><a class="button button-secondary" href="requests.php">Cancel</a></div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
