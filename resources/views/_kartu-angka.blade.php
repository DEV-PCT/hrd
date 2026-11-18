{{-- Deret kartu angka. $kartu: [label, nilai, ikon, url, keterangan] atau [label, nilai, ikon, keterangan] (tanpa tautan).
     $kolom: kelas row-cols (default 1 / 3 / 5 kolom). --}}
<div class="row g-3 mb-3 {{ $kolom ?? 'row-cols-1 row-cols-md-3 row-cols-xl-5' }}">
    @foreach ($kartu as $k)
        @php([$label, $nilai, $ikon] = $k)
        @php([$url, $ket] = count($k) > 4 ? [$k[3], $k[4]] : [null, $k[3] ?? ''])
        <div class="col">
            <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif class="card kartu h-100 text-decoration-none text-body">
                <div class="card-body d-flex gap-3 align-items-center">
                    <div class="ikon flex-shrink-0"><i class="bi bi-{{ $ikon }}"></i></div>
                    <div class="min-w-0">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4 fw-semibold lh-1 my-1">{{ is_int($nilai) ? number_format($nilai) : $nilai }}</div>
                        @if ($ket)<div class="text-muted small text-truncate">{{ $ket }}</div>@endif
                    </div>
                </div>
            </{{ $url ? 'a' : 'div' }}>
        </div>
    @endforeach
</div>
