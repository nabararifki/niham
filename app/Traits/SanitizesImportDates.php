<?php

namespace App\Traits;

trait SanitizesImportDates
{
    use CoercesImportValues;

    /**
     * Sanitize a raw spreadsheet value into a Y-m-d date, or null.
     *
     * Spreadsheets often contain placeholder text such as "N/A", "-", "?", or
     * locale-specific formats that PostgreSQL cannot cast to the date type.
     * This method tries several common formats and returns null for anything
     * that cannot be parsed as a real date, preventing INSERT errors.
     *
     * Deliberately the ONLY date parser in the import pipeline. It takes the two
     * non-string shapes XLSX can produce as well as strings, so no caller needs a
     * parallel implementation that could drift from this one:
     *
     *   - DateTimeInterface, from a cell carrying a date number format. This used
     *     to fall straight through the !is_string() guard below and come back null,
     *     silently discarding a date the file stated unambiguously.
     *   - A bare Excel serial (45366), from a date cell with NO date format. Only
     *     treated as a date inside Excel's own serial range, so an ordinary number
     *     in a mis-mapped column is not invented into a date.
     */
    protected function sanitizeDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ((is_int($value) || is_float($value)) && $this->looksLikeExcelSerial($value)) {
            return $this->excelSerialToDate($value);
        }

        if (empty($value) || !is_string($value)) {
            return null;
        }

        $value = trim($value);

        // A bare number needs deciding before the format list, because strtotime()
        // reads short digit strings as a CLOCK time and returns TODAY for them:
        // '2024' becomes 20:24 today, and a cost column mis-mapped here would have
        // stamped every row with the import date. Silent, and wrong.
        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            $whole = explode('.', $value)[0];

            // Four digits is a year, and a year alone means its first day.
            if (strlen($whole) === 4) {
                return $whole . '-01-01';
            }

            // Longer numbers can be Excel serials: CSV stringifies everything, and
            // Excel exports a date column as its serial once the format is stripped.
            if (strlen($whole) > 4 && $this->looksLikeExcelSerial((float) $value)) {
                return $this->excelSerialToDate((float) $value);
            }

            // Anything else — 1-3 digits, or a number past Excel's ceiling — is not
            // a date, and must not be allowed to fall through to strtotime().
            return null;
        }

        if ($value === '' || $value === '-' || $value === 'N/A' || $value === '?' || $value === '0') {
            return null;
        }

        // Try multiple common date formats from spreadsheets
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'j/n/Y', 'n/j/Y'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        // Last resort: PHP's strtotime for natural-language dates
        $ts = strtotime($value);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return null;
    }
}
