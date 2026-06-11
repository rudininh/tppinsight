<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'absensi' => [
        'base_url' => env('ABSENSI_BASE_URL', 'https://absensi.banjarmasinkota.go.id'),
        'cookie_session_key' => env('ABSENSI_COOKIE_SESSION_KEY', 'absensi_scraper.cookies'),
        'username' => env('ABSENSI_USERNAME'),
        'password' => env('ABSENSI_PASSWORD'),
        'skpd' => [
            1 => ['kode' => '1.01.01.', 'nama' => 'Dinas Pendidikan'],
            2 => ['kode' => '1.03.01.', 'nama' => 'Dinas Pekerjaan Umum dan Penataan Ruang.'],
            3 => ['kode' => '1.04.01.', 'nama' => 'Dinas Perumahan dan Kawasan Permukiman'],
            4 => ['kode' => '1.06.01.', 'nama' => 'Satuan Polisi Pamong Praja dan Pemadam Kebakaran'],
            5 => ['kode' => '1.06.02.', 'nama' => 'Badan Kesatuan Bangsa dan Politik'],
            6 => ['kode' => '1.07.01.', 'nama' => 'Dinas Sosial'],
            7 => ['kode' => '2.02.01.', 'nama' => 'Dinas Pemberdayaan Perempuan dan Perlindungan Anak'],
            8 => ['kode' => '2.03.01.', 'nama' => 'Dinas Ketahanan Pangan, Pertanian dan Perikanan'],
            9 => ['kode' => '2.05.01.', 'nama' => 'Dinas Lingkungan Hidup'],
            10 => ['kode' => '2.06.01.', 'nama' => 'Dinas Kependudukan dan Pencatatan Sipil'],
            11 => ['kode' => '2.08.01.', 'nama' => 'Dinas Pengendalian Penduduk, keluarga Berencana, dan Pemberdayaan Masyarakat'],
            12 => ['kode' => '2.09.01.', 'nama' => 'Dinas Perhubungan'],
            13 => ['kode' => '2.10.01.', 'nama' => 'Dinas Komunikasi, Informatika dan Statistik'],
            14 => ['kode' => '2.11.01.', 'nama' => 'Dinas Koperasi, Usaha Mikro dan Tenaga Kerja'],
            15 => ['kode' => '2.12.01.', 'nama' => 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu'],
            16 => ['kode' => '2.13.01.', 'nama' => 'Dinas Kepemudaan dan Olahraga'],
            17 => ['kode' => '2.16.01.', 'nama' => 'Dinas Kebudayaan dan Pariwisata'],
            18 => ['kode' => '2.17.01.', 'nama' => 'Dinas Perpustakaan dan Arsip'],
            19 => ['kode' => '3.04.01.', 'nama' => 'Dinas Perdagangan dan Perindustrian'],
            20 => ['kode' => '4.01.03.', 'nama' => 'Sekretariat Daerah'],
            21 => ['kode' => '4.01.04.', 'nama' => 'Sekretariat DPRD'],
            22 => ['kode' => '4.01.05.', 'nama' => 'Badan Keuangan Daerah'],
            23 => ['kode' => '4.01.06.', 'nama' => 'Inspektorat'],
            24 => ['kode' => '4.01.07.', 'nama' => 'Badan Kepegawaian Daerah, Pendidikan dan Pelatihan'],
            25 => ['kode' => '4.01.08.', 'nama' => 'Badan Penanggulangan Bencana Daerah'],
            26 => ['kode' => '4.01.09.', 'nama' => 'Kecamatan Banjarmasin Timur'],
            27 => ['kode' => '4.01.10.', 'nama' => 'Kecamatan Banjarmasin Utara'],
            28 => ['kode' => '4.01.11.', 'nama' => 'Kecamatan Banjarmasin Tengah'],
            29 => ['kode' => '4.01.12.', 'nama' => 'Kecamatan Banjarmasin Barat'],
            30 => ['kode' => '4.01.13.', 'nama' => 'Kecamatan Banjarmasin Selatan'],
            31 => ['kode' => '4.02.01.', 'nama' => 'Badan Perencanaan, Penelitian dan Pengembangan Daerah'],
            32 => ['kode' => '1.02.01.', 'nama' => 'Dinas Kesehatan'],
            33 => ['kode' => '11111', 'nama' => 'Dinas Baru'],
            34 => ['kode' => '1.05.01.', 'nama' => 'Dinas Pemadam Kebakaran dan Penyelamatan Kota Banjarmasin'],
            35 => ['kode' => '2.22.3.', 'nama' => 'Dinas Kebudayaan, Kepemudaan, Olahraga dan Pariwisata Kota Banjarmasin'],
        ],
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
