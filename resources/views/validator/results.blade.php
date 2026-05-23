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
    .ref-list { display: grid; gap: 0.2rem; }
    .equivalent-tag { font-size: 0.72rem; color: var(--warning); border: 1px solid rgba(245,158,11,0.4); padding: 1px 6px; border-radius: 4px; margin-left: 4px; }

    .price-range { font-size: 0.82rem; color: var(--text-muted); }
    .price-range strong { color: var(--text); }

    .reasoning-cell { font-size: 0.8rem; color: var(--text-muted); max-width: 260px; line-height: 1.45; }
</style>
@endpush

@section('content')

{{-- SUMMARY STATS DATA EXTRACTION --}}
@php
    $items = $project->rabItems;
    $results = $items->map(fn($i) => $i->validationResult)->filter();
    $wajar     = $results->where('status', 'Wajar')->count();
    $overprice = $results->where('status', 'Overprice')->count();
    $underprice = $results->where('status', 'Underprice')->count();
    $notFound  = $results->where('status', 'Tidak Ditemukan')->count();
    $totalItems = $items->count();
@endphp

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

{{-- PROGRESS BAR VALIDASI AI (UNTUK FREE API KEY) --}}
@if($results->count() < $totalItems)
<div class="card mb-2" id="validation-progress-card" style="border-color: var(--accent); background: rgba(99, 102, 241, 0.05); margin-bottom: 2rem;">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <strong style="color: var(--accent-hover); display: flex; align-items: center; gap: 6px;">
                <span>🤖</span> Sedang Menjalankan Validasi AI...
            </strong>
            <div class="text-muted" style="font-size: 0.82rem; margin-top: 2px;">
                Mengecek harga pasar di internet menggunakan model aktif: {{ config('services.gemini.model', 'gemini-2.5-flash') }}. Proses dijeda beberapa detik antar item untuk menghindari batasan kuota.
            </div>
        </div>
        <div>
            <span class="badge badge-indigo" id="progress-badge">0 / {{ $totalItems }} Item Selesai</span>
        </div>
    </div>
    <div style="background: var(--surface-2); height: 6px; border-radius: 3px; margin-top: 12px; overflow: hidden;">
        <div id="progress-bar" style="background: linear-gradient(90deg, var(--accent), #8b5cf6); width: 0%; height: 100%; transition: width 0.4s ease;"></div>
    </div>
