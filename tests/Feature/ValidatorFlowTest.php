<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidatorFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_creates_project_and_items_then_redirects_to_results(): void
    {
        $payload = [
            'project_name' => 'Renovasi Kantor Unit A',
            'location_province' => 'DKI Jakarta',
            'location_city' => 'Jakarta Pusat',
            'items' => [
                [
                    'category' => 'Pekerjaan Utama',
                    'item_name' => 'Semen PC 50kg',
                    'specification' => 'PCC',
                    'volume' => 10,
                    'unit' => 'zak',
                    'proposed_price' => 75000,
                ],
                [
                    'category' => 'Persiapan & Akhir',
                    'item_name' => 'Jasa kebersihan akhir',
                    'specification' => 'General cleaning',
                    'volume' => 1,
                    'unit' => 'ls',
                    'proposed_price' => 1200000,
                ],
            ],
        ];

        $response = $this->post('/validator/analyze', $payload);

        $project = Project::query()->first();
        $this->assertNotNull($project);
        $this->assertSame('Renovasi Kantor Unit A', $project->name);
        $this->assertCount(2, $project->rabItems()->get());
        $this->assertSame('1950000.00', (string) $project->total_proposed_budget);

        $response->assertRedirect('/validator/results/'.$project->id);
    }
}
