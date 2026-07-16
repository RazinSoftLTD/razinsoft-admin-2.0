<?php

namespace App\Support;

use libphonenumber\PhoneNumberUtil;

/** Phone parsing/validation via libphonenumber (per-country rules). Never throws. */
class Phone
{
    /**
     * Normalize + validate a raw phone number.
     * The region comes from a `+CC` prefix in the number itself, the given country
     * name (config/countries), or an explicit dial code. Returns
     * ['dial' => '+880', 'number' => '1711…'] when valid, null otherwise.
     */
    public static function normalize(?string $raw, ?string $countryName = null, ?string $dialCode = null): ?array
    {
        $raw = preg_replace('/[\s\-().]+/', '', trim((string) $raw));
        if ($raw === '') {
            return null;
        }

        try {
            $util = PhoneNumberUtil::getInstance();

            $region = null;
            if (! str_starts_with($raw, '+')) {
                $region = self::regionFromCountry($countryName)
                    ?? ($dialCode ? $util->getRegionCodeForCountryCode((int) ltrim($dialCode, '+')) : null);
                // "00" international prefix → treat as +
                if (str_starts_with($raw, '00')) {
                    $raw = '+'.substr($raw, 2);
                }
            }

            $number = $util->parse($raw, $region);
            if (! $util->isValidNumber($number)) {
                return null;
            }

            return [
                'dial' => '+'.$number->getCountryCode(),
                'number' => (string) $number->getNationalNumber(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** ISO region (BD, US …) from a country name in config/countries. */
    public static function regionFromCountry(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        static $map = null;
        $map ??= collect(config('countries', []))->mapWithKeys(fn ($c) => [mb_strtolower($c['name']) => $c['code']]);

        return $map[mb_strtolower($name)] ?? null;
    }
}
