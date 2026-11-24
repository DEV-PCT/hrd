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
    {{-- Warna gaya memimpin (sama di matriks, legenda & badge) dan warna klasik DISC --}}
    @php($warnaGaya = ['Tinggi' => '#28a745', 'Menengah' => '#0d6efd', 'Cukup' => '#f0ad00', 'Rendah' => '#dc3545'])
    @php($warnaDisc = ['D' => '#dc3545', 'I' => '#f0ad00', 'S' => '#28a745', 'C' => '#0d6efd'])
    @php($namaDisc = ['D' => 'Dominance', 'I' => 'Influence', 'S' => 'Steadiness', 'C' => 'Compliance'])
    @php($km = request('km')) @php($kp = request('kp'))
    @php($tampil = $baris->when($km, fn ($c) => $c->where('katKomitmen', $km))->when($kp, fn ($c) => $c->where('katKompetensi', $kp)))
    @php($maksSel = max(1, collect($matriks)->flatten()->max()))
    @php($totalDom = max(1, $dominan->sum()))
    @php($teratas = $dominan->except('?')->sortDesc()->keys()->first())
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
        @include('_kartu-angka', ['kolom' => 'row-cols-1 row-cols-sm-2 row-cols-xl-4', 'kartu' => [
            ['Dipetakan', $baris->count(), 'people', null, $tim ?: 'semua tim', 'info'],
            ['Gaya dominan terbanyak', $teratas ?? '–', 'person-badge', null, $teratas ? $namaDisc[$teratas] . ' · ' . $dominan[$teratas] . ' orang' : '', 'purple'],
            ['Siap Delegating', $baris->where('katKomitmen', 'Tinggi')->count(), 'send-check', null, 'komitmen tinggi', 'success'],
            ['Perlu Coaching / Directing', $baris->whereIn('katKomitmen', ['Cukup', 'Rendah'])->count(), 'mortarboard', null, 'komitmen cukup / rendah', 'danger'],
        ]])
        <div class="row g-4">
            <div class="col-lg-7">
                <h6 class="small text-uppercase fw-semibold">Komitmen × Kompetensi → cara memimpin</h6>
                <table class="table table-sm small text-center mb-2 matriks-disc">
                    <tr><th class="text-start">Komitmen ↓ / Kompetensi →</th>@foreach ($D::GAYA as $k => $g)<th>{{ $k }}</th>@endforeach</tr>
                    @foreach ($matriks as $rk => $baris2)
                        <tr><th class="text-start">{{ $rk }} <small class="text-muted fw-normal">({{ $D::GAYA[$rk] }})</small></th>
                            @foreach ($baris2 as $ck => $n)
                                @php($w = $rk === $ck ? $warnaGaya[$rk] : '#667085')
                                @php($alpha = $n ? str_pad(dechex((int) round(30 + 160 * $n / $maksSel)), 2, '0', STR_PAD_LEFT) : '00')
                                @php($aktif = $km === $rk && $kp === $ck)
                                <td class="p-1">@if ($n)<a href="{{ $aktif ? route('dashboard.pegawai', ['tab' => 'disc', 'tim' => $tim]) : route('dashboard.pegawai', ['tab' => 'disc', 'tim' => $tim, 'km' => $rk, 'kp' => $ck]) }}#tabel-disc"
                                    class="sel {{ $aktif ? 'aktif' : '' }}" style="background:{{ $w }}{{ $alpha }};color:{{ $n / $maksSel > .5 && $w !== '#f0ad00' ? '#fff' : '#344054' }}" title="{{ $n }} orang · klik untuk menyaring">{{ $n }}</a>@else<span class="sel kosong">·</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
                <div class="d-flex flex-wrap gap-2 small">
                    @foreach ($D::GAYA as $k => $g)<span class="chip-nama m-0" style="background:{{ $warnaGaya[$k] }}1a;border-color:{{ $warnaGaya[$k] }}55"><span class="titik" style="background:{{ $warnaGaya[$k] }}"></span>{{ $k }} → {{ $g }}</span>@endforeach
                    <span class="text-muted align-self-center">· warna lebih pekat = lebih banyak orang · klik sel untuk menyaring</span>
                </div>
            </div>
            <div class="col-lg-5">
                <h6 class="small text-uppercase fw-semibold">Gaya DISC dominan</h6>
                @php($maks = max(1, $dominan->max() ?? 1))
                @foreach ($dominan->sortKeys()->sortBy(fn ($n, $g) => $g === '?' ? 1 : 0) as $g => $n)
                    @php($w = $warnaDisc[$g] ?? '#adb5bd')
                    <div class="batang"><div>@if (isset($namaDisc[$g]))<span class="chip-disc" style="background:{{ $w }}">{{ $g }}</span> {{ $namaDisc[$g] }}@else<span class="text-muted">Belum diisi</span>@endif</div>
                        <div class="batang-jalur"><div class="batang-isi" style="width:{{ $n / $maks * 100 }}%;background:{{ $w }}"></div></div>
                        <div class="batang-nilai">{{ $n }}<small class="text-muted fw-normal"> · {{ round($n / $totalDom * 100) }}%</small></div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="card" id="tabel-disc">
    @if ($km || $kp)
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Disaring: komitmen <strong>{{ $km }}</strong> × kompetensi <strong>{{ $kp }}</strong> · {{ $tampil->count() }} orang</span>
            <a href="{{ route('dashboard.pegawai', ['tab' => 'disc', 'tim' => $tim]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Hapus saringan</a>
        </div>
    @endif
    <div class="table-responsive">
    <table class="table table-sm small mb-0 align-middle tabel-bulan">
        <thead class="table-light"><tr><th class="text-start">Nama</th><th class="text-start">Tim</th><th>Masa kerja</th><th>DISC</th>
            @foreach ($D::KOMITMEN + $D::KOMPETENSI as $l)<th title="{{ $l }}" style="cursor:help">{{ mb_substr($l, 0, 4) }}</th>@endforeach
            <th class="text-start">Komitmen</th><th class="text-start">Kompetensi</th></tr></thead>
        @foreach ($tampil as $b)
            <tr>
                <td class="text-start text-nowrap"><div class="fw-medium">{{ $b->staff->nama ?? '-' }}</div><small class="text-muted">{{ $b->staff->level ?? '' }} · {{ $b->staff->divisi ?? '' }}</small></td>
                <td class="text-start text-muted">{{ $b->disc->tim }}</td>
                <td class="text-nowrap">{{ $b->staff?->mulai_kerja ? (int) $b->staff->mulai_kerja->diffInYears(now()) . ' th' : '-' }}</td>
                <td class="text-nowrap">@forelse (array_filter(preg_split('/[^A-Za-z]/', strtoupper((string) $b->disc->profil))) as $h)<span class="chip-disc" style="background:{{ $warnaDisc[$h] ?? '#adb5bd' }}">{{ $h }}</span>@empty<span class="text-muted">-</span>@endforelse</td>
                @foreach (array_keys($D::KOMITMEN + $D::KOMPETENSI) as $k)
                    @php($v = $b->disc->$k)
                    <td><span class="chip {{ $v === null ? 'kosong' : ($v >= 4 ? 'hijau' : ($v <= 2 ? 'merah' : '')) }}">{{ $v ?? '·' }}</span></td>
                @endforeach
                @foreach ([[$b->disc->komitmen, $b->katKomitmen], [$b->disc->kompetensi, $b->katKompetensi]] as [$nilai, $kat])
                    <td class="text-start text-nowrap">@if ($kat){{ $nilai }} <span class="badge" style="background:{{ $warnaGaya[$kat] }}">{{ $kat }}</span>
                        <br><small class="text-muted">{{ $D::GAYA[$kat] }}</small>@else - @endif</td>
                @endforeach
            </tr>
        @endforeach
    </table>
    </div>
    <div class="card-footer small text-muted"><span class="chip hijau">4</span> kuat &nbsp; <span class="chip merah">1–2</span> perlu dikembangkan &nbsp; · arahkan kursor ke judul kolom untuk nama aspek.</div>
</div>

@endif
@endif
@endsection
