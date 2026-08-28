document.querySelector('[data-nav-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-nav]')?.classList.toggle('open');
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.dataset.confirm || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

const registrationForm = document.querySelector('[data-registration-form]');
if (registrationForm) {
    const roleSelect = registrationForm.querySelector('[data-role-select]');
    const bloodGroupField = registrationForm.querySelector('[data-blood-group-field]');
    const bloodGroupSelect = bloodGroupField?.querySelector('select');
    const syncBloodGroup = () => {
        const isDonor = roleSelect?.value === 'donor';
        if (bloodGroupField) bloodGroupField.hidden = !isDonor;
        if (bloodGroupSelect) bloodGroupSelect.required = isDonor;
    };
    roleSelect?.addEventListener('change', syncBloodGroup);
    syncBloodGroup();
}

const healthForm = document.querySelector('[data-health-form]');
if (healthForm) {
    const conditionSelect = healthForm.querySelector('[data-condition-select]');
    const conditionDetails = healthForm.querySelector('[data-condition-details]');
    const conditionTextarea = conditionDetails?.querySelector('textarea');
    const syncConditionDetails = () => {
        const hasCondition = conditionSelect?.value === '1';
        if (conditionDetails) conditionDetails.hidden = !hasCondition;
        if (conditionTextarea) conditionTextarea.required = hasCondition;
    };
    conditionSelect?.addEventListener('change', syncConditionDetails);
    syncConditionDetails();
}

/**
 * Bangladesh Location Hierarchy Dataset for Client-side cascading selection.
 */
