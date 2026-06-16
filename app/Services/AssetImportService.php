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
     * @param int $sheetIndex Zero-based index of the sheet to extract from (default: 0)
     */
    public function peek(string $filePath, string $extension, int $sheetIndex = 0): array
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
                    $cells = array_map(function ($val) {
                        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d');
                        return is_string($val) ? trim($val) : (string) $val;
                    }, $row->toArray());

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
        $trueHeaderIndex = 0;
        $maxCells = -1;

        foreach ($firstSheetRows as $idx => $row) {
            $nonEmptyCount = count(array_filter($row, fn($cell) => trim($cell) !== ''));
            if ($nonEmptyCount > $maxCells) {
                $maxCells = $nonEmptyCount;
                $trueHeaderIndex = $idx;
            }
        }

        // Sanitize headers to remove nulls, empty strings, and whitespace-only column headers
        $trueHeader = array_values(array_filter(
            array_map('trim', $firstSheetRows[$trueHeaderIndex] ?? []),
            fn($cell) => $cell !== '' && !is_null($cell)
        ));

        if (empty($trueHeader)) {
            throw new Exception('No valid headers detected or file is empty.');
        }

        // 2. Ekstrak Preview Data (10 baris setelah True Header)
        // Konversi dari indexed array ke associative array ber-key nama kolom
        // agar Alpine getCombinedValue(row, fieldId) bisa melakukan row[colName].
        $rawPreview = array_slice($firstSheetRows, $trueHeaderIndex + 1, 10);
        $previewData = [];
        foreach ($rawPreview as $row) {
            $assocRow = [];
            foreach ($trueHeader as $colIdx => $colName) {
                $assocRow[$colName] = $row[$colIdx] ?? '';
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
        ];
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
    private function readRows(string $filePath, string $extension): Generator
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
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                /** @var Row $row */
                $cells = array_map(function ($val) {
                    return is_string($val) ? trim($val) : $val;
                }, $row->toArray());

                yield $rowIndex => $cells;
                $rowIndex++;
            }
            break; // Only process the first sheet
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
            'tag'           => $get('tag'),
            'name'          => $get('name'),
            'category_id'   => '', // Will be mapped by user on review page
            'department_id' => '', // Will be mapped by user on review page
            'status'        => $this->normalizeStatus($get('status')),
            'model'         => $combined,
            'serial_number' => $get('serial_number'),
            'purchase_date' => $get('purchase_date'),
            '_category_hint' => $get('category'),
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
