<?php
/** File purpose: Document handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_login();
$id = (int) ($_GET['id']??0);
$pdo = db();
$doc = bb_one($pdo,'SELECT id,owner_id,request_id,kind,filename,mime_type,size_bytes FROM private_documents WHERE id=?',[$id]);
if (!$doc || !can_read_document($pdo,current_user(),$doc)) { http_response_code(404); exit('Document not available.'); }
audit_log($pdo,(int) current_user()['id'],'Read private document','PrivateDocument',$id,$doc['kind']);
$data = bb_one($pdo,'SELECT file_data FROM private_documents WHERE id=?',[$id]);
header('Content-Type: '.$doc['mime_type']);
header('Content-Disposition: attachment; filename="bloodbridge-'.$id.'-'.$doc['filename'].'"');
header('Content-Length: '.$doc['size_bytes']);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'");
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
echo $data['file_data'];
