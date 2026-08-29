<?php

namespace App\Traits;

/**
 * Turn whatever a spreadsheet cell actually contains into what the target column needs.
 *
 * CSV always hands us strings. XLSX does not: OpenSpout returns the cell's real
 * type, so a date-formatted cell arrives as DateTimeImmutable, a duration as
 * DateInterval, a number as int|float, and a checkbox as bool. Casting those with
 * a bare (string) throws for the object types, which is what used to kill the
 * import job outright before a single row was written.
 *
 * Worth knowing: a date cell is only a date OBJECT when it carries a date number
 * format. Without one the reader hands back the bare Excel serial as an int —
 * 45366 rather than 2024-03-15 — which never threw but did quietly turn dates into
 * meaningless numbers. sanitizeDate() handles that shape; see the trait next door.
 *
 * Nothing here throws. A value that cannot be coerced comes back with a reason
 * instead, so the caller can keep the raw text and tell the user about it rather
 * than failing the row — or, previously, the whole file.
 */
trait CoercesImportValues
{
    /**
     * Excel's day-zero. 1899-12-30 rather than 1900-01-01 because Excel treats
     * 1900 as a leap year, and starting two days early absorbs that off-by-one
     * for every date from 1900-03-01 onwards.
     */
    private const EXCEL_EPOCH = '1899-12-30';

    /** Serial 1 is 1899-12-31; 2958465 is 9999-12-31, Excel's own ceiling. */
    private const EXCEL_SERIAL_MIN = 1;

    private const EXCEL_SERIAL_MAX = 2958465;

    /**
     * Coerce any cell value into a string. Cannot fail — every shape has a sensible
     * textual form, which is why a string-target column never invalidates a row.
     */
    protected function coerceToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        // Y-m-d matches sanitizeDate()'s output and how dates are rendered
        // everywhere else in the app, so a date landing in Tag reads the same as
        // one landing in Purchase Date.
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // A cell formatted as a duration ([h]:mm:ss). Rare, but it is the other
        // object type OpenSpout can hand back, and it used to be fatal.
        if ($value instanceof \DateInterval) {
            return sprintf(
                '%02d:%02d:%02d',
                ($value->d * 24) + $value->h,
                $value->i,
                $value->s
            );
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_float($value)) {
            // (string) 1.0E+25 keeps the exponent, which is not a value anyone
            // typed into a spreadsheet. Trim trailing zeros so 1500.00 reads 1500.
            $formatted = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

            return $formatted === '' || $formatted === '-' ? '0' : $formatted;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        // Rich-text runs come back as an array of fragments.
        if (is_array($value)) {
            return trim(implode('', array_map(
                fn ($part) => is_scalar($part) ? (string) $part : '',
                $value
            )));
        }

        // Anything else stringable (a value object with __toString).
        if ($value instanceof \Stringable || method_exists($value, '__toString')) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * Read one spreadsheet row as an array of strings, keyed by column position.
     *
     * The single place any reader row is turned into cell text — peek(), the import
     * loop and the progress pre-pass all come through here, so the preview cannot
     * show one thing and the import stage another.
     *
     * Row::toArray() is deliberately NOT used. It calls Cell::getValue(), and for a
     * cell containing a formula that is the formula SOURCE ('=SUM(A1:A2)'), not its
     * result — so a mapped column full of formulas staged the literal text people
     * see in the formula bar. The value Excel computed is right there: the reader
     * parses the <v> node sitting next to <f> and keeps it on the cell, one method
     * call away (Reader/XLSX/Helper/CellValueFormatter::extractAndFormatNodeValue).
     * Nothing here evaluates a formula; it reads what the file already cached.
     *
     * @param  \OpenSpout\Common\Entity\Row $row
     * @return array<int, string>
     */
    protected function rowToStrings($row): array
    {
        // array_map over ->cells rather than a foreach: Row allows holes in the
        // index, and array_map preserves the original keys that cells are read at.
        return array_map(
            fn ($cell) => $this->coerceToString($this->resolveCellValue($cell)),
            $row->cells
        );
    }

    /**
     * The value a cell actually carries.
     *
     * Only formula cells need special handling. A formula whose cached result was
     * an error (#DIV/0!, #REF!) has a null computed value — the reader discards the
     * ErrorCell rather than storing it — and so reads as empty. That is the right
     * outcome: an empty cell is something the user can fix on the review page,
     * whereas the string '=A1/0' would be staged as if someone had typed it.
     *
     * @param \OpenSpout\Common\Entity\Cell $cell
     */
    protected function resolveCellValue($cell): mixed
    {
        return $cell instanceof \OpenSpout\Common\Entity\Cell\FormulaCell
            ? $cell->getComputedValue()
            : $cell->getValue();
    }

    /**
     * Coerce a value into a decimal string, or explain why it could not be.
     *
     * Handles what people actually put in a cost column: currency prefixes, and
     * both separator conventions — 1.250.000,50 and 1,250,000.50 mean the same
     * amount and differ only by locale.
     *
     * @return array{value: ?string, error: ?string} error is a raw-value string for
     *                                               the caller to localize, or null
     */
    protected function coerceToDecimal(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['value' => null, 'error' => null];
        }

        if (is_int($value) || is_float($value)) {
            return ['value' => $this->coerceToString($value), 'error' => null];
        }

        if (is_bool($value)) {
            return ['value' => null, 'error' => $value ? 'true' : 'false'];
        }

        $raw = $this->coerceToString($value);

        if ($raw === '') {
            return ['value' => null, 'error' => null];
        }

        // Drop currency symbols, spaces and the usual prefixes. Keep digits, both
        // separators, and a leading sign.
        $cleaned = preg_replace('/(?i)\b(rp|idr|usd)\b/', '', $raw);
        $cleaned = preg_replace('/[^\d,.\-]/', '', (string) $cleaned);
        $cleaned = trim((string) $cleaned);

        if ($cleaned === '' || !preg_match('/\d/', $cleaned)) {
            return ['value' => null, 'error' => $raw];
        }

        $negative = str_starts_with($cleaned, '-');
        $cleaned  = ltrim($cleaned, '-');

        // Whichever separator comes last is the decimal point, but only when it is
        // followed by one or two digits — "1.250.000" ends in a group of three, so
        // that final dot is a thousands separator, not a decimal point.
        $lastComma = strrpos($cleaned, ',');
        $lastDot   = strrpos($cleaned, '.');
        $lastSep   = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);

