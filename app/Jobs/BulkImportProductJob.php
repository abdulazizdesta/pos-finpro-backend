<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Product;
use App\Traits\GeneratesSku;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BulkImportProductJob implements ShouldQueue
{
    use Queueable, GeneratesSku;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly string $filePath,
        public readonly int $businessId,
    ) {
    }

    public function handle(): void
    {
        $handle = fopen($this->filePath, 'r');
        $header = array_map('trim', fgetcsv($handle));

        $success = 0;
        $failed = [];
        $row = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;

            if (count($line) !== count($header)) {
                $failed[] = ['row' => $row, 'reason' => 'Column count mismatch'];
                continue;
            }

            $data = array_combine($header, $line);

            if (empty($data['name']) || empty($data['price'])) {
                $failed[] = ['row' => $row, 'reason' => 'name and price are required'];
                continue;
            }

            $sku = !empty($data['sku']) ? trim($data['sku']) : $this->generateSku($data['name']);
            if (Product::where('sku', $sku)->exists()) {
                $failed[] = ['row' => $row, 'reason' => "SKU '{$sku}' already exists"];
                continue;
            }

            $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
            if ($categoryId !== null) {
                $categoryValid = Category::where('id', $categoryId)
                    ->where('business_id', $this->businessId)
                    ->exists();

                if (!$categoryValid) {
                    $failed[] = ['row' => $row, 'reason' => "Category {$categoryId} not found in this business"];
                    continue;
                }
            }

            try {
                Product::create([
                    'business_id' => $this->businessId,
                    'category_id' => $categoryId,
                    'name' => trim($data['name']),
                    'sku' => $sku,
                    'description' => $data['description'] ?? null,
                    'price' => (float) $data['price'],
                    'cost_price' => !empty($data['cost_price']) ? (float) $data['cost_price'] : null,
                    'has_variants' => filter_var($data['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
                $success++;
            } catch (\Throwable $e) {
                $failed[] = ['row' => $row, 'reason' => $e->getMessage()];
            }
        }

        fclose($handle);

        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }

        Log::channel('api')->info('BulkImportProductJob selesai', [
            'business_id' => $this->businessId,
            'imported' => $success,
            'failed' => count($failed),
            'errors' => $failed,
        ]);
    }
}