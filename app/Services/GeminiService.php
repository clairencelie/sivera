<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private const MARKETPLACE_DOMAINS = [
        'tokopedia.com',
        'shopee.co.id',
        'bukalapak.com',
        'lazada.co.id',
        'blibli.com',
        'jd.id',
        'olx.co.id',
    ];

    private const PREFERRED_RETAIL_DOMAINS = [
        'myhartono.com',
        'electronic-city.com',
        'electroniccity.co.id',
        'acehardware.co.id',
        'depo-bangunan.co.id',
        'mitra10.com',
        'ruparupa.com',
        'ikea.co.id',
        'informa.co.id',
        'lkpp.go.id',
    ];

    private const MAX_REASONING_LENGTH = 320;

    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash');
    }

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

        $start = microtime(true);

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
                    ],
                ]);
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
                return $this->errorResult(
                    reason: 'API request failed: ' . $response->status(),
                    apiError: $response->body(),
                    latencyMs: $latencyMs
                );
            }

            return $this->parseResponse($response->json(), $latencyMs);
        } catch (\Exception $e) {
            Log::error('GeminiService exception', ['error' => $e->getMessage()]);
            return $this->errorResult(
                reason: $e->getMessage(),
                apiError: $e->getMessage(),
                latencyMs: (int) round((microtime(true) - $start) * 1000)
            );
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
Kamu adalah asisten validasi harga material dan jasa konstruksi berbasis data internet terbaru.

Data item:
- Nama: {$itemName}
- Spesifikasi: {$specification}
- Volume: {$formattedVolume}
- Harga usulan: {$formattedPrice}
- Lokasi: {$locationCity}, {$locationProvince}

Aturan:
1. Cari harga via Google Search sekarang.
2. Prioritaskan sumber non-marketplace resmi (vendor/distributor/instansi/retail resmi).
3. Marketplace umum boleh dipakai hanya jika tidak ada sumber resmi, dan beri catatan.
4. Jika spesifikasi persis tidak ditemukan, boleh pakai item setara dan set `is_equivalent=true`.
5. Klasifikasi status:
   - Wajar: dalam ±15% dari harga pasar
   - Overprice: >15% di atas pasar
   - Underprice: >15% di bawah pasar
   - Tidak Ditemukan: tidak ada data

Kembalikan HANYA JSON valid:
{
  "found_item_name": "string|null",
  "is_equivalent": false,
  "price_min": 0,
  "price_max": 0,
  "reference_url": "https://...",
  "status": "Wajar|Overprice|Underprice|Tidak Ditemukan",
  "reasoning": "maksimal 2 kalimat ringkas"
}
PROMPT;
    }

    private function parseResponse(array $responseJson, int $latencyMs): array
    {
        try {
            $candidate = $responseJson['candidates'][0] ?? [];
            $parts = $candidate['content']['parts'] ?? [];
            $textParts = collect($parts)
                ->map(fn($part) => $part['text'] ?? null)
                ->filter()
                ->values()
                ->all();
            $text = trim(implode("\n", $textParts));
            $grounding = $candidate['groundingMetadata'] ?? [];
            $sourceUrls = $this->extractSourceUrls($grounding);
            $searchQueries = $grounding['webSearchQueries'] ?? [];

            if ($text === '') {
                return $this->errorResult(
                    reason: 'Empty response from Gemini',
                    groundingMetadata: is_array($grounding) ? $grounding : null,
                    sourceUrls: $sourceUrls,
                    searchQueries: is_array($searchQueries) ? $searchQueries : [],
                    latencyMs: $latencyMs
                );
            }

            $text = preg_replace('/^```json\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/i', '', $text);
            $data = json_decode((string) $text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Gemini non-JSON response', ['text' => $text]);
                return $this->errorResult(
                    reason: 'Invalid JSON in response',
                    groundingMetadata: is_array($grounding) ? $grounding : null,
                    sourceUrls: $sourceUrls,
                    searchQueries: is_array($searchQueries) ? $searchQueries : [],
                    latencyMs: $latencyMs
                );
            }

            $referenceFromModel = $data['reference_url'] ?? null;
            $primaryReference = $sourceUrls[0] ?? $referenceFromModel;
            $sourceQualityNote = $this->buildSourceQualityNote($sourceUrls);
            $reasoning = $this->normalizeReasoning((string) ($data['reasoning'] ?? ''));
            if ($sourceQualityNote) {
                $reasoning = trim(($reasoning ? rtrim($reasoning, '.') . '. ' : '') . $sourceQualityNote);
            }
            $reasoning = $this->truncateText($reasoning, self::MAX_REASONING_LENGTH);

            return [
                'ai_model_used' => $this->model,
                'found_item_name' => $data['found_item_name'] ?? null,
                'is_equivalent' => (bool) ($data['is_equivalent'] ?? false),
                'price_min' => (float) ($data['price_min'] ?? 0),
                'price_max' => (float) ($data['price_max'] ?? 0),
                'reference_url' => $primaryReference,
                'source_urls' => $sourceUrls,
                'web_search_queries' => is_array($searchQueries) ? $searchQueries : [],
                'grounding_metadata' => is_array($grounding) ? $grounding : null,
                'latency_ms' => $latencyMs,
                'api_error' => null,
                'status' => $data['status'] ?? 'Tidak Ditemukan',
                'reasoning' => $reasoning,
            ];
        } catch (\Exception $e) {
            return $this->errorResult(
                reason: 'Parse error: ' . $e->getMessage(),
                apiError: $e->getMessage(),
                latencyMs: $latencyMs
            );
        }
    }

    private function extractSourceUrls(array $groundingMetadata): array
    {
        $chunks = $groundingMetadata['groundingChunks'] ?? [];
        if (!is_array($chunks)) {
            return [];
        }

        $urls = [];
        foreach ($chunks as $chunk) {
            $uri = $chunk['web']['uri'] ?? null;
            if (is_string($uri) && filter_var($uri, FILTER_VALIDATE_URL)) {
                $urls[$uri] = true;
            }
        }

        $ordered = array_keys($urls);
        usort($ordered, fn($a, $b) => $this->sourceScore($b) <=> $this->sourceScore($a));
        return $ordered;
    }

    private function buildSourceQualityNote(array $sourceUrls): ?string
    {
        if (count($sourceUrls) === 0) {
            return null;
        }

        $marketplaceCount = 0;
        $preferredCount = 0;

        foreach ($sourceUrls as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if (!is_string($host)) {
                continue;
            }
            $normalizedHost = strtolower(preg_replace('/^www\./', '', $host));
            if ($this->hostMatchesAny($normalizedHost, self::MARKETPLACE_DOMAINS)) {
                $marketplaceCount++;
            }
            if ($this->hostMatchesAny($normalizedHost, self::PREFERRED_RETAIL_DOMAINS)) {
                $preferredCount++;
            }
        }

        if ($marketplaceCount === count($sourceUrls)) {
            return 'Catatan: sumber marketplace, perlu verifikasi lanjutan.';
        }
        if ($marketplaceCount > 0 && $preferredCount === 0) {
            return 'Catatan: ada sumber marketplace, verifikasi tambahan disarankan.';
        }

        return null;
    }

    private function sourceScore(string $url): int
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host)) {
            return 0;
        }
        $host = strtolower(preg_replace('/^www\./', '', $host));

        if ($this->hostMatchesAny($host, self::PREFERRED_RETAIL_DOMAINS)) {
            return 100;
        }
        if ($this->hostMatchesAny($host, self::MARKETPLACE_DOMAINS)) {
            return 20;
        }

        return 60;
    }

    private function hostMatchesAny(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            $domain = strtolower($domain);
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeReasoning(string $text): string
    {
        return preg_replace('/\s+/', ' ', trim($text)) ?? '';
    }

    private function truncateText(?string $text, int $maxLength): ?string
    {
        if (!$text) {
            return $text;
        }
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }

    private function errorResult(
        string $reason,
        ?string $apiError = null,
        ?array $groundingMetadata = null,
        array $sourceUrls = [],
        array $searchQueries = [],
        ?int $latencyMs = null
    ): array {
        return [
            'ai_model_used' => $this->model,
            'found_item_name' => null,
            'is_equivalent' => false,
            'price_min' => 0,
            'price_max' => 0,
            'reference_url' => $sourceUrls[0] ?? null,
            'source_urls' => $sourceUrls,
            'web_search_queries' => $searchQueries,
            'grounding_metadata' => $groundingMetadata,
            'latency_ms' => $latencyMs,
            'api_error' => $apiError,
            'status' => 'Tidak Ditemukan',
            'reasoning' => 'Gagal mendapatkan data dari AI: ' . $reason,
        ];
    }
}
