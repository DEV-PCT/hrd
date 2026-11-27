<?php

namespace App\Http\Controllers;

use App\Models\RiwayatPendidikan;
use App\Models\Staff;
use App\Services\PctApi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Pegawai = staff eUniversity: tambah/ubah/hapus ke API dulu, lalu salinan lokal (hrd_staffs).
// Isian HRD (atasan, status kepegawaian, pendidikan) hanya disimpan lokal.
class StaffController extends Controller
{
    use BiodataApi;

    public function __construct()
    {
        // Pegawai biasa hanya boleh melihat profilnya sendiri
        $this->middleware(function ($request, $next) {
            abort_unless($this->isAdmin(), 403);
            return $next($request);
        })->except('index', 'show');
    }

    public function index(Request $request)
    {
        if (!$this->isAdmin()) {
            return $this->myStaffId()
                ? redirect()->route('staff.show', $this->myStaffId())
                : redirect()->route('dashboard')->withErrors('Akun Anda belum tertaut ke data pegawai eUniversity. Hubungi HRD.');
        }

        $status = $request->input('status', 'aktif'); // default hanya pegawai aktif
        $filter = Staff::query()
            ->when($status !== 'semua', fn ($q) => $q->where('aktif', $status === 'aktif'))
            ->when($request->divisi, fn ($q, $v) => $q->where('id_divisi', $v))
            ->when($request->level, fn ($q, $v) => $q->where('id_level', $v))
            ->when($request->atasan === 'kosong', fn ($q) => $q->whereNull('atasan_id'))
            ->when($request->atasan === 'ada', fn ($q) => $q->whereNotNull('atasan_id'))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('nama', 'like', "%$s%")->orWhere('nip', 'like', "%$s%")->orWhere('username', 'like', "%$s%")))
            ;
        // Satu baris per orang: akun pertama (id terkecil) yang lolos filter per NIP; akun lainnya tampil sebagai chip
        $utama = (clone $filter)->selectRaw('min(id)')->groupByRaw("coalesce(nullif(nip, ''), concat('#', id))");
        $staff = (clone $filter)->with('atasan')->whereIn('id', $utama)->orderBy('nama')->paginate(50)->withQueryString();
        $akunLain = Staff::whereIn('nip', $staff->pluck('nip')->filter())->orderByDesc('aktif')->orderBy('id')
            ->get(['id', 'nip', 'username', 'divisi', 'level', 'aktif'])->groupBy('nip');

        // Kartu ringkasan (tidak ikut filter), dihitung per orang (NIP sama = 1)
        $nipAktif = Staff::aktif()->whereNotNull('nip')->where('nip', '!=', '')->pluck('nip')->unique();
        $nipDosen = Staff::whereIn('id', \App\Models\Dosen::where('aktif', true)->whereNotNull('staff_id')->pluck('staff_id'))->pluck('nip')->filter()->unique();
        $kartu = [
            ['Pegawai aktif', Staff::jumlahOrang(Staff::aktif()), 'people', route('staff.index'), ''],
            ['Dosen aktif', \App\Models\Dosen::where('aktif', true)->count(), 'mortarboard', route('dosen.index'), 'sesuai menu Data Dosen'],
            ['Tenaga kependidikan', Staff::jumlahOrang(Staff::aktif()->whereNotIn('nip', $nipDosen)), 'person-workspace', route('staff.index'), 'pegawai aktif non-dosen'],
            ['Belum ada atasan', Staff::jumlahOrang(Staff::aktif()->whereNull('atasan_id')), 'diagram-3', route('staff.index', ['atasan' => 'kosong']), 'pegawai aktif'],
            ['Nonaktif', Staff::jumlahOrang(Staff::where('aktif', false)->whereNotIn('nip', $nipAktif)), 'person-x', route('staff.index', ['status' => 'nonaktif']), 'tanpa akun aktif lain'],
        ];

        return view('staff.index', ['staff' => $staff, 'akunLain' => $akunLain, 'kartu' => $kartu] + $this->pilihan());
    }

    // Halaman Atasan & Bawahan: pilih atasan → lihat bawahannya → tambah/lepas bawahan sekaligus banyak.
    // Data divisi/level di eUniversity tidak cukup rapi untuk pengisian otomatis (satu akun bisa ada di banyak divisi).
    public function atasan(Request $request)
    {
        $atasan = $request->atasan ? Staff::find($request->atasan) : null;
        // Satu orang bisa punya beberapa akun (NIP sama): atasan & bawahan dihitung per orang
        $akunAtasan = $atasan ? Staff::satuOrang([$atasan->id]) : collect();
        $perOrang = fn ($q) => $q->whereIn('id', (clone $q)->selectRaw('min(id)')->groupByRaw("coalesce(nullif(nip, ''), concat('#', id))"));
        // Bawahan tampil semua (yang nonaktif ditandai, supaya bisa dilepas); calon atasan & kandidat hanya yang aktif
        $bawahan = $atasan ? Staff::whereIn('atasan_id', $akunAtasan)->orderByDesc('aktif')->orderBy('divisi')->orderBy('nama')->get()
            ->sortByDesc('aktif')->unique(fn ($b) => $b->nip ?: '#' . $b->id)->values() : collect();

        $kandidat = $atasan ? $perOrang(Staff::aktif()
            ->whereNotIn('id', $akunAtasan)
            ->where(fn ($q) => $q->whereNull('atasan_id')->orWhereNotIn('atasan_id', $akunAtasan))
            ->when($request->divisi, fn ($q, $v) => $q->where('id_divisi', $v))
            ->when($request->level, fn ($q, $v) => $q->where('id_level', $v))
            ->when($request->boolean('kosong'), fn ($q) => $q->whereNull('atasan_id'))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('nama', 'like', "%$s%")->orWhere('nip', 'like', "%$s%")->orWhere('username', 'like', "%$s%"))))
            ->with('atasan')->orderBy('nama')->paginate(50)->withQueryString() : null;

        // Calon atasan: satu pilihan per orang, jumlah bawahan dijumlah dari semua akunnya
        $semua = Staff::aktif()->withCount('bawahan')->orderBy('id')->get(['id', 'nip', 'nama', 'level', 'divisi'])
            ->groupBy(fn ($s) => $s->nip ?: '#' . $s->id)
            ->map(fn ($g) => tap($g->first(), fn ($u) => $u->bawahan_count = $g->sum('bawahan_count')))
            ->sortBy('nama')->values();

        return view('staff.atasan', compact('atasan', 'bawahan', 'kandidat', 'semua') + [
            'tanpaAtasan' => Staff::jumlahOrang(Staff::aktif()->whereNull('atasan_id')),
        ] + $this->pilihan());
    }

    // Simpan: pegawai[] dijadikan bawahan atasan_id (kosong = lepas atasan)
    public function aturAtasan(Request $request)
    {
        $data = $request->validate([
            'pegawai' => 'required|array|min:1',
            'pegawai.*' => 'integer',
            'atasan_id' => ['nullable', Rule::exists('staffs', 'id')],
        ], ['pegawai.required' => 'Centang dulu pegawai yang mau diatur.']);

        // Cegah lingkaran: atasan sendiri & semua atasan di atasnya tidak boleh dijadikan bawahannya
        $terlarang = [];
        for ($id = $data['atasan_id']; $id && !in_array($id, $terlarang); $id = Staff::whereKey($id)->value('atasan_id')) {
            $terlarang[] = $id;
        }
        // Atasan berlaku per orang: semua akun dengan NIP sama ikut diatur (akun milik atasan & atasan-atasannya dikecualikan)
        $terlarang = Staff::satuOrang($terlarang)->all();
        $pilih = collect($data['pegawai'])->reject(fn ($id) => in_array($id, $terlarang));
        $ids = Staff::satuOrang($pilih)->reject(fn ($id) => in_array($id, $terlarang));
        $n = Staff::jumlahOrang(Staff::whereIn('id', $ids)) ?: $ids->count();
        Staff::whereIn('id', $ids)->update(['atasan_id' => $data['atasan_id']]);
        $dilewati = count($data['pegawai']) - $pilih->count();

        $pesan = $data['atasan_id']
            ? "{$n} pegawai dijadikan bawahan dari: " . Staff::find($data['atasan_id'])->nama
            : "Atasan {$n} pegawai dilepas";
        return back()->with('ok', $pesan . ($dilewati ? " — {$dilewati} dilewati karena atasan tidak boleh menjadi bawahan dari bawahannya sendiri." : ''));
    }

    public function show(Staff $staff)
    {
        abort_unless($this->isAdmin() || $staff->id == $this->myStaffId(), 403);
        $staff->load('atasan', 'bawahan', 'dosen');
        $riwayat = RiwayatPendidikan::milik($staff, $staff->dosen)->get();
        $jatah = \App\Models\JenisCuti::rekap($staff->nip ? Staff::where('nip', $staff->nip)->pluck('id')->all() : [$staff->id]);
        return view('staff.show', compact('staff', 'riwayat', 'jatah'));
    }

    public function create()
    {
        return view('staff.form', ['staff' => new Staff] + $this->pilihan());
    }

    public function store(Request $request, PctApi $api)
    {
        $this->wajibScope('staff.create');
        $data = $this->validasi($request);
        $hasil = $this->keApi(fn () => $api->tulis('POST', '/staff', $this->payloadStaff($data)));

        $staff = Staff::create($this->lokal($data) + ['api_id' => $hasil['idStaff']]);
        return redirect()->route('staff.show', $staff)->with('ok', "Pegawai {$staff->nama} ditambahkan ke eUniversity & HRD.");
    }

    public function edit(Staff $staff)
    {
        return view('staff.form', ['staff' => $staff] + $this->pilihan($staff));
    }

    public function update(Request $request, Staff $staff, PctApi $api)
    {
        $this->wajibScope('staff.update');
        $data = $this->validasi($request, $staff);
        // NIP hanya dikirim bila berubah: satu orang bisa punya beberapa akun dengan NIP sama,
        // dan eUniversity menolak NIP yang sudah dipakai akun lain ("NIP sudah digunakan")
        $payload = $this->payloadStaff($data);
        if ((string) $data['nip'] === (string) $staff->nip) unset($payload['nip']);
        $this->keApi(fn () => $api->tulis('PATCH', "/staff/{$staff->api_id}", $payload));

        $staff->update($this->lokal($data));
        // Atasan berlaku per orang: akun lain dengan NIP sama ikut
        if ($staff->nip && array_key_exists('atasan_id', $data)) {
            Staff::where('nip', $staff->nip)->whereKeyNot($staff->id)->update(['atasan_id' => $staff->atasan_id]);
        }
        return redirect()->route('staff.show', $staff)->with('ok', "Data {$staff->nama} disimpan ke eUniversity & HRD.");
    }

    public function destroy(Staff $staff)
    {
        $this->wajibScope('staff.delete');
        if ($staff->api_id && $gagal = $this->hapusDiApi("/staff/{$staff->api_id}")) {
            return back()->withErrors($gagal);
        }
        Staff::where('atasan_id', $staff->id)->update(['atasan_id' => null]);
        $staff->delete();
        return redirect()->route('staff.index')->with('ok', "Pegawai {$staff->nama} dihapus.");
    }

    private function validasi(Request $request, ?Staff $staff = null): array
    {
        $d = $request->validate($this->aturanBiodata() + [
            // NIP tidak dicek unik di sini: satu orang bisa punya beberapa akun staff (beda divisi/role). API yang memutuskan (409).
            'nip' => 'required|string|max:30',
            'id_divisi' => 'required|integer',
            'id_level' => 'required|integer',
            // Isian HRD
            'atasan_id' => ['nullable', Rule::exists('staffs', 'id'), Rule::notIn($staff ? Staff::satuOrang([$staff->id])->all() : [])],
            'status_kepegawaian' => ['nullable', Rule::in(Staff::STATUS)],
        ], ['atasan_id.not_in' => 'Atasan tidak boleh diri sendiri.']);
        // Pasangan divisi × level harus ada di master struktur eUniversity (bila sudah disinkron)
        $struktur = \App\Models\Struktur::where('aktif', true);
        if ($struktur->exists() && !$struktur->where(['id_divisi' => $d['id_divisi'], 'id_level' => $d['id_level']])->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['id_level' => 'Level ini tidak ada di divisi tersebut menurut struktur eUniversity.']);
        }
        return $d;
    }

    private function payloadStaff(array $d): array
    {
        return $this->payloadBiodata($d) + collect($d)->only(['nip', 'id_divisi', 'id_level'])->all();
    }

    private function lokal(array $d): array
    {
        $p = $this->pilihan();
        return $this->lokalBiodata($d) + collect($d)->only(['atasan_id', 'status_kepegawaian'])->all() + [
            'nip' => $d['nip'],
            'id_divisi' => $d['id_divisi'],
            'divisi' => $p['divisiList'][$d['id_divisi']] ?? null,
            'id_level' => $d['id_level'],
            'level' => $p['levelList'][$d['id_level']] ?? null,
        ];
    }

    // Pilihan divisi/level dari master struktur eUniversity (tersinkron hrd:sync); cadangan: yang dipakai pegawai
    private function pilihan(?Staff $kecuali = null): array
    {
        $struktur = \App\Models\Struktur::where('aktif', true)->get();
        return [
            'divisiList' => $struktur->isNotEmpty() ? $struktur->sortBy('divisi')->pluck('divisi', 'id_divisi') : Staff::whereNotNull('id_divisi')->orderBy('divisi')->pluck('divisi', 'id_divisi'),
            'levelList' => $struktur->isNotEmpty() ? $struktur->sortBy('level')->pluck('level', 'id_level') : Staff::whereNotNull('id_level')->orderBy('level')->pluck('level', 'id_level'),
            // Level yang sah per divisi (untuk menyaring pilihan level di form)
            'strukturMap' => $struktur->groupBy('id_divisi')->map->pluck('id_level'),
            // Calon atasan: pegawai aktif (+ atasan yang sedang terpasang, supaya tidak hilang dari pilihan)
            'atasanList' => Staff::where(fn ($q) => $q->where('aktif', true)->orWhere('id', $kecuali?->atasan_id))
                ->when($kecuali, fn ($q) => $q->where('id', '!=', $kecuali->id))->orderBy('nama')->get(['id', 'nama', 'divisi', 'level']),
        ];
    }
}
