<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;

class CityBakSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        self::saudiArabiaCities();
    }

    protected function saudiArabiaCities()
    {
        $asir = State::firstWhere('name->en', 'Asir');
        $al_baha = State::firstWhere('name->en', 'Al Bahah');
        $al_jawf = State::firstWhere('name->en', 'Al Jawf');
        $al_madina = State::firstWhere('name->en', 'Al Madinah');
        $al_qassim = State::firstWhere('name->en', 'Al-Qassim');
        $eastern_prov = State::firstWhere('name->en', 'Eastern Province');
        $hail = State::firstWhere('name->en', "Ha'il");
        $jizan = State::firstWhere('name->en', 'Jizan');
        $makkah = State::firstWhere('name->en', 'Makkah');
        $najran = State::firstWhere('name->en', 'Najran');
        $northern_borders = State::firstWhere('name->en', 'Northern Borders');
        $riyadh = State::firstWhere('name->en', 'Riyadh');
        $tabuk = State::firstWhere('name->en', 'Tabuk');

        $asir_list = [
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Abha',
                    'ar' => 'أبها',
                ]
            ],
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Al Majāridah',
                    'ar' => 'المجاردة',
                ]
            ],
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Al Qahab',
                    'ar' => 'القهبه',
                ]
            ],
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Khamis Mushait',
                    'ar' => 'خميس مشيط',
                ]
            ],
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Mariyah',
                    'ar' => 'مرايه',
                ]
            ],
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Mifa',
                    'ar' => 'ميفا',
                ]
            ],
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Munayzir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $asir->id,
                'name' => [
                    'en' => 'Tabālah',
                    'ar' => '',
                ]
            ]
        ];

        $al_baha_list = [
            [
                'state_id' => $al_baha->id,
                'name' => [
                    'en' => 'Al Bahah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_baha->id,
                'name' => [
                    'en' => 'Al Mindak',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_baha->id,
                'name' => [
                    'en' => 'Hajrah',
                    'ar' => '',
                ]
            ],
        ];

        $al_jawf_list = [
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Al Isawiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Al-Haditha',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Halat Ammar',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Qurayyat',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Sakakah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Şuwayr',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Tabarjal',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_jawf->id,
                'name' => [
                    'en' => 'Ţubarjal',
                    'ar' => '',
                ]
            ]
        ];

        $al_madina_list = [
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Ajmiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Alya',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Ushash',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Ushayrah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Abū Shayţānah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => "Abyar Ali",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Ad Dulu',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Awali',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Uqul',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Akhal',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Bardiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Biqa',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Bustan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Faqirah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Furaysh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Jabriyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Jissah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Kharma',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Malbanah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Mufrihat',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Multasa',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Musayjid',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al Wuday',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al-Jafr',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Al-Ula',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'As Sadayir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'As Safra',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'As Sumariyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'As Suwayriqiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Ash Shufayyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Asira',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => "Baq`a'",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Bartiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => "Bi'r al Mashi",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => "Birkah",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Far',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Harthiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Haylat Radi al Baham',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Husayniyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Jadidah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Khayf Fadil',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Madsus',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Mahattat al Hafah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Maqrah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Maqshush',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Masahili',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Mawarah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Medina',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Milhah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Nujayl',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Qaba',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Rayyis',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Sha`tha',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Sidi Hamzah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Suq Suwayq',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Suqubiya',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Suwadah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Wasitah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_madina->id,
                'name' => [
                    'en' => 'Yanbu',
                    'ar' => '',
                ]
            ],
        ];

        $al_qassim_list = [
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Adh Dhibiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Al Bukayrīyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Al Fuwayliq',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Al Mithnab',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Al Thybiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Ar Rass',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Buraidah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Buraydah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Dukhnah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Qiba',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Tanūmah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $al_qassim->id,
                'name' => [
                    'en' => 'Wed Alnkil',
                    'ar' => '',
                ]
            ],
        ];

        $eastern_prov_list = [
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Abqaiq',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Awjām',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Baţţālīyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Hufūf',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Jafr',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Jubayl',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Khafjī',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Markaz',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Mubarraz',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Munayzilah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Muţayrifī',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Qārah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Qaţīf',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Qurayn',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al Ubaylah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al-Awamiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al-Awjam',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Al-Mubarraz',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'As Saffānīyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Aţ Ţaraf',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'At Tūbī',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Dammam',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Dhahran',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => "Ha'il",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Hafar Al-Batin',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Haradh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Julayjilah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Khobar',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Mulayjah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Nariyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Qaisumah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Raḩīmah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Şafwá',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Sayhāt',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Tārūt',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Udhailiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Umm as Sāhik',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $eastern_prov->id,
                'name' => [
                    'en' => 'Uqair',
                    'ar' => '',
                ]
            ],
        ];

        $hail_list = [
            [
                'state_id' => $hail->id,
                'name' => [
                    'en' => 'Jubbah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $hail->id,
                'name' => [
                    'en' => 'Mawqaq',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $hail->id,
                'name' => [
                    'en' => 'Qufar',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $hail->id,
                'name' => [
                    'en' => 'Simira',
                    'ar' => '',
                ]
            ],
        ];

        $jizan_list = [
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Abū ‘Arīsh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Abu Radif',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Ad Darb',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Ad Dur`iyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Adh Dhagharir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al `Ulayin',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al `Usaylah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Badawi',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Hadrur',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Hanashah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Harani',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Hasamah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Hijfar',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Jadi',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Jarādīyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Jawah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Jirbah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Karbus',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Kawahilah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => "Al Khadra' Jizan",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Kharabah Jizan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Kharadilah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Khashabiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Khubah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Kirs',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Luqiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Ma`ayin',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Madaya',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Mali',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Mayasam',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => "Al Qa'im",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Quful',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Qurayb',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Quwah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Al Wasili',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'An Najamiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Ar Rukubah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Ash Shuqayq',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Bakhshat Yamani',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Farasān',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Ghawiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Hamayyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Hamdah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Jizan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Juha Saudi Arabia',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Ka`lul',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Khabath Sa`id',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Khalfah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Khatib Saudi Arabia',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Khumsiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Khushaym',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Mahatah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Malgocta',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Mislīyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Mizhirah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Mukambal',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Mundaraq',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Muwassam',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Qitabir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Quwayda',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Rahwan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Rawkhah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Şabyā',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Sadiliyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Salamah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $jizan->id,
                'name' => [
                    'en' => 'Şāmitah',
                    'ar' => '',
                ]
            ],
        ];

        $makkah_list = [
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Abu Urwah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Abu Hisani',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Abu Qirfah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Abu Shuayb',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ad Dabbah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ad Dawh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ad Dur',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Ābār',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Adl',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Ashraf',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Balad',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Barabir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => "Al Bi'ar",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Birk',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Buraykah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Fawwarah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Faydah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Fazz',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Gharith',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Ghassalah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Ghulah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Hadā',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Halaqah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Hamimah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => "Al Harra' Makkah",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Hawiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Iskan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Jadidah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Jami`ah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Jid`',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Ju`ranah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Jumūm',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => "Al Khadra' Makkah",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Khalas',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Khamrah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Khaydar',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Khayf',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Khulasah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Kidwah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Kura',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Ma`rash',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Madiq Makkah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Maghal',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Mahjar',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Maqrah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Masarrah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Masfalah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Mashayikh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Mathnah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Mubarak',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Mudawwarah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Mulayha',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Mundassah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Muqayti',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Muqr',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Muwayh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qadimah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qararah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qaryat',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qawba`iyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qirshan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qu`tubah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qufayf',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Qushashiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Ukhaydir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Al Waht',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ar Rabwah as Sufla',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ar Rafah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ar Rawdah ash Shamaliyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ar Rudaymah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Arya',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'As Sadr',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'As Samd ash Shamali',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'As Sayl al Kabir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'As Sayl as Saghir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'As Sifyani',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'As Sudayrah Makkah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'As Suwadah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shafa',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shajwah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shamiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => "Ash Sharai",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shaybi',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shi`b',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shishah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shumaysi',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ash Shuwaybit',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'At Tan`im',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'At Tarfa',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'At Turqi',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Az Zaymah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Az Zilal',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Az Zughbah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Az Zurra',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Az Zuwayb',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Bahrat al Qadimah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Bahwil',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Baranah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Barzah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Bashm',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Buraykah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Burayman',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'CITY GHRAN',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Dabyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Dahaban',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Dughaybjah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Fayd',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Ghran',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Hadda',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Haddat ash Sham',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Hadhah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Hajur',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Halamah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Husnah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Jarwal',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Jeddah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Julayyil',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Khumrah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Kulakh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Madrakah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Mafruq',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Malakan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Mashajji',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Masihat Mahd al Hayl',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Maskar',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Matiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Mecca',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Mina',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Murshidiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Mushrif',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Nughayshiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Nuzlat al Faqin',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Qiya',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Quwayzah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Rābigh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Rabwah Ghran',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Raqiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Sabuhah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Shi`b `amir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Shira`ayn',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Sulaym',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Sumaymah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Suways',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => "Ta'if",
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Tharwah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Thuwal',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Turabah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Usfan',
                    'ar' => '',
                ]
            ],

            [
                'state_id' => $makkah->id,
                'name' => [
                    'en' => 'Wadi al Jalil',
                    'ar' => '',
                ]
            ],
        ];

        $najran_list = [
            [
                'state_id' => $najran->id,
                'name' => [
                    'en' => 'Najran',
                    'ar' => 'نجران',
                ]
            ],
        ];

        $northern_borders_list = [
            [
                'state_id' => $northern_borders->id,
                'name' => [
                    'en' => 'Arar',
                    'ar' => 'عرعر',
                ]
            ],
            [
                'state_id' => $northern_borders->id,
                'name' => [
                    'en' => 'Nisab',
                    'ar' => 'نصاب',
                ]
            ],
            [
                'state_id' => $northern_borders->id,
                'name' => [
                    'en' => 'Turaif',
                    'ar' => 'طريف',
                ]
            ],
            [
                'state_id' => $northern_borders->id,
                'name' => [
                    'en' => 'Umm Radamah',
                    'ar' => 'ام ردامة',
                ]
            ],
        ];

        $riyadh_list = [
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Ad Dawadimi',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Ad Dilam',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Afif',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Ain AlBaraha',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Al Arţāwīyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Al Bir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Al Hair',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Al Jurayfah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Al Kharj',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Ar Rayn',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'As Salamiyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'As Sulayyil',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Az Zulfī',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Dawadmi',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Diriyah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Harmah',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Jalajil',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Layla',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Manfuha',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Marāt',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Najan',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Riyadh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Sājir',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'shokhaibٍ',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $riyadh->id,
                'name' => [
                    'en' => 'Tumayr',
                    'ar' => '',
                ]
            ]
        ];

        $tabuk_list = [
            [
                'state_id' => $tabuk->id,
                'name' => [
                    'en' => 'Al Wajh',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $tabuk->id,
                'name' => [
                    'en' => 'Duba',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $tabuk->id,
                'name' => [
                    'en' => 'Tabuk',
                    'ar' => '',
                ]
            ],
            [
                'state_id' => $tabuk->id,
                'name' => [
                    'en' => 'Umm Lajj',
                    'ar' => '',
                ]
            ],
        ];

        self::insertData($asir_list);
        self::insertData($al_baha_list);
        self::insertData($al_jawf_list);
        self::insertData($al_madina_list);
        self::insertData($al_qassim_list);
        self::insertData($eastern_prov_list);
        self::insertData($hail_list);
        self::insertData($jizan_list);
        self::insertData($makkah_list);
        self::insertData($najran_list);
        self::insertData($northern_borders_list);
        self::insertData($riyadh_list);
        self::insertData($tabuk_list);
    }


    protected function insertData($list){
        foreach ($list as $city) {
            City::updateOrCreate(['name->en' => $city['name']['en']], $city);
        }
    }
}
