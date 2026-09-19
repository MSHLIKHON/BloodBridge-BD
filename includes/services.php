<?php
declare(strict_types=1);

function bb_one(PDO $pdo, string $sql, array $parameters = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetch() ?: null;
}

function bb_all(PDO $pdo, string $sql, array $parameters = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function bb_exec(PDO $pdo, string $sql, array $parameters = []): void
{
    $pdo->prepare($sql)->execute($parameters);
}

function bb_transaction(PDO $pdo, callable $work): mixed
{
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function app_setting(string $key, int $default): int
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try { foreach (db()->query('SELECT * FROM app_settings')->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value']; }
        catch (PDOException $exception) { /* Installer not yet run. */ }
    }
    return isset($settings[$key]) ? (int) $settings[$key] : $default;
}

function v100_ready(): bool
{
    try { return app_setting('schema_version', 0) >= 110; } catch (Throwable $exception) { return false; }
}

function valid_past_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date && $date <= date('Y-m-d');
}

function require_verified_hospital(PDO $pdo, int $hospitalId): void
{
    if (!bb_one($pdo, "SELECT id FROM hospitals WHERE id=? AND status='Verified'" . ($pdo->inTransaction()?' FOR UPDATE':''), [$hospitalId])) {
        throw new DomainException('This hospital is not currently verified.');
    }
}

function ensure_not_expired(array $record): void
{
    if(($record['source_type']??'')==='Donor' && !empty($record['donor_reported_at'])) return;
    if (!empty($record['expires_at']) && strtotime($record['expires_at']) <= time()) {
        throw new DomainException('This request has expired. Refresh the page to see its updated status.');
    }
}

function can_access_health(PDO $pdo, array $viewer, int $ownerId): bool
{
    if ((int) $viewer['id'] === $ownerId) return true;
    if ($viewer['role'] !== 'hospital') return false;
    return bb_one($pdo, "SELECT ha.donor_id FROM health_access ha JOIN hospitals h ON h.id=ha.hospital_id WHERE ha.donor_id=? AND ha.hospital_id=? AND h.status='Verified'", [$ownerId,(int) $viewer['hospital_id']]) !== null;
}

function can_review_prescription(PDO $pdo, array $viewer, array $request): bool
{
    if ((int) $viewer['id'] === (int) $request['seeker_id']) return false;
    if ($viewer['role'] === 'admin') return true;
    return $viewer['role'] === 'hospital'
        && (int) $viewer['hospital_id'] === (int) $request['review_hospital_id']
        && bb_one($pdo, "SELECT id FROM hospitals WHERE id=? AND status='Verified'", [(int) $viewer['hospital_id']]) !== null;
}

function can_read_document(PDO $pdo, array $viewer, array $document): bool
{
    if ((int) $viewer['id'] === (int) $document['owner_id']) return true;
    if ($document['kind'] === 'Health') return can_access_health($pdo,$viewer,(int) $document['owner_id']);
    $request = bb_one($pdo,'SELECT * FROM blood_requests WHERE id=?',[(int) $document['request_id']]);
    return $request !== null && can_review_prescription($pdo,$viewer,$request);
}

/** Files remain in the database, never in a public uploads directory. */
function receive_private_upload(string $field): array
{
    $file = $_FILES[$field] ?? null;
    if (!$file || !is_array($file) || !isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new DomainException('Choose a JPG, PNG or PDF (maximum 2 MB). If upload fails, check PHP upload limits.');
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) throw new DomainException('Invalid upload.');
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 2 * 1024 * 1024) throw new DomainException('File must be between 1 byte and 2 MB.');
    $data = file_get_contents($file['tmp_name']);
    if ($data === false) throw new DomainException('Could not read the upload.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
    if (!isset($extensions[$mime])) throw new DomainException('Only actual JPG, PNG or PDF files are accepted.');
    if (str_starts_with((string) $mime,'image/') && @getimagesizefromstring($data) === false) throw new DomainException('Image data is not valid.');
    if ($mime === 'application/pdf' && !str_starts_with($data,'%PDF-')) throw new DomainException('PDF data is not valid.');
    return ['data'=>$data,'mime'=>$mime,'size'=>$size,'filename'=>'document.' . $extensions[$mime]];
}

function save_private_document(PDO $pdo, int $ownerId, string $kind, array $upload, ?int $requestId = null, array $test = []): int
{
    $count = bb_one($pdo,'SELECT COUNT(*) AS n FROM private_documents WHERE owner_id=?',[$ownerId]);
    if ((int) $count['n'] >= 50) throw new DomainException('Maximum 50 documents per account. Remove an old document first.');
    $pdo->prepare('INSERT INTO private_documents (owner_id,request_id,kind,filename,mime_type,size_bytes,file_data,test_name,test_value,test_unit,test_date,lab_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
        $ownerId,$requestId,$kind,$upload['filename'],$upload['mime'],$upload['size'],$upload['data'],
        $test['name']??null,$test['value']??null,$test['unit']??null,$test['date']??null,$test['lab']??null,
    ]);
    $id = (int) $pdo->lastInsertId();
    audit_log($pdo,$ownerId,'Upload private document','PrivateDocument',$id,$kind);
    return $id;
}

