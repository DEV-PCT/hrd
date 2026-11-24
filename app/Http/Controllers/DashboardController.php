<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\JenisCuti;
use App\Models\JenisPengajuan;
use App\Models\Pengajuan;
use App\Models\RiwayatPendidikan;
use App\Models\Staff;

class DashboardController extends Controller
{
    public function __invoke()
    {
        // Admin: ringkasan SDM ada di tab pertama Dashboard Pegawai
        return $this->isAdmin() ? redirect()->route('dashboard.pegawai') : $this->pegawai();
    }

    // Data tab Ringkasan di Dashboard Pegawai (data cuti di menu Dashboard > Cuti, data dosen di Dashboard Dosen)
    public function ringkasan(): array
    {
        $terakhirSinkron = max(Staff::max('disinkron_at'), Dosen::max('disinkron_at'));
        $hitung = fn ($q, $kolom, $kosong, $isi = '*') => $q->selectRaw("coalesce($kolom, '$kosong') k, count($isi) c")
            ->groupBy('k')->orderByDesc('c')->toBase()->pluck('c', 'k');

        return [
            'kartu' => [
                ['Pegawai aktif', Staff::jumlahOrang(Staff::aktif()), 'people', route('staff.index'), ''],
                ['Pengajuan menunggu HRD', Pengajuan::tugas($this->myStaffIds(), true)->count(), 'inbox', route('pengajuan.index', ['tab' => 'tugas']), 'semua jenis pengajuan'],
                ['Studi lanjut', RiwayatPendidikan::where('status', 'Sedang Studi Lanjut')->count(), 'journal-bookmark', route('riwayat.index'), 'sedang berjalan'],
            ],
            'tindakan' => [
                ['Pegawai aktif belum punya atasan', Staff::jumlahOrang(Staff::aktif()->whereNull('atasan_id')), route('staff.atasan')],
                ['Studi lanjut lewat target lulus', RiwayatPendidikan::where('status', 'Sedang Studi Lanjut')->where('tahun_lulus', '<', now()->year)->count(), route('riwayat.index')],
            ],
            'terakhirSinkron' => $terakhirSinkron ? \Illuminate\Support\Carbon::parse($terakhirSinkron) : null,
            // Status pengajuan per jenis (gaya panel BAAK)
            'perJenis' => JenisPengajuan::orderBy('urutan')->get(['id', 'nama'])->map(function ($j) {
                $n = Pengajuan::where('jenis_pengajuan_id', $j->id)
                    ->selectRaw("case when status in ('Disetujui','Ditolak','Dibatalkan') then status else 'Menunggu' end s, count(*) c")
                    ->groupBy('s')->toBase()->pluck('c', 's');
                return ['id' => $j->id, 'nama' => $j->nama, 'n' => $n];
            }),
            'perDivisi' => $hitung(Staff::aktif()->whereNotNull('nip'), 'divisi', 'Tanpa divisi', 'distinct nip'), // per orang
        ];
    }

    // Menu Dashboard > Cuti: rekap pengajuan cuti seluruh pegawai (admin/pimpinan)
    public function cuti()
    {
        abort_unless(\App\Support\Akses::dashboardPegawai(), 403);
        $hariIni = now()->toDateString();
        $menunggu = Pengajuan::cuti()->menunggu();
        $disetujui = fn () => Pengajuan::cuti()->where('status', 'Disetujui');
        $daftar = fn ($status = null) => route('pengajuan.index', ['tab' => 'semua', 'jenis' => JenisPengajuan::where('kode', 'cuti')->value('id'), 'status' => $status]);

        return view('dashboard.cuti', [
            'kartu' => [
                ['Cuti menunggu', (clone $menunggu)->count(), 'hourglass-split', $daftar(), 'atasan / HRD'],
                ['Sedang cuti', $disetujui()->whereDate('tgl_mulai', '<=', $hariIni)->whereDate('tgl_selesai', '>=', $hariIni)->count(), 'calendar-x', $daftar('Disetujui'), 'hari ini'],
                ['Cuti disetujui ' . now()->year, $disetujui()->whereYear('tgl_mulai', now()->year)->count(), 'calendar-check', $daftar('Disetujui'), 'pengajuan'],
            ],
            'cutiMenunggu' => (clone $menunggu)->with('staff', 'jenisCuti')->orderBy('tgl_mulai')->limit(10)->get(),
            'semuaCuti' => $daftar(),
            'akanCuti' => $disetujui()->with('staff', 'jenisCuti')
                ->whereDate('tgl_selesai', '>=', $hariIni)->whereDate('tgl_mulai', '<=', now()->addDays(7))
                ->orderBy('tgl_mulai')->limit(10)->get(),
            'jenisCuti' => JenisPengajuan::where('kode', 'cuti')->value('id'),
        ]);
    }

    // Dashboard pribadi untuk admin/pejabat (yang juga pegawai): jatah cuti, cuti saya, persetujuan
    public function saya()
    {
        return $this->pegawai();
    }

    private function pegawai()
    {
        $akun = auth('pegawai')->user();
        $staff = $akun->staff()->with('atasan')->first();
        $me = $staff?->id;
        $bawahan = Staff::whereIn('atasan_id', $this->myStaffIds())->pluck('id');

        return view('dashboard.pegawai', [
            'akun' => $akun,
            'staff' => $staff,
            'jatah' => $me ? JenisCuti::rekap($this->myStaffIds()->all()) : collect(),
            'cutiSaya' => Pengajuan::cuti()->with('jenisCuti')->whereIn('staff_id', $this->myStaffIds())->latest()->limit(5)->get(),
            'pengajuanBawahan' => Pengajuan::tugas($this->myStaffIds(), false)->count(),
            'jenisCuti' => JenisPengajuan::where('kode', 'cuti')->value('id'),
            'jumlahBawahan' => $bawahan->count(),
        ]);
    }
}
