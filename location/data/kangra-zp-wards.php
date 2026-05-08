<?php
/**
 * ENOXX Elections — Kangra Zila Parishad Wards
 * Source: Official Kangra ZP ward list
 * Loaded via enx_add_kangra_zp_wards filter / hook
 * 54 wards total
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Hook into location data to inject ZP wards into Kangra district
add_filter( 'enx_location_data_loaded', function( $data ) {
    if ( isset( $data['kangra'] ) ) {
        $data['kangra']['zp_wards'] = enx_kangra_zp_wards();
    }
    return $data;
} );

function enx_kangra_zp_wards() {
    return [
        'majherna'         => ['label_en' => 'Majherna',       'label_hi' => 'मझैरना'],
        'tarehal'          => ['label_en' => 'Tarehal',        'label_hi' => 'तरेहल'],
        'lambagaon'        => ['label_en' => 'Lambagaon',      'label_hi' => 'लम्बागाँव'],
        'damtal'           => ['label_en' => 'Damtal',         'label_hi' => 'डमटाल'],
        'gadiara'          => ['label_en' => 'Gadiara',        'label_hi' => 'गदियाड़ा'],
        'bhatalla'         => ['label_en' => 'Bhatalla',       'label_hi' => 'भत्तला'],
        'kudail'           => ['label_en' => 'Kudail',         'label_hi' => 'कुदैल'],
        'badhal'           => ['label_en' => 'Badhal',         'label_hi' => 'बढल'],
        'bani'             => ['label_en' => 'Bani',           'label_hi' => 'बणी'],
        'shekhpur'         => ['label_en' => 'Shekhpur',       'label_hi' => 'शेखपुर'],
        'mamuh-gurchal'    => ['label_en' => 'Mamuh Gurchal',  'label_hi' => 'ममूह गुरचाल'],
        'nachcheer-bandla' => ['label_en' => 'Nachcheer Bandla','label_hi'=> 'नच्छीर बंदला'],
        'nagari-kalund'    => ['label_en' => 'Nagari Kalund',  'label_hi' => 'नगरी कलुण्ड'],
        'averi'            => ['label_en' => 'Averi',          'label_hi' => 'अवैरी'],
        'bhali'            => ['label_en' => 'Bhali',          'label_hi' => 'भाली'],
        'massal'           => ['label_en' => 'Massal',         'label_hi' => 'मस्सल'],
        'daulatpur'        => ['label_en' => 'Daulatpur',      'label_hi' => 'दौलतपुर'],
        'bhadiara'         => ['label_en' => 'Bhadiara',       'label_hi' => 'भडियारा'],
        'thakurdwara'      => ['label_en' => 'Thakurdwara',    'label_hi' => 'ठाकुरद्वारा'],
        'tharu'            => ['label_en' => 'Tharu',          'label_hi' => 'ठारू'],
        'kholi'            => ['label_en' => 'Kholi',          'label_hi' => 'खोली'],
        'tiara'            => ['label_en' => 'Tiara',          'label_hi' => 'तियारा'],
        'jhikali-ichchhi'  => ['label_en' => 'Jhikali Ichchhi','label_hi'=> 'झिकली इच्छी'],
        'naura'            => ['label_en' => 'Naura',          'label_hi' => 'नौरा'],
        'haripur'          => ['label_en' => 'Haripur',        'label_hi' => 'हरिपुर'],
        'sunni'            => ['label_en' => 'Sunni',          'label_hi' => 'सुन्नी'],
        'badi'             => ['label_en' => 'Badi',           'label_hi' => 'बाड़ी'],
        'sullah'           => ['label_en' => 'Sullah',         'label_hi' => 'सुलह'],
        'kopada'           => ['label_en' => 'Kopada',         'label_hi' => 'कोपडा'],
        'nangal-chowk'     => ['label_en' => 'Nangal Chowk',  'label_hi' => 'नंगल चौक'],
        'majhgraan'        => ['label_en' => 'Majhgraan',      'label_hi' => 'मझग्रां'],
        'bari-kalaan'      => ['label_en' => 'Bari Kalaan',    'label_hi' => 'बारी कलां'],
        'adhwani'          => ['label_en' => 'Adhwani',        'label_hi' => 'अधवानी'],
        'chari'            => ['label_en' => 'Chari',          'label_hi' => 'चड़ी'],
        'faria'            => ['label_en' => 'Faria',          'label_hi' => 'फारिया'],
        'ghati'            => ['label_en' => 'Ghati',          'label_hi' => 'घाटी'],
        'arla'             => ['label_en' => 'Arla',           'label_hi' => 'अरला'],
        'alampur'          => ['label_en' => 'Alampur',        'label_hi' => 'आलमपुर'],
        'jarot'            => ['label_en' => 'Jarot',          'label_hi' => 'जरोट'],
        'rehan-ii'         => ['label_en' => 'Rehan II',       'label_hi' => 'रेहन II'],
        'kavari'           => ['label_en' => 'Kavari',         'label_hi' => 'कवाड़ी'],
        'saukani-da-kot'   => ['label_en' => 'Saukani Da Kot', 'label_hi' => 'सौकणी दा कोट'],
        'manoh-sihal'      => ['label_en' => 'Manoh Sihal',    'label_hi' => 'मनोह सिहाल'],
        'riyali'           => ['label_en' => 'Riyali',         'label_hi' => 'रियाली'],
        'meran'            => ['label_en' => 'Meran',          'label_hi' => 'मैरां'],
        'pragpur'          => ['label_en' => 'Pragpur',        'label_hi' => 'प्रागपुर'],
        'bhadwar'          => ['label_en' => 'Bhadwar',        'label_hi' => 'भडवार'],
        'gurial'           => ['label_en' => 'Gurial',         'label_hi' => 'गुरियाल'],
        'gangath'          => ['label_en' => 'Gangath',        'label_hi' => 'गंगथ'],
        'indora'           => ['label_en' => 'Indora',         'label_hi' => 'इन्दौरा'],
        'baranda'          => ['label_en' => 'Baranda',        'label_hi' => 'बरण्डा'],
        'bhaleta'          => ['label_en' => 'Bhaleta',        'label_hi' => 'भलेटा'],
        'sansal'           => ['label_en' => 'Sansal',         'label_hi' => 'संसाल'],
        'haledkalan'       => ['label_en' => 'Haledkalan',     'label_hi' => 'हलेडकलां'],
    ];
}
