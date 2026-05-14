<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\RabValidatorService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ValidatorController extends Controller
{
    public function __construct(private RabValidatorService $validatorService) {}

    /**
     * Show the RAB input form.
     */
    public function index(): View
    {
        return view('validator.index');
    }

    /**
     * Accept the RAB submission, run AI validation, redirect to results.
     */
    public function analyze(Request $request): RedirectResponse
    {
        $request->validate([
            'project_name'        => 'required|string|max:255',
            'location_province'   => 'required|string|max:100',
            'location_city'       => 'required|string|max:100',
            'items'               => 'required|array|min:1',
            'items.*.category'    => 'required|in:Persiapan & Akhir,Pekerjaan Utama',
            'items.*.item_name'   => 'required|string|max:255',
            'items.*.volume'      => 'required|numeric|min:0.01',
            'items.*.unit'        => 'required|string|max:50',
            'items.*.proposed_price' => 'required|numeric|min:0',
        ]);

        $projectData = [
            'name'              => $request->input('project_name'),
            'location_city'     => $request->input('location_city'),
            'location_province' => $request->input('location_province'),
        ];

        $rabItemsData = collect($request->input('items'))->map(fn($item) => [
            'category'       => $item['category'],
            'item_name'      => $item['item_name'],
            'specification'  => $item['specification'] ?? null,
            'volume'         => (float) $item['volume'],
            'unit'           => $item['unit'],
            'proposed_price' => (float) $item['proposed_price'],
        ])->toArray();

        $project = $this->validatorService->processAndValidate($projectData, $rabItemsData);

        return redirect()->route('validator.results', $project->id)
            ->with('success', 'Validasi AI selesai!');
    }

    /**
     * Show validation results for a given project.
     */
    public function results(int $projectId): View
    {
        $project = Project::with('rabItems.validationResult')->findOrFail($projectId);

        $overheadAnalysis = $this->validatorService->getOverheadAnalysis($project);

        return view('validator.results', compact('project', 'overheadAnalysis'));
    }

    /**
     * List all past projects.
     */
    public function history(): View
    {
        $projects = Project::latest()->paginate(10);
        return view('validator.history', compact('projects'));
    }
}
