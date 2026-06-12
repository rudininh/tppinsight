<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pegawai - TPP Insight CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
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
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
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
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
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
                <div class="mx-auto flex max-w-[1800px] items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">Pegawai</h1>
                        <p class="mt-1 text-sm text-zinc-500">Data pegawai dari portal Absensi Banjarmasin.</p>
                    </div>
                    <a href="{{ route('dashboard') }}" class="hidden items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600 hover:bg-white sm:flex">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Dashboard
                    </a>
                </div>
            </header>

            <section class="mx-auto max-w-[1800px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Total Pegawai</p>
                            <i data-lucide="users" class="h-5 w-5 text-cyan-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($totalRows) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Ada Device ID</p>
                            <i data-lucide="smartphone" class="h-5 w-5 text-emerald-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($withDeviceRows) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Terakhir Ambil</p>
                            <i data-lucide="clock-3" class="h-5 w-5 text-amber-600"></i>
                        </div>
                        <p class="mt-3 break-words text-sm font-semibold text-zinc-800">{{ $lastFetchedAt ?? 'Belum ada data' }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)] 2xl:grid-cols-[340px_minmax(0,1fr)]">
                    <form method="POST" action="{{ route('cms.pegawai.fetch') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                <i data-lucide="database-zap" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil Pegawai</h2>
                                <p class="text-sm text-zinc-500">Mengambil seluruh halaman pegawai dari portal.</p>
                            </div>
                        </div>

                        @if (is_array($result))
                            <div class="mt-4 rounded-md border {{ ($result['success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-3 py-2 text-sm">
                                <div class="font-medium">
                                    @if ($result['success'] ?? false)
                                        Tersimpan {{ number_format($result['summary']['stored_rows'] ?? 0) }} baris.
                                    @else
                                        {{ $result['message'] ?? 'Fetch pegawai gagal.' }}
                                    @endif
                                </div>
                                @if ($result['success'] ?? false)
                                    <div class="mt-1 text-xs">
                                        Dibaca {{ number_format($result['summary']['page_count'] ?? 0) }} halaman, parsed {{ number_format($result['summary']['parsed_rows'] ?? 0) }} baris.
                                    </div>
                                @endif
                            </div>
                        @endif

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Ambil & Simpan
                        </button>
                    </form>

                    <div class="min-w-0 rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="border-b border-zinc-200 px-5 py-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-base font-semibold">Data Pegawai</h2>
                                    <p class="text-sm text-zinc-500">Menampilkan 100 data per halaman.</p>
                                </div>
                            </div>
                            <form method="GET" action="{{ route('cms.pegawai.index') }}" class="mt-4 grid gap-2 md:grid-cols-[minmax(0,1fr)_280px_auto]">
                                <input name="search" type="search" value="{{ request('search') }}" placeholder="Cari nama, NIP, jabatan, device"
                                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                <select name="skpd" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                    <option value="">Semua SKPD</option>
                                    @foreach ($skpdOptions as $skpd)
                                        <option value="{{ $skpd }}" @selected((string) request('skpd') === (string) $skpd)>{{ $skpd }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                    Filter
                                </button>
                            </form>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-[1440px] table-fixed divide-y divide-zinc-200 text-sm xl:min-w-full">
                                <thead class="bg-zinc-50">
                                    <tr>
                                        <th class="w-48 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">NIP</th>
                                        <th class="w-72 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Nama</th>
                                        <th class="w-56 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Pangkat</th>
                                        <th class="w-72 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">SKPD</th>
                                        <th class="w-72 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Unit Kerja</th>
                                        <th class="w-72 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Jabatan</th>
                                        <th class="w-48 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Device</th>
                                        <th class="w-32 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">History</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 bg-white">
                                    @forelse ($pegawai as $row)
                                        <tr class="hover:bg-cyan-50/50">
                                            <td class="px-4 py-3 font-medium text-zinc-700">{{ $row->nip ?: '-' }}</td>
                                            <td class="px-4 py-3 text-zinc-700">{{ $row->nama ?: '-' }}</td>
                                            <td class="px-4 py-3 text-zinc-700">{{ $row->pangkat_golongan ?: '-' }}</td>
                                            <td class="px-4 py-3 text-zinc-700">{{ $row->skpd ?: '-' }}</td>
                                            <td class="px-4 py-3 text-zinc-700">{{ $row->unit_kerja ?: '-' }}</td>
                                            <td class="px-4 py-3 text-zinc-700">{{ $row->jabatan ?: '-' }}</td>
                                            <td class="px-4 py-3 text-zinc-700">{{ $row->device_id ?: '-' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                @if ($row->history_url)
                                                    <a href="{{ $row->history_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">
                                                        <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                                                        History
                                                    </a>
                                                @else
                                                    <span class="text-zinc-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center text-sm text-zinc-500">
                                                Belum ada data pegawai di database.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-zinc-200 px-5 py-3">
                            {{ $pegawai->links() }}
                        </div>
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
