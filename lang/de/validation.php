<?php

declare(strict_types=1);

return [
    'required' => ':attribute muss ausgefüllt werden.',
    'string' => ':attribute muss ein Text sein.',
    'date' => ':attribute ist kein gültiges Datum.',
    'date_format' => ':attribute entspricht nicht dem Format :format.',
    'different' => ':attribute und :other müssen sich unterscheiden.',
    'after_or_equal' => ':attribute muss ein Datum nach oder gleich :date sein.',
    'enum' => ':attribute ist ungültig.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'confirmed' => ':attribute stimmt nicht mit der Wiederholung überein.',
    'current_password' => 'Das aktuelle Passwort ist falsch.',
    'lowercase' => ':attribute darf nur Kleinbuchstaben enthalten.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Zahl enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute kommt in einem Datenleck vor. Bitte ein anderes wählen.',
    ],
    'integer' => ':attribute muss eine Zahl sein.',
    'max' => [
        'string' => ':attribute darf maximal :max Zeichen lang sein.',
        'numeric' => ':attribute darf maximal :max sein.',
    ],
    'min' => [
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
    ],

    'attributes' => [
        'type' => 'Art',
        'date' => 'Datum',
        'starts_at' => 'Startzeit',
        'ends_at' => 'Endzeit',
        'note' => 'Notiz',
        'from' => 'Datum',
        'name' => 'Name',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'current_password' => 'Aktuelles Passwort',
        'weekly_hours' => 'Stunden pro Woche',
        'working_days' => 'Tage pro Woche',
        'theme' => 'Farbschema',
        'locale' => 'Sprache',
        'design_style' => 'Designmuster',
        'holiday_region' => 'Bundesland',
        'vacation_days' => 'Urlaubstage',
        'title' => 'Aufgabe',
    ],
];
