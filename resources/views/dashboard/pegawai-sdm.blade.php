@extends('layout')
@section('title', 'Dashboard')

@php
    $B = \App\Models\Kehadiran::BULAN;
    $url = fn ($ubah) => route('dashboard.pegawai', array_filter(['tab' => $tab, 'tahun' => $tahun, 'kelompok' => $kelompok] + $ubah));
    // Warna sel menit terlambat: makin lama makin merah
    // Nilai bulanan sebagai chip: merah ≥60, kuning ≥30, abu = 0, titik = belum ada data
    $panas = fn ($v) => $v === null ? 'kosong' : ($v >= 60 ? 'merah' : ($v >= 30 ? 'kuning' : ($v == 0 ? 'nol' : '')));
    $chip = fn ($v, $warna = true) => '<span class="chip ' . ($warna ? $panas($v) : ($v === null ? 'kosong' : ($v == 0 ? 'nol' : ''))) . '">' . ($v ?? '·') . '</span>';
@endphp

@section('content')
<ul class="nav nav-tabs mb-0">
    @foreach (['ringkasan' => 'Ringkasan', 'kehadiran' => 'Kehadiran & Keterlambatan', 'turnover' => 'Turn Over', 'disc' => 'Peta Karakter (DISC)'] as $k => $l)
        <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('dashboard.pegawai', ['tab' => $k, 'tahun' => $tahun]) }}">{{ $l }}</a></li>
    @endforeach
</ul>

@if ($tab === 'ringkasan')
<div class="mt-3">@include('dashboard._ringkasan')</div>
@else
<div class="card border-top-0 rounded-top-0 mb-3">
    @if ($tab === 'kehadiran')
    <form class="bar-filter rounded-0">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <select name="tahun" class="form-select" onchange="this.form.submit()">@foreach ($daftarTahun as $t)<option @selected($t == $tahun)>{{ $t }}</option>@endforeach</select>
            <select name="kelompok" class="form-select" onchange="this.form.submit()">
                <option value="">Semua kelompok</option>
                @foreach ($daftarKelompok as $k)<option @selected($k === $kelompok)>{{ $k }}</option>@endforeach
            </select>
            <span class="info">{{ $baris->count() }} orang{{ $tanpaData ? " · {$tanpaData} belum ada data" : '' }}</span>
    </form>
    @endif

