{{-- Grafik batang horizontal satu seri: $data = [label => jumlah] (sudah urut), $judul.
     Satu warna (besaran, bukan identitas), angka di samping batang, title = keterangan saat disorot. --}}
@php($maks = max(1, $data->max() ?? 1))
@php($total = max(1, $data->sum()))
<div class="card h-100">
    <div class="card-header d-flex justify-content-between align-items-center"><span>{{ $judul }}</span><small class="text-muted fw-normal">{{ $data->sum() }} orang</small></div>
    <div class="card-body">
        @forelse ($data as $label => $n)
            @php($url = isset($tautan) ? $tautan($label) : null)
            <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif class="batang text-decoration-none text-body" title="{{ $label }}: {{ $n }} ({{ round($n / $total * 100) }}%)">
                <div class="batang-label text-truncate">{{ $label }}</div>
                <div class="batang-jalur"><div class="batang-isi {{ str_starts_with($label, 'Belum') || str_starts_with($label, 'Tanpa') ? 'kosong' : '' }}" style="width: {{ $n / $maks * 100 }}%"></div></div>
                <div class="batang-nilai">{{ $n }}@if ($persen ?? false)<small class="text-muted fw-normal"> · {{ round($n / $total * 100) }}%</small>@endif</div>
            </{{ $url ? 'a' : 'div' }}>
        @empty
            <div class="text-muted small">Belum ada data.</div>
        @endforelse
    </div>
</div>
