<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

final class Holidays
{
    /** Extra holidays per federal state on top of the nationwide ones. */
    private const array REGIONAL = [
        'BW' => ['epiphany', 'corpus_christi', 'all_saints'],
        'BY' => ['epiphany', 'corpus_christi', 'all_saints'],
        'BE' => ['womens_day'],
        'BB' => ['reformation'],
        'HB' => ['reformation'],
        'HH' => ['reformation'],
        'HE' => ['corpus_christi'],
        'MV' => ['womens_day', 'reformation'],
        'NI' => ['reformation'],
        'NW' => ['corpus_christi', 'all_saints'],
        'RP' => ['corpus_christi', 'all_saints'],
        'SL' => ['corpus_christi', 'assumption', 'all_saints'],
        'SN' => ['reformation', 'repentance'],
        'ST' => ['epiphany', 'reformation'],
        'SH' => ['reformation'],
        'TH' => ['world_childrens_day', 'reformation'],
    ];

    private const array REGION_LABELS = [
        'BW' => 'Baden-Württemberg', 'BY' => 'Bayern', 'BE' => 'Berlin', 'BB' => 'Brandenburg',
        'HB' => 'Bremen', 'HH' => 'Hamburg', 'HE' => 'Hessen', 'MV' => 'Mecklenburg-Vorpommern',
        'NI' => 'Niedersachsen', 'NW' => 'Nordrhein-Westfalen', 'RP' => 'Rheinland-Pfalz',
        'SL' => 'Saarland', 'SN' => 'Sachsen', 'ST' => 'Sachsen-Anhalt',
        'SH' => 'Schleswig-Holstein', 'TH' => 'Thüringen',
    ];

    /** @return array<string, string> date => name */
    public function forYear(int $year, string $region = 'NW'): array
    {
        $easter = Carbon::createFromTimestamp(easter_date($year), config('app.timezone'))->startOfDay();

        $all = [
            'new_year' => [Carbon::create($year, 1, 1), 'Neujahr'],
            'epiphany' => [Carbon::create($year, 1, 6), 'Heilige Drei Könige'],
            'womens_day' => [Carbon::create($year, 3, 8), 'Internationaler Frauentag'],
            'good_friday' => [$easter->copy()->subDays(2), 'Karfreitag'],
            'easter_monday' => [$easter->copy()->addDay(), 'Ostermontag'],
            'labour_day' => [Carbon::create($year, 5, 1), 'Tag der Arbeit'],
            'ascension' => [$easter->copy()->addDays(39), 'Christi Himmelfahrt'],
            'whit_monday' => [$easter->copy()->addDays(50), 'Pfingstmontag'],
            'corpus_christi' => [$easter->copy()->addDays(60), 'Fronleichnam'],
            'assumption' => [Carbon::create($year, 8, 15), 'Mariä Himmelfahrt'],
            'world_childrens_day' => [Carbon::create($year, 9, 20), 'Weltkindertag'],
            'unity_day' => [Carbon::create($year, 10, 3), 'Tag der Deutschen Einheit'],
            'reformation' => [Carbon::create($year, 10, 31), 'Reformationstag'],
            'all_saints' => [Carbon::create($year, 11, 1), 'Allerheiligen'],
            'repentance' => [$this->repentanceDay($year), 'Buß- und Bettag'],
            'christmas' => [Carbon::create($year, 12, 25), '1. Weihnachtstag'],
            'boxing_day' => [Carbon::create($year, 12, 26), '2. Weihnachtstag'],
        ];

        $nationwide = ['new_year', 'good_friday', 'easter_monday', 'labour_day', 'ascension', 'whit_monday', 'unity_day', 'christmas', 'boxing_day'];
        $keys = array_merge($nationwide, self::REGIONAL[$region] ?? self::REGIONAL['NW']);

        $holidays = [];

        foreach ($keys as $key) {
            [$date, $name] = $all[$key];
            $holidays[$date->toDateString()] = $name;
        }

        ksort($holidays);

        return $holidays;
    }

    /** @return array<string, string> */
    public function between(Carbon $from, Carbon $to, string $region = 'NW'): array
    {
        $holidays = [];

        for ($year = $from->year; $year <= $to->year; $year++) {
            foreach ($this->forYear($year, $region) as $date => $name) {
                if ($date >= $from->toDateString() && $date <= $to->toDateString()) {
                    $holidays[$date] = $name;
                }
            }
        }

        return $holidays;
    }

    /** @return array<string, string> */
    public static function regions(): array
    {
        return self::REGION_LABELS;
    }

    private function repentanceDay(int $year): Carbon
    {
        $date = Carbon::create($year, 11, 23);

        while ($date->isoWeekday() !== 3) {
            $date->subDay();
        }

        return $date;
    }
}
