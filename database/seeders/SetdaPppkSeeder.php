<?php

namespace Database\Seeders;

use App\Models\AbsensiPppk;
use App\Models\AbsensiPppkReport;
use Illuminate\Database\Seeder;

class SetdaPppkSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            AbsensiPppk::query()->updateOrCreate(
                ['nip' => $row['nip']],
                [
                    'skpd_id' => 20,
                    'kode_skpd' => '4.01.01.',
                    'nama_skpd' => 'Sekretariat Daerah',
                    'unit_kerja' => $row['unit_kerja'],
                    'nama' => $row['nama'],
                    'jabatan' => $row['jabatan'],
                    'pangkat' => 'PPPK',
                    'status_asn' => 'PPPK',
                    'row_hash' => hash('sha256', 'setda-pppk-' . $row['nip']),
                    'row_data' => [
                        'source' => 'seed_setda_pppk',
                        'unit_organisasi' => $row['jabatan'],
                        'unit_induk' => $row['unit_kerja'],
                    ],
                    'fetched_at' => now(),
                ]
            );

            AbsensiPppkReport::query()
                ->where('nip', $row['nip'])
                ->update(['unit_kerja' => $row['unit_kerja']]);
        }
    }

    private function rows(): array
    {
        return [
            ['nama' => 'MONIKA KHUMAIRAH', 'nip' => '199305112024212030', 'unit_kerja' => 'Sekretariat Daerah - Bagian Pemerintahan', 'jabatan' => 'SUB BAGIAN ADMINISTRASI PEMERINTAHAN'],
            ['nama' => 'M. NOVAL', 'nip' => '199205312024211006', 'unit_kerja' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa', 'jabatan' => 'BAGIAN PENGADAAN BARANG DAN JASA'],
            ['nama' => 'FEBRIAN BUDIATMA', 'nip' => '199802062024211003', 'unit_kerja' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa', 'jabatan' => 'BAGIAN PENGADAAN BARANG DAN JASA'],
            ['nama' => 'NAILUL FADILAH', 'nip' => '198708192025212001', 'unit_kerja' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa', 'jabatan' => 'BAGIAN PENGADAAN BARANG DAN JASA'],
            ['nama' => 'AGUS SATRIA', 'nip' => '199808292025211003', 'unit_kerja' => 'Sekretariat Daerah - Bagian Umum', 'jabatan' => 'BAGIAN UMUM'],
            ['nama' => 'MUHAMMAD ABDURAHMAN SIDDIQ', 'nip' => '199305252025211010', 'unit_kerja' => 'Sekretariat Daerah - Bagian Pemerintahan', 'jabatan' => 'SUB BAGIAN ADMINISTRASI PEMERINTAHAN'],
            ['nama' => 'LENY HAMISAH', 'nip' => '197801012025212009', 'unit_kerja' => 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat', 'jabatan' => 'SUB BAGIAN BINA MENTAL SPIRITUAL'],
            ['nama' => 'MAULANA KHARISYA\'BANA', 'nip' => '199901212025211003', 'unit_kerja' => 'Sekretariat Daerah - Bagian Umum', 'jabatan' => 'SUB BAGIAN KEUANGAN'],
            ['nama' => 'DEDY RAHMADI', 'nip' => '200008312025211001', 'unit_kerja' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan', 'jabatan' => 'SUB BAGIAN KOMUNIKASI PIMPINAN'],
            ['nama' => 'INDRA PRAZATI KESUMA', 'nip' => '199610302025211002', 'unit_kerja' => 'Sekretariat Daerah - Bagian Organisasi', 'jabatan' => 'SUB BAGIAN PELAYANAN PUBLIK DAN TATA LAKSANA'],
            ['nama' => 'WAHYUDI', 'nip' => '199911112025211003', 'unit_kerja' => 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam', 'jabatan' => 'SUB BAGIAN PEREKONOMIAN'],
            ['nama' => 'YASMIN QAMARANI', 'nip' => '199410232025212002', 'unit_kerja' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan', 'jabatan' => 'SUB BAGIAN PROTOKOL'],
            ['nama' => 'AKHMAD RIZA PERDANA', 'nip' => '198304112025211007', 'unit_kerja' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan', 'jabatan' => 'SUB BAGIAN PROTOKOL'],
            ['nama' => 'MUHAMAD LUKMAN HAWARI', 'nip' => '199502282025211004', 'unit_kerja' => 'Sekretariat Daerah - Bagian Umum', 'jabatan' => 'SUB BAGIAN RUMAH TANGGA DAN PERLENGKAPAN'],
            ['nama' => 'MUHAMMAD REFKA ISNADI', 'nip' => '199610282025211004', 'unit_kerja' => 'Sekretariat Daerah - Bagian Umum', 'jabatan' => 'SUB BAGIAN RUMAH TANGGA DAN PERLENGKAPAN'],
            ['nama' => 'SHELLY SEPRIANI', 'nip' => '198909292025212008', 'unit_kerja' => 'Sekretariat Daerah - Bagian Umum', 'jabatan' => 'SUB BAGIAN RUMAH TANGGA DAN PERLENGKAPAN'],
        ];
    }
}
