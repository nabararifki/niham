<?php

namespace App\Services;

use Exception;
use Generator;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;

class AssetImportService
{
    use \App\Traits\SanitizesImportDates;

    /**
     * Bilingual keyword map: column header keywords → canonical field names.
     * Each entry uses regex with prefix/suffix tolerance.
     */
    private const HEADER_MAP = [
        'tag'           => '/(tag|kode\s*aset|asset\s*tag|kode)/i',
        'name'          => '/(nama|name|nama\s*aset|asset\s*name|deskripsi|description)/i',
        'category'      => '/(kategori|category|jenis|type|tipe)/i',
        'department'    => '/(departemen|department|dept|bagian|divisi|division)/i',
        'status'        => '/(status|kondisi|condition)/i',
        'serial_number' => '/(serial|seri|serial\s*number|no\s*seri|nomor\s*seri|s\/?n)/i',
        'purchase_date' => '/(tanggal\s*beli|purchase\s*date|tgl\s*beli|date|tanggal)/i',
        'brand'         => '/(merk|merek|brand|pabrikan|manufacturer)/i',
        'model'         => '/(model|tipe|type)/i',
        'vendor'        => '/(vendor|supplier|pemasok)/i',
        'cost'          => '/(harga|cost|price|biaya|purchase\s*cost|nilai)/i',
    ];

    /**
     * FASE 1: Buka file sementara, ambil sampel 15 baris, cari True Header,
     * dan jalankan Hybrid Matching Pipeline.
     *
     * @param int      $sheetIndex      Zero-based index of the sheet to extract from (default: 0)
     * @param int|null $headerRowIndex  Zero-based row to force as the header. Null runs the
     *                                  heuristic. Lets the user override a wrong guess on files
     *                                  with title banners or legend blocks above the real table.
     */
    public function peek(string $filePath, string $extension, int $sheetIndex = 0, ?int $headerRowIndex = null): array
    {
        $options = $extension === 'csv' ? new CsvOptions() : new XlsxOptions();
        $reader = $extension === 'csv' ? new CsvReader($options) : new XlsxReader($options);

        $reader->open($filePath);

        $sheets = [];
        $firstSheetRows = [];
        $rowCount = 0;
        $currentSheetIndex = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[] = $sheet->getName();
            
            // Extract max 15 rows from the target sheet
            if ($currentSheetIndex === $sheetIndex && $rowCount === 0) {
                foreach ($sheet->getRowIterator() as $row) {
                    /** @var Row $row */
                    // Shared with the import job, so the preview and the import
                    // agree on what a cell says. This guarded DateTimeInterface but
                    // not DateInterval, so a duration-formatted cell threw here and
                    // took the mapping page down with it.
                    $cells = array_map(fn ($val) => $this->coerceToString($val), $row->toArray());

                    $firstSheetRows[] = $cells;
                    $rowCount++;

                    if ($rowCount >= 15) {
                        break;
                    }
                }
            }
            $currentSheetIndex++;
        }
        $reader->close();

        // 1. True Header Detection (Baris dengan cell non-empty terbanyak)
        //
        // Bila user memilih baris header secara manual, heuristik ini dilewati
        // sepenuhnya — tapi HANYA titik ini yang berbeda. Sanitasi header, offset
        // preview (+1), dan generateMappingProposals() di bawah tetap satu jalur
        // untuk auto maupun manual, supaya keduanya tidak bisa menyimpang.
        if ($headerRowIndex !== null) {
            if ($headerRowIndex < 0 || $headerRowIndex >= count($firstSheetRows)) {
                throw new Exception('Selected header row is outside the first 15 rows of this sheet.');
            }
            $trueHeaderIndex = $headerRowIndex;
        } else {
            // Scored on the span a row covers — last filled column minus first, not
            // the count of filled cells.
            //
            // Counting filled cells loses the header row of exactly the files this
            // change is about. A header merged across three columns, or one with a
            // blank column in it, has FEWER filled cells than the data underneath it,
            // so the first data row won and the real column names never appeared on
            // the mapping page at all. Span is unmoved by those gaps while still
            // preferring the widest row, so the title-banner case it was written for
            // behaves as before. Ties go to the earliest row, as they always did.
            $trueHeaderIndex = 0;
            $widestSpan = -1;

            foreach ($firstSheetRows as $idx => $row) {
                $filled = [];
                foreach ($row as $col => $cell) {
                    if (trim((string) $cell) !== '') {
                        $filled[] = $col;
                    }
                }

                $span = empty($filled) ? 0 : (max($filled) - min($filled) + 1);

                if ($span > $widestSpan) {
                    $widestSpan = $span;
                    $trueHeaderIndex = $idx;
                }
            }
        }

