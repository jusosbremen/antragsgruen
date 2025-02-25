<?php

namespace app\components;

use app\models\db\Consultation;
use app\models\settings\Consultation as ConsultationSettings;
use app\models\exceptions\Internal;

class Tools
{
    public static function dateSql2timestamp(string $input): int
    {
        $parts = explode(' ', $input);
        $date  = array_map('IntVal', explode('-', $parts[0]));

        if (count($parts) == 2) {
            $time = array_map('IntVal', explode(':', $parts[1]));
        } else {
            $time = array(0, 0, 0);
        }

        return mktime($time[0], $time[1], $time[2], $date[1], $date[2], $date[0]);
    }

    public static function dateSql2Datetime(string $input): ?\DateTime
    {
        return \DateTime::createFromFormat('Y-m-d H:i:s', $input . ' 00:00:00');
    }

    public static function getCurrentDateLocale(): string
    {
        $consultation = Consultation::getCurrent();
        if ($consultation && $consultation->wordingBase) {
            return explode('-', $consultation->wordingBase)[0];
        }
        return explode('-', RequestContext::getWebApplication()->language)[0];
    }

    public static function getCurrentDateFormat(): string
    {
        $consultation = Consultation::getCurrent();
        if ($consultation && $consultation->getSettings()->dateFormat && $consultation->getSettings()->dateFormat !== ConsultationSettings::DATE_FORMAT_DEFAULT) {
            return $consultation->getSettings()->dateFormat;
        }

        switch (self::getCurrentDateLocale()) {
            case 'de':
                return ConsultationSettings::DATE_FORMAT_DMY_DOT;
            case 'fr':
                return ConsultationSettings::DATE_FORMAT_DMY_SLASH;
            case 'en':
            default:
                return ConsultationSettings::DATE_FORMAT_MDY_SLASH;
        }
    }

    public static function dateBootstraptime2sql(string $time, ?string $locale = null): string
    {
        if ($locale === null) {
            $locale = Tools::getCurrentDateLocale();
        }

        if ($locale === 'de') {
            $pattern = '/^(?<day>\\d{1,2})\.(?<month>\\d{1,2})\.(?<year>\\d{4}) ' .
                       '(?<hour>\\d{1,2})\:(?<minute>\\d{1,2})$/';
            if (preg_match($pattern, $time, $matches) && $matches['year'] > 1970) {
                return sprintf(
                    '%1$04d-%2$02d-%3$02d %4$02d:%5$02d:00',
                    $matches['year'],
                    $matches['month'],
                    $matches['day'],
                    $matches['hour'],
                    $matches['minute']
                );
            }
        } elseif ($locale === 'fr') {
            $pattern = '/^(?<day>\\d{1,2})\/(?<month>\\d{1,2})\/(?<year>\\d{4}) ' .
                       '(?<hour>\\d{1,2})\:(?<minute>\\d{1,2})$/';
            if (preg_match($pattern, $time, $matches) && $matches['year'] > 1970) {
                return sprintf(
                    '%1$04d-%2$02d-%3$02d %4$02d:%5$02d:00',
                    $matches['year'],
                    $matches['month'],
                    $matches['day'],
                    $matches['hour'],
                    $matches['minute']
                );
            }
        } elseif ($locale === 'en') {
            $pattern = '/^(?<month>\\d{1,2})\/(?<day>\\d{1,2})\/(?<year>\\d{4}) ' .
                       '(?<hour>\\d{1,2})\:(?<minute>\\d{1,2}) (?<ampm>am|pm)$/i';
            if (preg_match($pattern, $time, $matches) && $matches['year'] > 1970) {
                if (intval($matches['hour']) === 12) {
                    $hours = (strtolower($matches['ampm']) === 'pm' ? 12 : 0);
                } else {
                    $hours = (strtolower($matches['ampm']) === 'pm' ? intval($matches['hour']) + 12 : $matches['hour']);
                }

                return sprintf(
                    '%1$04d-%2$02d-%3$02d %4$02d:%5$02d:00',
                    $matches['year'],
                    $matches['month'],
                    $matches['day'],
                    (string)$hours,
                    $matches['minute']
                );
            }

            $pattern = '/^(?<month>\\d{1,2})\/(?<day>\\d{1,2})\/(?<year>\\d{4}) ' .
                       '(?<hour>\\d{1,2})\:(?<minute>\\d{1,2})$/';
            if (preg_match($pattern, $time, $matches)) {
                return sprintf(
                    '%1$04d-%2$02d-%3$02d %4$02d:%5$02d:00',
                    $matches['year'],
                    $matches['month'],
                    $matches['day'],
                    $matches['hour'],
                    $matches['minute']
                );
            }
        } else {
            throw new Internal('Unsupported Locale: ' . $locale);
        }

        return '';
    }

