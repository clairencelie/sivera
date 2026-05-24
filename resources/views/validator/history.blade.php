@extends('layouts.app')

@section('title', 'Riwayat Validasi — AI Price Validator')

@section('content')

<div class="page-header flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1>Riwayat Validasi</h1>
        <p>Daftar proyek yang telah divalidasi sebelumnya.</p>
    </div>
    <a href="{{ route('validator.index') }}" class="btn btn-primary btn-sm">+ Validasi Baru</a>
</div>

@if($projects->count() > 0)
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Proyek</th>
                    <th>Lokasi</th>
                    <th>Total Budget</th>
                    <th>Jumlah Item</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($projects as $project)
                <tr>
                    <td class="text-muted">{{ $project->id }}</td>
                    <td><strong>{{ $project->name }}</strong></td>
                    <td class="text-muted">{{ $project->location_city }}, {{ $project->location_province }}</td>
                    <td>Rp {{ number_format($project->total_proposed_budget, 0, ',', '.') }}</td>
                    <td>{{ $project->rab_items_count ?? $project->rabItems()->count() }} item</td>
                    <td class="text-muted" style="font-size:0.82rem;">{{ $project->created_at->format('d M Y, H:i') }}</td>
                    <td>
                        <a href="{{ route('validator.results', $project->id) }}" class="btn btn-secondary btn-sm">Lihat Hasil</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">
        {{ $projects->links() }}
    </div>
</div>
@else
<div class="card" style="text-align:center; padding: 3rem;">
    <p class="text-muted">Belum ada riwayat validasi. <a href="{{ route('validator.index') }}" style="color:var(--accent-hover);">Mulai validasi pertama Anda.</a></p>
</div>
@endif

@endsection
