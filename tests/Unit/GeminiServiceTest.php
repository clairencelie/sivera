<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_it_maps_grounding_metadata_to_sources_and_reference_url(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'found_item_name' => 'Semen Gresik 50kg',
                                'is_equivalent' => false,
                                'price_min' => 70000,
                                'price_max' => 76000,
                                'reference_url' => 'https://fallback.example/item',
                                'status' => 'Wajar',
                                'reasoning' => 'Harga masih dalam rentang pasar.',
                            ]),
                        ]],
                    ],
                    'groundingMetadata' => [
                        'webSearchQueries' => ['harga semen gresik tangerang'],
                        'groundingChunks' => [
                            ['web' => ['uri' => 'https://source-1.example', 'title' => 'Source 1']],
                            ['web' => ['uri' => 'https://source-2.example', 'title' => 'Source 2']],
                        ],
                        'groundingSupports' => [
                            ['groundingChunkIndices' => [0, 1]],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $service = new GeminiService();
        $result = $service->validateItemPrice(
            itemName: 'Semen PC 50kg',
            specification: 'PCC',
            volume: 10,
            unit: 'zak',
            proposedPrice: 72000,
            locationCity: 'Kota Tangerang',
            locationProvince: 'Banten',
        );

        $this->assertSame('Wajar', $result['status']);
        $this->assertSame('https://source-1.example', $result['reference_url']);
        $this->assertSame(['https://source-1.example', 'https://source-2.example'], $result['source_urls']);
        $this->assertSame(['harga semen gresik tangerang'], $result['web_search_queries']);
        $this->assertIsArray($result['grounding_metadata']);
        $this->assertNotNull($result['latency_ms']);
        $this->assertNull($result['api_error']);
    }
}
