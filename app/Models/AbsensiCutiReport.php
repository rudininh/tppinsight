<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiCutiReport extends Model
{
    protected $fillable = [
        'skpd_id',
        'kode_skpd',
        'nama_skpd',
        'tanggal_mulai',
        'tanggal_selesai',
        'jenis_cuti',
        'status',
        'nama_pegawai',
        'nip',
        'upload_url',
        'upload_label',
        'row_data',
        'row_hash',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
            'fetched_at' => 'datetime',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }
}
