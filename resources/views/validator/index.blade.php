@extends('layouts.app')

@section('title', 'Form Validasi RAB — AI Price Validator')

@push('styles')
<style>
    /* RAB Table header row */
    .rab-table th:last-child { width: 60px; }
    .rab-table td input, .rab-table td select { min-width: 100px; }

    /* Loading overlay */
    #loading-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(15,17,23,0.88);
        z-index: 200;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1.25rem;
    }
    #loading-overlay.visible { display: flex; }
    .spinner {
        width: 52px; height: 52px;
        border: 4px solid var(--surface-2);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.85s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-text { font-weight: 600; font-size: 1.05rem; color: var(--text); }
    .loading-sub { color: var(--text-muted); font-size: 0.85rem; text-align: center; max-width: 340px; }
</style>
@endpush

@section('content')
<div id="loading-overlay">
    <div class="spinner"></div>
    <div class="loading-text">Menyimpan Proyek...</div>
    <div class="loading-sub">Menyiapkan daftar item RAB untuk divalidasi oleh AI.</div>
</div>

<div class="page-header">
    <h1>🏗️ Form Validasi Harga RAB</h1>
    <p>Masukkan detail proyek dan daftar item RAB. AI akan memvalidasi kewajaran harga berdasarkan data pasar terkini.</p>
</div>

<form method="POST" action="{{ route('validator.analyze') }}" id="rab-form">
    @csrf

    {{-- PROJECT INFO --}}
    <div class="card mb-2">
        <div class="card-title">📋 Informasi Proyek</div>
        <div class="form-grid">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="project_name">Nama Proyek *</label>
                <input type="text" id="project_name" name="project_name"
                    placeholder="Renovasi Ruang Meeting Kantor Cabang" value="{{ old('project_name') }}" required>
                @error('project_name')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label for="location_province">Provinsi *</label>
                <select id="location_province" name="location_province" required onchange="updateCities()">
                    <option value="">-- Pilih Provinsi --</option>
                    @foreach(config('locations.provinces', []) as $prov)
                        <option value="{{ $prov }}" {{ old('location_province') === $prov ? 'selected' : '' }}>{{ $prov }}</option>
                    @endforeach
                </select>
                @error('location_province')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label for="location_city">Kabupaten / Kota *</label>
                <input type="text" id="location_city" name="location_city"
                    placeholder="Kota Surabaya" value="{{ old('location_city') }}" required>
                @error('location_city')<span class="form-error">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    {{-- RAB ITEMS --}}
    <div class="card">
        <div class="flex items-center justify-between mb-2">
            <div class="card-title" style="margin-bottom:0;">📦 Daftar Item RAB</div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">+ Tambah Item</button>
        </div>

        <div class="table-wrapper">
            <table class="rab-table">
                <thead>
                    <tr>
                        <th style="width:160px;">Kategori</th>
                        <th>Nama Pekerjaan / Material</th>
                        <th>Spesifikasi</th>
                        <th style="width:90px;">Volume</th>
                        <th style="width:80px;">Satuan</th>
                        <th style="width:150px;">Harga Satuan (Rp)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="rab-tbody">
                    {{-- Default first row --}}
                    <tr>
                        <td>
                            <select name="items[0][category]" required>
                                <option value="Persiapan & Akhir">Persiapan & Akhir</option>
                                <option value="Pekerjaan Utama" selected>Pekerjaan Utama</option>
                            </select>
                        </td>
                        <td><input type="text" name="items[0][item_name]" placeholder="Semen PC 50kg" required></td>
                        <td><input type="text" name="items[0][specification]" placeholder="Holcim / Tiga Roda"></td>
                        <td><input type="number" name="items[0][volume]" placeholder="10" min="0.01" step="0.01" required></td>
                        <td><input type="text" name="items[0][unit]" placeholder="zak" required></td>
                        <td><input type="number" name="items[0][proposed_price]" placeholder="75000" min="0" required></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">✕</button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex items-center justify-between flex-wrap gap-2">
            <p class="text-muted" style="font-size:0.82rem;">
                💡 Proses validasi AI membutuhkan waktu ~10–30 detik per item.
            </p>
            <button type="submit" class="btn btn-primary" id="submit-btn">
                🤖 Mulai Validasi AI
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    let rowIndex = 1;

    function addRow() {
        const tbody = document.getElementById('rab-tbody');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select name="items[${rowIndex}][category]" required>
                    <option value="Persiapan & Akhir">Persiapan & Akhir</option>
                    <option value="Pekerjaan Utama" selected>Pekerjaan Utama</option>
                </select>
            </td>
            <td><input type="text" name="items[${rowIndex}][item_name]" placeholder="Nama material / jasa" required></td>
            <td><input type="text" name="items[${rowIndex}][specification]" placeholder="Merk / spesifikasi"></td>
            <td><input type="number" name="items[${rowIndex}][volume]" placeholder="1" min="0.01" step="0.01" required></td>
            <td><input type="text" name="items[${rowIndex}][unit]" placeholder="unit" required></td>
            <td><input type="number" name="items[${rowIndex}][proposed_price]" placeholder="0" min="0" required></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">✕</button></td>
        `;
        // Animate new row
        row.style.opacity = '0';
        row.style.transition = 'opacity 0.25s';
        tbody.appendChild(row);
        requestAnimationFrame(() => row.style.opacity = '1');
        rowIndex++;
    }

    function removeRow(btn) {
        const row = btn.closest('tr');
        const tbody = document.getElementById('rab-tbody');
        if (tbody.rows.length > 1) {
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 200);
        }
    }

    function updateCities() {
        // Placeholder — in a full implementation, populate city dropdown via AJAX or JS map
        // For now, the city field is a free-text input for flexibility
    }

    document.getElementById('rab-form').addEventListener('submit', function () {
        document.getElementById('loading-overlay').classList.add('visible');
        document.getElementById('submit-btn').disabled = true;
    });
</script>
@endpush
