<?php
declare(strict_types=1);

/**
 * Bangladesh Location Hierarchy
 * 8 Divisions, 64 Districts, and their respective Upazilas / Thanas.
 */
function bangladesh_hierarchy(): array
{
    return [
        'Dhaka' => [
            'Dhaka' => [
                'Dhanmondi', 'Mirpur', 'Gulshan', 'Banani', 'Uttara', 'Mohammadpur',
                'Motijheel', 'Tejgaon', 'Shahbag', 'Khilgaon', 'Badda', 'Rampura',
                'Jatrabari', 'Paltan', 'Cantonment', 'Savar', 'Dhamrai', 'Keraniganj',
                'Dohar', 'Nawabganj', 'Adabor', 'Agargaon', 'Bashundhara', 'Mohakhali',
                'Old Dhaka', 'Demra', 'Hazaribagh', 'Kafrul', 'Kamrangirchar', 'Khilkhet',
                'Kotwali', 'Lalbagh', 'New Market', 'Pallabi', 'Sutrapur', 'Turag', 'Wari'
            ],
            'Gazipur' => [
                'Gazipur Sadar', 'Kaliakair', 'Kaliganj', 'Kapasia', 'Sreepur', 'Tongi'
            ],
            'Narayanganj' => [
                'Narayanganj Sadar', 'Bandar', 'Rupganj', 'Sonargaon', 'Araihazar'
            ],
            'Tangail' => [
                'Tangail Sadar', 'Gopalpur', 'Madhupur', 'Mirzapur', 'Ghatail', 'Basail',
                'Bhuapur', 'Delduar', 'Dhanbari', 'Kalihati', 'Nagarpur', 'Sakhipur'
            ],
            'Narsingdi' => [
                'Narsingdi Sadar', 'Belabo', 'Monohardi', 'Palash', 'Raipura', 'Shibpur'
            ],
            'Manikganj' => [
                'Manikganj Sadar', 'Singair', 'Shivalaya', 'Saturia', 'Harirampur', 'Ghior', 'Daulatpur'
            ],
            'Munshiganj' => [
                'Munshiganj Sadar', 'Sreenagar', 'Sirajdikhan', 'Louhajang', 'Tongibari', 'Gazaria'
            ],
            'Faridpur' => [
                'Faridpur Sadar', 'Boalmari', 'Alfadanga', 'Madhukhali', 'Bhanga',
                'Nagarkanda', 'Charbhadrasan', 'Sadarpur', 'Saltha'
            ],
            'Gopalganj' => [
                'Gopalganj Sadar', 'Kashiani', 'Kotalipara', 'Muksudpur', 'Tungipara'
            ],
            'Madaripur' => [
                'Madaripur Sadar', 'Kalkini', 'Rajoir', 'Shibchar', 'Dasar'
            ],
            'Rajbari' => [
                'Rajbari Sadar', 'Baliakandi', 'Goalandaghat', 'Pangsha', 'Kalukhali'
            ],
            'Shariatpur' => [
                'Shariatpur Sadar', 'Damudya', 'Naria', 'Jajira', 'Bhedarganj', 'Gosairhat'
            ],
            'Kishoreganj' => [
                'Kishoreganj Sadar', 'Bhairab', 'Bajitpur', 'Hossainpur', 'Itna',
                'Karimganj', 'Katiadi', 'Kuliarchar', 'Mithamain', 'Nikli', 'Pakundia', 'Tarail'
            ],
        ],
        'Chattogram' => [
            'Chattogram' => [
                'Agrabad', 'Chandgaon', 'Panchlaish', 'Pahartali', 'Halishahar',
                'Hathazari', 'Sitakunda', 'Mirsharai', 'Patiya', 'Boalkhali', 'Anwara',
                'Raozan', 'Rangunia', 'Fatikchhari', 'Sandwip', 'Lohagara', 'Satkania',
                'Banshkhali', 'Karnaphuli', 'Bakalia', 'Bayazid', 'Double Mooring',
                'Kotwali', 'Khulshi', 'Patenga'
            ],
            'Cox\'s Bazar' => [
                'Cox\'s Bazar Sadar', 'Chakaria', 'Maheshkhali', 'Teknaf', 'Ukhiya',
                'Ramu', 'Kutubdia', 'Pekua'
            ],
            'Cumilla' => [
                'Cumilla Sadar', 'Burichang', 'Chandina', 'Daudkandi', 'Debidwar',
                'Homna', 'Laksam', 'Muradnagar', 'Chauddagram', 'Brahmanpara',
                'Barura', 'Meghna', 'Monohargonj', 'Sadar Dakshin', 'Titas', 'Lalmai'
            ],
            'Feni' => [
                'Feni Sadar', 'Chhagalnaiya', 'Daganbhuiyan', 'Parshuram', 'Sonagazi', 'Fulgazi'
            ],
            'Brahmanbaria' => [
                'Brahmanbaria Sadar', 'Akhaura', 'Ashuganj', 'Bancharampur', 'Bijoynagar',
                'Kasba', 'Nabinagar', 'Nasirnagar', 'Sarail'
            ],
            'Noakhali' => [
                'Noakhali Sadar', 'Begumganj', 'Chatkhil', 'Companiganj', 'Hatiya',
                'Senbagh', 'Sonaimuri', 'Subarnachar', 'Kabirhat'
            ],
            'Chandpur' => [
                'Chandpur Sadar', 'Faridganj', 'Haimchar', 'Hajiganj', 'Kachua',
                'Matlab Dakshin', 'Matlab Uttar', 'Shahrasti'
            ],
            'Lakshmipur' => [
                'Lakshmipur Sadar', 'Raipur', 'Ramganj', 'Ramgati', 'Kamalnagar'
            ],
            'Khagrachhari' => [
                'Khagrachhari Sadar', 'Dighinala', 'Lakshmichhari', 'Mahalchhari',
                'Manikchhari', 'Matiranga', 'Panchhari', 'Ramgarh', 'Guimara'
            ],
            'Rangamati' => [
                'Rangamati Sadar', 'Baghaichhari', 'Barkal', 'Belaichhari', 'Juraichhari',
                'Kaptai', 'Kawkhali', 'Langadu', 'Naniarchar', 'Rajasthali'
            ],
            'Bandarban' => [
                'Bandarban Sadar', 'Alikadam', 'Lama', 'Naikhongchhari', 'Rowangchhari', 'Ruma', 'Thanchi'
            ],
        ],
        'Rajshahi' => [
            'Rajshahi' => [
                'Rajshahi Sadar', 'Boalia', 'Motihar', 'Rajputra', 'Godagari', 'Tanore',
                'Mohanpur', 'Bagmara', 'Durgapur', 'Puthia', 'Charghat', 'Bagha', 'Paba'
            ],
            'Bogura' => [
                'Bogura Sadar', 'Adamdighi', 'Dhunat', 'Dhupchanchia', 'Gabtali',
                'Kahaloo', 'Nandigram', 'Sariakandi', 'Shajahanpur', 'Sherpur', 'Shibganj', 'Sonatola'
            ],
            'Pabna' => [
                'Pabna Sadar', 'Atgharia', 'Bera', 'Bhangura', 'Chatmohar',
                'Faridpur', 'Ishwardi', 'Santhia', 'Sujanagar'
            ],
            'Sirajganj' => [
                'Sirajganj Sadar', 'Belkuchi', 'Chauhali', 'Kamarkhanda', 'Kazipur',
                'Raiganj', 'Shahjadpur', 'Tarash', 'Ullahpara'
            ],
            'Naogaon' => [
                'Naogaon Sadar', 'Atrai', 'Badalgachhi', 'Dhamoirhat', 'Manda',
                'Mohadevpur', 'Niamatpur', 'Patnitala', 'Porsha', 'Raninagar', 'Sapahar'
            ],
            'Natore' => [
                'Natore Sadar', 'Bagatipara', 'Baraigram', 'Gurudaspur', 'Lalpur', 'Singra', 'Naldanga'
            ],
            'Chapainawabganj' => [
                'Chapainawabganj Sadar', 'Bholahat', 'Gomastapur', 'Nachole', 'Shibganj'
            ],
            'Joypurhat' => [
                'Joypurhat Sadar', 'Akkelpur', 'Kalai', 'Khetlal', 'Panchbibi'
            ],
        ],
        'Khulna' => [
            'Khulna' => [
                'Khulna Sadar', 'Daulatpur', 'Khalishpur', 'Khan Jahan Ali', 'Sonadanga',
                'Batiaghata', 'Dacope', 'Dumuria', 'Dighalia', 'Koyra', 'Paikgachha',
                'Phultala', 'Rupsha', 'Terokhada'
            ],
            'Jashore' => [
                'Jashore Sadar', 'Abhaynagar', 'Bagherpara', 'Chaugachha',
                'Jhikargachha', 'Keshabpur', 'Manirampur', 'Sharsha'
            ],
            'Satkhira' => [
                'Satkhira Sadar', 'Assasuni', 'Debhata', 'Kalaroa', 'Kaliganj', 'Shyamnagar', 'Tala'
            ],
            'Kushtia' => [
                'Kushtia Sadar', 'Bheramara', 'Daulatpur', 'Khoksa', 'Kumarkhali', 'Mirpur'
            ],
            'Jhenaidah' => [
                'Jhenaidah Sadar', 'Harinakundu', 'Kaliganj', 'Kotchandpur', 'Maheshpur', 'Shailkupa'
            ],
            'Chuadanga' => [
                'Chuadanga Sadar', 'Alamdanga', 'Damurhuda', 'Jibannagar'
            ],
            'Meherpur' => [
                'Meherpur Sadar', 'Gangni', 'Mujibnagar'
            ],
            'Narail' => [
                'Narail Sadar', 'Kalia', 'Lohagara'
            ],
            'Magura' => [
                'Magura Sadar', 'Mohammadpur', 'Shalikha', 'Sreepur'
            ],
            'Bagerhat' => [
                'Bagerhat Sadar', 'Chitalmari', 'Fakirhat', 'Kachua', 'Mollahat',
                'Mongla', 'Morrelganj', 'Rampal', 'Sarankhola'
            ],
        ],
        'Barishal' => [
            'Barishal' => [
                'Barishal Sadar', 'Agailjhara', 'Babuganj', 'Bakerganj', 'Banaripara',
                'Gaurnadi', 'Hizla', 'Mehendiganj', 'Muladi', 'Wazirpur'
            ],
            'Patuakhali' => [
                'Patuakhali Sadar', 'Bauphal', 'Dashmina', 'Dumki', 'Galachipa',
                'Kalapara', 'Mirzaganj', 'Rangabali'
            ],
            'Bhola' => [
                'Bhola Sadar', 'Burhanuddin', 'Char Fasson', 'Daulatkhan',
                'Lalmohan', 'Manpura', 'Tazumuddin'
            ],
            'Pirojpur' => [
                'Pirojpur Sadar', 'Bhandaria', 'Kawkhali', 'Mathbaria',
                'Nazirpur', 'Nesarabad (Swarupkati)', 'Zianagar (Indurkani)'
            ],
            'Barguna' => [
                'Barguna Sadar', 'Amtali', 'Bamna', 'Betagi', 'Patharghata', 'Taltali'
            ],
            'Jhalokathi' => [
                'Jhalokathi Sadar', 'Kathalia', 'Nalchity', 'Rajapur'
            ],
        ],
        'Sylhet' => [
            'Sylhet' => [
                'Sylhet Sadar', 'Beanibazar', 'Bishwanath', 'Companiganj', 'Fenchuganj',
                'Golapganj', 'Gowainghat', 'Jaintiapur', 'Kanaighat', 'Osmani Nagar',
                'Zakiganj', 'Dakshin Surma'
            ],
            'Moulvibazar' => [
                'Moulvibazar Sadar', 'Barlekha', 'Juri', 'Kamalganj', 'Kulaura', 'Rajnagar', 'Sreemangal'
            ],
            'Habiganj' => [
                'Habiganj Sadar', 'Ajmiriganj', 'Bahubal', 'Baniyachong', 'Chunarughat',
                'Lakhai', 'Madhabpur', 'Nabiganj', 'Sayestaganj'
            ],
            'Sunamganj' => [
                'Sunamganj Sadar', 'Bishwamvarpur', 'Chhatak', 'Derai', 'Dharamapasha',
                'Dowarabazar', 'Jagannathpur', 'Jamalganj', 'Sullah', 'Tahirpur', 'Shantiganj'
            ],
        ],
        'Rangpur' => [
            'Rangpur' => [
                'Rangpur Sadar', 'Badarganj', 'Gangachhara', 'Kaunia', 'Mithapukur',
                'Pirgachha', 'Pirganj', 'Taraganj'
            ],
            'Dinajpur' => [
                'Dinajpur Sadar', 'Birampur', 'Birganj', 'Birol', 'Bochaganj',
                'Chirirbandar', 'Phulbari', 'Ghoraghat', 'Hakimpur', 'Kaharole',
                'Khansama', 'Nawabganj', 'Parbatipur'
            ],
            'Kurigram' => [
                'Kurigram Sadar', 'Bhurungamari', 'Char Rajibpur', 'Chilmari',
                'Phulbari', 'Nageshwari', 'Rajarhat', 'Rourmari', 'Ulipur'
            ],
            'Gaibandha' => [
                'Gaibandha Sadar', 'Fulchhari', 'Gobindaganj', 'Palashbari',
                'Sadullapur', 'Sughatta', 'Sundarganj'
            ],
            'Nilphamari' => [
                'Nilphamari Sadar', 'Dimla', 'Domar', 'Jaldhaka', 'Kishoreganj', 'Saidpur'
            ],
            'Lalmonirhat' => [
                'Lalmonirhat Sadar', 'Aditmari', 'Hatibandha', 'Kaliganj', 'Patgram'
            ],
            'Thakurgaon' => [
                'Thakurgaon Sadar', 'Baliadangi', 'Haripur', 'Pirganj', 'Ranisankhail'
            ],
            'Panchagarh' => [
                'Panchagarh Sadar', 'Atwari', 'Boda', 'Debiganj', 'Tetulia'
            ],
        ],
        'Mymensingh' => [
            'Mymensingh' => [
                'Mymensingh Sadar', 'Bhaluka', 'Dhobaura', 'Fulbaria', 'Gafargaon',
                'Gauripur', 'Haluaghat', 'Ishwarganj', 'Muktagachha', 'Nandail',
                'Phulpur', 'Trishal', 'Tara Khanda'
            ],
            'Jamalpur' => [
                'Jamalpur Sadar', 'Bakshiganj', 'Dewanganj', 'Islampur',
                'Madarganj', 'Melandaha', 'Sarishabari'
            ],
            'Netrokona' => [
                'Netrokona Sadar', 'Atpara', 'Barhatta', 'Durgapur', 'Kalmakanda',
                'Kendua', 'Madan', 'Mohanganj', 'Purbadhala', 'Khaliajuri'
            ],
            'Sherpur' => [
                'Sherpur Sadar', 'Jhenaigati', 'Nakla', 'Nalitabari', 'Sreebardi'
            ],
        ],
    ];
}

