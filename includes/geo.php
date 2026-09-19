<?php
declare(strict_types=1);

function bangladesh_point(mixed $latitude,mixed $longitude): ?array
{
    if(($latitude===null || $latitude==='') && ($longitude===null || $longitude==='')) return null;
    if(!is_numeric($latitude)||!is_numeric($longitude)) throw new DomainException('Choose a valid map point.');
    $lat=(float)$latitude;$lng=(float)$longitude;
    if(!is_finite($lat)||!is_finite($lng)||$lat<20.5||$lat>26.7||$lng<88.0||$lng>92.7) throw new DomainException('Choose a location within the Bangladesh service area.');
    return [$lat,$lng];
}

function distance_km(float $lat1,float $lng1,float $lat2,float $lng2): float
{
    $a=sin(deg2rad($lat2-$lat1)/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lng2-$lng1)/2)**2;
    return 6371.0088*2*asin(sqrt(min(1,max(0,$a))));
}

function nearby_candidates(PDO $pdo, array $owner, int $requestId): array
{
    $request=bb_one($pdo,'SELECT * FROM blood_requests WHERE id=?',[$requestId]);
    if(!$request || (int)$request['seeker_id']!==(int)$owner['id'] || !personal_account($owner)) throw new DomainException('Only the request owner can search around this request.');
    if($request['source_type']!=='Donor' || !in_array($request['status'],['Pending','Accepted'],true)) throw new DomainException('Nearby search is available on open donor requests.');
    ensure_not_expired($request);
    if($request['prescription_status']!=='Reviewed') throw new DomainException('Nearby search unlocks after prescription review.');
    $point=bangladesh_point($request['latitude'],$request['longitude']);
    if(!$point) throw new DomainException('This request has no map point. Add a location when creating a new request.');
    [$lat,$lng]=$point;$limitLat=1.0/110.0;$limitLng=1.0/(110.0*cos(deg2rad($lat)));
    $donors=bb_all($pdo,"SELECT u.* FROM users u WHERE u.location_consent=1 AND u.location_updated_at>=? AND u.donor_enabled=1 AND u.role IN ('donor','seeker') AND u.account_status='Active' AND u.is_available=1 AND u.screening_status='Eligible' AND u.blood_group=? AND u.id<>? AND u.latitude BETWEEN ? AND ? AND u.longitude BETWEEN ? AND ? AND NOT EXISTS (SELECT 1 FROM blood_requests b WHERE b.accepted_by=u.id AND b.status='Accepted' AND b.source_type='Donor') AND NOT EXISTS (SELECT 1 FROM direct_donations d WHERE d.donor_id=u.id AND d.status IN ('Pending','Screened')) ORDER BY u.id LIMIT 501",[date('Y-m-d H:i:s',time()-30*86400),$request['blood_group'],$owner['id'],$lat-$limitLat,$lat+$limitLat,$lng-$limitLng,$lng+$limitLng]);
    $result=[];
    foreach($donors as $donor) {
        if(donor_effective_status($donor)!=='Eligible' || distance_km($lat,$lng,(float)$donor['latitude'],(float)$donor['longitude'])>1.0) continue;
        $result[]=['label'=>'Available donor #'.(int)$donor['id'],'blood_group'=>$donor['blood_group'],'latitude'=>round((float)$donor['latitude'],2),'longitude'=>round((float)$donor['longitude'],2),'area'=>'Approximate area; within 1 km of the request point'];
    }
    return ['origin'=>['latitude'=>$lat,'longitude'=>$lng],'donors'=>array_slice($result,0,100),'limited'=>count($result)>100 || count($donors)>500,'radius_km'=>1];
}

function render_map_picker(?array $point=null): void
{
    echo '<fieldset class="form-wide" data-map-picker><legend>Map location (optional)</legend><p>Use a nearby public landmark or hospital. No continuous tracking. Map tiles need internet; you can enter coordinates manually.</p><button type="button" class="button button-secondary" data-locate>Use my location</button> <button type="button" class="button button-secondary" data-map-clear>Clear pin</button><div data-map-canvas style="height:300px;min-width:0" aria-label="Select map point"></div><p data-map-message role="status"></p><div class="location-picker-grid"><label><span>Latitude</span><input name="latitude" type="number" step="any" min="20.5" max="26.7" value="'.e(isset($point[0])?(string)$point[0]:'').'"></label><label><span>Longitude</span><input name="longitude" type="number" step="any" min="88" max="92.7" value="'.e(isset($point[1])?(string)$point[1]:'').'"></label></div></fieldset>';
}
