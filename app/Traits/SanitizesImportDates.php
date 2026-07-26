<?php

namespace App\Traits;

trait SanitizesImportDates
{
    /**
     * Sanitize a raw date string from a spreadsheet into a Y-m-d value or null.
     *
     * Spreadsheets often contain placeholder text such as "N/A", "-", "?", or
     * locale-specific formats that PostgreSQL cannot cast to the date type.
     * This method tries several common formats and returns null for anything
     * that cannot be parsed as a real date, preventing INSERT errors.
     */
    protected function sanitizeDate(mixed $value): ?string
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }

        $value = trim($value);

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