    public static function dateSql2bootstrapdate(?string $date, ?string $locale = null): string
    {
        if ($date === null) {
            return '';
        }
        if ($locale === null) {
            $locale = Tools::getCurrentDateLocale();
        }

        if (!preg_match('/^(?<year>\\d{4})-(?<month>\\d{1,2})-(?<day>\\d{1,2})$/', $date, $matches)) {
            return '';
        }

        if ($locale === 'de') {
            return $matches['day'] . '.' . $matches['month'] . '.' . $matches['year'];
        } elseif ($locale === 'fr') {
            return $matches['day'] . '/' . $matches['month'] . '/' . $matches['year'];
        } elseif ($locale === 'en') {
            return $matches['month'] . '/' . $matches['day'] . '/' . $matches['year'];
        } else {
            throw new Internal('Unsupported Locale: ' . $locale);
        }
    }

    public static function dateBootstrapdate2sql(?string $date, ?string $locale = null): string
    {
        if ($date === null) {
            return '';
        }
        if ($locale === null) {
            $locale = Tools::getCurrentDateLocale();
        }

        if ($locale === 'de') {
            $pattern = '/^(?<day>\\d{1,2})\.(?<month>\\d{1,2})\.(?<year>\\d{4})$/';
            if (preg_match($pattern, $date, $matches)) {
                return sprintf('%1$04d-%2$02d-%3$02d', $matches['year'], $matches['month'], $matches['day']);
            }
        } elseif ($locale === 'fr') {
            $pattern = '/^(?<day>\\d{1,2})\/(?<month>\\d{1,2})\/(?<year>\\d{4})$/';
            if (preg_match($pattern, $date, $matches)) {
                return sprintf('%1$04d-%2$02d-%3$02d', $matches['year'], $matches['month'], $matches['day']);
            }
        } elseif ($locale === 'en') {
            $pattern = '/^(?<month>\\d{1,2})\/(?<day>\\d{1,2})\/(?<year>\\d{4})$/';
            if (preg_match($pattern, $date, $matches)) {
                return sprintf('%1$04d-%2$02d-%3$02d', $matches['year'], $matches['month'], $matches['day']);
            }
        } else {
            throw new Internal('Unsupported Locale: ' . $locale);
        }

        return '';
    }

    public static function dateSql2bootstraptime(?string $time, ?string $locale = null): string
    {
        if ($time === null) {
            return '';
        }
        if ($locale === null) {
            $locale = Tools::getCurrentDateLocale();
        }

        $pattern = '/^(?<year>\\d{4})\-(?<month>\\d{2})\-(?<day>\\d{2}) ' .
                   '(?<hour>\\d{2})\:(?<minute>\\d{2})\:(?<second>\\d{2})$/';
        if (!preg_match($pattern, $time, $matches)) {
            return '';
        }

        if ($locale === 'de') {
            $date = $matches['day'] . '.' . $matches['month'] . '.' . $matches['year'] . ' ';
            $date .= $matches['hour'] . ':' . $matches['minute'];

            return $date;
        } elseif ($locale === 'fr') {
            $date = $matches['day'] . '/' . $matches['month'] . '/' . $matches['year'] . ' ';
            $date .= $matches['hour'] . ':' . $matches['minute'];

            return $date;
        } elseif ($locale === 'en') {
            $date = $matches['month'] . '/' . $matches['day'] . '/' . $matches['year'] . ' ';
            $date .= $matches['hour'] . ':' . $matches['minute'];

            return $date;
        } else {
            throw new Internal('Unsupported Locale: ' . $locale);
        }
    }