@if ($tab === 'kehadiran')
    <div class="card-body">
        <div class="row g-3">
            @foreach (['Rata-rata keterlambatan (menit / orang)' => $rataTerlambat, 'Rata-rata tidak hadir (hari / orang)' => $rataAbsen] as $judul => $rata)
                <div class="col-xl-6">
                    <h6 class="small text-muted text-uppercase fw-semibold">{{ $judul }}</h6>
                    <div class="table-responsive">
                    <table class="table table-sm small mb-0 tabel-bulan">
                        <thead class="table-light"><tr><th class="text-start">Kelompok</th>@foreach ($B as $b)<th>{{ $b }}</th>@endforeach</tr></thead>
                        @foreach ($daftarKelompok as $k)
                            <tr class="{{ $k === $kelompok ? 'table-primary' : '' }}"><td class="text-nowrap text-start fw-medium">{{ $k }}</td>
                                @foreach ($rata[$k] ?? [] as $v)<td>{!! $chip($v, str_contains($judul, 'menit')) !!}</td>@endforeach</tr>
                        @endforeach
                    </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4"><div class="card h-100">
        <div class="card-header">10 Terbanyak Terlambat {{ $tahun }}</div>
        <div class="card-body">
            @php($maks = max(1, $terlambatTeratas->max('totalTerlambat') ?? 1))
            @forelse ($terlambatTeratas as $o)
                <div class="batang"><div class="text-truncate" title="{{ $o->nama }}">{{ $o->nama }}</div>
                    <div class="batang-jalur"><div class="batang-isi" style="width:{{ $o->totalTerlambat / $maks * 100 }}%"></div></div>
                    <div class="batang-nilai">{{ $o->totalTerlambat }}</div></div>
            @empty
                <div class="text-muted small">Belum ada data keterlambatan.</div>
            @endforelse
            <div class="small text-muted mt-2">Total menit dalam setahun.</div>
        </div>
    </div></div>
    <div class="col-xl-8"><div class="card h-100">
        <div class="card-header">Rincian per Pegawai <small class="text-muted">· menit terlambat / hari tidak hadir</small></div>
        <div class="table-responsive" style="max-height:520px">
        <table class="table table-sm small mb-0 align-middle tabel-bulan">
            <thead class="table-light sticky-top"><tr><th class="text-start">Nama</th>@foreach ($B as $b)<th>{{ $b }}</th>@endforeach<th>Total</th></tr></thead>
            @foreach ($baris as $o)
                <tr>
                    <td class="text-nowrap text-start"><div class="fw-medium">{{ \Illuminate\Support\Str::limit($o->nama, 28) }}</div><small class="text-muted">{{ $o->kelompok }}</small></td>
                    @foreach ($B as $i => $b)
                        <td>{!! $chip($o->terlambat[$i]) !!}@if ($o->absen[$i])<div><span class="absen">{{ $o->absen[$i] + 0 }} hr</span></div>@endif</td>
                    @endforeach
                    <td class="kolom-total">{{ $o->totalTerlambat }}@if ($o->totalAbsen)<div><span class="absen">{{ $o->totalAbsen + 0 }} hr</span></div>@endif</td>
                </tr>
            @endforeach
        </table>
        </div>
        <div class="card-footer small text-muted"><span class="chip kuning">30+</span> ≥ 30 menit &nbsp; <span class="chip merah">60+</span> ≥ 60 menit sebulan &nbsp; <span class="absen">1 hr</span> hari tidak hadir &nbsp; · belum ada data</div>
    </div></div>
</div>

