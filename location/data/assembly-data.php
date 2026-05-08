<?php
/**
 * ENOXX Elections — HP Assembly Constituencies
 * 68 constituencies of Himachal Pradesh
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function enx_get_assembly_data() {
    return [
        // Chamba District
        'churah'         => ['label_en' => 'Churah',            'label_hi' => 'चुराह',           'district' => 'chamba'],
        'bharmour'       => ['label_en' => 'Bharmour',          'label_hi' => 'भरमौर',           'district' => 'chamba'],
        'chamba'         => ['label_en' => 'Chamba',            'label_hi' => 'चम्बा',           'district' => 'chamba'],
        'dalhousie'      => ['label_en' => 'Dalhousie',         'label_hi' => 'डलहौजी',          'district' => 'chamba'],
        'bhattiyat'      => ['label_en' => 'Bhattiyat',         'label_hi' => 'भट्टियात',        'district' => 'chamba'],

        // Kangra District
        'nurpur'         => ['label_en' => 'Nurpur',            'label_hi' => 'नूरपुर',          'district' => 'kangra'],
        'indora'         => ['label_en' => 'Indora',            'label_hi' => 'इंदौरा',          'district' => 'kangra'],
        'fatehpur'       => ['label_en' => 'Fatehpur',          'label_hi' => 'फतेहपुर',         'district' => 'kangra'],
        'jawali'         => ['label_en' => 'Jawali',            'label_hi' => 'जवाली',           'district' => 'kangra'],
        'dehra'          => ['label_en' => 'Dehra',             'label_hi' => 'देहरा',           'district' => 'kangra'],
        'jaswan-pragpur' => ['label_en' => 'Jaswan-Pragpur',    'label_hi' => 'जसवां-प्रागपुर',  'district' => 'kangra'],
        'jawalamukhi'    => ['label_en' => 'Jawalamukhi',       'label_hi' => 'ज्वालामुखी',      'district' => 'kangra'],
        'jaisinghpur'    => ['label_en' => 'Jaisinghpur',       'label_hi' => 'जयसिंहपुर',      'district' => 'kangra'],
        'sullah'         => ['label_en' => 'Sullah',            'label_hi' => 'सुलह',            'district' => 'kangra'],
        'nagrota'        => ['label_en' => 'Nagrota',           'label_hi' => 'नगरोटा',          'district' => 'kangra'],
        'kangra'         => ['label_en' => 'Kangra',            'label_hi' => 'काँगड़ा',         'district' => 'kangra'],
        'shahpur'        => ['label_en' => 'Shahpur',           'label_hi' => 'शाहपुर',          'district' => 'kangra'],
        'dharamshala'    => ['label_en' => 'Dharamshala',       'label_hi' => 'धर्मशाला',        'district' => 'kangra'],
        'palampur'       => ['label_en' => 'Palampur',          'label_hi' => 'पालमपुर',         'district' => 'kangra'],
        'baijnath'       => ['label_en' => 'Baijnath',          'label_hi' => 'बैजनाथ',          'district' => 'kangra'],

        // Lahaul-Spiti
        'lahaul-spiti'   => ['label_en' => 'Lahaul and Spiti',  'label_hi' => 'लाहौल और स्पीति', 'district' => 'lahaul-spiti'],

        // Kullu District
        'manali'         => ['label_en' => 'Manali',            'label_hi' => 'मनाली',           'district' => 'kullu'],
        'kullu'          => ['label_en' => 'Kullu',             'label_hi' => 'कुल्लू',          'district' => 'kullu'],
        'banjar'         => ['label_en' => 'Banjar',            'label_hi' => 'बंजार',           'district' => 'kullu'],
        'anni'           => ['label_en' => 'Anni',              'label_hi' => 'आनी',             'district' => 'kullu'],

        // Mandi District
        'karsog'         => ['label_en' => 'Karsog',            'label_hi' => 'करसोग',          'district' => 'mandi'],
        'sundernagar'    => ['label_en' => 'Sundernagar',       'label_hi' => 'सुंदरनगर',        'district' => 'mandi'],
        'nachan'         => ['label_en' => 'Nachan',            'label_hi' => 'नाचन',            'district' => 'mandi'],
        'seraj'          => ['label_en' => 'Seraj',             'label_hi' => 'सेराज',           'district' => 'mandi'],
        'darang'         => ['label_en' => 'Darang',            'label_hi' => 'दारंग',           'district' => 'mandi'],
        'jogindernagar'  => ['label_en' => 'Jogindernagar',     'label_hi' => 'जोगिंदरनगर',     'district' => 'mandi'],
        'dharampur'      => ['label_en' => 'Dharampur',         'label_hi' => 'धर्मपुर',         'district' => 'mandi'],
        'mandi'          => ['label_en' => 'Mandi',             'label_hi' => 'मंडी',            'district' => 'mandi'],
        'balh'           => ['label_en' => 'Balh',              'label_hi' => 'बल्ह',            'district' => 'mandi'],
        'sarkaghat'      => ['label_en' => 'Sarkaghat',         'label_hi' => 'सरकाघाट',         'district' => 'mandi'],

        // Hamirpur District
        'bhoranj'        => ['label_en' => 'Bhoranj',           'label_hi' => 'भोरंज',           'district' => 'hamirpur'],
        'sujanpur'       => ['label_en' => 'Sujanpur',          'label_hi' => 'सुजानपुर',        'district' => 'hamirpur'],
        'hamirpur'       => ['label_en' => 'Hamirpur',          'label_hi' => 'हमीरपुर',         'district' => 'hamirpur'],
        'barsar'         => ['label_en' => 'Barsar',            'label_hi' => 'बड़सर',           'district' => 'hamirpur'],
        'nadaun'         => ['label_en' => 'Nadaun',            'label_hi' => 'नादौन',           'district' => 'hamirpur'],

        // Una District
        'chintpurni'     => ['label_en' => 'Chintpurni',        'label_hi' => 'चिंतपूर्णी',      'district' => 'una'],
        'gagret'         => ['label_en' => 'Gagret',            'label_hi' => 'गगरेट',           'district' => 'una'],
        'haroli'         => ['label_en' => 'Haroli',            'label_hi' => 'हरोली',           'district' => 'una'],
        'una'            => ['label_en' => 'Una',               'label_hi' => 'ऊना',             'district' => 'una'],
        'kutlehar'       => ['label_en' => 'Kutlehar',          'label_hi' => 'कुटलेहर',         'district' => 'una'],

        // Bilaspur District
        'jhanduta'       => ['label_en' => 'Jhanduta',          'label_hi' => 'झंडूता',          'district' => 'bilaspur'],
        'ghumarwin'      => ['label_en' => 'Ghumarwin',         'label_hi' => 'घुमारवीं',        'district' => 'bilaspur'],
        'bilaspur'       => ['label_en' => 'Bilaspur',          'label_hi' => 'बिलासपुर',        'district' => 'bilaspur'],
        'shri-naina-deviji'=> ['label_en'=> 'Shri Naina Deviji','label_hi' => 'श्री नैना देवीजी', 'district' => 'bilaspur'],

        // Solan District
        'arki'           => ['label_en' => 'Arki',              'label_hi' => 'अर्की',           'district' => 'solan'],
        'nalagarh'       => ['label_en' => 'Nalagarh',          'label_hi' => 'नालागढ़',         'district' => 'solan'],
        'doon'           => ['label_en' => 'Doon',              'label_hi' => 'दून',             'district' => 'solan'],
        'solan'          => ['label_en' => 'Solan',             'label_hi' => 'सोलन',            'district' => 'solan'],
        'kasauli'        => ['label_en' => 'Kasauli',           'label_hi' => 'कसौली',          'district' => 'solan'],

        // Sirmour District
        'pachhad'        => ['label_en' => 'Pachhad',           'label_hi' => 'पच्छाद',          'district' => 'sirmour'],
        'nahan'          => ['label_en' => 'Nahan',             'label_hi' => 'नाहन',            'district' => 'sirmour'],
        'sri-renukaji'   => ['label_en' => 'Sri Renukaji',      'label_hi' => 'श्री रेणुकाजी',   'district' => 'sirmour'],
        'paonta-sahib'   => ['label_en' => 'Paonta Sahib',      'label_hi' => 'पाँवटा साहिब',    'district' => 'sirmour'],
        'shillai'        => ['label_en' => 'Shillai',           'label_hi' => 'शिल्लाई',         'district' => 'sirmour'],

        // Shimla District
        'chopal'         => ['label_en' => 'Chopal',            'label_hi' => 'चौपाल',           'district' => 'shimla'],
        'theog'          => ['label_en' => 'Theog',             'label_hi' => 'ठियोग',           'district' => 'shimla'],
        'kasumpti'       => ['label_en' => 'Kasumpti',          'label_hi' => 'कसुम्पटी',        'district' => 'shimla'],
        'shimla'         => ['label_en' => 'Shimla',            'label_hi' => 'शिमला',           'district' => 'shimla'],
        'shimla-rural'   => ['label_en' => 'Shimla Rural',      'label_hi' => 'शिमला ग्रामीण',   'district' => 'shimla'],
        'jubbal-kotkhai' => ['label_en' => 'Jubbal-Kotkhai',    'label_hi' => 'जुब्बल-कोटखाई',  'district' => 'shimla'],
        'rampur'         => ['label_en' => 'Rampur',            'label_hi' => 'रामपुर',          'district' => 'shimla'],
        'rohru'          => ['label_en' => 'Rohru',             'label_hi' => 'रोहड़ू',          'district' => 'shimla'],

        // Kinnaur District
        'kinnaur'        => ['label_en' => 'Kinnaur',           'label_hi' => 'किन्नौर',         'district' => 'kinnaur'],
    ];
}
