<?php


    namespace App\Services;


    use App\Models\User;
    use Illuminate\Support\Collection;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Hash;
    use Illuminate\Support\Str;

    class UserService extends ModelService
    {

        public function __construct()
        {
            parent::__construct(User::class);
        }

        public function load(): Collection
        {
            return Cache::remember('users', 18000, function () {
                return User::with([])->get();
            });
        }

        /**
         * Get all notifications(read, unread) of (current or specified user) or empty
         * collection if user not logged in
         * @param \App\Models\User $user
         * <p>
         * Default value: null,
         * Get notifications of specified user
         * </p>
         */
        public function notifications(\App\Models\User $user = null): \Illuminate\Notifications\DatabaseNotificationCollection
        {
            if ($user) {
                return $user->notifications;
            } else {
                if (auth()->check()) {
                    return auth()->user()->notifications;
                }
            }
            return new \Illuminate\Notifications\DatabaseNotificationCollection([]);
        }

        /**
         * Get Unread notifications of (current or specified user) or empty
         * collection if user not logged in
         * @param \App\Models\User $user
         * <p>
         * Default value: null,
         * Get unread notifications of specified user
         * </p>
         */
        public function unreadNotifications(\App\Models\User $user = null): \Illuminate\Notifications\DatabaseNotificationCollection
        {
            if ($user) {
                return $user->unreadNotifications;
            } else {
                if (auth()->check()) {
                    return auth()->user()->unreadNotifications;
                }
            }
            return new \Illuminate\Notifications\DatabaseNotificationCollection([]);
        }


        /**
         * Get read notifications of (current or specified user) or empty
         * collection if user not logged in
         * @param \App\Models\User $user
         * <p>
         * Default value: null,
         * Get read notifications of specified user
         * </p>
         */
        public function readNotifications(\App\Models\User $user = null): Collection
        {
            if ($user) {
                return $user->notifications->filter(function ($item) {
                    return $item->read_at != null;
                });
            } else {
                if (auth()->check()) {
                    return auth()->user()->notifications->filter(function ($item) {
                        return $item->read_at != null;
                    });
                }
            }
            return collect();
        }


        public function signUp(array $data, $role, array $permissions)
        {
            if (Hash::needsRehash($data['password']))
                $data['password'] = bcrypt($data['password']);

            $user = $this->create($data);
            $user->assignRole($role);
            $user->givePermissionTo($permissions);
            return $user;
        }

        public function loginByPhone()
        {

        }

        public function logout()
        {
            auth()->logout();
        }

        public function disable(User $user): bool
        {
            return $user->update(['active' => 0]);
        }

        public function enable(User $user): bool
        {
            return $user->update(['active' => 1]);
        }

        public function createRC(User $user): User
        {
            if (!$user->referral_code) {
                $user->update(
                    [
                        'referral_code' => self::generateRC()
                    ]
                );
            }

            return $user;
        }

        public static function generateRC()
        {
            $rc = Str::random(5);

            $exist = User::where('referral_code', $rc)->first();

            while ($exist) {
                $rc = Str::random(5);
                $exist = User::where('referral_code', $rc)->first();
            }

            return $rc;
        }

        public static function getRC($device_id)
        {
            $user = User::where('device_id', $device_id)->first();

            return $user === null ? null : $user->referral_code;
        }


        public function getNationalities()
        {
            $data = '[
    [
        "Afghan"
    ],
    [
        "Albanian"
    ],
    [
        "Algerian"
    ],
    [
        "American"
    ],
    [
        "Andorran"
    ],
    [
        "Angolan"
    ],
    [
        "Anguillan"
    ],
    [
        "Argentine"
    ],
    [
        "Armenian"
    ],
    [
        "Australian"
    ],
    [
        "Austrian"
    ],
    [
        "Azerbaijani"
    ],
    [
        "Bahamian"
    ],
    [
        "Bahraini"
    ],
    [
        "Bangladeshi"
    ],
    [
        "Barbadian"
    ],
    [
        "Belarusian"
    ],
    [
        "Belgian"
    ],
    [
        "Belizean"
    ],
    [
        "Beninese"
    ],
    [
        "Bermudian"
    ],
    [
        "Bhutanese"
    ],
    [
        "Bolivian"
    ],
    [
        "Botswanan"
    ],
    [
        "Brazilian"
    ],
    [
        "British"
    ],
    [
        "British Virgin Islander"
    ],
    [
        "Bruneian"
    ],
    [
        "Bulgarian"
    ],
    [
        "Burkinan"
    ],
    [
        "Burmese"
    ],
    [
        "Burundian"
    ],
    [
        "Cambodian"
    ],
    [
        "Cameroonian"
    ],
    [
        "Canadian"
    ],
    [
        "Cape Verdean"
    ],
    [
        "Cayman Islander"
    ],
    [
        "Central African"
    ],
    [
        "Chadian"
    ],
    [
        "Chilean"
    ],
    [
        "Chinese"
    ],
    [
        "Citizen of Antigua and Barbuda"
    ],
    [
        "Citizen of Bosnia and Herzegovina"
    ],
    [
        "Citizen of Guinea-Bissau"
    ],
    [
        "Citizen of Kiribati"
    ],
    [
        "Citizen of Seychelles"
    ],
    [
        "Citizen of the Dominican Republic"
    ],
    [
        "Citizen of Vanuatu"
    ],
    [
        "Colombian"
    ],
    [
        "Comoran"
    ],
    [
        "Congolese (Congo)"
    ],
    [
        "Congolese (DRC)"
    ],
    [
        "Cook Islander"
    ],
    [
        "Costa Rican"
    ],
    [
        "Croatian"
    ],
    [
        "Cuban"
    ],
    [
        "Cymraes"
    ],
    [
        "Cymro"
    ],
    [
        "Cypriot"
    ],
    [
        "Czech"
    ],
    [
        "Danish"
    ],
    [
        "Djiboutian"
    ],
    [
        "Dominican"
    ],
    [
        "Dutch"
    ],
    [
        "East Timorese"
    ],
    [
        "Ecuadorean"
    ],
    [
        "Egyptian"
    ],
    [
        "Emirati"
    ],
    [
        "English"
    ],
    [
        "Equatorial Guinean"
    ],
    [
        "Eritrean"
    ],
    [
        "Estonian"
    ],
    [
        "Ethiopian"
    ],
    [
        "Faroese"
    ],
    [
        "Fijian"
    ],
    [
        "Filipino"
    ],
    [
        "Finnish"
    ],
    [
        "French"
    ],
    [
        "Gabonese"
    ],
    [
        "Gambian"
    ],
    [
        "Georgian"
    ],
    [
        "German"
    ],
    [
        "Ghanaian"
    ],
    [
        "Gibraltarian"
    ],
    [
        "Greek"
    ],
    [
        "Greenlandic"
    ],
    [
        "Grenadian"
    ],
    [
        "Guamanian"
    ],
    [
        "Guatemalan"
    ],
    [
        "Guinean"
    ],
    [
        "Guyanese"
    ],
    [
        "Haitian"
    ],
    [
        "Honduran"
    ],
    [
        "Hong Konger"
    ],
    [
        "Hungarian"
    ],
    [
        "Icelandic"
    ],
    [
        "Indian"
    ],
    [
        "Indonesian"
    ],
    [
        "Iranian"
    ],
    [
        "Iraqi"
    ],
    [
        "Irish"
    ],
    [
        "Italian"
    ],
    [
        "Ivorian"
    ],
    [
        "Jamaican"
    ],
    [
        "Japanese"
    ],
    [
        "Jordanian"
    ],
    [
        "Kazakh"
    ],
    [
        "Kenyan"
    ],
    [
        "Kittitian"
    ],
    [
        "Kosovan"
    ],
    [
        "Kuwaiti"
    ],
    [
        "Kyrgyz"
    ],
    [
        "Lao"
    ],
    [
        "Latvian"
    ],
    [
        "Lebanese"
    ],
    [
        "Liberian"
    ],
    [
        "Libyan"
    ],
    [
        "Liechtenstein citizen"
    ],
    [
        "Lithuanian"
    ],
    [
        "Luxembourger"
    ],
    [
        "Macanese"
    ],
    [
        "Macedonian"
    ],
    [
        "Malagasy"
    ],
    [
        "Malawian"
    ],
    [
        "Malaysian"
    ],
    [
        "Maldivian"
    ],
    [
        "Malian"
    ],
    [
        "Maltese"
    ],
    [
        "Marshallese"
    ],
    [
        "Martiniquais"
    ],
    [
        "Mauritanian"
    ],
    [
        "Mauritian"
    ],
    [
        "Mexican"
    ],
    [
        "Micronesian"
    ],
    [
        "Moldovan"
    ],
    [
        "Monegasque"
    ],
    [
        "Mongolian"
    ],
    [
        "Montenegrin"
    ],
    [
        "Montserratian"
    ],
    [
        "Moroccan"
    ],
    [
        "Mosotho"
    ],
    [
        "Mozambican"
    ],
    [
        "Namibian"
    ],
    [
        "Nauruan"
    ],
    [
        "Nepalese"
    ],
    [
        "New Zealander"
    ],
    [
        "Nicaraguan"
    ],
    [
        "Nigerian"
    ],
    [
        "Nigerien"
    ],
    [
        "Niuean"
    ],
    [
        "North Korean"
    ],
    [
        "Northern Irish"
    ],
    [
        "Norwegian"
    ],
    [
        "Omani"
    ],
    [
        "Pakistani"
    ],
    [
        "Palauan"
    ],
    [
        "Palestinian"
    ],
    [
        "Panamanian"
    ],
    [
        "Papua New Guinean"
    ],
    [
        "Paraguayan"
    ],
    [
        "Peruvian"
    ],
    [
        "Pitcairn Islander"
    ],
    [
        "Polish"
    ],
    [
        "Portuguese"
    ],
    [
        "Prydeinig"
    ],
    [
        "Puerto Rican"
    ],
    [
        "Qatari"
    ],
    [
        "Romanian"
    ],
    [
        "Russian"
    ],
    [
        "Rwandan"
    ],
    [
        "Salvadorean"
    ],
    [
        "Sammarinese"
    ],
    [
        "Samoan"
    ],
    [
        "Sao Tomean"
    ],
    [
        "Saudi Arabian"
    ],
    [
        "Scottish"
    ],
    [
        "Senegalese"
    ],
    [
        "Serbian"
    ],
    [
        "Sierra Leonean"
    ],
    [
        "Singaporean"
    ],
    [
        "Slovak"
    ],
    [
        "Slovenian"
    ],
    [
        "Solomon Islander"
    ],
    [
        "Somali"
    ],
    [
        "South African"
    ],
    [
        "South Korean"
    ],
    [
        "South Sudanese"
    ],
    [
        "Spanish"
    ],
    [
        "Sri Lankan"
    ],
    [
        "St Helenian"
    ],
    [
        "St Lucian"
    ],
    [
        "Stateless"
    ],
    [
        "Sudanese"
    ],
    [
        "Surinamese"
    ],
    [
        "Swazi"
    ],
    [
        "Swedish"
    ],
    [
        "Swiss"
    ],
    [
        "Syrian"
    ],
    [
        "Taiwanese"
    ],
    [
        "Tajik"
    ],
    [
        "Tanzanian"
    ],
    [
        "Thai"
    ],
    [
        "Togolese"
    ],
    [
        "Tongan"
    ],
    [
        "Trinidadian"
    ],
    [
        "Tristanian"
    ],
    [
        "Tunisian"
    ],
    [
        "Turkish"
    ],
    [
        "Turkmen"
    ],
    [
        "Turks and Caicos Islander"
    ],
    [
        "Tuvaluan"
    ],
    [
        "Ugandan"
    ],
    [
        "Ukrainian"
    ],
    [
        "Uruguayan"
    ],
    [
        "Uzbek"
    ],
    [
        "Vatican citizen"
    ],
    [
        "Venezuelan"
    ],
    [
        "Vietnamese"
    ],
    [
        "Vincentian"
    ],
    [
        "Wallisian"
    ],
    [
        "Welsh"
    ],
    [
        "Yemeni"
    ],
    [
        "Zambian"
    ],
    [
        "Zimbabwean"
    ]
]';

            return array_combine(collect(json_decode($data, true))->collapse()->toArray(), collect(json_decode($data, true))->collapse()->toArray());
        }
    }
