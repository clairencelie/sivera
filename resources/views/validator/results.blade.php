@extends('layouts.app')

@section('title', 'Hasil Validasi — ' . $project->name)

@push('styles')
<style>
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem 1.5rem; }
    .stat-label { font-size: 0.78rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; }
    .stat-value { font-size: 1.5rem; font-weight: 800; }

    .overhead-alert {
        border-radius: var(--radius-sm);
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        border: 1px solid;
        font-size: 0.88rem;
    }
    .overhead-alert.warn { border-color: var(--warning); background: rgba(245,158,11,0.08); color: var(--warning); }
    .overhead-alert.ok   { border-color: var(--success); background: rgba(16,185,129,0.08); color: var(--success); }

    .ref-link { color: var(--accent-hover); text-decoration: none; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px; }
    .ref-link:hover { text-decoration: underline; }
    .equivalent-tag { font-size: 0.72rem; color: var(--warning); border: 1px solid rgba(245,158,11,0.4); padding: 1px 6px; border-radius: 4px; margin-left: 4px; }

    .price-range { font-size: 0.82rem; color: var(--text-muted); }
    .price-range strong { color: var(--text); }

    .reasoning-cell { font-size: 0.8rem; color: var(--text-muted); max-width: 260px; line-height: 1.45; }
</style>
@endpush

@section('content')

<div class="page-header flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1>📊 Hasil Validasi Harga</h1>
        <p>{{ $project->name }} — {{ $project->location_city }}, {{ $project->location_province }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('validator.index') }}" class="btn btn-secondary btn-sm">+ Validasi Baru</a>
        <a href="{{ route('validator.history') }}" class="btn btn-secondary btn-sm">📁 Riwayat</a>
    </div>
</div>

{{-- SUMMARY STATS --}}
@php
    $items = $project->rabItems;
    $results = $items->map(fn($i) => $i->validationResult)->filter();
    $wajar     = $results->where('status', 'Wajar')->count();
    $overprice = $results->where('status', 'Overprice')->count();
    $underprice = $results->where('status', 'Underprice')->count();
    $notFound  = $results->where('status', 'Tidak Ditemukan')->count();
    $totalItems = $items->count();
@endphp

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total Item</div>
        <div class="stat-value">{{ $totalItems }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">✅ Wajar</div>
        <div class="stat-value text-success">{{ $wajar }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">🔴 Overprice</div>
        <div class="stat-value text-danger">{{ $overprice }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">🔵 Underprice</div>
        <div class="stat-value" style="color:var(--info);">{{ $underprice }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Budget Diusulkan</div>
        <div class="stat-value" style="font-size:1.05rem;">Rp {{ number_format($project->total_proposed_budget, 0, ',', '.') }}</div>
    </div>
</div>

{{-- OVERHEAD ANALYSIS --}}
@php $ov = $overheadAnalysis; @endphp
<div class="overhead-alert {{ $ov['is_reasonable'] ? 'ok' : 'warn' }}">
    @if($ov['is_reasonable'])
        ✅ <strong>Biaya Persiapan Wajar:</strong>
        Rasio biaya "Persiapan & Akhir" terhadap "Pekerjaan Utama" adalah <strong>{{ $ov['overhead_ratio'] }}%</strong>
        (batas: {{ $ov['threshold'] }}%). Masih dalam batas kewajaran.
    @else
        ⚠️ <strong>Peringatan Overhead:</strong> {{ $ov['warning_message'] }}
        (Persiapan: Rp {{ number_format($ov['preparation_total'], 0, ',', '.') }} | Pekerjaan Utama: Rp {{ number_format($ov['main_work_total'], 0, ',', '.') }})
    @endif
</div>

{{-- RESULTS TABLE --}}
<div class="card">
    <div class="card-title">Detail Validasi per Item</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Item</th>
                    <th>Spesifikasi</th>
                    <th>Vol / Satuan</th>
                    <th>Harga Usulan</th>
                    <th>Range Harga Pasar</th>
                    <th>Status</th>
                    <th>Referensi</th>
                    <th>Keterangan AI</th>
                </tr>
            </thead>
            <tbody>
                @foreach($project->rabItems as $item)
                @php $res = $item->validationResult; @endphp
                <tr>
                    <td>
                        @if($item->category === 'Persiapan & Akhir')
                            <span class="badge badge-indigo">Persiapan</span>
                        @else
                            <span class="badge badge-neutral">Utama</span>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $item->item_name }}</strong>
                        @if($res && $res->is_equivalent)
                            <span class="equivalent-tag">~Setara</span>
                        @endif
                        @if($res && $res->found_item_name && $res->found_item_name !== $item->item_name)
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                AI: {{ $res->found_item_name }}
                            </div>
                        @endif
                    </td>
                    <td class="text-muted" style="font-size:0.82rem;">{{ $item->specification ?? '—' }}</td>
                    <td>{{ number_format($item->volume, 2, ',', '.') }} {{ $item->unit }}</td>
                    <td>Rp {{ number_format($item->proposed_price, 0, ',', '.') }}</td>
                    <td class="price-range">
                        @if($res && $res->price_min)
                            <strong>Rp {{ number_format($res->price_min, 0, ',', '.') }}</strong>
                            &nbsp;–&nbsp;
                            <strong>Rp {{ number_format($res->price_max, 0, ',', '.') }}</strong>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($res)
                            @php
                                $badgeClass = match($res->status) {
                                    'Wajar'    => 'badge-success',
                                    'Overprice' => 'badge-danger',
                                    'Underprice' => 'badge-warning',
                                    default    => 'badge-neutral',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $res->status }}</span>
                        @else
                            <span class="badge badge-neutral">Menunggu</span>
                        @endif
                    </td>
                    <td>
                        @if($res && $res->reference_url)
                            <a href="{{ $res->reference_url }}" target="_blank" rel="noopener" class="ref-link">
                                🔗 Lihat Sumber
                            </a>
                        @else
                            <span class="text-muted" style="font-size:0.8rem;">—</span>
                        @endif
                    </td>
                    <td class="reasoning-cell">{{ $res->reasoning ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