        $rawHeaderRow = $firstSheetRows[$trueHeaderIndex] ?? [];
        $rawPreview   = array_slice($firstSheetRows, $trueHeaderIndex + 1, 10);

        // Merge ranges are only worth the lookup when the header actually has an
        // interior gap — see readMergeRanges() for why this is not unconditional.
        $mergeRanges = ($extension !== 'csv' && $this->headerHasInteriorGap($rawHeaderRow))
            ? $this->readMergeRanges($filePath, $sheetIndex)
            : [];

        // Column identity is the original spreadsheet position, kept as the array
        // key throughout. Everything downstream reads cells at that key, so a blank
        // or merged column can no longer shift what sits after it.
        $headerColumns = $this->resolveHeaderColumns($rawHeaderRow, $rawPreview, $mergeRanges);

        if (empty($headerColumns)) {
            throw new Exception('No valid headers detected or file is empty.');
        }

        $trueHeader = array_values($headerColumns);

        // 2. Ekstrak Preview Data (10 baris setelah True Header)
        // Konversi dari indexed array ke associative array ber-key nama kolom
        // agar Alpine getCombinedValue(row, fieldId) bisa melakukan row[colName].
        $previewData = [];
        foreach ($rawPreview as $row) {
            $assocRow = [];
            foreach ($headerColumns as $originalIndex => $colName) {
                $assocRow[$colName] = $row[$originalIndex] ?? '';
            }
            $previewData[] = $assocRow;
        }

        // 3. Eksekusi Hybrid Matching Pipeline
        // generateMappingProposals mengembalikan {numericIndex: fieldName|null}.
        // Alpine mengharapkan {fieldName: columnName} — kita inversi di sini.
        $rawProposals = $this->generateMappingProposals($trueHeader);
        $mappingProposals = [];
        foreach ($rawProposals as $colIndex => $fieldName) {
            if ($fieldName === null || !isset($trueHeader[$colIndex])) {
                continue;
            }
            // Normalisasi: category_id → category, department_id → department
            // agar sesuai dengan key di Alpine mapping{} object.
            $normalizedField = str_replace('_id', '', $fieldName);

            // Jika field sudah dipetakan, jadikan array (multi-column merge)
            if (isset($mappingProposals[$normalizedField])) {
                if (!is_array($mappingProposals[$normalizedField])) {
                    $mappingProposals[$normalizedField] = [$mappingProposals[$normalizedField]];
                }
                $mappingProposals[$normalizedField][] = $trueHeader[$colIndex];
            } else {
                $mappingProposals[$normalizedField] = $trueHeader[$colIndex];
            }
        }

