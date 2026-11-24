{{-- Deret kartu angka berwarna (gaya small-box BAAK). $kartu: [label, nilai, ikon, url, keterangan, warna?] atau [label, nilai, ikon, keterangan] (tanpa tautan).
     warna: info|danger|warning|success|primary|purple (default bergiliran). $kolom: kelas row-cols. --}}
@php($palet = ['info', 'success', 'warning', 'danger', 'primary', 'purple'])
<div class="row g-3 mb-3 {{ $kolom ?? 'row-cols-1 row-cols-md-3 row-cols-xl-5' }}">
    @foreach ($kartu as $i => $k)
        @php([$label, $nilai, $ikon] = $k)
        @php([$url, $ket] = count($k) > 4 ? [$k[3], $k[4]] : [null, $k[3] ?? ''])
        @php($warna = $k[5] ?? $palet[$i % count($palet)])
        <div class="col">
            <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif class="kotak k-{{ $warna }} h-100">
                <i class="bi bi-{{ $ikon }} bi-latar"></i>
                <div class="isi">
                    <div class="angka">{{ is_int($nilai) ? number_format($nilai) : $nilai }}</div>
                    <div class="fw-semibold">{{ $label }}</div>
                    @if ($ket)<div class="ket text-truncate">{{ $ket }}</div>@endif
                </div>
                @if ($url)<div class="kaki">Lihat Detail <i class="bi bi-arrow-right-circle-fill"></i></div>@endif
            </{{ $url ? 'a' : 'div' }}>
        </div>
    @endforeach
</div>
