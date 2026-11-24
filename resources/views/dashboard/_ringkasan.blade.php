{{-- Ringkasan SDM (dulu halaman Dashboard admin), tab pertama Dashboard Pegawai --}}
{{-- Kartu angka utama --}}
@include('_kartu-angka', ['kolom' => 'row-cols-1 row-cols-md-3'])

<div class="row g-3 mb-3">
    {{-- Perlu ditindaklanjuti --}}
    <div class="col-lg-6">
        <div class="card h-100">
            @php($perlu = $tindakan->filter(fn ($t) => $t[1])->count())
            <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-list-check me-1"></i> Perlu Ditindaklanjuti</span>
                <span class="badge rounded-pill {{ $perlu ? 'text-bg-warning' : 'text-bg-success' }}">{{ $perlu ? "$perlu perlu dicek" : 'Semua beres' }}</span></div>
            <div class="list-group list-group-flush tindakan">
                @foreach ($tindakan as [$label, $n, $url, $ket])
                    <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center {{ $n ? '' : 'beres' }}">
                        <span class="bulat {{ $n ? 'kuning' : 'hijau' }}"><i class="bi {{ $n ? 'bi-exclamation-lg' : 'bi-check-lg' }}"></i></span>
                        <span class="flex-grow-1"><span class="d-block fw-medium">{{ $label }}</span><small class="text-muted">{{ $n ? $ket : 'Sudah beres' }}</small></span>
                        <span class="badge rounded-pill {{ $n ? 'text-bg-warning' : 'text-bg-light' }}">{{ $n }}</span>
                    </a>
                @endforeach
                @php($lama = !$terakhirSinkron || $terakhirSinkron->lt(now()->subDays(7)))
                <a href="{{ route('sinkron.index') }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                    <span class="bulat {{ $lama ? 'kuning' : 'hijau' }}"><i class="bi bi-arrow-repeat"></i></span>
                    <span class="flex-grow-1"><span class="d-block fw-medium">Sinkron eUniversity terakhir</span><small class="text-muted">{{ $lama ? 'Lebih dari seminggu, sinkronkan ulang' : 'Data pegawai terbaru' }}</small></span>
                    <span class="small fw-semibold {{ $lama ? 'text-warning' : 'text-muted' }}">{{ $terakhirSinkron ? $terakhirSinkron->diffForHumans() : 'belum pernah' }}</span>
                </a>
                @if ($ultah->isNotEmpty())
                    <div class="list-group-item d-flex gap-3 align-items-start">
                        <span class="bulat ungu"><i class="bi bi-cake2"></i></span>
                        <span><span class="d-block fw-medium">Ulang tahun 7 hari ke depan</span>
                            <small class="text-muted">@foreach ($ultah as $u){{ $u->nama }} <span class="text-primary">({{ $u->hari ? $u->tgl_lahir->translatedFormat('d M') : 'hari ini' }})</span>{{ $loop->last ? '' : ' · ' }}@endforeach</small></span>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">@include('dashboard._batang', ['judul' => 'Pegawai per Divisi', 'data' => $perDivisi, 'persen' => true,
        'tautan' => fn ($label) => isset($idDivisi[$label]) ? route('staff.index', ['divisi' => $idDivisi[$label]]) : null])</div>
</div>

{{-- Status pengajuan per jenis (panel berwarna gaya BAAK) --}}
<h6 class="fw-semibold mb-2"><i class="bi bi-inboxes me-1"></i> Pengajuan per Jenis</h6>
<div class="row g-3 mb-3 row-cols-1 row-cols-md-2 row-cols-xl-4">
    @foreach ($perJenis as $j)
        <div class="col">
            <div class="card h-100 panel-status">
                <div class="card-header fw-semibold text-truncate" title="{{ $j['nama'] }}">{{ $j['nama'] }}</div>
                <div class="list-group list-group-flush">
                    @foreach (['Menunggu' => 'info', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Dibatalkan' => 'secondary'] as $st => $w)
                        @php($n = (int) ($j['n'][$st] ?? 0))
                        <a href="{{ route('pengajuan.index', ['tab' => 'semua', 'jenis' => $j['id'], 'status' => $st]) }}"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span class="text-primary">{{ $st }}</span>
                            <span class="badge text-bg-{{ $w }} {{ $n ? '' : 'opacity-50' }}">{{ $n }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