        return [
            'sheets' => $sheets,
            'true_header' => $trueHeader,
            'preview_data' => $previewData,
            'mapping_proposals' => $mappingProposals,
            // originalColumnIndex => displayName. ProcessImportJob resolves the
            // mapping payload's column names through this rather than searching the
            // raw header row, because a merged or synthesised name exists in no cell.
            'header_columns' => $headerColumns,
        ];
    }

    /**
     * Resolve a header row into originalColumnIndex => unique display name.
     *
     * The array KEY is the column's real position in the file and is what every
     * consumer must read cells at. Compacting these away was the bug behind
     * "columns after a blank one show the wrong data": the names were renumbered
     * while the data rows they were paired with were not.
     *
     * Rules, applied in order:
     *
     *   1. A horizontal merge covering this row forward-fills its top-left value
     *      across the range, so a header merged over three columns names all three
     *      instead of leaving two of them headerless.
     *   2. A non-empty cell keeps its own trimmed text.
     *   3. An empty cell is kept only when some sample row has content beneath it,
     *      named "Column D" after its spreadsheet letter — a column with real data
     *      stays mappable even with no header. An empty cell over an empty column is
     *      dropped; the columns after it keep their true indices regardless.
     *   4. Names are made unique with a " (2)", " (3)" suffix. Two columns sharing a
     *      name genuinely lose one downstream — the preview's associative array
     *      collapses them, the job's array_search finds only the first, and the
     *      mapping page's x-for hits a duplicate Alpine key — and rule 1 manufactures
     *      exactly that situation, so this is a prerequisite for it rather than a
     *      nicety.
     *
     * Deliberately NOT handled:
     *   - Merged cells in the data body. "One asset spanning three rows" and "three
     *     assets sharing a department" are byte-identical, and filling the wrong one
     *     silently fabricates asset records. Left as the file has them.
     *   - A header merged vertically across two header rows. Only one row is ever
     *     the header here; the second is read as data.
     *   - Any merge in a CSV. The format has no such concept, so those files get
     *     rules 2-4 only.
     *
     * @param  array  $headerCells  Raw cells of the header row, original indices
     * @param  array  $sampleRows   Rows below the header, used only by rule 3
     * @param  array  $mergeRanges  ["A1:C1", ...] as OpenSpout reports them; [] for CSV
     */
    private function resolveHeaderColumns(array $headerCells, array $sampleRows, array $mergeRanges = []): array
    {
        $names = [];
        foreach ($headerCells as $index => $value) {
            $names[$index] = trim((string) $value);
        }

        foreach ($this->horizontalMergesForRow($mergeRanges, $names) as $from => $to) {
            for ($i = $from + 1; $i <= $to; $i++) {
                $names[$i] = $names[$from];
            }
        }

        // A CSV header line can be shorter than its data lines, and a column with
        // data but no header still needs a name, so the sweep runs to the widest row.
        $width = count($headerCells);
        foreach ($sampleRows as $row) {
            $width = max($width, count($row));
        }

        $columns = [];
        for ($index = 0; $index < $width; $index++) {
            $name = $names[$index] ?? '';

            if ($name === '') {
                if (! $this->columnHasSampleData($sampleRows, $index)) {
                    continue;
                }
                $name = 'Column ' . $this->columnIndexToLetter($index);
            }

            $columns[$index] = $name;
        }

        return $this->makeColumnNamesUnique($columns);
    }

    /**
     * Pick out merge ranges that horizontally span part of this header row.
     *
     * Matched by shape rather than by row number on purpose. A merge ref names a
     * physical sheet row, but both readers skip blank rows, so our row indices drift
     * from the file's the moment there is an empty line above the table. Requiring
     * the range's first cell to be filled and the rest of it empty verifies the range
     * really does describe the row in hand, and costs nothing.
     */
    private function horizontalMergesForRow(array $mergeRanges, array $names): array
    {
        $groups = [];

        foreach ($mergeRanges as $ref) {
            if (! preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/i', (string) $ref, $matches)) {
                continue;
            }

            // Spans more than one row: a two-row header, which we do not support.
            if ($matches[2] !== $matches[4]) {
                continue;
            }

            $from = $this->columnLetterToIndex($matches[1]);
            $to   = $this->columnLetterToIndex($matches[3]);

            if ($to <= $from || ($names[$from] ?? '') === '') {
                continue;
            }

            for ($i = $from + 1; $i <= $to; $i++) {
                if (($names[$i] ?? '') !== '') {
                    continue 2;
                }
            }

            $groups[$from] = $to;
        }

        return $groups;
    }

    private function columnHasSampleData(array $sampleRows, int $index): bool
    {
        foreach ($sampleRows as $row) {
            if (trim((string) ($row[$index] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Suffix duplicates until every name is distinct, including the case where the
     * suffix itself collides with a literal name already in the file.
     */
    private function makeColumnNamesUnique(array $columns): array
    {
        $taken  = [];
        $unique = [];

        foreach ($columns as $index => $name) {
            $candidate = $name;
            $suffix    = 1;

            while (isset($taken[$candidate])) {
                $suffix++;
                $candidate = $name . ' (' . $suffix . ')';
            }

            $taken[$candidate] = true;
            $unique[$index]    = $candidate;
        }

        return $unique;
    }

    /**
     * Whether an empty header cell sits between two filled ones — the only shape a
     * merge lookup could explain, and the gate that keeps that lookup off the
     * common path.
     */
    private function headerHasInteriorGap(array $headerCells): bool
    {
        $filled = [];
        foreach ($headerCells as $index => $value) {
            if (trim((string) $value) !== '') {
                $filled[] = $index;
            }
        }

        if (count($filled) < 2) {
            return false;
        }

        return (max($filled) - min($filled) + 1) > count($filled);
    }

    /**
     * Read merge ranges for one sheet. XLSX only — CSV has no such concept.
     *
     * Called only when the header has an interior gap, because it is not cheap:
     * OpenSpout's SheetMergeCellsReader registers a callback that never stops the
     * XML processor, and <mergeCells> follows <sheetData> in OOXML, so obtaining the
     * ranges walks the whole sheet's XML. SheetManager does that while ENUMERATING
     * sheets, so the cost lands once per sheet up to the target. Turning this on
     * unconditionally would put an O(file) pass in front of the 100K-row imports the
     * streaming reader exists to make possible.
     *
     * Failure is not fatal: without ranges the header still resolves under rules 2-4.
     */
    protected function readMergeRanges(string $filePath, int $sheetIndex): array
    {
        try {
            $reader = new XlsxReader(new XlsxOptions(SHOULD_LOAD_MERGE_CELLS: true));
            $reader->open($filePath);

            $ranges  = [];
            $current = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($current === $sheetIndex) {
                    $ranges = $sheet->getMergeCells();
                    break;
                }
                $current++;
            }

            $reader->close();

            return $ranges;
        } catch (\Throwable $e) {
            Log::warning('Merge range lookup failed, falling back to blank-cell rules: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * "A" => 0, "B" => 1, ... "AA" => 26.
     */
    private function columnLetterToIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function columnIndexToLetter(int $index): string
    {
        $letters = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters   = chr(ord('A') + $remainder) . $letters;
            $index     = intdiv($index - 1 - $remainder, 26);
        }

        return $letters;
    }

    /**
     * Hybrid Matching Pipeline untuk mengusulkan kolom database.
     */
    private function generateMappingProposals(array $headerRow): array
    {
        $proposals = [];
        $dictionary = [
            'tag' => ['tag', 'kodeaset', 'assettag', 'kode', 'nomoraset', 'assetnumber'],
            'name' => ['nama', 'name', 'namaaset', 'assetname', 'deskripsi', 'description', 'namabarang', 'itemname'],
            'category_id' => ['kategori', 'category', 'jenis', 'type', 'tipe'],
            'department_id' => ['departemen', 'department', 'dept', 'bagian', 'divisi', 'division', 'lokasi'],
            'status' => ['status', 'kondisi', 'condition', 'state'],
            'model' => ['model', 'tipe', 'type', 'merk', 'merek', 'brand', 'pabrikan', 'manufacturer'],
            'serial_number' => ['serial', 'seri', 'serialnumber', 'noseri', 'nomorseri', 'sn', 'imei'],
            'purchase_date' => ['tanggalbeli', 'purchasedate', 'tglbeli', 'date', 'tanggal', 'tahun', 'year'],
            'purchase_cost' => ['harga', 'cost', 'price', 'biaya', 'purchasecost', 'nilai', 'hargabeli', 'nilaiaset'],
            'remarks' => ['keterangan', 'catatan', 'remarks', 'note', 'notes', 'desc', 'description'],
        ];

        foreach ($headerRow as $colIndex => $colName) {
            if (trim($colName) === '') {
                $proposals[$colIndex] = null;
                continue;
            }

            // Tahap 1: Exact Match (Direct canonical database fields intersection check)
            $normalizedCol = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $colName));
            $matchedField = null;

            $canonicalKeys = [
                'tag' => 'tag',
                'name' => 'name',
                'category' => 'category_id',
                'department' => 'department_id',
                'status' => 'status',
                'model' => 'model',
                'serial_number' => 'serial_number',
                'purchase_date' => 'purchase_date',
                'purchase_cost' => 'purchase_cost',
                'remarks' => 'remarks',
            ];

            if (isset($canonicalKeys[$normalizedCol])) {
                $matchedField = $canonicalKeys[$normalizedCol];
            }

            // Tahap 2: Dictionary Config
            if (!$matchedField) {
                $dictNormalizedCol = str_replace('_', '', $normalizedCol);
                foreach ($dictionary as $field => $aliases) {
                    $cleanField = str_replace('_', '', $field);
                    if ($dictNormalizedCol === $cleanField || in_array($dictNormalizedCol, array_map(fn($a) => str_replace('_', '', $a), $aliases), true)) {
                        $matchedField = $field;
                        break;
                    }
                }
            }

            // Tahap 3: Jaro-Winkler Distance
            if (!$matchedField) {
                $bestScore = 0;
                $bestField = null;
                $dictNormalizedCol = str_replace('_', '', $normalizedCol);

                foreach ($dictionary as $field => $aliases) {
                    $cleanField = str_replace('_', '', $field);
                    $score = $this->jaroWinkler($dictNormalizedCol, $cleanField);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestField = $field;
                    }

                    foreach ($aliases as $alias) {
                        $cleanAlias = str_replace('_', '', $alias);
                        $aliasScore = $this->jaroWinkler($dictNormalizedCol, $cleanAlias);
                        if ($aliasScore > $bestScore) {
                            $bestScore = $aliasScore;
                            $bestField = $field;
                        }
                    }
                }

                // Threshold > 0.85 untuk dianggap cocok
                if ($bestScore > 0.85) {
                    $matchedField = $bestField;
                }
            }

            $proposals[$colIndex] = $matchedField;
        }

        return $proposals;
    }

    /**
     * Native PHP implementation of Jaro-Winkler distance.
     * Mengembalikan float 0.0 - 1.0
     */
    private function jaroWinkler(string $string1, string $string2): float
    {
        if ($string1 === $string2) return 1.0;

        $len1 = strlen($string1);
        $len2 = strlen($string2);
        if ($len1 === 0 || $len2 === 0) return 0.0;

        $matchDistance = (int) floor(max($len1, $len2) / 2) - 1;

        $matches1 = array_fill(0, $len1, false);
        $matches2 = array_fill(0, $len2, false);

        $matches = 0;
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if (!$matches2[$j] && $string1[$i] === $string2[$j]) {
                    $matches1[$i] = true;
                    $matches2[$j] = true;
                    $matches++;
                    break;
                }
            }
        }

        if ($matches === 0) return 0.0;

        $t = 0;
        $point = 0;
        for ($i = 0; $i < $len1; $i++) {
            if ($matches1[$i]) {
                while (!$matches2[$point]) {
                    $point++;
                }
                if ($string1[$i] !== $string2[$point]) {
                    $t++;
                }
                $point++;
            }
        }
        $t /= 2;

        $jaro = (($matches / $len1) + ($matches / $len2) + (($matches - $t) / $matches)) / 3.0;

        $prefix = 0;
        $maxPrefix = min(4, min($len1, $len2));
        for ($i = 0; $i < $maxPrefix; $i++) {
            if ($string1[$i] === $string2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }

        return $jaro + ($prefix * 0.1 * (1.0 - $jaro));
    }

    /**
     * Parse an uploaded file using stream-based row-by-row reading.
     * Returns a flat array of mapped asset rows.
     *
     * @param  string  $filePath  Absolute path to the uploaded temp file
     * @param  string  $extension  File extension (csv or xlsx)
     * @return array The parsed array of asset rows
     *
     * @throws Exception If header detection fails or file is unreadable
     */
    public function parseFile(string $filePath, string $extension): array
    {
        $results = [];
        $headerMap = null;
        $headerRowIndex = null;
        $scannedRows = 0;
        $maxHeaderScanRows = 15; // Only scan top 15 rows for header

        foreach ($this->readRows($filePath, $extension) as $rowIndex => $cells) {
            $scannedRows++;

            // Phase 1: Header detection (scan first N rows)
            if ($headerMap === null && $scannedRows <= $maxHeaderScanRows) {
                $detected = $this->detectHeader($cells);
                if ($detected !== null) {
                    $headerMap = $detected;
                    $headerRowIndex = $rowIndex;
                    Log::info("Header detected at row {$rowIndex}: " . json_encode($headerMap));
                    continue;
                }
                continue; // Skip pre-header rows
            }

            // If we scanned N rows and found no header, abort
            if ($headerMap === null && $scannedRows > $maxHeaderScanRows) {
                throw new Exception(__('assets.import_parse_error', [
                    'message' => 'Could not detect a valid header row in the first 15 rows.',
                ]));
            }

            // Skip the header row itself
            if ($rowIndex === $headerRowIndex) {
                continue;
            }

            // Phase 2: Data extraction
            $mapped = $this->mapRow($cells, $headerMap);

            // Skip completely empty rows
            if ($this->isEmptyRow($mapped)) {
                continue;
            }

            $results[] = $mapped;
        }

        // Edge case: file had a header but zero data rows
        if ($headerMap === null) {
            Log::warning('Import file had no detectable header row.');
        }

        return $results;
    }

    /**
     * Generator: yields rows one at a time from CSV or XLSX.
     * Memory-efficient — never loads entire file.
     */
    private function readRows(string $filePath, string $extension, int $sheetIndex = 0): Generator
    {
        if ($extension === 'csv') {
            $options = new CsvOptions();
            $reader = new CsvReader($options);
        } else {
            $options = new XlsxOptions();
            $reader = new XlsxReader($options);
        }

        $reader->open($filePath);

        $rowIndex = 0;
        $currentSheet = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($currentSheet === $sheetIndex) {
                foreach ($sheet->getRowIterator() as $row) {
                    /** @var Row $row */
                    $cells = array_map(function ($val) {
                        return is_string($val) ? trim($val) : $val;
                    }, $row->toArray());

                    yield $rowIndex => $cells;
                    $rowIndex++;
                }
                break; // Only process the target sheet
            }
            $currentSheet++;
        }

        $reader->close();
    }

    /**
     * Heuristic header detection.
     * A row is considered a header if it matches >= 2 known column keywords.
     *
     * @return array|null  Map of canonical_field => column_index, or null if not a header
     */
    private function detectHeader(array $cells): ?array
    {
        $map = [];
        $matchCount = 0;

        foreach ($cells as $colIndex => $cellValue) {
            if (empty($cellValue) || !is_string($cellValue)) {
                continue;
            }

            $normalized = strtolower(trim($cellValue));

            foreach (self::HEADER_MAP as $field => $pattern) {
                if (isset($map[$field])) {
                    continue; // Already mapped this field
                }

                if (preg_match($pattern, $normalized)) {
                    $map[$field] = $colIndex;
                    $matchCount++;
                    break; // One cell → one field
                }
            }
        }

        // Require at least 2 keyword matches to confirm this is a header row
        return $matchCount >= 2 ? $map : null;
    }

    /**
     * Map a data row's cells into a standardized asset array using the header map.
     */
    private function mapRow(array $cells, array $headerMap): array
    {
        $get = function (string $field) use ($cells, $headerMap) {
            if (!isset($headerMap[$field])) {
                return '';
            }
            $val = $cells[$headerMap[$field]] ?? '';
            // Handle DateTimeInterface objects from XLSX
            if ($val instanceof \DateTimeInterface) {
                return $val->format('Y-m-d');
            }
            return is_string($val) ? trim($val) : (string) $val;
        };

        // Combine brand + model into a single field for the review UI
        $brand = $get('brand');
        $model = $get('model');
        $combined = trim("{$brand} {$model}");

        return [
            'tag'              => $get('tag'),
            'name'             => $get('name'),
            'category_id'      => '', // Will be mapped by user on review page
            'department_id'    => '', // Will be mapped by user on review page
            'status'           => $this->normalizeStatus($get('status')),
            'model'            => $combined,
            'serial_number'    => $get('serial_number'),
            'purchase_date'    => $get('purchase_date'),
            'purchase_cost'    => $get('cost'),
            '_category_hint'   => $get('category'),
            '_department_hint' => $get('department'),
        ];
    }

    /**
     * Normalize status strings to valid enum values.
     */
    private function normalizeStatus(string $raw): string
    {
        $raw = strtolower(trim($raw));

        if (preg_match('/(aktif|active|in.?service|baik|good|bagus)/i', $raw)) {
            return 'in_service';
        }
        if (preg_match('/(rusak|broken|out.?of.?service|tidak.?aktif|inactive|non.?aktif)/i', $raw)) {
            return 'out_of_service';
        }
        if (preg_match('/(disposed|dibuang|dihapus|removed|scrap)/i', $raw)) {
            return 'disposed';
        }

        return 'in_service'; // Default
    }

    /**
     * Check if a mapped row is completely empty (no meaningful data).
     */
    private function isEmptyRow(array $row): bool
    {
        $checkFields = ['tag', 'name', 'serial_number', 'model', 'purchase_date'];
        foreach ($checkFields as $field) {
            if (!empty($row[$field])) {
                return false;
            }
        }
        return true;
    }
}