function notify_hospital(PDO $pdo, int $hospitalId, string $title, string $message, string $link): void
{
    foreach (bb_all($pdo,"SELECT id FROM users WHERE hospital_id=? AND role='hospital' AND account_status='Active'",[$hospitalId]) as $staff) {
        create_notification($pdo,(int) $staff['id'],$title,$message,$link);
    }
}

function notify_admins(PDO $pdo, string $title, string $message, string $link): void
{
    foreach (bb_all($pdo,"SELECT id FROM users WHERE role='admin' AND account_status='Active'") as $admin) create_notification($pdo,(int) $admin['id'],$title,$message,$link);
}

function assert_no_active_donation(PDO $pdo, int $donorId): void
{
    // Caller locks the donor row first, serializing all donation commitments.
    if (bb_one($pdo,"SELECT id FROM blood_requests WHERE accepted_by=? AND source_type='Donor' AND status='Accepted'",[$donorId])
        || bb_one($pdo,"SELECT id FROM direct_donations WHERE donor_id=? AND status IN ('Pending','Screened')",[$donorId])) {
        throw new DomainException('You already have an active donation commitment. Complete or cancel it first.');
    }
}

function complete_donor_history(PDO $pdo, array $donor, ?int $requestId, ?int $directId, ?int $hospitalId, int $verifierId, ?int $clubId = null, ?string $donationDate = null): void
{
    $donationDate ??= date('Y-m-d');
    if(!valid_past_date($donationDate)) throw new DomainException('Invalid recorded donation date.');
    $next = next_eligible_date($donor['last_donation_date'] ?? null);
    if ($next && $next > $donationDate) throw new DomainException('Donation interval has not passed. Recheck the donor history.');
    if (($donor['screening_status']??'Pending') !== 'Eligible') throw new DomainException('Current clinical screening is required.');
    bb_exec($pdo,'INSERT INTO donation_history (request_id,direct_donation_id,donor_id,hospital_id,verified_by_user_id,blood_group,units,donation_date,notes) VALUES (?,?,?,?,?,?,1,?,?)',[
        $requestId,$directId,(int) $donor['id'],$hospitalId,$verifierId,$donor['blood_group'],$donationDate,
        $directId ? 'Hospital-confirmed direct donation' : 'Request completion confirmed by request owner; not a clinical certification',
    ]);
    bb_exec($pdo,"UPDATE users SET last_donation_date=?,total_donations=total_donations+1,screening_status='Pending',verified_by_hospital=0,profile_updated_at=NOW() WHERE id=?",[$donationDate,(int) $donor['id']]);
}

function safe_error(Throwable $exception): string
{
    error_log($exception->getMessage());
    return $exception instanceof DomainException ? $exception->getMessage() : 'The action could not be completed. Check the server log or try again.';
}

function form_errors(array $errors): void
{
    if (!$errors) return;
    echo '<div class="alert alert-error"><ul>';
    foreach ($errors as $error) echo '<li>'.e($error).'</li>';
    echo '</ul></div>';
}
