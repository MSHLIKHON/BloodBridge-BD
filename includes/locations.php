<?php
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

function render_location_datalist(string $id = 'bangladesh-locations'): void
{
    echo '<datalist id="' . e($id) . '">';
    foreach (bangladesh_locations() as $location) {
        echo '<option value="' . e($location) . '"></option>';
    }
    echo '</datalist>';
}