/**
 * Returns a flat list of all divisions.
 */
function bangladesh_divisions(): array
{
    return array_keys(bangladesh_hierarchy());
}

/**
 * Returns districts for a given division.
 */
function bangladesh_districts_by_division(string $division): array
{
    $data = bangladesh_hierarchy();
    return isset($data[$division]) ? array_keys($data[$division]) : [];
}

/**
 * Returns upazilas for a given division and district.
 */
function bangladesh_upazilas_by_district(string $division, string $district): array
{
    $data = bangladesh_hierarchy();
    return $data[$division][$district] ?? [];
}

/**
 * Validates whether the given division, district, and upazila exist in the hierarchy.
 */
function validate_location_hierarchy(string $division, string $district, string $upazila): bool
{
    $data = bangladesh_hierarchy();
    if (!isset($data[$division])) return false;
    if (!isset($data[$division][$district])) return false;
    return in_array($upazila, $data[$division][$district], true);
}

/**
 * Formats the selected division, district, and upazila into the standard location string:
 * "Upazila, District, Division"
 */
function format_location_string(string $upazila, string $district, string $division): string
{
    return trim($upazila) . ', ' . trim($district) . ', ' . trim($division);
}

/**
 * Parses a saved location string into division, district, and upazila components.
 * Handles both new ("Upazila, District, Division") and legacy ("Dhanmondi, Dhaka", "Dhaka") formats.
 */
