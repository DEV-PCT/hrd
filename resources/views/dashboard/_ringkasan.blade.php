{{-- Ringkasan SDM (dulu halaman Dashboard admin), tab pertama Dashboard Pegawai --}}
{{-- Kartu angka utama --}}
@include('_kartu-angka', ['kolom' => 'row-cols-1 row-cols-md-3'])

<div class="row g-3 mb-3">
    {{-- Perlu ditindaklanjuti --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-list-check me-1"></i> Perlu Ditindaklanjuti</div>
            <div class="list-group list-group-flush">
                @foreach ($tindakan as [$label, $n, $url])
                    <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi {{ $n ? 'bi-exclamation-circle text-warning' : 'bi-check-circle text-success' }} me-1"></i>{{ $label }}
                        </span>
                        <span class="badge rounded-pill {{ $n ? 'text-bg-warning' : 'text-bg-light' }}">{{ $n }}</span>
                    </a>
                @endforeach
                @php($lama = !$terakhirSinkron || $terakhirSinkron->lt(now()->subDays(7)))
                <div class="list-group-item d-flex justify-content-between align-items-center small">
                    <span><i class="bi {{ $lama ? 'bi-exclamation-circle text-warning' : 'bi-arrow-repeat text-success' }} me-1"></i>Sinkron eUniversity terakhir</span>
                    <span class="{{ $lama ? 'text-warning fw-semibold' : 'text-muted' }}">{{ $terakhirSinkron ? $terakhirSinkron->diffForHumans() : 'belum pernah' }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">@include('dashboard._batang', ['judul' => 'Pegawai per Divisi', 'data' => $perDivisi])</div>
</div>
