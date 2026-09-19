<?php
declare(strict_types=1);
require_once __DIR__.'/locations.php';

function address_input(array $data, bool $optional=false): string
{
    $division=trim((string)($data['division']??''));
    $district=trim((string)($data['district']??''));
    $area=trim((string)($data['upazila']??''));
    $hierarchy=bangladesh_location_hierarchy();
    if($optional && $division==='' && $district==='' && $area==='') return '';
    if(!isset($hierarchy[$division]) || ($district!=='' && !isset($hierarchy[$division][$district])) || ($area!=='' && !valid_bangladesh_location($division,$district,$area))) throw new DomainException('Choose a valid Division, District and Upazila / Area.');
    if(!$optional && ($district==='' || $area==='')) throw new DomainException('Select Division, District and Upazila / Area.');
    return $area!==''?format_bangladesh_location($division,$district,$area):($district!==''?$district.', '.$division.' Division':$division.' Division');
}

function render_address_picker(string $saved='', bool $optional=false): void
{
    $parts=['division'=>'','district'=>'','upazila'=>''];
    foreach(bangladesh_location_hierarchy() as $division=>$districts) foreach($districts as $district=>$areas) foreach($areas as $area) {
        if(format_bangladesh_location($division,$district,$area)===$saved) {$parts=['division'=>$division,'district'=>$district,'upazila'=>$area];break 3;}
    }
    foreach($parts as $key=>$_) if(isset($_POST[$key]) || isset($_GET[$key])) $parts[$key]=(string)($_POST[$key]??$_GET[$key]);
    echo '<fieldset class="form-wide location-fieldset" data-address-picker><legend>Location</legend><div class="location-picker-grid">';
    foreach(['division'=>'Division','district'=>'District / Zila','upazila'=>'Upazila / Area'] as $key=>$label) {
        echo '<label><span>'.e($label).'</span><select name="'.$key.'" data-address-'.$key.' data-selected="'.e($parts[$key]).'"'.($optional?'':' required').'><option value="">'.($optional?'All':'Select').' '.e($label).'</option>';
        $options=$key==='division'?array_keys(bangladesh_location_hierarchy()):($key==='district'?array_keys(bangladesh_location_hierarchy()[$parts['division']]??[]):(bangladesh_location_hierarchy()[$parts['division']][$parts['district']]??[]));
        foreach($options as $option) echo '<option value="'.e($option).'"'.($option===$parts[$key]?' selected':'').'>'.e($option).'</option>';
        echo '</select></label>';
    }
    echo '</div><script type="application/json" data-address-data>'.json_encode(bangladesh_location_hierarchy(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script></fieldset>';
}
