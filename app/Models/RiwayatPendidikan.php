<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatPendidikan extends Model
{
    protected $connection = 'mysql';
    protected $table = 'riwayat_pendidikan';
    protected $guarded = ['id'];

    // Urutan = tingkat (untuk menentukan pendidikan terakhir)
    const JENJANG = ['SMA', 'D3', 'S1', 'Profesi', 'S2', 'S3'];
    const STATUS = ['Lulus', 'Sedang Studi Lanjut', 'Tidak Selesai'];
    const PEMBIAYAAN = ['Mandiri', 'Beasiswa Institusi', 'Beasiswa Luar'];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function dosen()
    {
        return $this->belongsTo(Dosen::class);
    }

    public function getNamaAttribute(): string
    {
        return $this->staff->nama ?? $this->dosen->nama ?? '-';
    }

    // Riwayat milik satu orang: dosen yang juga pegawai punya satu riwayat gabungan
    // Akun pegawai & data dosen milik orang yang sama (NIP sama = satu orang; dosen tertaut ke salah satu akunnya)
    public static function pemilik(?Staff $staff, ?Dosen $dosen): array
    {
        $staffIds = Staff::satuOrang(array_filter([$staff?->id, $dosen?->staff_id]));
        $dosenIds = collect(array_filter([$dosen?->id]))
            ->merge($staffIds->isEmpty() ? [] : Dosen::whereIn('staff_id', $staffIds)->pluck('id'))->unique()->values();
        return [$staffIds, $dosenIds];
    }

    // Riwayat satu orang: dari akun pegawai mana pun miliknya maupun data dosennya
    public static function milik(?Staff $staff, ?Dosen $dosen)
    {
        [$staffIds, $dosenIds] = self::pemilik($staff, $dosen);
        return self::where(fn ($q) => $q
            ->when($staffIds->isNotEmpty(), fn ($w) => $w->orWhereIn('staff_id', $staffIds))
            ->when($dosenIds->isNotEmpty(), fn ($w) => $w->orWhereIn('dosen_id', $dosenIds))
            ->when($staffIds->isEmpty() && $dosenIds->isEmpty(), fn ($w) => $w->whereRaw('1 = 0')))
            ->orderByDesc('tahun_lulus')->orderByDesc('tahun_masuk');
    }

    // Jenjang tertinggi yang sudah lulus → dipakai sebagai "pendidikan terakhir"
    public static function tertinggi($riwayat): ?string
    {
        return $riwayat->where('status', 'Lulus')
            ->sortByDesc(fn ($r) => array_search($r->jenjang, self::JENJANG))
            ->first()?->jenjang;
    }

    // Perbarui pendidikan_terakhir pegawai & dosen pemilik riwayat
    public static function perbaruiPendidikanTerakhir(?int $staffId, ?int $dosenId): void
    {
        $staff = $staffId ? Staff::find($staffId) : null;
        $dosen = $dosenId ? Dosen::find($dosenId) : null;
        $tertinggi = self::tertinggi(self::milik($staff, $dosen)->get());

        // Berlaku untuk semua akun & data dosen orang itu
        [$staffIds, $dosenIds] = self::pemilik($staff, $dosen);
        Staff::whereIn('id', $staffIds)->update(['pendidikan_terakhir' => $tertinggi]);
        // Pilihan pendidikan dosen hanya S1/S2/S3; selain itu isian manual dibiarkan
        if (in_array($tertinggi, ['S1', 'S2', 'S3'])) Dosen::whereIn('id', $dosenIds)->update(['pendidikan_terakhir' => $tertinggi]);
    }
}
