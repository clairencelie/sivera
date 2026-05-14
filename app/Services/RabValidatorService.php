<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RabItem;
use App\Models\ValidationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RabValidatorService
{
    // Threshold: biaya "Persiapan & Akhir" dianggap tidak wajar jika melebihi X% dari total "Pekerjaan Utama"
    private const OVERHEAD_WARNING_THRESHOLD_PERCENT = 10;

    public function __construct(private GeminiService $geminiService) {}

    /**
     * Main orchestrator: menerima data proyek + list item RAB,
     * menyimpan ke DB, lalu memanggil AI per item.
     * Returns project with all validation results loaded.
     */
    public function processAndValidate(array $projectData, array $rabItemsData): Project
    {
        return DB::transaction(function () use ($projectData, $rabItemsData) {
            // 1. Simpan data proyek
            $totalBudget = collect($rabItemsData)->sum(fn($i) => ($i['volume'] ?? 0) * ($i['proposed_price'] ?? 0));
            $project = Project::create(array_merge($projectData, ['total_proposed_budget' => $totalBudget]));

            // 2. Simpan semua item RAB
            $rabItems = [];
            foreach ($rabItemsData as $itemData) {
                $rabItems[] = $project->rabItems()->create([
                    'category'      => $itemData['category'],
                    'item_name'     => $itemData['item_name'],
                    'specification' => $itemData['specification'] ?? null,
                    'volume'        => $itemData['volume'],
                    'unit'          => $itemData['unit'],
                    'proposed_price' => $itemData['proposed_price'],
                ]);
            }

            // 3. Validasi harga setiap item via AI
            foreach ($rabItems as $rabItem) {
                $this->validateItem($rabItem, $project);
            }

            // 4. Load relasi lengkap untuk dikembalikan
            return $project->load('rabItems.validationResult');
        });
    }

    /**
     * Panggil Gemini untuk satu item, simpan hasilnya ke validation_results.
     */
    private function validateItem(RabItem $rabItem, Project $project): void
    {
        try {
            $result = $this->geminiService->validateItemPrice(
                itemName: $rabItem->item_name,
                specification: $rabItem->specification ?? '',
                volume: (float) $rabItem->volume,
                unit: $rabItem->unit,
                proposedPrice: (float) $rabItem->proposed_price,
                locationCity: $project->location_city,
                locationProvince: $project->location_province,
            );

            ValidationResult::create(array_merge(['rab_item_id' => $rabItem->id], $result));

        } catch (\Exception $e) {
            Log::error('Failed to validate RAB item', ['item_id' => $rabItem->id, 'error' => $e->getMessage()]);
            // Simpan hasil error agar tidak memblokir item lain
            ValidationResult::create([
                'rab_item_id'   => $rabItem->id,
                'ai_model_used' => 'gemini-1.5-flash',
                'status'        => 'Tidak Ditemukan',
                'reasoning'     => 'Error saat memproses: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Hitung rasio overhead dan kembalikan analisis kewajaran biaya persiapan.
     */
    public function getOverheadAnalysis(Project $project): array
    {
        $project->loadMissing('rabItems');

        $preparationTotal = $project->rabItems
            ->where('category', 'Persiapan & Akhir')
            ->sum(fn($i) => (float) $i->volume * (float) $i->proposed_price);

        $mainWorkTotal = $project->rabItems
            ->where('category', 'Pekerjaan Utama')
            ->sum(fn($i) => (float) $i->volume * (float) $i->proposed_price);

        $ratio = $mainWorkTotal > 0
            ? round(($preparationTotal / $mainWorkTotal) * 100, 2)
            : 0;

        $isReasonable = $ratio <= self::OVERHEAD_WARNING_THRESHOLD_PERCENT;

        return [
            'preparation_total'   => $preparationTotal,
            'main_work_total'     => $mainWorkTotal,
            'overhead_ratio'      => $ratio,
            'threshold'           => self::OVERHEAD_WARNING_THRESHOLD_PERCENT,
            'is_reasonable'       => $isReasonable,
            'warning_message'     => $isReasonable
                ? null
                : "Biaya Persiapan & Akhir ({$ratio}%) melebihi batas kewajaran " . self::OVERHEAD_WARNING_THRESHOLD_PERCENT . "% dari total Pekerjaan Utama.",
        ];
    }
}
