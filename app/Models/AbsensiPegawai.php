<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiPegawai extends Model
{
    protected $fillable = [
        'pegawai_id',
        'nip',
        'nama',
        'pangkat_golongan',
        'skpd',
        'jabatan',
        'puskesmas',
        'device_id',
        'history_url',
        'row_data',
        'row_hash',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