const BANGLADESH_LOCATIONS = {
    'Dhaka': {
        'Dhaka': [
            'Dhanmondi', 'Mirpur', 'Gulshan', 'Banani', 'Uttara', 'Mohammadpur',
            'Motijheel', 'Tejgaon', 'Shahbag', 'Khilgaon', 'Badda', 'Rampura',
            'Jatrabari', 'Paltan', 'Cantonment', 'Savar', 'Dhamrai', 'Keraniganj',
            'Dohar', 'Nawabganj', 'Adabor', 'Agargaon', 'Bashundhara', 'Mohakhali',
            'Old Dhaka', 'Demra', 'Hazaribagh', 'Kafrul', 'Kamrangirchar', 'Khilkhet',
            'Kotwali', 'Lalbagh', 'New Market', 'Pallabi', 'Sutrapur', 'Turag', 'Wari'
        ],
        'Gazipur': ['Gazipur Sadar', 'Kaliakair', 'Kaliganj', 'Kapasia', 'Sreepur', 'Tongi'],
        'Narayanganj': ['Narayanganj Sadar', 'Bandar', 'Rupganj', 'Sonargaon', 'Araihazar'],
        'Tangail': ['Tangail Sadar', 'Gopalpur', 'Madhupur', 'Mirzapur', 'Ghatail', 'Basail', 'Bhuapur', 'Delduar', 'Dhanbari', 'Kalihati', 'Nagarpur', 'Sakhipur'],
        'Narsingdi': ['Narsingdi Sadar', 'Belabo', 'Monohardi', 'Palash', 'Raipura', 'Shibpur'],
        'Manikganj': ['Manikganj Sadar', 'Singair', 'Shivalaya', 'Saturia', 'Harirampur', 'Ghior', 'Daulatpur'],
        'Munshiganj': ['Munshiganj Sadar', 'Sreenagar', 'Sirajdikhan', 'Louhajang', 'Tongibari', 'Gazaria'],
        'Faridpur': ['Faridpur Sadar', 'Boalmari', 'Alfadanga', 'Madhukhali', 'Bhanga', 'Nagarkanda', 'Charbhadrasan', 'Sadarpur', 'Saltha'],
        'Gopalganj': ['Gopalganj Sadar', 'Kashiani', 'Kotalipara', 'Muksudpur', 'Tungipara'],
        'Madaripur': ['Madaripur Sadar', 'Kalkini', 'Rajoir', 'Shibchar', 'Dasar'],
        'Rajbari': ['Rajbari Sadar', 'Baliakandi', 'Goalandaghat', 'Pangsha', 'Kalukhali'],
        'Shariatpur': ['Shariatpur Sadar', 'Damudya', 'Naria', 'Jajira', 'Bhedarganj', 'Gosairhat'],
        'Kishoreganj': ['Kishoreganj Sadar', 'Bhairab', 'Bajitpur', 'Hossainpur', 'Itna', 'Karimganj', 'Katiadi', 'Kuliarchar', 'Mithamain', 'Nikli', 'Pakundia', 'Tarail']
    },
    'Chattogram': {
        'Chattogram': [
            'Agrabad', 'Chandgaon', 'Panchlaish', 'Pahartali', 'Halishahar',
            'Hathazari', 'Sitakunda', 'Mirsharai', 'Patiya', 'Boalkhali', 'Anwara',
            'Raozan', 'Rangunia', 'Fatikchhari', 'Sandwip', 'Lohagara', 'Satkania',
            'Banshkhali', 'Karnaphuli', 'Bakalia', 'Bayazid', 'Double Mooring',
            'Kotwali', 'Khulshi', 'Patenga'
        ],
        'Cox\'s Bazar': ['Cox\'s Bazar Sadar', 'Chakaria', 'Maheshkhali', 'Teknaf', 'Ukhiya', 'Ramu', 'Kutubdia', 'Pekua'],
        'Cumilla': ['Cumilla Sadar', 'Burichang', 'Chandina', 'Daudkandi', 'Debidwar', 'Homna', 'Laksam', 'Muradnagar', 'Chauddagram', 'Brahmanpara', 'Barura', 'Meghna', 'Monohargonj', 'Sadar Dakshin', 'Titas', 'Lalmai'],
        'Feni': ['Feni Sadar', 'Chhagalnaiya', 'Daganbhuiyan', 'Parshuram', 'Sonagazi', 'Fulgazi'],
        'Brahmanbaria': ['Brahmanbaria Sadar', 'Akhaura', 'Ashuganj', 'Bancharampur', 'Bijoynagar', 'Kasba', 'Nabinagar', 'Nasirnagar', 'Sarail'],
        'Noakhali': ['Noakhali Sadar', 'Begumganj', 'Chatkhil', 'Companiganj', 'Hatiya', 'Senbagh', 'Sonaimuri', 'Subarnachar', 'Kabirhat'],
        'Chandpur': ['Chandpur Sadar', 'Faridganj', 'Haimchar', 'Hajiganj', 'Kachua', 'Matlab Dakshin', 'Matlab Uttar', 'Shahrasti'],
        'Lakshmipur': ['Lakshmipur Sadar', 'Raipur', 'Ramganj', 'Ramgati', 'Kamalnagar'],
        'Khagrachhari': ['Khagrachhari Sadar', 'Dighinala', 'Lakshmichhari', 'Mahalchhari', 'Manikchhari', 'Matiranga', 'Panchhari', 'Ramgarh', 'Guimara'],
        'Rangamati': ['Rangamati Sadar', 'Baghaichhari', 'Barkal', 'Belaichhari', 'Juraichhari', 'Kaptai', 'Kawkhali', 'Langadu', 'Naniarchar', 'Rajasthali'],
        'Bandarban': ['Bandarban Sadar', 'Alikadam', 'Lama', 'Naikhongchhari', 'Rowangchhari', 'Ruma', 'Thanchi']
    },
    'Rajshahi': {
        'Rajshahi': ['Rajshahi Sadar', 'Boalia', 'Motihar', 'Rajputra', 'Godagari', 'Tanore', 'Mohanpur', 'Bagmara', 'Durgapur', 'Puthia', 'Charghat', 'Bagha', 'Paba'],
        'Bogura': ['Bogura Sadar', 'Adamdighi', 'Dhunat', 'Dhupchanchia', 'Gabtali', 'Kahaloo', 'Nandigram', 'Sariakandi', 'Shajahanpur', 'Sherpur', 'Shibganj', 'Sonatola'],
        'Pabna': ['Pabna Sadar', 'Atgharia', 'Bera', 'Bhangura', 'Chatmohar', 'Faridpur', 'Ishwardi', 'Santhia', 'Sujanagar'],
        'Sirajganj': ['Sirajganj Sadar', 'Belkuchi', 'Chauhali', 'Kamarkhanda', 'Kazipur', 'Raiganj', 'Shahjadpur', 'Tarash', 'Ullahpara'],
        'Naogaon': ['Naogaon Sadar', 'Atrai', 'Badalgachhi', 'Dhamoirhat', 'Manda', 'Mohadevpur', 'Niamatpur', 'Patnitala', 'Porsha', 'Raninagar', 'Sapahar'],
        'Natore': ['Natore Sadar', 'Bagatipara', 'Baraigram', 'Gurudaspur', 'Lalpur', 'Singra', 'Naldanga'],
        'Chapainawabganj': ['Chapainawabganj Sadar', 'Bholahat', 'Gomastapur', 'Nachole', 'Shibganj'],
        'Joypurhat': ['Joypurhat Sadar', 'Akkelpur', 'Kalai', 'Khetlal', 'Panchbibi']
    },
    'Khulna': {
        'Khulna': ['Khulna Sadar', 'Daulatpur', 'Khalishpur', 'Khan Jahan Ali', 'Sonadanga', 'Batiaghata', 'Dacope', 'Dumuria', 'Dighalia', 'Koyra', 'Paikgachha', 'Phultala', 'Rupsha', 'Terokhada'],
        'Jashore': ['Jashore Sadar', 'Abhaynagar', 'Bagherpara', 'Chaugachha', 'Jhikargachha', 'Keshabpur', 'Manirampur', 'Sharsha'],
        'Satkhira': ['Satkhira Sadar', 'Assasuni', 'Debhata', 'Kalaroa', 'Kaliganj', 'Shyamnagar', 'Tala'],
        'Kushtia': ['Kushtia Sadar', 'Bheramara', 'Daulatpur', 'Khoksa', 'Kumarkhali', 'Mirpur'],
        'Jhenaidah': ['Jhenaidah Sadar', 'Harinakundu', 'Kaliganj', 'Kotchandpur', 'Maheshpur', 'Shailkupa'],
        'Chuadanga': ['Chuadanga Sadar', 'Alamdanga', 'Damurhuda', 'Jibannagar'],
        'Meherpur': ['Meherpur Sadar', 'Gangni', 'Mujibnagar'],
        'Narail': ['Narail Sadar', 'Kalia', 'Lohagara'],
        'Magura': ['Magura Sadar', 'Mohammadpur', 'Shalikha', 'Sreepur'],
        'Bagerhat': ['Bagerhat Sadar', 'Chitalmari', 'Fakirhat', 'Kachua', 'Mollahat', 'Mongla', 'Morrelganj', 'Rampal', 'Sarankhola']
    },
    'Barishal': {
        'Barishal': ['Barishal Sadar', 'Agailjhara', 'Babuganj', 'Bakerganj', 'Banaripara', 'Gaurnadi', 'Hizla', 'Mehendiganj', 'Muladi', 'Wazirpur'],
        'Patuakhali': ['Patuakhali Sadar', 'Bauphal', 'Dashmina', 'Dumki', 'Galachipa', 'Kalapara', 'Mirzaganj', 'Rangabali'],
        'Bhola': ['Bhola Sadar', 'Burhanuddin', 'Char Fasson', 'Daulatkhan', 'Lalmohan', 'Manpura', 'Tazumuddin'],
        'Pirojpur': ['Pirojpur Sadar', 'Bhandaria', 'Kawkhali', 'Mathbaria', 'Nazirpur', 'Nesarabad (Swarupkati)', 'Zianagar (Indurkani)'],
        'Barguna': ['Barguna Sadar', 'Amtali', 'Bamna', 'Betagi', 'Patharghata', 'Taltali'],
        'Jhalokathi': ['Jhalokathi Sadar', 'Kathalia', 'Nalchity', 'Rajapur']
    },
    'Sylhet': {
        'Sylhet': ['Sylhet Sadar', 'Beanibazar', 'Bishwanath', 'Companiganj', 'Fenchuganj', 'Golapganj', 'Gowainghat', 'Jaintiapur', 'Kanaighat', 'Osmani Nagar', 'Zakiganj', 'Dakshin Surma'],
        'Moulvibazar': ['Moulvibazar Sadar', 'Barlekha', 'Juri', 'Kamalganj', 'Kulaura', 'Rajnagar', 'Sreemangal'],
        'Habiganj': ['Habiganj Sadar', 'Ajmiriganj', 'Bahubal', 'Baniyachong', 'Chunarughat', 'Lakhai', 'Madhabpur', 'Nabiganj', 'Sayestaganj'],
        'Sunamganj': ['Sunamganj Sadar', 'Bishwamvarpur', 'Chhatak', 'Derai', 'Dharamapasha', 'Dowarabazar', 'Jagannathpur', 'Jamalganj', 'Sullah', 'Tahirpur', 'Shantiganj']
    },
    'Rangpur': {
        'Rangpur': ['Rangpur Sadar', 'Badarganj', 'Gangachhara', 'Kaunia', 'Mithapukur', 'Pirgachha', 'Pirganj', 'Taraganj'],
        'Dinajpur': ['Dinajpur Sadar', 'Birampur', 'Birganj', 'Birol', 'Bochaganj', 'Chirirbandar', 'Phulbari', 'Ghoraghat', 'Hakimpur', 'Kaharole', 'Khansama', 'Nawabganj', 'Parbatipur'],
        'Kurigram': ['Kurigram Sadar', 'Bhurungamari', 'Char Rajibpur', 'Chilmari', 'Phulbari', 'Nageshwari', 'Rajarhat', 'Rourmari', 'Ulipur'],
        'Gaibandha': ['Gaibandha Sadar', 'Fulchhari', 'Gobindaganj', 'Palashbari', 'Sadullapur', 'Sughatta', 'Sundarganj'],
        'Nilphamari': ['Nilphamari Sadar', 'Dimla', 'Domar', 'Jaldhaka', 'Kishoreganj', 'Saidpur'],
        'Lalmonirhat': ['Lalmonirhat Sadar', 'Aditmari', 'Hatibandha', 'Kaliganj', 'Patgram'],
        'Thakurgaon': ['Thakurgaon Sadar', 'Baliadangi', 'Haripur', 'Pirganj', 'Ranisankhail'],
        'Panchagarh': ['Panchagarh Sadar', 'Atwari', 'Boda', 'Debiganj', 'Tetulia']
    },
    'Mymensingh': {
        'Mymensingh': ['Mymensingh Sadar', 'Bhaluka', 'Dhobaura', 'Fulbaria', 'Gafargaon', 'Gauripur', 'Haluaghat', 'Ishwarganj', 'Muktagachha', 'Nandail', 'Phulpur', 'Trishal', 'Tara Khanda'],
        'Jamalpur': ['Jamalpur Sadar', 'Bakshiganj', 'Dewanganj', 'Islampur', 'Madarganj', 'Melandaha', 'Sarishabari'],
        'Netrokona': ['Netrokona Sadar', 'Atpara', 'Barhatta', 'Durgapur', 'Kalmakanda', 'Kendua', 'Madan', 'Mohanganj', 'Purbadhala', 'Khaliajuri'],
        'Sherpur': ['Sherpur Sadar', 'Jhenaigati', 'Nakla', 'Nalitabari', 'Sreebardi']
    }
};

