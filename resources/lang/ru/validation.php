<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Языковые ресурсы для проверки значений
    |--------------------------------------------------------------------------
    |
    | Последующие языковые строки содержат сообщения по-умолчанию, используемые
    | классом, проверяющим значения (валидатором). Некоторые из правил имеют
    | несколько версий, например, size. Вы можете поменять их на любые
    | другие, которые лучше подходят для вашего приложения.
    |
    */

    'accepted'             => 'Вы должны принять :attribute.',
    'active_url'           => 'Поле ":attribute" содержит недействительный URL.',
    'after'                => 'В поле ":attribute" должна быть дата больше :date.',
    'after_or_equal'       => 'В поле ":attribute" должна быть дата больше или равняться :date.',
    'alpha'                => 'Поле ":attribute" может содержать только буквы.',
    'alpha_dash'           => 'Поле ":attribute" может содержать только буквы, цифры, дефис и нижнее подчеркивание.',
    'alpha_num'            => 'Поле ":attribute" может содержать только буквы и цифры.',
    'array'                => 'Поле ":attribute" должно быть массивом.',
    'before'               => 'В поле ":attribute" должна быть дата раньше :date.',
    'before_or_equal'      => 'В поле ":attribute" должна быть дата раньше или равняться :date.',
    'between'              => [
        'numeric' => 'Поле ":attribute" должно быть между :min и :max.',
        'file'    => 'Размер файла в поле ":attribute" должен быть между :min и :max килобайт(а).',
        'string'  => 'Количество символов в поле ":attribute" должно быть между :min и :max.',
        'array'   => 'Количество элементов в поле ":attribute" должно быть между :min и :max.',
    ],
    'boolean'              => 'Поле ":attribute" должно иметь значение логического типа.',
    'confirmed'            => ':attribute не совпадает с подтверждением.',
    'date'                 => 'Поле ":attribute" не является датой.',
    'date_equals'          => 'Поле ":attribute" должно быть датой равной :date.',
    'date_format'          => 'Поле ":attribute" не соответствует формату :format.',
    'different'            => 'Поля :attribute и :other должны различаться.',
    'digits'               => 'Длина цифрового поля :attribute должна быть :digits.',
    'digits_between'       => 'Длина цифрового поля :attribute должна быть между :min и :max.',
    'dimensions'           => 'Поле ":attribute" имеет недопустимые размеры изображения.',
    'distinct'             => 'Поле ":attribute" содержит повторяющееся значение.',
    'email'                => ':attribute должно быть действительным электронным адресом.',
    'ends_with'            => 'Поле ":attribute" должно заканчиваться одним из следующих значений: :values',
    'exists'               => 'Выбранное значение для :attribute некорректно.',
    'file'                 => 'Поле ":attribute" должно быть файлом.',
    'filled'               => 'Поле ":attribute" обязательно для заполнения.',
    'gt'                   => [
        'numeric' => 'Поле ":attribute" должно быть больше :value.',
        'file'    => 'Размер файла в поле ":attribute" должен быть больше :value килобайт(а).',
        'string'  => 'Количество символов в поле ":attribute" должно быть больше :value.',
        'array'   => 'Количество элементов в поле ":attribute" должно быть больше :value.',
    ],
    'gte'                  => [
        'numeric' => 'Поле ":attribute" должно быть :value или больше.',
        'file'    => 'Размер файла в поле ":attribute" должен быть :value килобайт(а) или больше.',
        'string'  => 'Количество символов в поле ":attribute" должно быть :value или больше.',
        'array'   => 'Количество элементов в поле ":attribute" должно быть :value или больше.',
    ],
    'image'                => 'Поле ":attribute" должно быть изображением.',
    'in'                   => 'Выбранное значение для :attribute ошибочно.',
    'in_array'             => 'Поле ":attribute" не существует в :other.',
    'integer'              => 'Поле ":attribute" должно быть целым числом.',
    'ip'                   => 'Поле ":attribute" должно быть действительным IP-адресом.',
    'ipv4'                 => 'Поле ":attribute" должно быть действительным IPv4-адресом.',
    'ipv6'                 => 'Поле ":attribute" должно быть действительным IPv6-адресом.',
    'json'                 => 'Поле ":attribute" должно быть JSON строкой.',
    'lt'                   => [
        'numeric' => 'Поле ":attribute" должно быть меньше :value.',
        'file'    => 'Размер файла в поле ":attribute" должен быть меньше :value килобайт(а).',
        'string'  => 'Количество символов в поле ":attribute" должно быть меньше :value.',
        'array'   => 'Количество элементов в поле ":attribute" должно быть меньше :value.',
    ],
    'lte'                  => [
        'numeric' => 'Поле ":attribute" должно быть :value или меньше.',
        'file'    => 'Размер файла в поле ":attribute" должен быть :value Килобайт(а) или меньше.',
        'string'  => 'Количество символов в поле ":attribute" должно быть :value или меньше.',
        'array'   => 'Количество элементов в поле ":attribute" должно быть :value или меньше.',
    ],
    'max'                  => [
        'numeric' => 'Поле ":attribute" не может быть больше :max.',
        'file'    => 'Размер файла в поле ":attribute" не может быть больше :max килобайт(а).',
        'string'  => 'Количество символов в поле ":attribute" не может превышать :max.',
        'array'   => 'Количество элементов в поле ":attribute" не может превышать :max.',
    ],

    // Эта кастомная валидация создана в Аpp/Providers/AppServiceProvider.php 
    'max_mb' => 'Размер файла не может быть больше :max_mb мегабайт(а).',

    'mimes'                => 'Файл должен быть формата: :values.',
    'mimetypes'            => 'Файл должен быть формата: :values.',
    'min'                  => [
        'numeric' => 'Поле ":attribute" должно быть не меньше :min.',
        'file'    => 'Размер файла в поле ":attribute" должен быть не меньше :min килобайт(а).',
        'string'  => 'Количество символов в поле ":attribute" должно быть не меньше :min.',
        'array'   => 'Количество элементов в поле ":attribute" должно быть не меньше :min.',
    ],
    'multiple_of'          => 'Значение поля :attribute должно быть кратным :value',
    'not_in'               => 'Выбранное значение для :attribute ошибочно.',
    'not_regex'            => 'Выбранный формат для :attribute ошибочный.',
    'numeric'              => 'Поле ":attribute" должно быть числом.',
    'password'             => 'Неверный пароль.',
    'present'              => 'Поле ":attribute" должно присутствовать.',
    'regex'                => 'Поле ":attribute" имеет ошибочный формат.',
    'required'             => 'Заполните поле ":attribute"',
    'required_if'          => 'Заполните поле ":attribute"',
    'required_unless'      => 'Поле ":attribute" обязательно для заполнения',
    'required_with'        => 'Поле ":attribute" обязательно для заполнения, когда :values указано.',
    'required_with_all'    => 'Поле ":attribute" обязательно для заполнения, когда :values указано.',
    'required_without'     => 'Поле ":attribute" обязательно для заполнения, когда :values не указано.',
    'required_without_all' => 'Поле ":attribute" обязательно для заполнения, когда ни одно из :values не указано.',
    'same'                 => 'Значения полей ":attribute" и ":other" должны совпадать.',
    'size'                 => [
        'numeric' => 'Поле ":attribute" должно быть равным :size.',
        'file'    => 'Размер файла в поле ":attribute" должен быть равен :size килобайт(а).',
        'string'  => 'Количество символов в поле ":attribute" должно быть равным :size.',
        'array'   => 'Количество элементов в поле ":attribute" должно быть равным :size.',
    ],
    'starts_with'          => 'Поле ":attribute" должно начинаться из одного из следующих значений: :values',
    'string'               => 'Поле ":attribute" должно быть строкой.',
    'timezone'             => 'Поле ":attribute" должно быть действительным часовым поясом.',
    'unique'               => 'Поле ":attribute" с таким значением уже существует.',
    'uploaded'             => 'Загрузка поля :attribute не удалась.',
    'url'                  => 'Поле ":attribute" имеет ошибочный формат URL.',
    'uuid'                 => 'Поле ":attribute" должно быть корректным UUID.',

    /*
    |--------------------------------------------------------------------------
    | Собственные языковые ресурсы для проверки значений
    |--------------------------------------------------------------------------
    |
    | Здесь Вы можете указать собственные сообщения для атрибутов.
    | Это позволяет легко указать свое сообщение для заданного правила атрибута.
    |
    | http://laravel.com/docs/validation#custom-error-messages
    | Пример использования
    |
    |   'custom' => [
    |       'email' => [
    |           'required' => 'Нам необходимо знать Ваш электронный адрес!',
    |       ],
    |   ],
    |
    */

    'custom' => [
        'favicon_file' => [
            'dimensions' => 'Ширина и высота иконки должно быть 192x192 пикселей'
        ],
        'school_domain' => [
            'regex' => 'Домен должен состоять только из строчных латинских букв без пробела'
        ],
        'video_file' => [
            'required' => 'Выберите видеофайл',
            'required_if' => 'Выберите видеофайл',
        ],
        'selected_video_id' => [
            'required' => 'Выберите видеофайл',
            'required_if' => 'Выберите видеофайл',
        ],
        'audio_file' => [
            'required' => 'Выберите аудиофайл',
            'required_if' => 'Выберите аудиофайл',
        ],
        'selected_audio_id' => [
            'required' => 'Выберите аудиофайл',
            'required_if' => 'Выберите аудиофайл',
        ],
        'image_file' => [
            'required' => 'Выберите файл изображения',
            'required_if' => 'Выберите файл изображения',
        ],
        'selected_image_id' => [
            'required' => 'Выберите файл изображения',
            'required_if' => 'Выберите файл изображения',
        ],
        'roles_count' => [
            'min' => 'Количество ролей пользователя должно быть не меньше :min',
        ],
        'course_id' => [
            'required' => 'Выберите курс',
        ],

        'level_id' => [
            'required' => 'Выберите категорию',
        ],

        'section_id' => [
            'required' => 'Выберите главу',
        ],

        'lesson_id' => [
            'required' => 'Выберите урок',
        ],

        'course_mentors_count' => [
            'min' => 'Добавьте кураторов к данному курсу',
        ],
        'members_count' => [
            'min' => 'Добавьте участников в группу',
        ],
        'mentor_id' => [
            'required' => 'Выберите куратора',
        ],
        'group_category_id' => [
            'required' => 'Выберите категорию группы',
        ],
        'task_answer' => [
            'required' => 'Дайте ответ на задание',
        ],
        'words_count' => [
            'min' => 'Минимальное количество выбранных слов должно быть :min',
        ],
        'generate_new_word_audio_file' => [
            'required' => 'Сгенерируйте аудиофайл для слова',
        ],
        'generate_edit_word_audio_file' => [
            'required' => 'Сгенерируйте аудиофайл для слова',
        ],
        'sentences_count' => [
            'min' => 'Минимальное количество выбранных фраз должно быть :min',
        ],
        'generate_new_sentence_audio_file' => [
            'required' => 'Сгенерируйте аудиофайл для фразы',
        ],
        'generate_edit_sentence_audio_file' => [
            'required' => 'Сгенерируйте аудиофайл для фразы',
        ],
        'sections_count' => [
            'min' => 'Минимальное количество созданных секций должно быть :min',
        ],
        'seconds_per_word' => [
            'required' => 'Укажите количество секунд, отведенное на поиск каждого слова',
        ],
        'seconds_per_sentence' => [
            'required' => 'Укажите количество секунд, отведенное на поиск каждого Фраза',
        ],
        'seconds_per_section' => [
            'required' => 'Укажите количество секунд, отведенное на поиск каждой секции',
        ],

        'seconds_per_question' => [
            'required' => 'Укажите количество секунд, отведенное на ответ каждого вопроса',
        ],

        'start_date' => [
            'after_or_equal' => 'Дата начала должна быть равна сегодняшней или поздней.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Собственные названия атрибутов
    |--------------------------------------------------------------------------
    |
    | Последующие строки используются для подмены программных имен элементов
    | пользовательского интерфейса на удобочитаемые. Например, вместо имени
    | поля "email" в сообщениях будет выводиться "электронный адрес".
    |
    | Пример использования
    |
    |   'attributes' => [
    |       'email' => 'электронный адрес',
    |   ],
    |
    */

    'attributes' => [
        'name'                  => 'Имя',
        'username'              => 'Никнейм',
        'email'                 => 'E-Mail адрес',
        'first_name'            => 'Имя',
        'last_name'             => 'Фамилия',
        'login'                 => 'Логин', 
        'iin'                   => 'ИИН',
        'password'              => 'Пароль',
        'password_confirmation' => 'Подтверждение пароля',

        'school_name'           => 'Название школы',
        'school_domain'         => 'Доменное имя школы',

        'recovery_code'         => 'Код восстановления пароля',

        'start_date'            => 'Дата начала занятии',
        'start_time'            => 'Время начала занятии',

        'partner_name'          => 'Наименование организации',
        'partner_org_name'      => 'Полное наименование организации',
        'partner_bin'           => 'БИН организации',
        'partner_email'         => 'E-Mail организации',
        'partner_phone'         => 'Телефон организации',

        'organization_id'       => 'Наименование организации',

        'service_title'         => 'Название услуги',
        'service_description'   => 'Описание услуги',
        'category_id'           => 'Категория',

        'current_password'      => 'Текущий пароль',
        'course_name'           => 'Название курса',
        'course_description'    => 'Описание курса',
        'course_content'        => 'Полное описание курса',
        'course_category_id'    => 'Категория курса',
        'course_lang_id'        => 'Язык курса',
        'level_type_id'         => 'Уровень прохождения курса',
        'section_name'          => 'Название главы',
        'author_id'             => 'Автор курса',
        'course_cost'           => 'Стоимость курса',
        'course_poster_file'    => 'Обложка курса',
        'new_course_poster_file'    => 'Обложка курса',
        'review'                => 'Отзыв',

        'word'                  => 'Слово',
        'transcription'         => 'Транскрипция слова',
        'word_kk'               => 'Перевод слова на казахском языке',
        'word_ru'               => 'Перевод слова на русском языке',

        'sentence'              => 'Фраза',
        'sentence_kk'           => 'Перевод фразы на казахском языке',
        'sentence_ru'           => 'Перевод фразы на русском языке',

        'lesson_name'           => 'Название урока',
        'lesson_description'    => 'Описание урока',
        'lesson_type_id'        => 'Тип урока',
        'annotation'            => 'Аннотация',

        'file_name'             => 'Название файла',
        'video_name'            => 'Название видео',
        'audio_name'            => 'Название аудио',

        'image_name'            => 'Название изображения',

        'task_name'             => 'Название задания',
        'task_name_kk'          => 'Название задания на казахском языке',
        'task_name_ru'          => 'Название задания на русском языке',
        'task_slug'             => 'Название задания на основном языке',
        'task_description'      => 'Описание задания',

        'group_name'            => 'Название группы',

        'flat'                  => 'Квартира',
        'house'                 => 'Дом',
        'street'                => 'Улица',
        'city'                  => 'Город',
        'region'                => 'Регион',
        'country'               => 'Страна',
        'address'               => 'Адрес',
        'phone'                 => 'Телефон',
        'phone_additional'      => 'Дополнительный телефон',
        'mobile'                => 'Моб. номер',
        'age'                   => 'Возраст',
        'sex'                   => 'Пол',
        'gender'                => 'Пол',
        'day'                   => 'День',
        'month'                 => 'Месяц',
        'year'                  => 'Год',
        'hour'                  => 'Час',
        'minute'                => 'Минута',
        'second'                => 'Секунда',
        'title'                 => 'Наименование',
        'content'               => 'Контент',
        'description'           => 'Описание',
        'excerpt'               => 'Выдержка',
        'date'                  => 'Дата',
        'time'                  => 'Время',
        'available'             => 'Доступно',
        'size'                  => 'Размер',
        'seconds_per_word'      => 'Количество секунд, отведенное на поиск каждого слова',
        'seconds_per_sentence'  => 'Количество секунд, отведенное на поиск каждого фразы',
        'seconds_per_section'   => 'Количество секунд, отведенное на поиск каждой секции',
        'seconds_per_question'  => 'Количество секунд, отведенное на ответ каждого вопроса',
    ],
];
