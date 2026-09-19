<?php
declare(strict_types=1);
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../includes/locations.php';

function bloodbridge_unit_tests(): array
{
    $results=[];
    $check=static function(string $name,callable $test) use(&$results):void {
        try { $ok=$test(); $results[]=['test'=>$name,'status'=>$ok===true?'PASS':'FAIL']; }
        catch(Throwable $e) { $results[]=['test'=>$name,'status'=>'FAIL','detail'=>$e->getMessage()]; }
    };
    $check('Eight blood groups',fn()=>count(valid_blood_groups())===8);
    $check('Blood groups unique',fn()=>count(array_unique(valid_blood_groups()))===8);
    $check('Bangladesh local phone',fn()=>normalize_bd_phone('01712345678')==='01712345678');
    $check('Bangladesh international phone',fn()=>normalize_bd_phone('+880 1712-345678')==='01712345678');
    $check('Phone leading zero normalization',fn()=>normalize_bd_phone('1712345678')==='01712345678');
    $check('Invalid phone prefix rejected',fn()=>normalize_bd_phone('01212345678')===null);
    $check('Short phone rejected',fn()=>normalize_bd_phone('017')===null);
    $check('Non-numeric phone rejected',fn()=>normalize_bd_phone('invalid')===null);
    $check('Strong password accepted',fn()=>password_validation_errors('BloodBridge!123')===[]);
    $check('Weak password rejected',fn()=>count(password_validation_errors('abc'))>0);
    $check('Oversize password rejected',fn()=>count(password_validation_errors(str_repeat('Aa1!',40)))>0);
    $check('Missing uppercase rejected',fn()=>count(password_validation_errors('bloodbridge!1'))>0);
    $check('Missing digit rejected',fn()=>count(password_validation_errors('BloodBridge!'))>0);
    $check('Missing symbol rejected',fn()=>count(password_validation_errors('BloodBridge123'))>0);
    $check('HTML escaping',fn()=>e('<script>"&')==='&lt;script&gt;&quot;&amp;');
    $check('Null escaping',fn()=>e(null)==='');
    $check('Valid leap day',fn()=>valid_past_date('2024-02-29'));
    $check('Invalid leap day rejected',fn()=>!valid_past_date('2025-02-29'));
    $check('Invalid month rejected',fn()=>!valid_past_date('2025-13-01'));
    $check('Future past-date rejected',fn()=>!valid_past_date(date('Y-m-d',strtotime('+1 day'))));
    $check('Today allowed',fn()=>valid_past_date(date('Y-m-d')));
    $check('Empty past-date rejected',fn()=>!valid_past_date(''));
    $days=120;
    $check('Supplied interval policy used',fn()=>next_eligible_date('2024-02-01',$days)===(new DateTimeImmutable('2024-02-01'))->modify('+'.$days.' days')->format('Y-m-d'));
    $check('Missing donation date not fabricated',fn()=>next_eligible_date(null)===null);
    $check('Malformed donation date handled',fn()=>next_eligible_date('not-a-date')===null);
    $d=['account_status'=>'Active','donor_enabled'=>1,'is_available'=>1,'screening_status'=>'Eligible','last_donation_date'=>null];
    $check('Screened available donor status',fn()=>donor_effective_status($d)==='Eligible');
    $check('Blocked donor not available',fn()=>donor_effective_status(array_replace($d,['account_status'=>'Blocked']))==='Unavailable');
    $check('Disabled donation not available',fn()=>donor_effective_status(array_replace($d,['donor_enabled'=>0]))==='Unavailable');
    $check('Unavailable donor excluded',fn()=>donor_effective_status(array_replace($d,['is_available'=>0]))==='Unavailable');
    $check('Pending screening retained',fn()=>donor_effective_status(array_replace($d,['screening_status'=>'Pending']))==='Pending');
    $check('Recent donation deferred',fn()=>donor_effective_status(array_replace($d,['last_donation_date'=>date('Y-m-d')]),$days)==='Temporarily Unavailable');
    $check('Permanent screening restriction retained',fn()=>donor_effective_status(array_replace($d,['screening_status'=>'Permanently Ineligible']))==='Permanently Ineligible');
    $check('Expiry at boundary rejected',function():bool { try { ensure_not_expired(['expires_at'=>date('Y-m-d H:i:s')]);return false; }catch(DomainException $e){return true;} });
    $check('Past expiry rejected',function():bool { try { ensure_not_expired(['expires_at'=>'2000-01-01 00:00:00']);return false; }catch(DomainException $e){return true;} });
    $check('Future expiry allowed',function():bool { ensure_not_expired(['expires_at'=>date('Y-m-d H:i:s',time()+600)]);return true; });
    $check('Legacy null expiry allowed',function():bool { ensure_not_expired(['expires_at'=>null]);return true; });
    $check('Missing upload rejected',function():bool { try { receive_private_upload('missing_test_field');return false; }catch(DomainException $e){return true;} });
    $check('CSRF token unpredictable-length',fn()=>strlen(csrf_token())===64 && ctype_xdigit(csrf_token()));
    $check('CSRF token stable in session',fn()=>csrf_token()===csrf_token());
    $hierarchy=bangladesh_location_hierarchy();
    $check('Eight divisions in offline data',fn()=>count($hierarchy)===8);
    $districts=0;$areas=0;foreach($hierarchy as $division=>$ds){$districts+=count($ds);foreach($ds as $us)$areas+=count($us);}
    $check('64 districts in offline data',fn()=>$districts===64);
    $check('Original 494 areas plus supplied urban areas retained',fn()=>$areas>=494 && valid_bangladesh_location('Dhaka','Dhaka','Dhanmondi'));
    $check('Known legacy address upgraded without guessing',fn()=>canonical_legacy_location('Dhanmondi, Dhaka')==='Dhanmondi, Dhaka, Dhaka Division');
    $check('Unknown legacy address preserved',fn()=>canonical_legacy_location('Custom road 123')==='Custom road 123');
    $division=array_key_first($hierarchy);$district=array_key_first($hierarchy[$division]);$area=$hierarchy[$division][$district][0];
    $check('Real location hierarchy accepted',fn()=>valid_bangladesh_location($division,$district,$area));
    $check('Unknown division rejected',fn()=>!valid_bangladesh_location('Fake Division',$district,$area));
    $check('Unknown district rejected',fn()=>!valid_bangladesh_location($division,'Fake District',$area));
    $check('Unknown area rejected',fn()=>!valid_bangladesh_location($division,$district,'Fake Area'));
    $check('Other division district rejected',function()use($hierarchy,$division,$district,$area):bool { foreach($hierarchy as $dv=>$ds)if($dv!==$division && !isset($ds[$district]))return !valid_bangladesh_location($dv,$district,$area);return false; });
    return $results;
}

if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    $results=bloodbridge_unit_tests();echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;
    exit(count(array_filter($results,fn($r)=>$r['status']==='FAIL'))?1:0);
}
