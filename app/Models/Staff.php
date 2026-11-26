<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Pegawai = staff eUniversity. Kolom identitas/biodata dari API (sinkron);
// atasan_id, status_kepegawaian = isian HRD, tidak ditimpa sinkron.
// pendidikan_terakhir = otomatis dari riwayat pendidikan (jenjang tertinggi yang lulus).
class Staff extends Model
{
    protected $connection = 'mysql';
    protected $table = 'staffs';
    protected $guarded = ['id'];
    protected $casts = ['tgl_lahir' => 'date', 'mulai_kerja' => 'date', 'tanggal_keluar' => 'date', 'kontrak_berakhir' => 'date', 'aktif' => 'boolean', 'roles' => 'array'];

    // Pegawai aktif = akun login eUniversity-nya aktif (diisi sinkron)
    /**
     * Akun staff utama satu orang (NIP sama bisa punya beberapa akun): aktif dulu, lalu id terkecil.
     * Data per orang (DISC, kehadiran) disimpan di akun ini.
     */
    public static function utama(?string $nip): ?self
    {
        return $nip ? static::where('nip', $nip)->orderByDesc('aktif')->orderBy('id')->first() : null;
    }

    // Semua akun milik orang-orang dari daftar akun $ids (NIP sama = orang sama; tanpa NIP = akun itu saja)
    public static function satuOrang(iterable $ids): \Illuminate\Support\Collection
    {
        $ids = collect($ids)->filter()->values();
        $nip = static::whereIn('id', $ids)->whereNotNull('nip')->where('nip', '!=', '')->pluck('nip');
        return $ids->merge($nip->isEmpty() ? [] : static::whereIn('nip', $nip)->pluck('id'))->unique()->values();
    }

    /**
     * Atasan berlaku per orang: akun lain dengan NIP sama yang atasannya kosong diisi atasan akun utamanya
     * (atau akun mana pun yang sudah diisi). Akun yang sudah punya atasan berbeda tidak ditimpa. Mengembalikan jumlah akun yang diisi.
     */
    public static function samakanAtasan(?iterable $nips = null): int
    {
        $n = 0;
        $q = static::whereNotNull('nip')->where('nip', '!=', '')->whereNotNull('atasan_id')
            ->when($nips !== null, fn ($w) => $w->whereIn('nip', collect($nips)->filter()));
        foreach ($q->orderByDesc('aktif')->orderBy('id')->get(['id', 'nip', 'atasan_id'])->unique('nip') as $s) {
            // jangan jadikan orang itu atasan bagi dirinya sendiri
            $akunSendiri = static::where('nip', $s->nip)->pluck('id');
            if ($akunSendiri->contains($s->atasan_id)) continue;
            $n += static::where('nip', $s->nip)->whereNull('atasan_id')->update(['atasan_id' => $s->atasan_id]);
        }
        return $n;
    }

    // Semua akun staff milik orang yang sama (NIP sama) dengan akun $id
    public static function akunSatuOrang(?int $id): \Illuminate\Support\Collection
    {
        $nip = $id ? static::whereKey($id)->value('nip') : null;
        return $nip ? static::where('nip', $nip)->pluck('id') : collect($id ? [$id] : []);
    }

    // Satu baris per orang: akun utama tiap NIP
    public static function perOrang($query)
    {
        return $query->whereNotNull('nip')->orderByDesc('aktif')->orderBy('id')->get()->unique('nip')->sortBy('nama')->values();
    }

    // Jumlah orang (bukan akun): akun dengan NIP sama dihitung 1, akun tanpa NIP tidak dihitung (sama seperti perOrang)
    public static function jumlahOrang($query): int
    {
        return $query->whereNotNull('nip')->where('nip', '!=', '')->distinct()->count('nip');
    }

    public function scopeAktif($q)
    {
        return $q->where('aktif', true);
    }

    const STATUS = ['Tetap', 'Kontrak', 'Honorer'];

    public function atasan()
    {
        return $this->belongsTo(Staff::class, 'atasan_id');
    }

    public function bawahan()
    {
        return $this->hasMany(Staff::class, 'atasan_id');
    }

    public function dosen()
    {
        return $this->hasOne(Dosen::class, 'staff_id');
    }
}
