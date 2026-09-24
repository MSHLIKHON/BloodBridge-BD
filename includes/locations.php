<?php
/** File purpose: Locations provides shared application logic and presentation helpers. */
declare(strict_types=1);

/**
 * Common Bangladesh locations used by the searchable location fields.
 * Users may still type a more specific address that is not in this list.
 */
function bangladesh_locations(): array
{
    return [
        'Dhaka Division', 'Chattogram Division', 'Rajshahi Division', 'Khulna Division',
        'Barishal Division', 'Sylhet Division', 'Rangpur Division', 'Mymensingh Division',
        'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria',
        'Chandpur', 'Chapainawabganj', 'Chattogram', 'Chuadanga', "Cox's Bazar", 'Cumilla',
        'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha', 'Gazipur', 'Gopalganj',
        'Habiganj', 'Jamalpur', 'Jashore', 'Jhalokathi', 'Jhenaidah', 'Joypurhat',
        'Khagrachhari', 'Khulna', 'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur',
        'Lalmonirhat', 'Madaripur', 'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar',
        'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj', 'Narsingdi',
        'Natore', 'Netrokona', 'Nilphamari', 'Noakhali', 'Pabna', 'Panchagarh',
        'Patuakhali', 'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati', 'Rangpur',
        'Satkhira', 'Shariatpur', 'Sherpur', 'Sirajganj', 'Sunamganj', 'Sylhet',
        'Tangail', 'Thakurgaon',
        'Adabor, Dhaka', 'Agargaon, Dhaka', 'Badda, Dhaka', 'Banani, Dhaka',
        'Bashundhara, Dhaka', 'Cantonment, Dhaka', 'Dhanmondi, Dhaka', 'Farmgate, Dhaka',
        'Gulshan, Dhaka', 'Jatrabari, Dhaka', 'Khilgaon, Dhaka', 'Mirpur, Dhaka',
        'Mohakhali, Dhaka', 'Mohammadpur, Dhaka', 'Motijheel, Dhaka', 'Old Dhaka',
        'Paltan, Dhaka', 'Rampura, Dhaka', 'Shahbag, Dhaka', 'Tejgaon, Dhaka',
        'Uttara, Dhaka', 'Dhamrai, Dhaka', 'Dohar, Dhaka', 'Keraniganj, Dhaka',
        'Nawabganj, Dhaka', 'Savar, Dhaka',
        'Agrabad, Chattogram', 'Anwara, Chattogram', 'Boalkhali, Chattogram',
        'Chandgaon, Chattogram', 'Halishahar, Chattogram', 'Hathazari, Chattogram',
        'Pahartali, Chattogram', 'Panchlaish, Chattogram', 'Patenga, Chattogram',
        'Sitakunda, Chattogram', 'Akhaura, Brahmanbaria', 'Ashuganj, Brahmanbaria',
        'Bhairab, Kishoreganj', 'Bhaluka, Mymensingh', 'Birampur, Dinajpur',
        'Burichang, Cumilla', 'Chandina, Cumilla', 'Chatmohar, Pabna',
        'Chowmuhani, Noakhali', 'Daudkandi, Cumilla', 'Fatikchhari, Chattogram',
        'Gafargaon, Mymensingh', 'Gauripur, Mymensingh', 'Godagari, Rajshahi',
        'Gopalpur, Tangail', 'Ishwardi, Pabna', 'Kaliakair, Gazipur',
        'Kaliganj, Gazipur', 'Kapasia, Gazipur', 'Kulaura, Moulvibazar',
        'Madhabpur, Habiganj', 'Madhupur, Tangail', 'Mawna, Gazipur',
        'Mongla, Bagerhat', 'Nabinagar, Brahmanbaria', 'Narsingdi Sadar, Narsingdi',
        'Parbatipur, Dinajpur', 'Patiya, Chattogram', 'Raozan, Chattogram',
        'Rupganj, Narayanganj', 'Saidpur, Nilphamari', 'Sreemangal, Moulvibazar',
        'Tongi, Gazipur', 'Trishal, Mymensingh'
    ];
}

/**
 * Division → District → Upazila hierarchy used by the registration form.
 * Kept in PHP so the form works offline in XAMPP.
 */
