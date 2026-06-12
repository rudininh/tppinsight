<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiPppkReport extends Model
{
    protected $fillable = [
        'pppk_id',
        'skpd_id',
        'kode_skpd',
        'nama_skpd',
        'unit_kerja',
        'nip',
        'nama_pegawai',
        'jabatan',
        'tanggal',
        'hari',
        'jam_masuk',
        'jam_pulang',
        'keterangan',
        'telat',
        'lebih_awal',
        'row_data',
        'row_hash',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'row_data' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
