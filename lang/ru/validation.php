<?php

// Общие сообщения проверки форм по-русски. Формы заказа и заявок задают свои
// тексты в FormRequest; эти — для форм без своих (вход покупателя и т.п.).
return [
    'accepted' => 'Нужно отметить «:attribute».',
    'confirmed' => 'Значения «:attribute» не совпадают.',
    'email' => 'Проверьте :attribute.',
    'max' => [
        'string' => ':Attribute — не длиннее :max символов.',
    ],
    'min' => [
        'string' => ':Attribute — не короче :min символов.',
    ],
    'required' => 'Укажите :attribute.',
    'string' => ':Attribute должен быть строкой.',
    'unique' => 'Такой :attribute уже используется.',

    'attributes' => [
        'email' => 'e-mail',
        'password' => 'пароль',
        'name' => 'имя',
        'phone' => 'телефон',
        'token' => 'ссылку восстановления',
    ],
];