function bangladesh_location_hierarchy(): array
{
    static $hierarchy = null;

    if ($hierarchy === null) {
        $hierarchy = json_decode(<<<'JSON'
{
  "Chattogram": {
    "Cumilla": [
      "Debidwar",
      "Barura",
      "Brahmanpara",
      "Chandina",
      "Chauddagram",
      "Daudkandi",
      "Homna",
      "Laksam",
      "Muradnagar",
      "Nangalkot",
      "Comilla Sadar",
      "Meghna",
      "Monohargonj",
      "Sadarsouth",
      "Titas",
      "Burichang",
      "Lalmai"
    ],
    "Feni": [
      "Chhagalnaiya",
      "Feni Sadar",
      "Sonagazi",
      "Fulgazi",
      "Parshuram",
      "Daganbhuiyan"
    ],
    "Brahmanbaria": [
      "Brahmanbaria Sadar",
      "Kasba",
      "Nasirnagar",
      "Sarail",
      "Ashuganj",
      "Akhaura",
      "Nabinagar",
      "Bancharampur",
      "Bijoynagar"
    ],
    "Rangamati": [
      "Rangamati Sadar",
      "Kaptai",
      "Kawkhali",
      "Baghaichari",
      "Barkal",
      "Langadu",
      "Rajasthali",
      "Belaichari",
      "Juraichari",
      "Naniarchar"
    ],
    "Noakhali": [
      "Noakhali Sadar",
      "Companiganj",
      "Begumganj",
      "Hatia",
      "Subarnachar",
      "Kabirhat",
      "Senbug",
      "Chatkhil",
      "Sonaimori"
    ],
    "Chandpur": [
      "Haimchar",
      "Kachua",
      "Shahrasti",
      "Chandpur Sadar",
      "Matlab South",
      "Hajiganj",
      "Matlab North",
      "Faridgonj"
    ],
    "Lakshmipur": [
      "Lakshmipur Sadar",
      "Kamalnagar",
      "Raipur",
      "Ramgati",
      "Ramganj"
    ],
    "Chattogram": [
      "Rangunia",
      "Sitakunda",
      "Mirsharai",
      "Patiya",
      "Sandwip",
      "Banshkhali",
      "Boalkhali",
      "Anwara",
      "Chandanaish",
      "Satkania",
      "Lohagara",
      "Hathazari",
      "Fatikchhari",
      "Raozan",
      "Karnafuli"
    ],
    "Coxs Bazar": [
      "Coxsbazar Sadar",
      "Chakaria",
      "Kutubdia",
      "Ukhiya",
      "Moheshkhali",
      "Pekua",
      "Ramu",
      "Teknaf",
      "Eidgaon"
    ],
    "Khagrachhari": [
      "Khagrachhari Sadar",
      "Dighinala",
      "Panchari",
      "Laxmichhari",
      "Mohalchari",
      "Manikchari",
      "Ramgarh",
      "Matiranga",
      "Guimara"
    ],
    "Bandarban": [
      "Bandarban Sadar",
      "Alikadam",
      "Naikhongchhari",
      "Rowangchhari",
      "Lama",
      "Ruma",
      "Thanchi"
    ]
  },
  "Rajshahi": {
    "Sirajganj": [
      "Belkuchi",
      "Chauhali",
      "Kamarkhand",
      "Kazipur",
      "Raigonj",
      "Shahjadpur",
      "Sirajganj Sadar",
      "Tarash",
      "Ullapara"
    ],
    "Pabna": [
      "Sujanagar",
      "Ishurdi",
      "Bhangura",
      "Pabna Sadar",
      "Bera",
      "Atghoria",
      "Chatmohar",
      "Santhia",
      "Faridpur"
    ],
    "Bogura": [
      "Kahaloo",
      "Bogra Sadar",
      "Shariakandi",
      "Shajahanpur",
      "Dupchanchia",
      "Adamdighi",
      "Nondigram",
      "Sonatala",
      "Dhunot",
      "Gabtali",
      "Sherpur",
      "Shibganj"
    ],
    "Rajshahi": [
      "Paba",
      "Durgapur",
      "Mohonpur",
      "Charghat",
      "Puthia",
      "Bagha",
      "Godagari",
      "Tanore",
      "Bagmara"
    ],
    "Natore": [
      "Natore Sadar",
      "Singra",
      "Baraigram",
      "Bagatipara",
      "Lalpur",
      "Gurudaspur",
      "Naldanga"
    ],
    "Joypurhat": [
      "Akkelpur",
      "Kalai",
      "Khetlal",
      "Panchbibi",
      "Joypurhat Sadar"
    ],
    "Chapainawabganj": [
      "Chapainawabganj Sadar",
      "Gomostapur",
      "Nachol",
      "Bholahat",
      "Shibganj"
    ],
    "Naogaon": [
      "Mohadevpur",
      "Badalgachi",
      "Patnitala",
      "Dhamoirhat",
      "Niamatpur",
      "Manda",
      "Atrai",
      "Raninagar",
      "Naogaon Sadar",
      "Porsha",
      "Sapahar"
    ]
  },
  "Khulna": {
    "Jashore": [
      "Manirampur",
      "Abhaynagar",
      "Bagherpara",
      "Chougachha",
      "Jhikargacha",
      "Keshabpur",
      "Jessore Sadar",
      "Sharsha"
    ],
    "Satkhira": [
      "Assasuni",
      "Debhata",
      "Kalaroa",
      "Satkhira Sadar",
      "Shyamnagar",
      "Tala",
      "Kaliganj"
    ],
    "Meherpur": [
      "Mujibnagar",
      "Meherpur Sadar",
      "Gangni"
    ],
    "Narail": [
      "Narail Sadar",
      "Lohagara",
      "Kalia"
    ],
    "Chuadanga": [
      "Chuadanga Sadar",
      "Alamdanga",
      "Damurhuda",
      "Jibannagar"
    ],
    "Kushtia": [
      "Kushtia Sadar",
      "Kumarkhali",
      "Khoksa",
      "Mirpur",
      "Daulatpur",
      "Bheramara"
    ],
    "Magura": [
      "Shalikha",
      "Sreepur",
      "Magura Sadar",
      "Mohammadpur"
    ],
    "Khulna": [
      "Paikgasa",
      "Fultola",
      "Digholia",
      "Rupsha",
      "Terokhada",
      "Dumuria",
      "Botiaghata",
      "Dakop",
      "Koyra"
    ],
    "Bagerhat": [
      "Fakirhat",
      "Bagerhat Sadar",
      "Mollahat",
      "Sarankhola",
      "Rampal",
      "Morrelganj",
      "Kachua",
      "Mongla",
      "Chitalmari"
    ],
    "Jhenaidah": [
      "Jhenaidah Sadar",
      "Shailkupa",
      "Harinakundu",
      "Kaliganj",
      "Kotchandpur",
      "Moheshpur"
    ]
  },
  "Barishal": {
    "Jhalokathi": [
      "Jhalakathi Sadar",
      "Kathalia",
      "Nalchity",
      "Rajapur"
    ],
    "Patuakhali": [
      "Bauphal",
      "Patuakhali Sadar",
      "Dumki",
      "Dashmina",
      "Kalapara",
      "Mirzaganj",
      "Galachipa",
      "Rangabali"
    ],
    "Pirojpur": [
      "Pirojpur Sadar",
      "Nazirpur",
      "Kawkhali",
      "Zianagar",
      "Bhandaria",
      "Mathbaria",
      "Nesarabad"
    ],
    "Barishal": [
      "Barisal Sadar",
      "Bakerganj",
      "Babuganj",
      "Wazirpur",
      "Banaripara",
      "Gournadi",
      "Agailjhara",
      "Mehendiganj",
      "Muladi",
      "Hizla"
    ],
    "Bhola": [
      "Bhola Sadar",
      "Borhan Sddin",
      "Charfesson",
      "Doulatkhan",
      "Monpura",
      "Tazumuddin",
      "Lalmohan"
    ],
    "Barguna": [
      "Amtali",
      "Barguna Sadar",
      "Betagi",
      "Bamna",
      "Pathorghata",
      "Taltali"
    ]
  },
  "Sylhet": {
    "Sylhet": [
      "Balaganj",
      "Beanibazar",
      "Bishwanath",
      "Companiganj",
      "Fenchuganj",
      "Golapganj",
      "Gowainghat",
      "Jaintiapur",
      "Kanaighat",
      "Sylhet Sadar",
      "Zakiganj",
      "Dakshinsurma",
      "Osmaninagar"
    ],
    "Moulvibazar": [
      "Barlekha",
      "Kamolganj",
      "Kulaura",
      "Moulvibazar Sadar",
      "Rajnagar",
      "Sreemangal",
      "Juri"
    ],
    "Habiganj": [
      "Nabiganj",
      "Bahubal",
      "Ajmiriganj",
      "Baniachong",
      "Lakhai",
      "Chunarughat",
      "Habiganj Sadar",
      "Madhabpur"
    ],
    "Sunamganj": [
      "Sunamganj Sadar",
      "South Sunamganj",
      "Bishwambarpur",
      "Chhatak",
      "Jagannathpur",
      "Dowarabazar",
      "Tahirpur",
      "Dharmapasha",
      "Jamalganj",
      "Shalla",
      "Derai",
      "Madhyanagar"
    ]
  },
  "Dhaka": {
    "Narsingdi": [
      "Belabo",
      "Monohardi",
      "Narsingdi Sadar",
      "Palash",
      "Raipura",
      "Shibpur"
    ],
    "Gazipur": [
      "Kaliganj",
      "Kaliakair",
      "Kapasia",
      "Gazipur Sadar",
      "Sreepur"
    ],
    "Shariatpur": [
      "Shariatpur Sadar",
      "Naria",
      "Zajira",
      "Gosairhat",
      "Bhedarganj",
      "Damudya"
    ],
    "Narayanganj": [
      "Araihazar",
      "Bandar",
      "Narayanganj Sadar",
      "Rupganj",
      "Sonargaon"
    ],
    "Tangail": [
      "Basail",
      "Bhuapur",
      "Delduar",
      "Ghatail",
      "Gopalpur",
      "Madhupur",
      "Mirzapur",
      "Nagarpur",
      "Sakhipur",
      "Tangail Sadar",
      "Kalihati",
      "Dhanbari"
    ],
    "Kishoreganj": [
      "Itna",
      "Katiadi",
      "Bhairab",
      "Tarail",
      "Hossainpur",
      "Pakundia",
      "Kuliarchar",
      "Kishoreganj Sadar",
      "Karimgonj",
      "Bajitpur",
      "Austagram",
      "Mithamoin",
      "Nikli"
    ],
    "Manikganj": [
      "Harirampur",
      "Saturia",
      "Manikganj Sadar",
      "Gior",
      "Shibaloy",
      "Doulatpur",
      "Singiar"
    ],
    "Dhaka": [
      "Savar",
      "Dhamrai",
      "Keraniganj",
      "Nawabganj",
      "Dohar"
    ],
    "Munshiganj": [
      "Munshiganj Sadar",
      "Sreenagar",
      "Sirajdikhan",
      "Louhajanj",
      "Gajaria",
      "Tongibari"
    ],
    "Rajbari": [
      "Rajbari Sadar",
      "Goalanda",
      "Pangsa",
      "Baliakandi",
      "Kalukhali"
    ],
    "Madaripur": [
      "Madaripur Sadar",
      "Shibchar",
      "Kalkini",
      "Rajoir",
      "Dasar"
    ],
    "Gopalganj": [
      "Gopalganj Sadar",
      "Kashiani",
      "Tungipara",
      "Kotalipara",
      "Muksudpur"
    ],
    "Faridpur": [
      "Faridpur Sadar",
      "Alfadanga",
      "Boalmari",
      "Sadarpur",
      "Nagarkanda",
      "Bhanga",
      "Charbhadrasan",
      "Madhukhali",
      "Saltha"
    ]
  },
  "Rangpur": {
    "Panchagarh": [
      "Panchagarh Sadar",
      "Debiganj",
      "Boda",
      "Atwari",
      "Tetulia"
    ],
    "Dinajpur": [
      "Nawabganj",
      "Birganj",
      "Ghoraghat",
      "Birampur",
      "Parbatipur",
      "Bochaganj",
      "Kaharol",
      "Fulbari",
      "Dinajpur Sadar",
      "Hakimpur",
      "Khansama",
      "Birol",
      "Chirirbandar"
    ],
    "Lalmonirhat": [
      "Lalmonirhat Sadar",
      "Kaliganj",
      "Hatibandha",
      "Patgram",
      "Aditmari"
    ],
    "Nilphamari": [
      "Syedpur",
      "Domar",
      "Dimla",
      "Jaldhaka",
      "Kishorganj",
      "Nilphamari Sadar"
    ],
    "Gaibandha": [
      "Sadullapur",
      "Gaibandha Sadar",
      "Palashbari",
      "Saghata",
      "Gobindaganj",
      "Sundarganj",
      "Phulchari"
    ],
    "Thakurgaon": [
      "Thakurgaon Sadar",
      "Pirganj",
      "Ranisankail",
      "Haripur",
      "Baliadangi"
    ],
    "Rangpur": [
      "Rangpur Sadar",
      "Gangachara",
      "Taragonj",
      "Badargonj",
      "Mithapukur",
      "Pirgonj",
      "Kaunia",
      "Pirgacha"
    ],
    "Kurigram": [
      "Kurigram Sadar",
      "Nageshwari",
      "Bhurungamari",
      "Phulbari",
      "Rajarhat",
      "Ulipur",
      "Chilmari",
      "Rowmari",
      "Charrajibpur"
    ]
  },
  "Mymensingh": {
    "Sherpur": [
      "Sherpur Sadar",
      "Nalitabari",
      "Sreebordi",
      "Nokla",
      "Jhenaigati"
    ],
    "Mymensingh": [
      "Fulbaria",
      "Trishal",
      "Bhaluka",
      "Muktagacha",
      "Mymensingh Sadar",
      "Dhobaura",
      "Phulpur",
      "Haluaghat",
      "Gouripur",
      "Gafargaon",
      "Iswarganj",
      "Nandail",
      "Tarakanda"
    ],
    "Jamalpur": [
      "Jamalpur Sadar",
      "Melandah",
      "Islampur",
      "Dewangonj",
      "Sarishabari",
      "Madarganj",
      "Bokshiganj"
    ],
    "Netrokona": [
      "Barhatta",
      "Durgapur",
      "Kendua",
      "Atpara",
      "Madan",
      "Khaliajuri",
      "Kalmakanda",
      "Mohongonj",
      "Purbadhala",
      "Netrokona Sadar"
    ]
  }
}
JSON, true, 512, JSON_THROW_ON_ERROR);
        foreach(bangladesh_locations() as $legacy) {
            $parts=explode(', ',$legacy);
            if(count($parts)!==2) continue;
            foreach($hierarchy as &$districts) {
                if(isset($districts[$parts[1]]) && !in_array($parts[0],$districts[$parts[1]],true)) $districts[$parts[1]][]=$parts[0];
            }
            unset($districts);
        }
    }

    return $hierarchy;
}