@elseif ($tab === 'turnover')
    {{-- Chip persen turn over: merah >=20%, kuning >=10%; kelompok kecil (awal < 5) bergaris putus agar tidak menyesatkan --}}
    @php($chipTo = function ($x) { if ($x['rasio'] === null) return '<span class="chip to nol">·</span>'; $p = round($x['rasio'] * 100); $w = $x['rasio'] >= 0.2 ? 'merah' : ($x['rasio'] >= 0.1 ? 'kuning' : ($p == 0 ? 'nol' : '')); $kecil = $x['awal'] < 5 && $p > 0; return '<span class="chip to ' . $w . ($kecil ? ' kecil' : '') . '"' . ($kecil ? ' title="Kelompok kecil, persentase kurang bermakna"' : '') . '>' . $p . '%' . ($kecil ? '*' : '') . '</span>'; })
    @php($palet = ['#0d6efd', '#28a745', '#fd7e14', '#6f42c1', '#17a2b8', '#d63384', '#6c757d', '#20c997'])
    @php($warnaKelompok = $turnover->keys()->values()->mapWithKeys(fn ($k, $i) => [$k => $palet[$i % count($palet)]]))
    @php($r = $ringkasTurnover)
    @php($pr = fn ($v) => $v === null ? '–' : round($v * 100, 1) . '%')
    <div class="card-body">
        @include('_kartu-angka', ['kolom' => 'row-cols-1 row-cols-sm-2 row-cols-xl-4', 'kartu' => [
            ['Turn over ' . now()->year, $pr($r['rasioIni']), 'graph-down-arrow', null, 'keluar ÷ orang per 1 Januari', ($r['rasioIni'] ?? 0) >= 0.1 ? 'danger' : 'info'],
            ['Keluar ' . now()->year, $r['keluarIni'], 'box-arrow-right', null, 'orang', 'warning'],
            ['Rata-rata ' . $r['jumlahTahunRata'] . ' tahun', $pr($r['rata']), 'bar-chart-line', null, 'tahun-tahun sebelumnya', 'success'],
            ['Tertinggi ' . now()->year, $r['tertinggi'] ?? '–', 'exclamation-triangle', null, $r['tertinggi'] ? $pr($r['rasioTertinggi']) . ' (kelompok ≥ 5 orang)' : 'belum ada', 'purple'],
        ]])

        @if ($tanpaTanggal)
            <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center small py-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span class="flex-grow-1"><strong>{{ $tanpaTanggal }} pegawai nonaktif</strong> belum punya tanggal keluar, jadi belum terhitung di turn over.</span>
                <a href="{{ route('staff.index', ['status' => 'nonaktif']) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square me-1"></i>Lengkapi</a>
            </div>
        @endif

        <div class="table-responsive">
        <table class="table table-sm small mb-0 tabel-bulan">
            <thead class="table-light"><tr><th class="text-start">Kelompok</th>@foreach ($tahunList as $t)<th>{{ $t }}</th>@endforeach</tr></thead>
            @foreach ($turnover as $k => $per)
                <tr><td class="text-nowrap text-start fw-medium"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $warnaKelompok[$k] }}"></span>{{ $k }}</td>
                    @foreach ($per as $x)
                        <td title="{{ $x['nama']->join(', ') }}">{!! $chipTo($x) !!}@if ($x['rasio'] !== null)<span class="sub">{{ $x['keluar'] }}/{{ $x['awal'] }}</span>@endif</td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="baris-total"><td class="text-start">Total</td>
                @foreach ($totalTurnover as $x)<td>{!! $chipTo($x + ['awal' => 99]) !!}@if ($x['rasio'] !== null)<span class="sub">{{ $x['keluar'] }}/{{ $x['awal'] }}</span>@endif</td>@endforeach</tr>
        </table>
        </div>
        <div class="small text-muted mt-2">Turn over = jumlah yang keluar di tahun itu ÷ jumlah orang per 1 Januari, dihitung dari tanggal keluar.
            <span class="chip to kuning">10%</span> ≥ 10% &nbsp; <span class="chip to merah">20%</span> ≥ 20% &nbsp; <span class="chip to merah kecil">100%*</span> kelompok &lt; 5 orang.</div>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-clock-history me-1"></i> Yang Keluar per Tahun</span>
        <small class="fw-normal">@foreach ($warnaKelompok as $k => $w)<span class="text-nowrap me-2"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $w }}"></span>{{ $k }}</span>@endforeach</small></div>
    <div class="card-body">
        <div class="timeline">
            @php($ada = false)
            @foreach (array_reverse($tahunList) as $t)
                @php($orang = $turnover->flatMap(fn ($p, $k) => $p[$t]['orang']->map(fn ($o) => $o + ['kelompok' => $k])))
                @if ($orang->isNotEmpty())
                    @php($ada = true)
                    <div class="tl">
                        <div class="fw-semibold mb-1">{{ $t }} <span class="badge rounded-pill text-bg-light ms-1">{{ $orang->count() }} orang</span></div>
                        @foreach ($orang as $o)
                            @php($w = $warnaKelompok[$o['kelompok']])
                            <a href="{{ route('staff.show', $o['id']) }}" class="chip-nama" style="background:{{ $w }}14;border-color:{{ $w }}40;color:#344054" title="{{ $o['kelompok'] }}">
                                <span class="titik" style="background:{{ $w }}"></span>{{ $o['nama'] }}</a>
                        @endforeach
                    </div>
                @endif
            @endforeach
            @unless ($ada)<div class="text-muted small">Belum ada pegawai yang keluar dengan tanggal keluar tercatat.</div>@endunless
        </div>
    </div>
</div>

