<?php

namespace App\Support;

/**
 * CSV writing that is safe to open in Excel / Sheets.
 *
 * A cell that starts with = + - @ (or a tab / carriage return) is run as a
 * formula by spreadsheet programs, so a customer, product or supplier named
 * "=HYPERLINK(...)" turns an export into an attack on whoever opens it.
 * Such text cells get a leading apostrophe. Real numbers (including "-5.00" or
 * "+254712345678", which is_numeric() accepts) are left alone.
 */
class Csv
{
    public static function cell($value)
    {
        if (is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value)) {
            return "'" . $value;
        }
        return $value;
    }

    /** Drop-in for the plain PHP CSV writer: same handle and fields, cells made safe. */
    public static function put($handle, array $fields, string $separator = ',', string $enclosure = '"', string $escape = "\\", string $eol = "\n")
    {
        return fputcsv($handle, array_map([self::class, 'cell'], $fields), $separator, $enclosure, $escape, $eol);
    }
}