/**
 * Initializes dependent Division -> District -> Upazila dropdowns.
 */
function initLocationPickers() {
    document.querySelectorAll('[data-location-picker]').forEach((container) => {
        const divisionSelect = container.querySelector('[data-location-division]');
        const districtSelect = container.querySelector('[data-location-district]');
        const upazilaSelect = container.querySelector('[data-location-upazila]');

        if (!divisionSelect || !districtSelect || !upazilaSelect) return;

        const populateDistricts = (selectedDistrict = '') => {
            const division = divisionSelect.value;
            districtSelect.innerHTML = '<option value="">Select District</option>';
            upazilaSelect.innerHTML = '<option value="">Select Upazila</option>';

            if (!division || !BANGLADESH_LOCATIONS[division]) {
                districtSelect.disabled = true;
                upazilaSelect.disabled = true;
                return;
            }

            districtSelect.disabled = false;
            const districts = Object.keys(BANGLADESH_LOCATIONS[division]);
            districts.forEach((dist) => {
                const opt = document.createElement('option');
                opt.value = dist;
                opt.textContent = dist;
                if (dist === selectedDistrict) opt.selected = true;
                districtSelect.appendChild(opt);
            });

            if (selectedDistrict && districts.includes(selectedDistrict)) {
                populateUpazilas(districtSelect.dataset.initialUpazila || '');
            } else {
                upazilaSelect.disabled = true;
            }
        };

        const populateUpazilas = (selectedUpazila = '') => {
            const division = divisionSelect.value;
            const district = districtSelect.value;
            upazilaSelect.innerHTML = '<option value="">Select Upazila</option>';

            if (!division || !district || !BANGLADESH_LOCATIONS[division] || !BANGLADESH_LOCATIONS[division][district]) {
                upazilaSelect.disabled = true;
                return;
            }

            upazilaSelect.disabled = false;
            const upazilas = BANGLADESH_LOCATIONS[division][district];
            upazilas.forEach((upz) => {
                const opt = document.createElement('option');
                opt.value = upz;
                opt.textContent = upz;
                if (upz === selectedUpazila) opt.selected = true;
                upazilaSelect.appendChild(opt);
            });
        };

        divisionSelect.addEventListener('change', () => {
            districtSelect.dataset.initialUpazila = '';
            populateDistricts();
        });

        districtSelect.addEventListener('change', () => {
            populateUpazilas();
        });

        // Initialize with pre-selected values if available
        if (divisionSelect.value) {
            const initialDist = districtSelect.value;
            const initialUpz = upazilaSelect.value;
            districtSelect.dataset.initialUpazila = initialUpz;
            populateDistricts(initialDist);
            if (initialDist) {
                populateUpazilas(initialUpz);
            }
        }
    });
}

// Run location picker initialization
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLocationPickers);
} else {
    initLocationPickers();
}