@elseif ($tab === 'disc')
    @php($D = \App\Models\Disc::class)
    <form class="bar-filter rounded-0">
        <input type="hidden" name="tab" value="disc">
        <select name="tim" class="form-select" onchange="this.form.submit()">
            <option value="">Semua tim</option>
            @foreach ($daftarTim as $t)<option @selected($t === $tim)>{{ $t }}</option>@endforeach
        </select>
        @can('admin')<a href="{{ route('input.disc') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>Input DISC</a>@endcan
        <span class="info">{{ $baris->count() }} orang dipetakan</span>
    </form>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-7">
                <h6 class="small text-muted text-uppercase fw-semibold">Komitmen × Kompetensi → cara memimpin</h6>
                <table class="table table-sm table-bordered small text-center mb-0">
                    <tr class="table-light"><th class="text-start">Komitmen ↓ / Kompetensi →</th>@foreach ($D::GAYA as $k => $g)<th>{{ $k }}</th>@endforeach</tr>
                    @foreach ($matriks as $km => $baris2)
                        <tr><th class="text-start table-light">{{ $km }} <small class="text-muted fw-normal">({{ $D::GAYA[$km] }})</small></th>
                            @foreach ($baris2 as $n)<td class="{{ $n ? 'bg-primary-subtle fw-semibold' : 'text-muted' }}">{{ $n ?: '·' }}</td>@endforeach</tr>
                    @endforeach
                </table>
                <div class="small text-muted mt-1">Tinggi → Delegating, Menengah → Supporting, Cukup → Coaching, Rendah → Directing.</div>
            </div>
            <div class="col-lg-5">
                <h6 class="small text-muted text-uppercase fw-semibold">Gaya DISC dominan</h6>
                @php($maks = max(1, $dominan->max() ?? 1))
                @foreach ($dominan as $g => $n)
                    <div class="batang"><div>{{ ['D' => 'D · Dominance', 'I' => 'I · Influence', 'S' => 'S · Steadiness', 'C' => 'C · Compliance'][$g] ?? $g }}</div>
                        <div class="batang-jalur"><div class="batang-isi" style="width:{{ $n / $maks * 100 }}%"></div></div><div class="batang-nilai">{{ $n }}</div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>


<div class="card">
    <div class="table-responsive">
    <table class="table table-sm table-hover small mb-0 align-middle">
        <thead class="table-light"><tr><th>Nama</th><th>Tim</th><th>Masa kerja</th><th>DISC</th>
            @foreach ($D::KOMITMEN + $D::KOMPETENSI as $l)<th class="text-center" title="{{ $l }}">{{ mb_substr($l, 0, 4) }}</th>@endforeach
            <th>Komitmen</th><th>Kompetensi</th></tr></thead>
        @foreach ($baris as $b)
            <tr>
                <td>{{ $b->staff->nama ?? '-' }}<br><small class="text-muted">{{ $b->staff->level ?? '' }} · {{ $b->staff->divisi ?? '' }}</small></td>
                <td class="text-muted">{{ $b->disc->tim }}</td>
                <td class="text-nowrap">{{ $b->staff?->mulai_kerja ? $b->staff->mulai_kerja->diffInYears(now()) . ' th' : '-' }}</td>
                <td><span class="badge text-bg-light">{{ $b->disc->profil ?? '-' }}</span></td>
                @foreach (array_keys($D::KOMITMEN + $D::KOMPETENSI) as $k)
                    <td class="text-center {{ ($b->disc->$k ?? 9) <= 1 ? 'bg-danger-subtle' : (($b->disc->$k ?? 0) >= 4 ? 'bg-success-subtle' : '') }}">{{ $b->disc->$k ?? '·' }}</td>
                @endforeach
                @foreach ([[$b->disc->komitmen, $b->katKomitmen], [$b->disc->kompetensi, $b->katKompetensi]] as [$nilai, $kat])
                    <td class="text-nowrap">@if ($kat){{ $nilai }} <span class="badge text-bg-{{ ['Tinggi' => 'success', 'Menengah' => 'primary', 'Cukup' => 'warning', 'Rendah' => 'danger'][$kat] }}">{{ $kat }}</span>
                        <br><small class="text-muted">{{ $D::GAYA[$kat] }}</small>@else - @endif</td>
                @endforeach
            </tr>
        @endforeach
    </table>
    </div>
</div>

@endif
@endif
@endsection
