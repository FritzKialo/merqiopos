<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProducts implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const REQUIRED_HEADERS = ['name', 'selling_price'];
    const OPTIONAL_HEADERS = [
        'sku', 'description', 'category',
        'buying_price', 'stock_qty', 'reorder_level',
        'unit', 'status',
    ];

    // Other names people commonly give the same columns (after lower-casing and
    // turning spaces into underscores), so a spreadsheet does not have to be
    // renamed to match the template exactly.
    const HEADER_ALIASES = [
        'product' => 'name', 'product_name' => 'name', 'item' => 'name', 'item_name' => 'name',
        'price' => 'selling_price', 'sell_price' => 'selling_price', 'selling' => 'selling_price', 'sale_price' => 'selling_price',
        'cost' => 'buying_price', 'cost_price' => 'buying_price', 'buy_price' => 'buying_price', 'buying' => 'buying_price',
        'stock' => 'stock_qty', 'quantity' => 'stock_qty', 'qty' => 'stock_qty', 'opening_stock' => 'stock_qty',
        'reorder' => 'reorder_level', 'reorder_qty' => 'reorder_level', 'min_stock' => 'reorder_level',
        'code' => 'sku', 'barcode' => 'sku', 'product_code' => 'sku',
        'category_name' => 'category', 'units' => 'unit',
    ];

    const MAX_PRICE = 9999999999.99; // decimal(12,2)

    public int $tries = 1;

    public function __construct(
        private readonly string $filePath,
        private readonly int    $businessId,
        private readonly int    $planLimit,
    ) {}

    public function handle(): array {
        try {
            return $this->run();
        } finally {
            // The uploaded file holds the business's product list — never leave
            // it behind, including when the import stops early on an error.
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
        }
    }

    private function run(): array {
        $results = [
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => [],
        ];

        [$headers, $rows] = $this->parseRows($results);

        if (! empty($results['errors'])) {
            return $results;
        }

        // Validate required headers exist
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers)) {
                $results['errors'][] = "Missing required column: \"{$required}\". Please use the template. Columns found: "
                    . (implode(', ', array_filter($headers)) ?: 'none') . '.';
                return $results;
            }
        }

        // SKUs and category names compare case-insensitively (the database does),
        // and deleted products still hold their SKU.
        $existingSkus = Product::withTrashed()->forBusiness($this->businessId)
            ->pluck('sku')
            ->map(fn ($s) => mb_strtolower(trim((string) $s)))
            ->flip()
            ->toArray();

        $categoryMap = [];
        foreach (Category::where('business_id', $this->businessId)->get(['id', 'name']) as $c) {
            $categoryMap[mb_strtolower(trim($c->name))] = $c->id;
        }

        $currentCount = Product::forBusiness($this->businessId)->count();
        $rowNumber    = 1;

        foreach ($rows as $row) {
            $rowNumber++;

            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $header) {
                if ($header === '') continue;
                $data[$header] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            $skip = function (string $why) use (&$results, $rowNumber) {
                $results['skipped']++;
                $results['errors'][] = "Row {$rowNumber}: Skipped — {$why}";
            };

            if ($currentCount >= $this->planLimit) {
                $skip("product limit ({$this->planLimit}) reached.");
                continue;
            }

            if (empty($data['name'])) {
                $skip('"name" is required.');
                continue;
            }
            if (mb_strlen($data['name']) > 255) {
                $skip('name is longer than 255 characters.');
                continue;
            }

            $sellingPrice = $this->number($data['selling_price'] ?? '');
            if ($sellingPrice === null || $sellingPrice < 0 || $sellingPrice > self::MAX_PRICE) {
                $skip("invalid selling_price \"" . ($data['selling_price'] ?? '') . "\".");
                continue;
            }

            // Optional numbers: blank means 0, but a value that is present and
            // unreadable is an error — it used to be saved as 0 without a word.
            $buyingPrice = 0.0;
            if (($data['buying_price'] ?? '') !== '') {
                $buyingPrice = $this->number($data['buying_price']);
                if ($buyingPrice === null || $buyingPrice < 0 || $buyingPrice > self::MAX_PRICE) {
                    $skip("invalid buying_price \"{$data['buying_price']}\".");
                    continue;
                }
            }

            $counts = [];
            foreach (['stock_qty', 'reorder_level'] as $field) {
                $counts[$field] = 0;
                if (($data[$field] ?? '') === '') continue;
                $n = $this->number($data[$field]);
                if ($n === null || $n < 0 || $n > 2000000000 || floor($n) != $n) {
                    $skip("{$field} \"{$data[$field]}\" must be a whole number of 0 or more.");
                    continue 2;
                }
                $counts[$field] = (int) $n;
            }

            $sku = $data['sku'] ?? '';
            if ($sku !== '' && mb_strlen($sku) > 255) {
                $skip('SKU is longer than 255 characters.');
                continue;
            }
            if ($sku !== '' && isset($existingSkus[mb_strtolower($sku)])) {
                $skip("SKU \"{$sku}\" already exists.");
                continue;
            }

            $categoryId = null;
            if (! empty($data['category'] ?? '')) {
                $catName = $data['category'];
                $key     = mb_strtolower($catName);
                if (! isset($categoryMap[$key])) {
                    try {
                        $cat = Category::create([
                            'business_id' => $this->businessId,
                            'name'        => mb_substr($catName, 0, 255),
                        ]);
                        $categoryMap[$key] = $cat->id;
                    } catch (\Throwable $e) {
                        // A category the map missed (e.g. it differs only by
                        // accents): use the existing one instead of aborting
                        // the whole import half-way.
                        $existing = Category::where('business_id', $this->businessId)->where('name', $catName)->first();
                        if (! $existing) {
                            $skip("could not create category \"{$catName}\".");
                            Log::warning("ProductImport row {$rowNumber} category failed: " . $e->getMessage());
                            continue;
                        }
                        $categoryMap[$key] = $existing->id;
                    }
                }
                $categoryId = $categoryMap[$key];
            }

            if ($sku === '') {
                $sku = Product::generateSku($data['name'], $this->businessId);
            }

            // 'unit' is optional per the docs but the column is NOT NULL — a file
            // without it (or with it blank) failed every single row. Default to
            // 'piece', and map the template's own 'pcs' example onto it.
            $unit = strtolower(trim($data['unit'] ?? ''));
            if ($unit === '' || in_array($unit, ['pcs', 'pc', 'pieces'], true)) {
                $unit = 'piece';
            }
            $unit = mb_substr($unit, 0, 50);

            $status = strtolower($data['status'] ?? 'active');
            if (! in_array($status, ['active', 'inactive'])) {
                $status = 'active';
            }

            try {
                Product::create([
                    'business_id'   => $this->businessId,
                    'category_id'   => $categoryId,
                    'name'          => $data['name'],
                    'sku'           => $sku,
                    'description'   => ($data['description'] ?? '') !== '' ? $data['description'] : null,
                    'buying_price'  => $buyingPrice,
                    'selling_price' => $sellingPrice,
                    'stock_qty'     => $counts['stock_qty'],
                    'reorder_level' => $counts['reorder_level'],
                    'unit'          => $unit,
                    'status'        => $status,
                ]);

                $existingSkus[mb_strtolower($sku)] = true;
                $results['imported']++;
                $currentCount++;

            } catch (\Throwable $e) {
                $results['skipped']++;
                // Full exception text carries the raw SQL plus DB host/name —
                // log it, show the user something readable.
                $results['errors'][] = "Row {$rowNumber}: Could not be saved — please check the values in this row.";
                Log::warning("ProductImport row {$rowNumber} failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Read a number the way people actually write it in a spreadsheet:
     * "1,500", "KES 1 500.50", "Ksh1,000", "12,50" (decimal comma). Returns
     * null when the text is not a number at all.
     */
    private function number(mixed $raw): ?float {
        $s = trim((string) $raw);
        if ($s === '') return null;

        $s = preg_replace('/^(kes|ksh|kshs|ksh\.|kes\.|\$)\s*/i', '', $s);
        $s = str_replace(["\u{00A0}", ' '], '', $s);

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);            // 1,500.50
        } elseif (preg_match('/^-?\d+,\d{1,2}$/', $s)) {
            $s = str_replace(',', '.', $s);           // 12,50
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function normaliseHeader(mixed $h): string {
        $h = strtolower(trim((string) ($h ?? '')));
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        $h = trim(preg_replace('/[\s\-]+/', '_', $h), '_');
        return self::HEADER_ALIASES[$h] ?? $h;
    }

    /**
     * Parse the file into [headers[], rows[][]].
     * Supports CSV/TXT (fgetcsv) and Excel/ODS (PhpSpreadsheet).
     */
    private function parseRows(array &$results): array {
        $ext = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

        if (in_array($ext, ['xlsx', 'xls', 'ods'])) {
            return $this->parseSpreadsheet($results);
        }

        return $this->parseCsv($results);
    }

    private function parseCsv(array &$results): array {
        $content = @file_get_contents($this->filePath);
        if ($content === false) {
            $results['errors'][] = 'Could not open the uploaded file.';
            return [[], []];
        }

        // Excel's "CSV UTF-8" adds an invisible marker at the start, which used
        // to turn the first column name into something unrecognisable.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Older Excel saves "CSV" as Windows-1252, not UTF-8.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        if (trim($content) === '') {
            $results['errors'][] = 'The file appears to be empty.';
            return [[], []];
        }

        // Excel in many regions writes semicolons (or tabs) instead of commas.
        $firstLine = strtok($content, "\r\n");
        $delimiter = ',';
        $best = substr_count($firstLine, ',');
        foreach ([';', "\t"] as $d) {
            if (substr_count($firstLine, $d) > $best) {
                $best = substr_count($firstLine, $d);
                $delimiter = $d;
            }
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (! $rawHeaders) {
            fclose($handle);
            $results['errors'][] = 'The file appears to be empty.';
            return [[], []];
        }

        $headers = array_map(fn ($h) => $this->normaliseHeader($h), $rawHeaders);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return [$headers, $rows];
    }

    private function parseSpreadsheet(array &$results): array {
        try {
            $spreadsheet = IOFactory::load($this->filePath);
        } catch (\Throwable $e) {
            $results['errors'][] = 'Could not read the spreadsheet: ' . $e->getMessage();
            return [[], []];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);

        if (empty($data)) {
            $results['errors'][] = 'The spreadsheet appears to be empty.';
            return [[], []];
        }

        $rawHeaders = array_shift($data);
        $headers    = array_map(fn ($h) => $this->normaliseHeader($h), $rawHeaders);

        return [$headers, $data];
    }
}
