<?php
/** File purpose: Nearby handles the corresponding BloodBridge BD web workflow. */
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/geo.php';
require_role(['donor','seeker']);
header('Content-Type: application/json; charset=utf-8');
try {
    if(time()-(int)($_SESSION['nearby_last']??0)<3) {http_response_code(429);echo json_encode(['error'=>'Please wait a few seconds before searching again.']);exit;}
    $_SESSION['nearby_last']=time();
    echo json_encode(nearby_candidates(db(),current_user(),(int)($_GET['request_id']??0)));
} catch(DomainException $e) {http_response_code(403);echo json_encode(['error'=>$e->getMessage()]);}
