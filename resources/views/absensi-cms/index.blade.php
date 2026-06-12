@php
    $payload = $result['cuti'] ?? $latest['cuti'] ?? null;
    $meta = $latest['meta'] ?? null;
    $tables = is_array($payload['tables'] ?? null) ? $payload['tables'] : [];
    $firstTable = $tables[0] ?? null;
    $rows = is_array($firstTable['rows'] ?? null) ? $firstTable['rows'] : [];
    $headers = is_array($firstTable['headers'] ?? null) ? $firstTable['headers'] : [];
    $headers = $headers === [] && isset($rows[0]) && is_array($rows[0]) ? array_keys($rows[0]) : $headers;
    $totalRows = collect($tables)->sum(fn ($table) => (int) ($table['row_count'] ?? 0));
    $lastStatus = $result['success'] ?? null;
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TPP Insight CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-zinc-100 text-zinc-900">
    <div class="flex min-h-screen">
        <aside class="hidden w-72 shrink-0 border-r border-zinc-200 bg-zinc-950 text-white lg:block">
            <div class="px-6 py-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400 text-zinc-950">
                        <i data-lucide="scan-line" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-wide">TPP Insight</div>
                        <div class="text-xs text-zinc-400">Absensi CMS</div>
                    </div>
                </div>
            </div>

            <nav class="space-y-1 px-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
                    <i data-lucide="layout-dashboard" class="h-4 w-4"></i>
                    Dashboard
                </a>
                <a href="{{ route('cms.laporan-cuti.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>
                    Laporan Cuti
                </a>
                <a href="{{ route('cms.laporan-absensi-harian.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="calendar-check" class="h-4 w-4"></i>
                    Laporan Absensi PNS
                </a>
                <a href="{{ route('cms.laporan-pppk.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="id-card" class="h-4 w-4"></i>
                    Laporan Absensi PPPK
                </a>
                <a href="{{ route('cms.laporan-balai-kota.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="building-2" class="h-4 w-4"></i>
                    Laporan Balai Kota
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    ASN
                </a>
                <a href="{{ route('absensi-scraper.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="braces" class="h-4 w-4"></i>
                    API Scraper
                </a>
                <a href="{{ route('tpp-scraper.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="network" class="h-4 w-4"></i>
                    TPP Tools
                </a>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">Data Cuti Absensi</h1>
                        <p class="mt-1 text-sm text-zinc-500">Ambil, simpan, dan tinjau data cuti dari portal Absensi Banjarmasin.</p>
                    </div>
                    <div class="hidden items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600 sm:flex">
                        <i data-lucide="database" class="h-4 w-4 text-emerald-600"></i>
                        JSON storage
                    </div>
                </div>
            </header>

            <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Status Fetch</p>
                            <i data-lucide="{{ $lastStatus === false ? 'circle-alert' : 'circle-check' }}" class="h-5 w-5 {{ $lastStatus === false ? 'text-rose-500' : 'text-emerald-600' }}"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">
                            @if ($lastStatus === true)
                                Berhasil
                            @elseif ($lastStatus === false)
                                Gagal
                            @else
                                Siap
                            @endif
                        </p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Total Baris</p>
                            <i data-lucide="rows-3" class="h-5 w-5 text-cyan-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($totalRows) }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Terakhir Simpan</p>
                            <i data-lucide="clock-3" class="h-5 w-5 text-amber-600"></i>
                        </div>
                        <p class="mt-3 break-words text-sm font-semibold text-zinc-800">{{ $meta['fetched_at'] ?? 'Belum ada data' }}</p>
                        @if (isset($meta['skpd_id']))
                            <p class="mt-1 text-xs text-zinc-500">SKPD ID {{ $meta['skpd_id'] }} - {{ $meta['path'] ?? '/admin/cuti' }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                    <form method="POST" action="{{ route('cms.absensi-cuti.fetch') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                <i data-lucide="key-round" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil Data Cuti</h2>
                                <p class="text-sm text-zinc-500">Login memakai kredensial dari konfigurasi server.</p>
                            </div>
                        </div>

                        @if ($errors->any())
                            <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        @if (is_array($result) && ($result['success'] ?? false) === false)
                            <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                <div>{{ $result['message'] ?? 'Fetch gagal. Periksa kredensial atau akses halaman cuti.' }}</div>
                                @php
                                    $loginInfo = $result['login'] ?? [];
                                    $skpdInfo = $loginInfo['skpd_login'] ?? [];
                                    $cutiInfo = $loginInfo['cuti_page'] ?? ($result['page'] ?? []);
                                @endphp
                                @if (is_array($loginInfo) || is_array($skpdInfo) || is_array($cutiInfo))
                                    <div class="mt-2 space-y-1 text-xs text-rose-600">
                                        @if (isset($loginInfo['login']['status_code']))
                                            <div>Login: HTTP {{ $loginInfo['login']['status_code'] }}</div>
                                        @endif
                                        @if (isset($skpdInfo['path']))
                                            <div>SKPD: {{ $skpdInfo['path'] }} - HTTP {{ $skpdInfo['status_code'] ?? '-' }} ({{ $skpdInfo['action']['source'] ?? 'unknown' }})</div>
                                        @endif
                                        @if (isset($cutiInfo['path']))
                                            <div>Cuti: {{ $cutiInfo['path'] }} - HTTP {{ $cutiInfo['status_code'] ?? '-' }}</div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        <label class="mt-5 block text-sm font-medium text-zinc-700" for="skpd_id">SKPD ID</label>
                        <input id="skpd_id" name="skpd_id" type="number" min="1" value="{{ old('skpd_id', 1) }}" required
                            class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">

                        <label class="mt-4 flex items-center gap-3 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-700">
                            <input type="checkbox" name="redact" value="1" checked class="h-4 w-4 rounded border-zinc-300 text-cyan-700 focus:ring-cyan-600">
                            Samarkan kolom sensitif
                        </label>

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Ambil Data
                        </button>
                    </form>

                    <div class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-base font-semibold">Preview Data</h2>
                                <p class="text-sm text-zinc-500">{{ $payload['title'] ?? 'Hasil halaman cuti akan tampil di sini.' }}</p>
                            </div>
                            <a href="{{ route('absensi-scraper.index') }}" class="inline-flex items-center gap-2 rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                                <i data-lucide="terminal" class="h-4 w-4"></i>
                                Endpoint
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            @if ($rows !== [])
                                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                                    <thead class="bg-zinc-50">
                                        <tr>
                                            @foreach ($headers as $header)
                                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">{{ $header }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 bg-white">
                                        @foreach (array_slice($rows, 0, 25) as $row)
                                            <tr class="hover:bg-cyan-50/50">
                                                @foreach ($headers as $header)
                                                    <td class="max-w-xs whitespace-nowrap px-4 py-3 text-zinc-700">{{ $row[$header] ?? '' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="flex min-h-80 items-center justify-center px-6 py-12 text-center">
                                    <div>
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500">
                                            <i data-lucide="table-2" class="h-6 w-6"></i>
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-zinc-800">Belum ada tabel cuti</h3>
                                        <p class="mt-1 max-w-md text-sm text-zinc-500">Jalankan fetch dari panel kiri. Setelah berhasil, data tersimpan sebagai JSON dan preview akan muncul di sini.</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($rows !== [])
                            <div class="border-t border-zinc-200 px-5 py-3 text-xs text-zinc-500">
                                Menampilkan maksimal 25 baris pertama dari tabel pertama. File lengkap ada di <span class="font-mono">storage/scraping/absensi_cuti.json</span>.
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
