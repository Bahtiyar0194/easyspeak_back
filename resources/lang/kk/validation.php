<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted'             => ' :attribute қабылдануы керек.',
    'active_url'           => ' :attribute жарамды URL мекенжайы емес.',
    'after'                => ' :attribute мәні :date күнінен кейінгі күн болуы керек.',
    'after_or_equal'       => ' :attribute мәні :date күнінен кейінгі күн немесе тең болуы керек.',
    'alpha'                => ' :attribute тек әріптерден тұруы керек.',
    'alpha_dash'           => ' :attribute тек әріптерден, сандардан және сызықшалардан тұруы керек.',
    'alpha_num'            => ' :attribute тек әріптерден және сандардан тұруы керек.',
    'array'                => ' :attribute жиым болуы керек.',
    'before'               => ' :attribute мәні :date күнінен дейінгі күн болуы керек.',
    'before_or_equal'      => ' :attribute мәні :date күнінен дейінгі күн немесе тең болуы керек.',
    'between'              => [
        'numeric' => ' :attribute мәні :min және :max аралығында болуы керек.',
        'file'    => ' :attribute көлемі :min және :max килобайт аралығында болуы керек.',
        'string'  => ' :attribute тармағы :min және :max аралығындағы таңбалардан тұруы керек.',
        'array'   => ' :attribute жиымы :min және :max аралығындағы элементтерден тұруы керек.',
    ],
    'boolean'              => ' :attribute жолы шын немесе жалған мән болуы керек.',
    'confirmed'            => ' :attribute растауы сәйкес емес.',
    'date'                 => ' :attribute жарамды күн емес.',
    'date_equals'          => ' :attribute мәні :date күнімен тең болуы керек.',
    'date_format'          => ' :attribute пішімі :format пішіміне сай емес.',
    'different'            => ' :attribute және :other әр түрлі болуы керек.',
    'digits'               => ' :attribute мәні :digits сан болуы керек.',
    'digits_between'       => ' :attribute мәні :min және :max аралығындағы сан болуы керек.',
    'dimensions'           => ' :attribute сурет өлшемі жарамсыз.',
    'distinct'             => ' :attribute жолында қосарланған мән бар.',
    'email'                => ' :attribute жарамды электрондық пошта мекенжайы болуы керек.',
    'ends_with'            => ' :attribute келесі мәндердің біреуінен аяқталуы керек: :values',
    'exists'               => ' таңдалған :attribute жарамсыз.',
    'file'                 => ' :attribute файл болуы тиіс.',
    'filled'               => ' :attribute жолы толтырылуы керек.',
    'gt'                   => [
        'numeric' => ' :attribute мәні :value үлкен болуы керек.',
        'file'    => ' :attribute файл өлшемі :value килобайттан үлкен болуы керек.',
        'string'  => ' :attribute мәні :value таңбалардан үлкен болуы керек.',
        'array'   => ' :attribute мәні :value элементтерден үлкен болуы керек.',
    ],
    'gte'                  => [
        'numeric' => ' :attribute мәні :value үлкен немесе тең болуы керек.',
        'file'    => ' :attribute файл өлшемі :value килобайттан үлкен немесе тең болуы керек.',
        'string'  => ' :attribute мәні :value таңбалардан үлкен немесе тең болуы керек.',
        'array'   => ' :attribute мәні :value элементтерден үлкен немесе тең болуы керек.',
    ],
    'image'                => ' :attribute кескін болуы керек.',
    'in'                   => ' таңдалған :attribute жарамсыз.',
    'in_array'             => ' :attribute жолы :other ішінде жоқ.',
    'integer'              => ' :attribute бүтін сан болуы керек.',
    'ip'                   => ' :attribute жарамды IP мекенжайы болуы керек.',
    'ipv4'                 => ' :attribute жарамды IPv4 мекенжайы болуы керек.',
    'ipv6'                 => ' :attribute жарамды IPv6 мекенжайы болуы керек.',
    'json'                 => ' :attribute жарамды JSON тармағы болуы керек.',
    'lt'                   => [
        'numeric' => ' :attribute мәні :value кіші болуы керек.',
        'file'    => ' :attribute файл өлшемі :value килобайттан кіші болуы керек.',
        'string'  => ' :attribute мәні :value таңбалардан кіші болуы керек.',
        'array'   => ' :attribute мәні :value элементтерден кіші болуы керек.',
    ],
    'lte'                  => [
        'numeric' => ' :attribute мәні :value кіші немесе тең болуы керек.',
        'file'    => ' :attribute файл өлшемі :value килобайттан кіші неменсе тең болуы керек.',
        'string'  => ' :attribute мәні :value таңбалардан кіші немесе тең болуы керек.',
        'array'   => ' :attribute мәні :value элементтерден кіші немесе тең болуы керек.',
    ],
    'max'                  => [
        'numeric' => ' :attribute мәні :max мәнінен көп болмауы керек.',
        'file'    => ' :attribute :max килобайттан көп болмауы керек.',
        'string'  => ' :attribute тармағы :max таңбадан ұзын болмауы керек.',
        'array'   => ' :attribute жиымының құрамы :max элементтен аспауы керек.',
    ],

    // Эта кастомная валидация создана в Аpp/Providers/AppServiceProvider.php 
    'max_mb' => 'Файлдың өлшемі :max_mb мегабайттан аспауы керек.',

    'mimes'                => 'Файлдың түрі: :values болуы керек.',
    'mimetypes'            => 'Файлдың түрі: :values болуы керек.',
    'min'                  => [
        'numeric' => ' :attribute кемінде :min болуы керек.',
        'file'    => ' :attribute көлемі кемінде :min килобайт болуы керек.',
        'string'  => ' :attribute кемінде :min таңбадан тұруы керек.',
        'array'   => ' :attribute кемінде :min элементтен тұруы керек.',
    ],
    'multiple_of'          => 'The :attribute must be a multiple of :value',
    'not_in'               => ' таңдалған :attribute жарамсыз.',
    'not_regex'            => ' таңдалған :attribute форматы жарамсыз.',
    'numeric'              => ' :attribute сан болуы керек.',
    'password'             => 'Қате құпиясөз.',
    'present'              => ' :attribute болуы керек.',
    'regex'                => ' :attribute пішімі жарамсыз.',
    'required'             => ' :attribute толық болуы керек.',
    'required_if'          => ' :attribute толық болуы керек.',
    'required_unless'      => ' :attribute толық болуы керек',
    'required_with'        => ' :attribute жолы :values болғанда толтырылуы керек.',
    'required_with_all'    => ' :attribute жолы :values болғанда толтырылуы керек.',
    'required_without'     => ' :attribute жолы :values болмағанда толтырылуы керек.',
    'required_without_all' => ' :attribute жолы ешбір :values болмағанда толтырылуы керек.',
    'same'                 => ' :attribute және :other сәйкес болуы керек.',
    'size'                 => [
        'numeric' => ' :attribute көлемі :size болуы керек.',
        'file'    => ' :attribute көлемі :size килобайт болуы керек.',
        'string'  => ' :attribute тармағы :size таңбадан тұруы керек.',
        'array'   => ' :attribute жиымы :size элементтен тұруы керек.',
    ],
    'starts_with'          => ' :attribute келесі мәндердің біреуінен басталуы керек: :values',
    'string'               => ' :attribute тармақ болуы керек.',
    'timezone'             => ' :attribute жарамды аймақ болуы керек.',
    'unique'               => ' :attribute бұрын алынған.',
    'uploaded'             => ' :attribute жүктелуі сәтсіз аяқталды.',
    'url'                  => ' :attribute пішімі жарамсыз.',
    'uuid'                 => ' :attribute мәні жарамды UUID болуы керек.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'favicon_file' => [
            'dimensions' => 'Белгішенің ені мен биіктігі 192x192 пиксель болуы керек'
        ],
        'school_domain' => [
            'regex' => 'Домен тек кіші латын әріптерінен тұруы керек'
        ],
        'video_file' => [
            'required' => 'Cіз бейнежазбаны таңдауыңыз қажет',
            'required_if' => 'Cіз бейнежазбаны таңдауыңыз қажет',
        ],
        'selected_video_id' => [
            'required' => 'Cіз бейнежазбаны таңдауыңыз қажет',
            'required_if' => 'Cіз бейнежазбаны таңдауыңыз қажет',
        ],
        'audio_file' => [
            'required' => 'Сіз аудиожазбаны таңдауыңыз қажет',
            'required_if' => 'Сіз аудиожазбаны таңдауыңыз қажет',
        ],
        'selected_audio_id' => [
            'required' => 'Сіз аудиожазбаны таңдауыңыз қажет',
            'required_if' => 'Сіз аудиожазбаны таңдауыңыз қажет',
        ],
        'image_file' => [
            'required' => 'Cіз суретті таңдауыңыз қажет',
            'required_if' => 'Cіз суретті таңдауыңыз қажет',
        ],
        'selected_image_id' => [
            'required' => 'Cіз суретті таңдауыңыз қажет',
            'required_if' => 'Cіз кескінді таңдауыңыз қажет',
        ],
        'roles_count' => [
            'min' => 'Қолданушы рөлдерінің саны :min-ден кем болмауы керек',
        ],
        'course_id' => [
            'required' => 'Курсты таңдаңыз',
        ],

        'level_id' => [
            'required' => 'Санатты таңдаңыз',
        ],

        'section_id' => [
            'required' => 'Тарауды таңдаңыз',
        ],

        'lesson_id' => [
            'required' => 'Сабақты таңдаңыз',
        ],

        'course_mentors_count' => [
            'min' => 'Кураторларды қосыңыз',
        ],
        'members_count' => [
            'min' => 'Топқа қатысушыларды қосыңыз',
        ],
        'mentor_id' => [
            'required' => 'Тәлімгерді таңдаңыз',
        ],
        'group_category_id' => [
            'required' => 'Топтың санатын таңдаңыз',
        ],
        'task_answer' => [
            'required' => 'Тапсырмаға жауап беріңіз',
        ],
        'words_count' => [
            'min' => 'Кем дегенде тізімнен :min сөз таңдауыңыз қажет',
        ],

        'generate_new_word_audio_file' => [
            'required' => 'Сөзге дыбыс жазбасын генерациялау қажет',
        ],

        'generate_edit_word_audio_file' => [
            'required' => 'Сөзге дыбыс жазбасын генерациялау қажет',
        ],

        'sentences_count' => [
            'min' => 'Кем дегенде тізімнен :min сөйлем таңдауыңыз қажет',
        ],

        'generate_new_sentence_audio_file' => [
            'required' => 'Cөйлемге дыбыс жазбасын генерациялау қажет',
        ],

        'generate_edit_sentence_audio_file' => [
            'required' => 'Cөйлемге дыбыс жазбасын генерациялау қажет',
        ],

        'sections_count' => [
            'min' => 'Кем дегенде :min бөлім қосуыңыз қажет',
        ],

        'seconds_per_word' => [
            'required' => 'Әр сөзді табуға бөлінген секунд санын көрсету қажет',
        ],
        'seconds_per_sentence' => [
            'required' => 'Әр сөйлемді табуға бөлінген секунд санын көрсету қажет',
        ],
        'seconds_per_section' => [
            'required' => 'Әр бөлімді табуға бөлінген секунд санын көрсету қажет',
        ],

        'seconds_per_question' => [
            'required' => 'Әр сұраққа жауап беруге бөлінген секунд санын көрсету қажет',
        ],

        'start_date' => [
            'after_or_equal' => 'Басталу күні бүгінгі күнге тең немесе кейінгі болуы керек.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [
        'name'                  => 'Аты',
        'username'              => 'Тегіңіз',
        'email'                 => 'Электрондық пошта',
        'first_name'            => 'Атыңыз',
        'last_name'             => 'Тегіңіз',
        'login'                 => 'Логин', 
        'iin'                   => 'ЖСН',
        'password'              => 'Құпиясөз',
        'password_confirmation' => 'Құпиясөзді растау',

        'recovery_code'         => 'Құпиясөзді қалпына келтіру коды',

        'start_date'            => 'Сабақтың басталу күні',
        'start_time'            => 'Сабақтың басталу уақыты',

        'school_name'           => 'Мектептің атауы',
        'school_domain'         => 'Мектептің домендік атауы',

        'partner_name'          => 'Ұйымның атауы',
        'partner_org_name'      => 'Ұйымның толық атауы',
        'partner_bin'           => 'БСН',
        'partner_email'         => 'Ұйымның электрондық поштасы',
        'partner_phone'         => 'Ұйымның телефон нөмірі',

        'organization_id'       => 'Ұйымның атауы',

        'service_title'         => 'Қызметтің атауы',
        'service_description'   => 'Қызметтің сипаттамасы',
        'category_id'           => 'Санат',

        'course_name'           => 'Курстың атауы',
        'course_description'    => 'Курстың қысқаша сипаттамасы',
        'course_content'        => 'Курстың толық сипаттамасы',
        'course_category_id'    => 'Курстың санаты',
        'course_lang_id'        => 'Курстың тілі',
        'level_type_id'         => 'Курстың өту деңгейі',
        'section_name'          => 'Тараудың атауы',
        'author_id'             => 'Курстың авторы',
        'course_cost'           => 'Курстың бағасы',
        'course_poster_file'    => 'Курстың мұқабасы',
        'new_course_poster_file'  => 'Курстың мұқабасы',
        'review'                => 'Пікір',

        'word'                  => 'Сөз',
        'transcription'         => 'Сөздің транскрипциясы',
        'word_kk'               => 'Сөздің қазақша аудармасы',
        'word_ru'               => 'Сөздің орысша аудармасы',

        'sentence'                  => 'Сөйлем',
        'sentence_kk'               => 'Сөйлемнің қазақша аудармасы',
        'sentence_ru'               => 'Сөйлемнің орысша аудармасы',

        'lesson_name'           => 'Сабақтың атауы',
        'lesson_description'    => 'Сабақтың қысқаша сипаттамасы',
        'lesson_type_id'        => 'Сабақтың түрі',
        'annotation'            => 'Аннотация',

        'file_name'             => 'Файлдың аты',
        'video_name'            => 'Бейнежазбаның аты',
        'audio_name'            => 'Aудиожазбаның аты',
        'image_name'            => 'Кескіннің аты',

        'task_name'             => 'Тапсырманың аты',
        'task_name_kk'          => 'Тапсырманың қазақша атауы',
        'task_name_ru'          => 'Тапсырманың орысша атауы',
        'task_slug'             => 'Тапсырманың негізгі тілдегі атауы',
        'task_description'      => 'Тапсырманың қысқаша сипаттамасы',

        'group_name'            => 'Топтың атауы',

        'flat'                  => 'Пәтер',
        'house'                 => 'Үй',
        'street'                => 'Көше',
        'city'                  => 'Қала',
        'country'               => 'Ел',
        'region'                => 'Өңір',
        'address'               => 'Мекенжай',
        'phone'                 => 'Телефон',
        'phone_additional'      => 'Қосымша телефон',
        'mobile'                => 'Моб. нөмір',
        'age'                   => 'Жасы',
        'sex'                   => 'Жынысы',
        'gender'                => 'Жынысы',
        'day'                   => 'Күн',
        'month'                 => 'Ай',
        'year'                  => 'Жыл',
        'hour'                  => 'Сағат',
        'minute'                => 'Минут',
        'second'                => 'Секунд',
        'title'                 => 'Атауы',
        'content'               => 'Контент',
        'description'           => 'Сипаттамасы',
        'excerpt'               => 'Үзінді',
        'date'                  => 'Күні',
        'time'                  => 'Уақыт',
        'available'             => 'Қолжетімді',
        'size'                  => 'Көлемі',
        'seconds_per_word'      => 'Әр сөзді табуға бөлінген секунд саны',
        'seconds_per_sentence'  => 'Әр сөйлемді табуға бөлінген секунд саны',
        'seconds_per_section'   => 'Әр бөлімді табуға бөлінген секунд саны',
        'seconds_per_question'  => 'Әр сұраққа жауап беруге бөлінген секунд саны'
    ],
];