        if ($lastSep >= 0) {
            $decimals = strlen($cleaned) - $lastSep - 1;

            if ($decimals >= 1 && $decimals <= 2) {
                $integerPart = preg_replace('/[^\d]/', '', substr($cleaned, 0, $lastSep));
                $cleaned     = $integerPart . '.' . substr($cleaned, $lastSep + 1);
            } else {
                $cleaned = preg_replace('/[^\d]/', '', $cleaned);
            }
        }

        if (!is_numeric($cleaned)) {
            return ['value' => null, 'error' => $raw];
        }

        return ['value' => ($negative ? '-' : '') . $cleaned, 'error' => null];
    }

    /**
     * Coerce a value for a date column, or explain why it could not be.
     *
     * Delegates to sanitizeDate() rather than parsing here — one date parser for
     * the whole pipeline. This only adds the "so why did it fail" half, which
     * sanitizeDate() has no way to express through a nullable return.
     *
     * @return array{value: ?string, error: ?string}
     */
    protected function coerceToDate(mixed $value): array
    {
        $sanitized = $this->sanitizeDate($value);

        if ($sanitized !== null) {
            return ['value' => $sanitized, 'error' => null];
        }

        $raw = $this->coerceToString($value);

        // Blanks and the usual placeholders are absences, not failures — flagging
        // every empty date cell would bury the ones that are genuinely wrong.
        if ($raw === '' || in_array($raw, ['-', 'N/A', '?', '0'], true)) {
            return ['value' => null, 'error' => null];
        }

        return ['value' => null, 'error' => $raw];
    }

    /**
     * Whether a number is plausibly an Excel date serial rather than just a number.
     */
    protected function looksLikeExcelSerial(int|float $value): bool
    {
        return $value >= self::EXCEL_SERIAL_MIN && $value <= self::EXCEL_SERIAL_MAX;
    }

    /**
     * Convert an Excel date serial into Y-m-d. Caller checks the range first.
     */
    protected function excelSerialToDate(int|float $serial): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', self::EXCEL_EPOCH);

        if ($date === false) {
            return null;
        }

        return $date->modify('+' . (int) floor($serial) . ' days')?->format('Y-m-d');
    }
}