    public static function date2bootstraptime(?\DateTime $time, ?string $locale = null): string
    {
        if ($time === null) {
            return '';
        }
        if ($locale === null) {
            $locale = Tools::getCurrentDateLocale();
        }

        if ($locale === 'de') {
            return $time->format('d.m.Y H:i');
        } elseif ($locale === 'fr') {
            return $time->format('d/m/Y H:i');
        } elseif ($locale === 'en') {
            return $time->format('m/d/Y H:i');
        } else {
            throw new Internal('Unsupported Locale: ' . $locale);
        }
    }

    private static int $last_time = 0;

    public static function debugTime(string $name): void
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = sprintf('%14.0f', intval($sec) * 10000 + floatval($usec) * 10000);
        if (self::$last_time) {
            echo 'Time (' . $name . '): ' . ($time - self::$last_time) . ' (' . date('Y-m-d H:i:s') . ')<br>';
        }
        self::$last_time = (int)$time;
    }

    public static function formatMysqlDateWithAria(?string $mysqldate, bool $allowRelativeDates = true): string
    {
        $currentTs = DateTools::getCurrentTimestamp();

        if ($mysqldate === null || strlen($mysqldate) === 0) {
            return '-';
        } elseif (substr($mysqldate, 0, 10) === date('Y-m-d', $currentTs) && $allowRelativeDates) {
            return \Yii::t('base', 'Today');
        } elseif (substr($mysqldate, 0, 10) === date('Y-m-d', $currentTs - 3600 * 24) && $allowRelativeDates) {
            return \Yii::t('base', 'Yesterday');
        }

        $date = explode('-', substr($mysqldate, 0, 10));
        if (count($date) !== 3) {
            return '-';
        }

        $replaces = [
            '%DAY%'       => sprintf('%02d', $date[2]),
            '%MONTH%'     => sprintf('%02d', $date[1]),
            '%YEAR%'      => sprintf('%04d', $date[0]),
            '%MONTHNAME%' => \Yii::t('structure', 'months_' . intval($date[1])),
        ];

        switch (self::getCurrentDateFormat()) {
            case ConsultationSettings::DATE_FORMAT_DMY_DOT:
                $pattern = '<span aria-label="%DAY%. %MONTHNAME% %YEAR%">%DAY%.%MONTH%.%YEAR%</span>';
                break;
            case ConsultationSettings::DATE_FORMAT_DMY_SLASH:
                $pattern = '<span aria-label="%DAY%. %MONTHNAME% %YEAR%">%DAY%/%MONTH%/%YEAR%</span>';
                break;
            case ConsultationSettings::DATE_FORMAT_MDY_SLASH:
                $pattern = '<span aria-label="%DAY%. %MONTHNAME% %YEAR%">%MONTH%/%DAY%/%YEAR%</span>';
                break;
            case ConsultationSettings::DATE_FORMAT_YMD_DASH:
                $pattern = '<span aria-label="%DAY%. %MONTHNAME% %YEAR%">%YEAR%-%MONTH%-%DAY%</span>';
                break;
            default:
                throw new Internal('Unsupported date format: ' . self::getCurrentDateFormat());
        }

        return str_replace(array_keys($replaces), array_values($replaces), $pattern);
    }

    public static function formatMysqlDate(?string $mysqldate, bool $allowRelativeDates = true): string
    {
        $currentTs = DateTools::getCurrentTimestamp();

        if ($mysqldate === null || strlen($mysqldate) === 0) {
            return '-';
        } elseif (substr($mysqldate, 0, 10) === date('Y-m-d', $currentTs) && $allowRelativeDates) {
            return \Yii::t('base', 'Today');
        } elseif (substr($mysqldate, 0, 10) === date('Y-m-d', $currentTs - 3600 * 24) && $allowRelativeDates) {
            return \Yii::t('base', 'Yesterday');
        }

        $date = explode('-', substr($mysqldate, 0, 10));
        if (count($date) !== 3) {
            return '-';
        }
        switch (self::getCurrentDateFormat()) {
            case ConsultationSettings::DATE_FORMAT_DMY_DOT:
                return sprintf('%02d.%02d.%04d', $date[2], $date[1], $date[0]);
            case ConsultationSettings::DATE_FORMAT_DMY_SLASH:
                return sprintf('%02d/%02d/%04d', $date[2], $date[1], $date[0]);
            case ConsultationSettings::DATE_FORMAT_MDY_SLASH:
                return sprintf('%02d/%02d/%04d', $date[1], $date[2], $date[0]);
            case ConsultationSettings::DATE_FORMAT_YMD_DASH:
                return sprintf('%04d-%02d-%02d', $date[0], $date[1], $date[2]);
            default:
                throw new Internal('Unsupported date format: ' . self::getCurrentDateFormat());
        }
    }

    public static function formatMysqlDateTime(string $mysqlDate, bool $allowRelativeDates = true): string
    {
        if (strlen($mysqlDate) === 0) {
            return '-';
        }

        return self::formatMysqlDate($mysqlDate, $allowRelativeDates) . ", " . substr($mysqlDate, 11, 5);
    }

    public static function formatRemainingTime(?\DateTime $deadline): string
    {
        if (!$deadline) {
            return '?';
        }
        $seconds = $deadline->getTimestamp() - DateTools::getCurrentTimestamp();
        if ($seconds < 0) {
            return \Yii::t('structure', 'remaining_over');
        }
        if ($seconds >= 3600 * 24) {
            $days = (int)floor($seconds / (3600 * 24));

            return $days . ' ' . \Yii::t('structure', $days === 1 ? 'remaining_day' : 'remaining_days');
        } elseif ($seconds >= 3600) {
            $hours = (int)floor($seconds / 3600);

            return $hours . ' ' . \Yii::t('structure', $hours === 1 ? 'remaining_hour' : 'remaining_hours');
        } elseif ($seconds >= 60) {
            $minutes = (int)floor($seconds / 60);

            return $minutes . ' ' . \Yii::t('structure', $minutes === 1 ? 'remaining_minute' : 'remaining_minutes');
        } else {
            return $seconds . ' ' . \Yii::t('structure', $seconds === 1 ? 'remaining_second' : 'remaining_seconds');
        }
    }

    public static function compareSqlTimes(string $dateTime1, string $dateTime2): int
    {
        $ts1 = ($dateTime1 ? self::dateSql2timestamp($dateTime1) : 0);
        $ts2 = ($dateTime2 ? self::dateSql2timestamp($dateTime2) : 0);
        if ($ts1 < $ts2) {
            return -1;
        } elseif ($ts1 > $ts2) {
            return 1;
        } else {
            return 0;
        }
    }

    public static function sanitizeFilename(string $filename, bool $noUmlaut): string
    {
        $filename = str_replace(' ', '_', $filename);
        $filename = str_replace('/', '-', $filename);
        $filename = str_replace('.', '_', $filename);
        $filename = preg_replace('/[^\w0-9_-]/siu', '', $filename);
        if ($noUmlaut) {
            $filename = str_replace(
                ['ä', 'Ä', 'ö', 'Ö', 'ü', 'Ü', 'ß'],
                ['ae', 'Ae', 'oe', 'Oe', 'ue', 'Ue', 'ss'],
                $filename
            );
        }

        return $filename;
    }

    public static function formatModelValidationErrors(array $errors): string
    {
        $errorStrs = [];
        foreach ($errors as $field => $error) {
            foreach ($error as $err) {
                $errorStrs[] = $field . ': ' . $err;
            }
        }

        return implode("\n", $errorStrs);
    }

    private static function parsePhpSize(string $size): int
    {
        if (is_numeric($size)) {
            return $size;
        } else {
            $value_length = strlen($size);
            $qty          = floatval(substr($size, 0, $value_length - 1));
            $unit         = strtolower(substr($size, $value_length - 1));
            switch ($unit) {
                case 'k':
                    $qty *= 1024;
                    break;
                case 'm':
                    $qty *= 1048576;
                    break;
                case 'g':
                    $qty *= 1073741824;
                    break;
            }

            return $qty;
        }
    }

    public static function getMaxUploadSize(): int
    {
        $post_max_size = self::parsePhpSize(ini_get('post_max_size'));
        $upload_size   = self::parsePhpSize(ini_get('upload_max_filesize'));
        if ($upload_size < $post_max_size) {
            return $upload_size;
        } else {
            return $post_max_size;
        }
    }
}
