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
                    'proposed_price' => 70000,
                ],
            ],
        ];

        $response = $this->post('/validator/analyze', $payload);

        $project = Project::query()->first();
        $this->assertNotNull($project);
        $this->assertSame('Renovasi Kantor Unit A', $project->name);
        $this->assertCount(2, $project->rabItems()->get());
        $this->assertSame('820000.00', (string) $project->total_proposed_budget);

        $response->assertRedirect('/validator/results/'.$project->id);
    }

    public function test_analyze_rejects_preparation_items_without_main_work_item(): void
    {
        $payload = [
            'project_name' => 'Renovasi Kantor Unit B',
            'location_province' => 'DKI Jakarta',
            'location_city' => 'Jakarta Selatan',
            'items' => [
                [
                    'category' => 'Persiapan & Akhir',
                    'item_name' => 'Mobilisasi dan demobilisasi',
                    'specification' => 'Paket',
                    'volume' => 1,
                    'unit' => 'ls',
                    'proposed_price' => 2500000,
                ],
            ],
        ];

        $response = $this->from('/validator')->post('/validator/analyze', $payload);

        $response->assertRedirect('/validator');
        $response->assertSessionHasErrors();
        $this->assertSame(0, Project::query()->count());
    }
}
