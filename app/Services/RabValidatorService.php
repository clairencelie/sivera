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
            foreach ($rabItemsData as $itemData) {
                $project->rabItems()->create([
                    'category'      => $itemData['category'],
                    'item_name'     => $itemData['item_name'],
                    'specification' => $itemData['specification'] ?? null,
                    'volume'        => $itemData['volume'],
                    'unit'          => $itemData['unit'],
                    'proposed_price' => $itemData['proposed_price'],
                ]);
            }

            // 3. Load relasi untuk dikembalikan
            return $project->load('rabItems.validationResult');
        });
    }

    /**
     * Validasi satu item RAB secara individu (untuk pemrosesan AJAX).
     * Menyimpan hasil validasi ke DB dan mengembalikannya.
     */
    public function validateSingleItem(int $rabItemId): ValidationResult
    {
        $rabItem = RabItem::findOrFail($rabItemId);
        $project = $rabItem->project;

        // Cek jika sudah ada hasil validasi untuk menghindari duplikasi call
        $existingResult = ValidationResult::where('rab_item_id', $rabItemId)->first();
        if ($existingResult) {
            return $existingResult;
        }

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

            return ValidationResult::create(array_merge(['rab_item_id' => $rabItem->id], $result));

        } catch (\Exception $e) {
            Log::error('Failed to validate RAB item via AJAX', ['item_id' => $rabItem->id, 'error' => $e->getMessage()]);
            
            // Simpan hasil dengan status error agar UI tetap dapat merender baris ini dengan anggun
            return ValidationResult::create([
                'rab_item_id'   => $rabItem->id,
                'ai_model_used' => config('services.gemini.model', 'gemini-2.5-flash'),
                'status'        => 'Tidak Ditemukan',
                'api_error'     => $e->getMessage(),
                'reasoning'     => 'Gagal memproses validasi AI: ' . $e->getMessage(),
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
