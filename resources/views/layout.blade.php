<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · HRD ITB PalComTech</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .sidebar { width: 240px; }
        .sidebar .nav-link { color: #cfd8e3; border-radius: .375rem; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .sidebar .nav-link.active { background: #0d6efd; color: #fff; }
        /* Bar filter di atas tabel/daftar: satu gaya untuk semua halaman */
        .bar-filter { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; padding: .75rem 1rem; background: #fff; border-bottom: 1px solid var(--bs-border-color); border-radius: var(--bs-card-inner-border-radius) var(--bs-card-inner-border-radius) 0 0; }
        .bar-filter > .form-select { width: auto; flex: 1 1 150px; max-width: 220px; }
        .bar-filter > .input-group { flex: 2 1 220px; max-width: 320px; }
        .bar-filter > .form-check { margin: 0 .25rem; }
        .bar-filter > .info { margin-left: auto; font-size: .875rem; color: var(--bs-secondary-color); white-space: nowrap; }

        /* Dashboard: kartu angka & grafik batang horizontal */
        .kartu { transition: box-shadow .15s; }
        .kartu:hover { box-shadow: 0 .25rem .75rem rgba(0,0,0,.08); }
        /* Kartu berwarna (gaya small-box BAAK) */
        .kotak { position: relative; border-radius: .5rem; overflow: hidden; color: #fff; display: flex; flex-direction: column; text-decoration: none; box-shadow: 0 1px 3px rgba(0,0,0,.12); transition: transform .15s, box-shadow .15s; }
        a.kotak:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 .4rem 1rem rgba(0,0,0,.15); }
        .kotak .isi { padding: 1rem 1.1rem; flex: 1; position: relative; z-index: 1; }
        .kotak .angka { font-size: 2.2rem; font-weight: 700; line-height: 1.1; }
        .kotak .ket { font-size: .8rem; opacity: .85; }
        .kotak .bi-latar { position: absolute; right: 1rem; top: .6rem; font-size: 4rem; opacity: .2; }
        .kotak .kaki { background: rgba(0,0,0,.12); text-align: center; padding: .3rem; font-size: .85rem; }
        .kotak.k-info { background: #17a2b8; } .kotak.k-danger { background: #dc3545; } .kotak.k-warning { background: #ffc107; color: #212529; }
        .kotak.k-success { background: #28a745; } .kotak.k-primary { background: #0d6efd; } .kotak.k-purple { background: #6f42c1; }
        a.kotak.k-warning:hover { color: #212529; }
        .panel-status .list-group-item { padding: .55rem 1rem; }
        .kartu .ikon { width: 44px; height: 44px; border-radius: .5rem; display: grid; place-items: center; font-size: 1.25rem; background: #e7f0ff; color: #0d6efd; }
        .batang { display: grid; grid-template-columns: minmax(90px, 38%) 1fr 36px; align-items: center; gap: .5rem; padding: 3px 0; font-size: .875rem; }
        .batang:hover { background: #f8f9fa; }
        .batang-jalur { background: #f1f3f5; border-radius: 4px; height: 12px; }
        .batang-isi { background: #0d6efd; height: 100%; border-radius: 4px; min-width: 2px; }
        .batang-isi.kosong { background: #adb5bd; }
        .batang-nilai { text-align: right; font-variant-numeric: tabular-nums; color: #495057; }

        /* ===== Tampilan global (gaya BAAK): kartu lembut, header beraksen, tabel & tab lebih hidup ===== */
        body.bg-light { background: #f4f6f9 !important; }
        .content .card { border: 0; border-radius: .6rem; box-shadow: 0 1px 3px rgba(16,24,40,.08), 0 1px 2px rgba(16,24,40,.04); }
        .content .card > .card-header { background: #fff; border-bottom: 1px solid #eef0f3; font-weight: 600; color: #344054;
            padding: .8rem 1rem; border-left: 4px solid #0d6efd; border-radius: .6rem .6rem 0 0; }
        .content .card > .card-header .bi:first-child { color: #0d6efd; }
        .content .row > [class*=col]:nth-child(4n+2) > .card > .card-header { border-left-color: #28a745; }
        .content .row > [class*=col]:nth-child(4n+2) > .card > .card-header .bi:first-child { color: #28a745; }
        .content .row > [class*=col]:nth-child(4n+3) > .card > .card-header { border-left-color: #fd7e14; }
        .content .row > [class*=col]:nth-child(4n+3) > .card > .card-header .bi:first-child { color: #fd7e14; }
        .content .row > [class*=col]:nth-child(4n) > .card > .card-header { border-left-color: #6f42c1; }
        .content .row > [class*=col]:nth-child(4n) > .card > .card-header .bi:first-child { color: #6f42c1; }
        .content .list-group-item-action:hover { background: #f5f9ff; }
        /* Tabel */
        .content .table > thead th { background: #f1f5fb !important; color: #475467; font-size: .78rem; text-transform: uppercase; letter-spacing: .02em; border-bottom: 2px solid #dbe4f0; white-space: nowrap; }
        .content .table > tbody tr:hover > * { background: #f8fbff; }
        .content .table td, .content .table th { vertical-align: middle; }
        .content h6.text-uppercase, .content .small.text-uppercase { color: #0d6efd !important; letter-spacing: .03em; }
        /* Tab */
        .content .nav-tabs { border-bottom: 2px solid #e4e8ee; gap: .25rem; }
        .content .nav-tabs .nav-link { border: 0; color: #667085; font-weight: 500; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .content .nav-tabs .nav-link:hover { color: #0d6efd; background: #eef4ff; }
        .content .nav-tabs .nav-link.active { color: #0d6efd; background: transparent; border-bottom-color: #0d6efd; }
        /* Badge & tombol */
        .content .badge { font-weight: 600; padding: .38em .6em; }
        .content .btn { border-radius: .45rem; }
        /* Batang: gradasi + warna bergiliran per baris */
        .batang { border-radius: 4px; padding: 4px 2px; }
        .batang-jalur { height: 14px; border-radius: 7px; background: #eef1f5; }
        .batang-isi { border-radius: 7px; background: linear-gradient(90deg, #4f8bff, #0d6efd); }
        .batang-nilai { font-weight: 600; color: #344054; }
        /* Sorotan sel peta panas (bg-warning-subtle dll.) lebih tegas */
        .content .table .bg-warning-subtle, .content .table .table-warning { background: #ffe8a3 !important; font-weight: 600; }
        .content .table .bg-danger-subtle { background: #ffd0d4 !important; font-weight: 600; }
        /* Tabel bulanan (kehadiran): kolom rata tengah, angka sebagai chip */
        .tabel-bulan th, .tabel-bulan td { text-align: center; font-variant-numeric: tabular-nums; padding: .45rem .35rem; }
        .tabel-bulan tbody tr:nth-child(even) > * { background: #fafbfd; }
        .tabel-bulan .chip { display: inline-block; min-width: 2.6em; padding: .15em .45em; border-radius: 999px; font-weight: 500; color: #344054; }
        .tabel-bulan .chip.nol, .tabel-bulan .chip.kosong { color: #c0c6cf; font-weight: 400; }
        .chip.kuning { background: #fff1c2; color: #8a5a00 !important; font-weight: 600 !important; display: inline-block; padding: .15em .5em; border-radius: 999px; }
        .chip.merah { background: #ffd9dc; color: #b42318 !important; font-weight: 600 !important; display: inline-block; padding: .15em .5em; border-radius: 999px; }
        .absen { display: inline-block; margin-top: 2px; font-size: .7rem; padding: .05em .45em; border-radius: 999px; background: #eef2f6; color: #475467; border: 1px solid #dde3ea; }
        .tabel-bulan .kolom-total { font-weight: 700; color: #0d6efd; background: #f1f6ff !important; }
        /* Sapaan Dashboard Saya */
        .content .card.sapaan { background: linear-gradient(120deg, #0d6efd, #17a2b8); color: #fff; }
        .sapaan .ikon { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; font-size: 1.6rem; background: rgba(255,255,255,.2); color: #fff; }
        @media (min-width: 992px) { .sidebar { position: fixed; top: 0; bottom: 0; } .content { margin-left: 240px; } }

        /* Bilah progres AJAX (hrd.js) */
        #progres { position: fixed; top: 0; left: 0; height: 3px; width: 0; opacity: 0; z-index: 2000; background: #0d6efd;
                   box-shadow: 0 0 8px rgba(13,110,253,.6); transition: width .3s ease, opacity .3s; }
        /* Skeleton loading (komponen x-skeleton) */
        .sk { background: linear-gradient(90deg, #eceff3 25%, #f6f7f9 37%, #eceff3 63%); background-size: 400% 100%;
              animation: sk-kilau 1.2s ease infinite; border-radius: 4px; }
        .sk-teks { height: 12px; } .sk-teks-kecil { height: 9px; } .sk-judul { height: 22px; }
        .sk-input { height: 34px; flex: 1 1 150px; max-width: 220px; } .sk-tombol { height: 30px; width: 64px; }
        @keyframes sk-kilau { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
        @media (prefers-reduced-motion: reduce) { .sk { animation: none; } }
    </style>
</head>
<body class="bg-light">
<div id="progres" role="progressbar" aria-label="Memuat"></div>
@php
    $admin = Gate::allows('admin');
    // Item: [route, pola aktif, ikon, label]. Grup yang bisa dilipat: ['grup', id, judul, ikon, route induk|null, item]
    // Item grup: [route, ikon, label, 'aktif' => pola?]; tanpa pola: route-nya sendiri, untuk *.index juga turunannya (mis. jenis-cuti.*)
    // Dashboard analitik mengikuti role & level eUniversity (App\Support\Akses): admin/Rektor/Warek semua, kaprodi dosen prodinya.
    $peran = \App\Models\Pejabat::peranDari(auth()->user()->staff_id ? [auth()->user()->staff_id] : []);
    $dasbor = array_values(array_filter([
        \App\Support\Akses::dashboardPegawai() ? ['dashboard.pegawai', 'person-lines-fill', 'Pegawai', 'aktif' => ['dashboard', 'dashboard.pegawai*']] : null,
        \App\Support\Akses::dashboardDosen() ? ['dashboard.dosen', 'clipboard-data', 'Dosen', 'aktif' => 'dashboard.dosen*'] : null,
    ]));
    // Yang punya dashboard SDM tetap pegawai: dashboard pribadinya di "Saya"
    $saya = ['dashboard.saya', 'person-circle', 'Saya', 'aktif' => 'dashboard.saya'];
    // Notifikasi: pengajuan yang menunggu tindakan akun ini (persetujuan atasan / proses HRD)
    $tugas = \App\Models\Pengajuan::tugas(\App\Models\Staff::akunSatuOrang(auth()->user()->staff_id), \Illuminate\Support\Facades\Gate::allows('admin'));
    $tugasCuti = (clone $tugas)->cuti()->count();
    $tugas = $tugas->count();
    $menu = array_filter([
        $dasbor ? ['grup', 'grupDashboard', 'Dashboard', 'speedometer2', null, \App\Support\Akses::dashboardPegawai() ? [$saya, ...$dasbor] : [['dashboard', 'house', 'Ringkasan', 'aktif' => 'dashboard'], ...$dasbor]]
            : ['dashboard', 'dashboard', 'speedometer2', 'Dashboard'],
        \App\Support\Akses::dashboardPegawai() ? ['grup', 'grupPengajuan', 'Pengajuan', 'ui-checks', null, [
            ['pengajuan.index', 'list-check', 'Semua Pengajuan', 'aktif' => 'pengajuan.*', 'badge' => $tugas],
            ['cuti.rekap', 'calendar-check', 'Pengajuan Cuti', 'aktif' => 'cuti.rekap', 'badge' => $tugasCuti],
        ], 'badge' => $tugas] : ['pengajuan.index', 'pengajuan.*', 'ui-checks', 'Pengajuan', 'badge' => $tugas],
        $admin ? ['grup', 'grupInput', 'Input Data', 'pencil-square', 'input.index', \App\Support\Menu::input()] : null,
        $admin ? ['grup', 'grupMaster', 'Master Data', 'database', null, [
            ['staff.index', 'people', 'Data Pegawai', 'aktif' => ['staff.index', 'staff.show', 'staff.create', 'staff.edit']],
            ['staff.atasan', 'diagram-3', 'Atasan & Bawahan', 'aktif' => 'staff.atasan*'],
            ['dosen.index', 'mortarboard', 'Data Dosen', 'aktif' => 'dosen*'],
            ['riwayat.index', 'journal-bookmark', 'Studi Lanjut', 'aktif' => 'riwayat*'],
        ]] : ['staff.index', 'staff.*', 'people', 'Profil Saya'],
        $admin ? ['grup', 'grupLaporan', 'Laporan', 'file-earmark-bar-graph', 'laporan.index', \App\Support\Menu::laporan()] : null,
        $admin ? ['grup', 'grupPengaturan', 'Pengaturan', 'gear', 'pengaturan.index', \App\Support\Menu::pengaturan()] : null,
    ]);
    $aktif = fn ($m) => isset($m['aktif']) ? request()->routeIs($m['aktif'])
        : request()->routeIs($m[0]) || (str_ends_with($m[0], '.index') && request()->routeIs(\Illuminate\Support\Str::beforeLast($m[0], '.') . '.*'));
@endphp

{{-- Offcanvas: sidebar tetap di desktop, laci di mobile --}}
<aside class="sidebar offcanvas-lg offcanvas-start bg-dark text-white d-flex flex-column" tabindex="-1" id="sidebar">
    <div class="p-3 d-flex align-items-center gap-2 border-bottom border-secondary">
        <img src="{{ asset('logo-pct1.png') }}" height="28" alt="" class="bg-white rounded p-1">
        <span class="fw-semibold">HRD PalComTech</span>
        <button class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
    </div>
    {{-- flex-nowrap: tanpa ini .nav melipat ke kolom kedua saat menu lebih tinggi dari layar --}}
    <nav class="nav flex-column flex-nowrap p-2 gap-1 flex-grow-1 overflow-y-auto overflow-x-hidden" id="menu" style="min-height:0">
        @foreach ($menu as $m)
            @if ($m[0] !== 'grup')
                <a href="{{ route($m[0]) }}" class="nav-link d-flex align-items-center {{ request()->routeIs($m[1]) ? 'active' : '' }}">
                    <i class="bi bi-{{ $m[2] }} me-2"></i>{{ $m[3] }}@if (!empty($m['badge']))<span class="badge rounded-pill text-bg-danger ms-auto" title="Menunggu tindakan Anda">{{ $m['badge'] }}</span>@endif
                </a>
                @continue
            @endif
            @php([, $id, $judul, $ikon, $induk, $item] = $m)
            @php($buka = ($induk && request()->routeIs($induk)) || collect($item)->contains($aktif))
            <a class="nav-link d-flex align-items-center {{ $buka ? 'text-white' : '' }}" data-bs-toggle="collapse" href="#{{ $id }}" role="button" aria-expanded="{{ $buka ? 'true' : 'false' }}">
                <i class="bi bi-{{ $ikon }} me-2"></i>{{ $judul }}@if (!empty($m['badge']))<span class="badge rounded-pill text-bg-danger ms-auto" title="Menunggu tindakan Anda">{{ $m['badge'] }}</span>@endif<i class="bi bi-chevron-down {{ empty($m['badge']) ? 'ms-auto' : 'ms-2' }} small"></i>
            </a>
            <div class="collapse {{ $buka ? 'show' : '' }}" id="{{ $id }}">
                <nav class="nav flex-column gap-1 ms-3 mb-1">
                    @foreach ($item as $i)
                        <a href="{{ route($i[0], !empty($i['badge']) && $i[0] === 'pengajuan.index' ? ['tab' => 'tugas'] : []) }}" class="nav-link py-1 small d-flex align-items-center {{ $aktif($i) ? 'active' : '' }}">
                            <i class="bi bi-{{ $i[1] }} me-2"></i>{{ $i[2] }}@if (!empty($i['badge']))<span class="badge rounded-pill text-bg-danger ms-auto" title="Menunggu tindakan Anda">{{ $i['badge'] }}</span>@endif
                        </a>
                    @endforeach
                </nav>
            </div>
        @endforeach
    </nav>
    <div class="p-3 border-top border-secondary small">
        <div class="fw-semibold">{{ auth()->user()->name }}</div>
        @if (auth()->user()->staff_id)<a href="{{ route('staff.show', auth()->user()->staff_id) }}" class="small text-white-50 text-decoration-none"><i class="bi bi-person-vcard me-1"></i>Profil Saya</a>@endif
        <div class="text-white-50 mb-2">{{ ['admin' => 'Admin HRD', 'dosen' => 'Dosen', 'staff' => 'Staf'][auth()->user()->peran] }}</div>
        <form method="post" action="{{ route('logout') }}" data-no-ajax>@csrf<button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right me-1"></i>Keluar</button></form>
    </div>
</aside>

<div class="content">
    <nav class="navbar bg-white border-bottom d-lg-none mb-3">
        <div class="container-fluid">
            <button class="btn" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-list fs-4"></i></button>
            <span class="fw-semibold">HRD PalComTech</span>
            <span></span>
        </div>
    </nav>
    {{-- #halaman = bagian yang ditukar oleh hrd.js saat navigasi/submit AJAX --}}
    <div id="halaman">
    <header class="bg-white border-bottom px-3 px-lg-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none"><i class="bi bi-house-door"></i> Beranda</a></li>
                    @yield('breadcrumb')
                    @hasSection('title')<li class="breadcrumb-item active" aria-current="page">@yield('title')</li>@endif
                </ol>
            </nav>
            <h1 class="h4 mb-0">@yield('title', 'Dashboard')</h1>
        </div>
        <div class="d-flex flex-wrap gap-2">@yield('actions')</div>
    </header>
    <main class="container-fluid p-3 p-lg-4">
        @if (session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
    </div>
</div>

<template id="tplSkeleton"><x-skeleton /></template>
<x-toast />
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/hrd.js') }}?v={{ filemtime(public_path('js/hrd.js')) }}"></script>
</body>
</html>
