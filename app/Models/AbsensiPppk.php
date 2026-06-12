<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiPppk extends Model
{
    protected $fillable = [
        'pppk_id',
        'skpd_id',
        'kode_skpd',
        'nama_skpd',
        'unit_kerja',
        'nip',
        'nama',
        'jabatan',
        'pangkat',
        'tanggal_lahir',
        'jenis_presensi',
        'status_asn',
        'presensi_url',
        'row_data',
        'row_hash',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'row_data' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