function valid_bangladesh_location(string $division, string $district, string $upazila): bool
{
    $hierarchy = bangladesh_location_hierarchy();

    return isset($hierarchy[$division][$district])
        && in_array($upazila, $hierarchy[$division][$district], true);
}

function format_bangladesh_location(string $division, string $district, string $upazila): string
{
    return $upazila . ', ' . $district . ', ' . $division . ' Division';
}

function render_location_datalist(string $id = 'bangladesh-locations'): void
{
    echo '<datalist id="' . e($id) . '">';
    foreach (bangladesh_locations() as $location) {
        echo '<option value="' . e($location) . '"></option>';
    }
    echo '</datalist>';
}

function canonical_legacy_location(string $location): string
{
    static $aliases=null;
    if($aliases===null) {
        $aliases=[];
        foreach(bangladesh_location_hierarchy() as $division=>$districts) foreach($districts as $district=>$areas) {
            $aliases[$district]=$district.', '.$division.' Division';
            foreach($areas as $area) $aliases[$area.', '.$district]=format_bangladesh_location($division,$district,$area);
        }
    }
    return $aliases[trim($location)]??$location;
}

function normalize_saved_addresses(PDO $pdo): void
{
    foreach(['users','hospitals','blood_requests','clubs','campaigns'] as $table) {
        foreach(bb_all($pdo,"SELECT DISTINCT location FROM $table WHERE location IS NOT NULL") as $row) {
            $canonical=canonical_legacy_location($row['location']);
            if($canonical!==$row['location']) bb_exec($pdo,"UPDATE $table SET location=? WHERE location=?",[$canonical,$row['location']]);
        }
    }
}
