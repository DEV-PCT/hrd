@extends('layout')
@section('title', 'Studi Lanjut')

@section('actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahRiwayat"><i class="bi bi-plus-lg me-1"></i>Tambah Riwayat</button>
    <a href="{{ route('riwayat.impor') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i>Impor Excel</a>
@endsection

@section('content')
{{-- Tambah riwayat: pilih pegawai dulu, lalu ke form riwayat miliknya --}}
<div class="modal fade" id="modalTambahRiwayat" tabindex="-1">
    <div class="modal-dialog"><form method="get" action="{{ route('riwayat.create') }}" class="modal-content">
        <input type="hidden" name="kembali" value="riwayat">
        <div class="modal-header"><h5 class="modal-title">Tambah Riwayat Pendidikan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label">Pegawai *</label>
            <select name="staff_id" class="form-select" required>
                <option value="">Pilih pegawai…</option>
                {{-- Satu pilihan per orang; semua divisi akunnya ditampilkan, ditandai bila juga dosen --}}
                @php($akunAktif = \App\Models\Staff::aktif()->whereNotNull('nip')->get(['id', 'nip', 'divisi'])->groupBy('nip'))
                @php($nipDosen = \App\Models\Staff::whereIn('id', \App\Models\Dosen::where('aktif', true)->pluck('staff_id')->filter())->pluck('nip')->flip())
                @foreach (\App\Models\Staff::perOrang(\App\Models\Staff::aktif()) as $s)<option value="{{ $s->id }}">{{ $s->nama }} — {{ $s->nip }} · {{ ($akunAktif[$s->nip] ?? collect())->pluck('divisi')->filter()->unique()->join(', ') }}{{ isset($nipDosen[$s->nip]) ? ' · Dosen' : '' }}</option>@endforeach
            </select>
            <div class="form-text">Riwayat berlaku untuk orangnya: tampil di semua akun pegawai dan profil dosennya. Untuk studi lanjut yang sedang berjalan, pilih status <strong>Sedang Studi Lanjut</strong> di form berikutnya.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Lanjut <i class="bi bi-arrow-right ms-1"></i></button></div>
    </form></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Pendidikan Terakhir Dosen Aktif</div>
            <table class="table mb-0">
                @php($total = $komposisi->sum())
                @foreach ($komposisi->sortKeys() as $j => $c)
                    <tr><td>{{ $j }}</td><td class="text-end">{{ $c }}</td><td class="text-end text-muted small" style="width:70px">{{ $total ? round($c / $total * 100) : 0 }}%</td></tr>
                @endforeach
            </table>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="alert alert-info small h-100 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Riwayat pendidikan diisi dari profil <a href="{{ route('staff.index') }}">pegawai</a> atau <a href="{{ route('dosen.index') }}">dosen</a>
            (bagian <strong>Riwayat Pendidikan & Studi Lanjut</strong>). Pendidikan terakhir terisi otomatis dari jenjang tertinggi yang sudah lulus.
            Saat studi lanjut selesai, ubah statusnya menjadi <strong>Lulus</strong> dan unggah ijazahnya.
        </div>
    </div>
</div>

<div class="card">
<form class="bar-filter">
    <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">Semua status</option>
        @foreach (\App\Models\RiwayatPendidikan::STATUS as $o)<option @selected(request('status') == $o)>{{ $o }}</option>@endforeach
    </select>
    <select name="jenjang" class="form-select" onchange="this.form.submit()">
        <option value="">Semua jenjang</option>
        @foreach (\App\Models\RiwayatPendidikan::JENJANG as $o)<option @selected(request('jenjang') == $o)>{{ $o }}</option>@endforeach
    </select>
    <select name="pembiayaan" class="form-select" onchange="this.form.submit()">
        <option value="">Semua pembiayaan</option>
        @foreach (\App\Models\RiwayatPendidikan::PEMBIAYAAN as $o)<option @selected(request('pembiayaan') == $o)>{{ $o }}</option>@endforeach
    </select>
    <div class="input-group">
        <input name="q" class="form-control" value="{{ request('q') }}" placeholder="Cari nama / NIP / institusi">
        <button class="btn btn-primary"><i class="bi bi-search"></i></button>
    </div>
    @if (request()->hasAny(['status', 'jenjang', 'pembiayaan', 'q']) && array_filter(request()->only(['status', 'jenjang', 'pembiayaan', 'q'])))
        <a href="{{ route('riwayat.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Reset</a>
    @endif
    <span class="info">{{ $studi->total() }} data</span>
</form>
<div class="table-responsive">
<table class="table table-hover mb-0 align-middle">
    <thead><tr><th>Nama</th><th>Jenjang</th><th>Institusi / Prodi</th><th>Masuk</th><th>Lulus / Target</th><th>Status</th><th>Pembiayaan</th><th></th></tr></thead>
    <tbody>
    @forelse ($studi as $r)
        @php($terlambat = $r->status === 'Sedang Studi Lanjut' && $r->tahun_lulus && $r->tahun_lulus < now()->year)
        <tr>
            <td><a href="{{ $r->staff_id ? route('staff.show', $r->staff_id) : route('dosen.show', $r->dosen_id) }}">{{ $r->nama }}</a></td>
            <td><span class="badge text-bg-secondary">{{ $r->jenjang }}</span></td>
            <td>{{ $r->institusi }}@if ($r->program_studi)<br><small class="text-muted">{{ $r->program_studi }}</small>@endif</td>
            <td>{{ $r->tahun_masuk ?? '-' }}</td>
            <td class="{{ $terlambat ? 'text-danger fw-semibold' : '' }}">{{ $r->tahun_lulus ?? '-' }}@if ($terlambat)<br><small>lewat target</small>@endif</td>
            <td><span class="badge text-bg-{{ ['Lulus' => 'success', 'Sedang Studi Lanjut' => 'primary'][$r->status] ?? 'secondary' }}">{{ $r->status }}</span></td>
            <td>{{ $r->pembiayaan ?? '-' }}</td>
            <td class="text-end"><a href="{{ route('riwayat.edit', $r) }}" class="btn btn-sm btn-outline-secondary">Ubah</a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $studi->links() }}</div>
@endsection