function parse_location_string(?string $locationStr): array
{
    $result = [
        'division' => '',
        'district' => '',
        'upazila' => '',
    ];

    if (!$locationStr) {
        return $result;
    }

    $data = bangladesh_hierarchy();
    $parts = array_values(array_filter(array_map('trim', explode(',', $locationStr))));

    if (count($parts) >= 3) {
        // Standard format: [0 => Upazila, 1 => District, 2 => Division]
        $upazila = $parts[0];
        $district = $parts[1];
        $division = $parts[2];

        if (validate_location_hierarchy($division, $district, $upazila)) {
            return [
                'division' => $division,
                'district' => $district,
                'upazila' => $upazila,
            ];
        }
    }

    // Attempt intelligent fuzzy/fallback matching across hierarchy
    $joined = strtolower($locationStr);
    foreach ($data as $divName => $districts) {
        foreach ($districts as $distName => $upazilas) {
            foreach ($upazilas as $upName) {
                // Check if upazila is mentioned in the location string
                if (stripos($joined, strtolower($upName)) !== false) {
                    return [
                        'division' => $divName,
                        'district' => $distName,
                        'upazila' => $upName,
                    ];
                }
            }
            // Check if district is mentioned
            if (stripos($joined, strtolower($distName)) !== false) {
                return [
                    'division' => $divName,
                    'district' => $distName,
                    'upazila' => $districts[$distName][0] ?? '',
                ];
            }
        }
        // Check if division is mentioned
        if (stripos($joined, strtolower($divName)) !== false) {
            $firstDist = array_key_first($districts);
            return [
                'division' => $divName,
                'district' => $firstDist ?? '',
                'upazila' => $districts[$firstDist][0] ?? '',
            ];
        }
    }

    return $result;
}

