<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
    }

    /**
     * Validates the price of a single RAB item using Gemini with Google Search Grounding.
     * Returns a structured array with price range, reference URL, status, and reasoning.
     */
    public function validateItemPrice(
        string $itemName,
        string $specification,
        float $volume,
        string $unit,
        float $proposedPrice,
        string $locationCity,
        string $locationProvince
    ): array {
        $prompt = $this->buildPrompt(
            $itemName,
            $specification,
            $volume,
            $unit,
            $proposedPrice,
            $locationCity,
            $locationProvince
        );

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'tools' => [
                        ['google_search' => (object) []],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
                return $this->errorResult('API request failed: ' . $response->status());
            }

            return $this->parseResponse($response->json());

        } catch (\Exception $e) {
            Log::error('GeminiService exception', ['error' => $e->getMessage()]);
            return $this->errorResult($e->getMessage());
        }
    }

    private function buildPrompt(
        string $itemName,
        string $specification,
        float $volume,
        string $unit,
        float $proposedPrice,
        string $locationCity,
        string $locationProvince
    ): string {
        $formattedPrice = 'Rp ' . number_format($proposedPrice, 0, ',', '.');
        $formattedVolume = number_format($volume, 2, ',', '.') . ' ' . $unit;

        return <<<PROMPT
Kamu adalah asisten validasi harga material dan jasa konstruksi yang sangat teliti dan berbasis data nyata dari internet.

## Data Item yang Harus Divalidasi:
- **Nama Item:** {$itemName}
- **Spesifikasi:** {$specification}
- **Volume:** {$formattedVolume}
- **Harga Satuan Diusulkan:** {$formattedPrice}
- **Lokasi Proyek:** {$locationCity}, {$locationProvince}

## Instruksi Wajib:
1. **Cari harga** item ini di internet SEKARANG menggunakan Google Search.
2. **Prioritaskan pencarian** di wilayah {$locationCity}. Jika tidak ada data, gunakan data tingkat {$locationProvince}.
3. **Prioritaskan sumber** dari: e-katalog.lkpp.go.id, website toko bangunan/elektronik setempat, atau marketplace (Tokopedia, Shopee, myHartono).
4. **Jika spesifikasi persis tidak ditemukan**, cari item dengan spesifikasi SETARA (equivalent) dan tandai `is_equivalent: true`.
5. **Wajib** menyertakan URL referensi yang valid dan nyata. JANGAN mengarang URL.
6. **Evaluasi status** berdasarkan perbandingan harga usulan vs harga pasar:
   - `Wajar`: harga usulan berada dalam ±15% dari harga pasar
   - `Overprice`: harga usulan lebih dari 15% di atas harga pasar
   - `Underprice`: harga usulan lebih dari 15% di bawah harga pasar
   - `Tidak Ditemukan`: data harga tidak dapat ditemukan di internet

## Format Output (JSON):
Kembalikan HANYA JSON valid berikut, tanpa teks lain:
```json
{
  "found_item_name": "nama item yang ditemukan atau dicek (bisa berbeda jika equivalent)",
  "is_equivalent": false,
  "price_min": 0,
  "price_max": 0,
  "reference_url": "https://...",
  "status": "Wajar|Overprice|Underprice|Tidak Ditemukan",
  "reasoning": "Penjelasan singkat dalam Bahasa Indonesia mengapa status tersebut diberikan, dan sumber data dari mana."
}
```
PROMPT;
    }

    private function parseResponse(array $responseJson): array
    {
        try {
            $text = $responseJson['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                return $this->errorResult('Empty response from Gemini');
            }

            // Strip markdown code fences if present
            $text = preg_replace('/^```json\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/i', '', $text);

            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gemini non-JSON response', ['text' => $text]);
                return $this->errorResult('Invalid JSON in response');
            }

            return [
                'ai_model_used'   => $this->model,
                'found_item_name' => $data['found_item_name'] ?? null,
                'is_equivalent'   => (bool) ($data['is_equivalent'] ?? false),
                'price_min'       => (float) ($data['price_min'] ?? 0),
                'price_max'       => (float) ($data['price_max'] ?? 0),
                'reference_url'   => $data['reference_url'] ?? null,
                'status'          => $data['status'] ?? 'Tidak Ditemukan',
                'reasoning'       => $data['reasoning'] ?? null,
            ];

        } catch (\Exception $e) {
            return $this->errorResult('Parse error: ' . $e->getMessage());
        }
    }

    private function errorResult(string $reason): array
    {
        return [
            'ai_model_used'   => $this->model,
            'found_item_name' => null,
            'is_equivalent'   => false,
            'price_min'       => 0,
            'price_max'       => 0,
            'reference_url'   => null,
            'status'          => 'Tidak Ditemukan',
            'reasoning'       => 'Gagal mendapatkan data dari AI: ' . $reason,
        ];
    }
}
