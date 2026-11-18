@extends('layout')
@section('title', 'Pengajuan Cuti')

@section('actions')
    <a href="{{ $semuaCuti }}" class="btn btn-outline-secondary"><i class="bi bi-list-ul me-1"></i>Semua Pengajuan Cuti</a>
    <a href="{{ route('pengajuan.create', ['jenis' => $jenisCuti]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Ajukan Cuti</a>
@endsection

@section('content')
@include('_kartu-angka', ['kolom' => 'row-cols-1 row-cols-md-3'])

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi bi-hourglass-split me-1"></i> Cuti Menunggu Persetujuan</span>
                <a href="{{ $semuaCuti }}" class="small text-decoration-none">Semua</a></div>
            <div class="list-group list-group-flush">
                @forelse ($cutiMenunggu as $c)
                    <a href="{{ route('pengajuan.show', $c) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between"><strong class="text-truncate">{{ $c->staff->nama ?? '-' }}</strong><small class="text-muted text-nowrap ms-2">{{ $c->hari }} hari</small></div>
                        <small class="text-muted">{{ $c->jenisCuti->nama }} · {{ $c->tgl_mulai->translatedFormat('d M') }} – {{ $c->tgl_selesai->translatedFormat('d M Y') }} · {{ $c->status }}</small>
                    </a>
                @empty
                    <div class="list-group-item text-muted small"><i class="bi bi-check-circle text-success me-1"></i>Tidak ada cuti yang menunggu.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-week me-1"></i> Cuti 7 Hari ke Depan</div>
            <div class="list-group list-group-flush">
                @forelse ($akanCuti as $c)
                    @php($sedang = $c->tgl_mulai->lte(today()))
                    <a href="{{ route('pengajuan.show', $c) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between"><strong class="text-truncate">{{ $c->staff->nama ?? '-' }}</strong>
                            <span class="badge {{ $sedang ? 'text-bg-primary' : 'text-bg-light' }} ms-2">{{ $sedang ? 'Sedang cuti' : $c->tgl_mulai->translatedFormat('d M') }}</span></div>
                        <small class="text-muted">{{ $c->jenisCuti->nama }} · s.d. {{ $c->tgl_selesai->translatedFormat('d M Y') }}</small>
                    </a>
                @empty
                    <div class="list-group-item text-muted small">Tidak ada pegawai yang cuti dalam 7 hari ke depan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
