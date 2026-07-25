<?php

namespace App\Support;

use DateTimeZone;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\PhoneNumberUtil;

/**
 * Work out which country a lead belongs to (and the current local time there):
 *  1. the explicitly stored country name, else
 *  2. parsed from the phone number via libphonenumber.
 * Local-format numbers are assumed to be in the business's home region (BD).
 */
class CountryTime
{
    /** Home region for parsing local-format numbers (no country code). */
    private const DEFAULT_REGION = 'BD';

    /**
     * Business/capital timezone for multi-zone countries, where PHP's per-country
     * list would return a poor alphabetical first (US → Adak, AU → Macquarie).
     */
    private const PRIMARY = [
        'US' => 'America/New_York', 'CA' => 'America/Toronto', 'AU' => 'Australia/Sydney',
        'RU' => 'Europe/Moscow', 'BR' => 'America/Sao_Paulo', 'MX' => 'America/Mexico_City',
        'ID' => 'Asia/Jakarta', 'CN' => 'Asia/Shanghai', 'KZ' => 'Asia/Almaty',
        'AR' => 'America/Argentina/Buenos_Aires', 'CL' => 'America/Santiago', 'ES' => 'Europe/Madrid',
        'PT' => 'Europe/Lisbon', 'NZ' => 'Pacific/Auckland', 'UA' => 'Europe/Kyiv',
        'CD' => 'Africa/Kinshasa', 'EC' => 'America/Guayaquil',
    ];

    private static ?array $nameToCode = null;

    private static ?array $codeToName = null;

    /**
     * Resolve a lead to ['country' => name, 'tz' => IANA] or null when unknown.
     * The stored country name wins; otherwise the phone number is parsed.
     */
    public static function forLead(?string $country, ?string $phone): ?array
    {
        $name = trim((string) $country);
        if ($name !== '' && ($tz = self::timezone($name))) {
            return ['country' => $name, 'tz' => $tz];
        }

        return self::fromPhone($phone);
    }

    /** Country NAME → representative IANA timezone (for the explicit country field). */
    public static function timezone(?string $countryName): ?string
    {
        $name = trim((string) $countryName);
        if ($name === '') {
            return null;
        }
        self::loadMaps();
        $code = self::$nameToCode[mb_strtolower($name)] ?? null;

        return $code ? self::zoneForCode($code) : null;
    }

    private static function fromPhone(?string $phone): ?array
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();
        $tzMapper = PhoneNumberToTimeZonesMapper::getInstance();

        // Try the number as-is (home region for local numbers); if that yields nothing and
        // the number looks like a bare international one, retry with a leading '+'.
        $candidates = [$raw];
        $digits = preg_replace('/\D/', '', $raw);
        if (! str_contains($raw, '+') && $digits !== '' && $digits[0] !== '0' && strlen($digits) >= 8) {
            $candidates[] = '+'.$digits;
        }

        foreach ($candidates as $candidate) {
            try {
                $num = $util->parse($candidate, self::DEFAULT_REGION);
            } catch (\Throwable $e) {
                continue;
            }
            if (! $util->isValidNumber($num)) {
                continue;
            }
            $zones = $tzMapper->getTimeZonesForNumber($num);
            $tz = $zones[0] ?? null;
            if (! $tz || $tz === 'Etc/Unknown') {
                continue;
            }

            $region = $util->getRegionCodeForNumber($num);
            self::loadMaps();
            $name = ($region && isset(self::$codeToName[$region]))
                ? self::$codeToName[$region]
                : PhoneNumberOfflineGeocoder::getInstance()->getDescriptionForNumber($num, 'en');

            return ['country' => $name ?: null, 'tz' => $tz];
        }

        return null;
    }

    private static function zoneForCode(string $code): ?string
    {
        if (isset(self::PRIMARY[$code])) {
            return self::PRIMARY[$code];
        }
        $zones = @timezone_identifiers_list(DateTimeZone::PER_COUNTRY, $code);

        return $zones[0] ?? null;
    }

    private static function loadMaps(): void
    {
        if (self::$nameToCode !== null) {
            return;
        }
        self::$nameToCode = self::$codeToName = [];
        foreach ((array) config('countries', []) as $c) {
            if (! empty($c['name']) && ! empty($c['code'])) {
                self::$nameToCode[mb_strtolower($c['name'])] = $c['code'];
                self::$codeToName[$c['code']] = $c['name'];
            }
        }
    }
}