/**
 * Renders the 3 dependent location dropdowns (Division, District, Upazila).
 */
function render_location_dropdowns(
    string $prefix = '',
    ?string $currentLocation = null,
    bool $required = true,
    ?array $overrideValues = null
): void {
    $divField = $prefix ? $prefix . '_division' : 'division';
    $distField = $prefix ? $prefix . '_district' : 'district';
    $upzField = $prefix ? $prefix . '_upazila' : 'upazila';

    $parsed = parse_location_string($currentLocation);
    $selectedDiv = $overrideValues['division'] ?? $parsed['division'];
    $selectedDist = $overrideValues['district'] ?? $parsed['district'];
    $selectedUpz = $overrideValues['upazila'] ?? $parsed['upazila'];

    $data = bangladesh_hierarchy();
    $divisions = array_keys($data);
    $districts = $selectedDiv && isset($data[$selectedDiv]) ? array_keys($data[$selectedDiv]) : [];
    $upazilas = $selectedDiv && $selectedDist && isset($data[$selectedDiv][$selectedDist])
        ? $data[$selectedDiv][$selectedDist]
        : [];

    $reqAttr = $required ? 'required' : '';
    ?>
    <div class="location-picker-group form-wide" data-location-picker>
        <label>
            <span>Division</span>
            <select name="<?= e($divField) ?>" data-location-division <?= $reqAttr ?>>
                <option value="">Select Division</option>
                <?php foreach ($divisions as $div): ?>
                    <option value="<?= e($div) ?>" <?= $selectedDiv === $div ? 'selected' : '' ?>><?= e($div) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>District</span>
            <select name="<?= e($distField) ?>" data-location-district <?= $reqAttr ?> <?= empty($districts) ? 'disabled' : '' ?>>
                <option value="">Select District</option>
                <?php foreach ($districts as $dst): ?>
                    <option value="<?= e($dst) ?>" <?= $selectedDist === $dst ? 'selected' : '' ?>><?= e($dst) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Upazila / Area</span>
            <select name="<?= e($upzField) ?>" data-location-upazila <?= $reqAttr ?> <?= empty($upazilas) ? 'disabled' : '' ?>>
                <option value="">Select Upazila</option>
                <?php foreach ($upazilas as $upz): ?>
                    <option value="<?= e($upz) ?>" <?= $selectedUpz === $upz ? 'selected' : '' ?>><?= e($upz) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <?php
}

/**
 * Backward compatibility fallback datalist function.
 */
function render_location_datalist(string $id = 'bangladesh-locations'): void
{
    // Deprecated in favor of dependent selects, retained for safety
}