</div>
@endif

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
                <tr data-item-id="{{ $item->id }}" data-status="{{ $res ? 'done' : 'pending' }}">
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
                        @if($res && is_array($res->source_urls) && count($res->source_urls) > 0)
                            <div class="ref-list">
                                @foreach(array_slice($res->source_urls, 0, 3) as $idx => $sourceUrl)
                                    <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="ref-link">
                                        🔗 Sumber {{ $idx + 1 }}
                                    </a>
                                @endforeach
                            </div>
                        @elseif($res && $res->reference_url)
                            <a href="{{ $res->reference_url }}" target="_blank" rel="noopener" class="ref-link">🔗 Lihat Sumber</a>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pendingRows = document.querySelectorAll('tr[data-status="pending"]');
    if (pendingRows.length === 0) return;

    const totalPending = pendingRows.length;
    const totalItems = document.querySelectorAll('tr[data-item-id]').length;
    let completed = totalItems - totalPending;

    updateProgress(completed, totalItems);

    async function processPendingItems() {
        for (let i = 0; i < pendingRows.length; i++) {
            const row = pendingRows[i];
            const itemId = row.getAttribute('data-item-id');
            
            // Visual highlight on current row
            row.style.background = 'rgba(99, 102, 241, 0.08)';
            row.style.transition = 'background 0.3s ease';
            
            const badge = row.querySelector('.badge');
            if (badge) {
                badge.className = 'badge badge-indigo';
                badge.textContent = 'Memproses...';
            }

            try {
                const response = await fetch(`/validator/items/${itemId}/validate`);
                if (!response.ok) throw new Error('Response error');
                
                const data = await response.json();
                if (data.success && data.result) {
                    const res = data.result;
                    
                    // Mark row as done
                    row.setAttribute('data-status', 'done');
                    row.style.background = '';

                    // 1. Update Badge
                    let badgeClass = 'badge-neutral';
                    if (res.status === 'Wajar') badgeClass = 'badge-success';
                    else if (res.status === 'Overprice') badgeClass = 'badge-danger';
                    else if (res.status === 'Underprice') badgeClass = 'badge-warning';

                    if (badge) {
                        badge.className = `badge ${badgeClass}`;
                        badge.textContent = res.status;
                    }

                    // 2. Update Range
                    const rangeCell = row.querySelector('.price-range');
                    if (rangeCell) {
                        if (parseFloat(res.price_min) && parseFloat(res.price_max)) {
                            const minFormatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(res.price_min);
                            const maxFormatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(res.price_max);
                            rangeCell.innerHTML = `<strong>${minFormatted}</strong> – <strong>${maxFormatted}</strong>`;
                        } else {
                            rangeCell.innerHTML = '<span class="text-muted">—</span>';
                        }
                    }

                    // 3. Update Equivalent tag if equivalent
                    if (res.is_equivalent) {
                        const nameCell = row.querySelector('td:nth-child(2)');
                        if (nameCell && !nameCell.querySelector('.equivalent-tag')) {
                            const tag = document.createElement('span');
                            tag.className = 'equivalent-tag';
                            tag.textContent = '~Setara';
                            nameCell.querySelector('strong').after(tag);
                        }
                    }

                    // 4. Update found item name if different
                    if (res.found_item_name && res.found_item_name !== row.querySelector('strong').textContent.trim()) {
                        const nameCell = row.querySelector('td:nth-child(2)');
                        if (nameCell) {
                            let subName = nameCell.querySelector('.ai-found-name');
                            if (!subName) {
                                subName = document.createElement('div');
                                subName.className = 'ai-found-name';
                                subName.style.fontSize = '0.75rem';
                                subName.style.color = 'var(--text-muted)';
                                subName.style.marginTop = '2px';
                                nameCell.appendChild(subName);
                            }
                            subName.textContent = `AI: ${res.found_item_name}`;
                        }
                    }

                    // 5. Update Reference URL(s)
                    const refCell = row.querySelector('td:nth-child(8)');
                    if (refCell) {
                        if (Array.isArray(res.source_urls) && res.source_urls.length > 0) {
                            const links = res.source_urls.slice(0, 3).map((url, idx) =>
                                `<a href="${url}" target="_blank" rel="noopener" class="ref-link">🔗 Sumber ${idx + 1}</a>`
                            );
                            refCell.innerHTML = `<div class="ref-list">${links.join('')}</div>`;
                        } else if (res.reference_url) {
                            refCell.innerHTML = `<a href="${res.reference_url}" target="_blank" rel="noopener" class="ref-link">🔗 Lihat Sumber</a>`;
                        } else {
                            refCell.innerHTML = '<span class="text-muted" style="font-size:0.8rem;">—</span>';
                        }
                    }

                    // 6. Update Reasoning
                    const reasoningCell = row.querySelector('.reasoning-cell');
                    if (reasoningCell) {
                        reasoningCell.textContent = res.reasoning || '—';
                    }
                } else {
                    throw new Error('Invalid JSON payload');
                }
            } catch (error) {
                console.error('Validation failed for item:', itemId, error);
                row.style.background = 'rgba(239, 68, 68, 0.08)';
                if (badge) {
                    badge.className = 'badge badge-danger';
                    badge.textContent = 'Gagal';
                }
            }

            completed++;
            updateProgress(completed, totalItems);

            // Jeda waktu aman 3 detik sebelum item berikutnya untuk membatasi RPM di Free Tier
            if (i < pendingRows.length - 1) {
                await new Promise(resolve => setTimeout(resolve, 3000));
            }
        }

        // Setelah semua item diproses, reload halaman setelah jeda singkat
        setTimeout(() => {
            window.location.reload();
        }, 1200);
    }

    function updateProgress(completed, total) {
        const percent = Math.round((completed / total) * 100);
        const bar = document.getElementById('progress-bar');
        const badge = document.getElementById('progress-badge');
        if (bar) bar.style.width = `${percent}%`;
        if (badge) badge.textContent = `${completed} / ${total} Item Selesai`;
    }

    // Jalankan pemrosesan asinkron
    setTimeout(processPendingItems, 500);
});
</script>
@endpush

@endsection
