<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Cuti - TPP Insight CMS</title>
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
                <a href="{{ route('cms.laporan-cuti.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
                    <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>
                    Laporan Cuti
                </a>
                <a href="{{ route('cms.pegawai.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-zinc-300 hover:bg-white/10 hover:text-white">
                    <i data-lucide="users" class="h-4 w-4"></i>
                    Pegawai
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
                        <h1 class="text-xl font-semibold tracking-tight">Laporan Cuti</h1>
                        <p class="mt-1 text-sm text-zinc-500">Data cuti semua SKPD tersimpan di database dan bisa ditinjau dari satu halaman.</p>
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
                            <p class="text-sm font-medium text-zinc-500">Total Data</p>
                            <i data-lucide="rows-3" class="h-5 w-5 text-cyan-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($totalRows) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Ada Upload</p>
                            <i data-lucide="paperclip" class="h-5 w-5 text-emerald-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($withUploadRows) }}</p>
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
                    <form method="POST" action="{{ route('cms.laporan-cuti.fetch-all') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                <i data-lucide="database-zap" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil Semua SKPD</h2>
                                <p class="text-sm text-zinc-500">Login otomatis dari konfigurasi server.</p>
                            </div>
                        </div>

                        @if ($errors->any())
                            <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        @if (is_array($result))
                            <div class="mt-4 rounded-md border {{ ($result['success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-3 py-2 text-sm">
                                <div class="font-medium">
                                    Tersimpan {{ number_format($result['summary']['stored_rows'] ?? 0) }} baris.
                                </div>
                                <div class="mt-1 text-xs">
                                    Berhasil {{ $result['summary']['success_count'] ?? 0 }} SKPD, gagal {{ $result['summary']['failed_count'] ?? 0 }} SKPD.
                                </div>
                            </div>
                        @endif

                        <div class="mt-5 grid grid-cols-1 gap-3 2xl:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="date_start">Tanggal Awal</label>
                                <input id="date_start" name="date_start" type="date" value="{{ old('date_start', $dateStart) }}" required
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="date_end">Tanggal Akhir</label>
                                <input id="date_end" name="date_end" type="date" value="{{ old('date_end', $dateEnd) }}" required
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="start_skpd_id">SKPD Dari</label>
                                <input id="start_skpd_id" name="start_skpd_id" type="number" min="1" value="{{ old('start_skpd_id', 1) }}" required
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="end_skpd_id">SKPD Sampai</label>
                                <input id="end_skpd_id" name="end_skpd_id" type="number" min="1" value="{{ old('end_skpd_id', 35) }}" required
                                    class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                        </div>

                        <label class="mt-4 flex items-center gap-3 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-700">
                            <input type="checkbox" name="redact" value="1" class="h-4 w-4 rounded border-zinc-300 text-cyan-700 focus:ring-cyan-600">
                            Samarkan kolom sensitif sebelum simpan
                        </label>

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Ambil & Simpan
                        </button>
                    </form>

                    <div class="min-w-0 rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="border-b border-zinc-200 px-5 py-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-base font-semibold">Data Tersimpan</h2>
                                    <p class="text-sm text-zinc-500">Menampilkan 100 data per halaman. Filter data lalu export sesuai kebutuhan.</p>
                                </div>
                            </div>
                            <form method="GET" action="{{ route('cms.laporan-cuti.index') }}" class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-6">
                                <input name="search" type="search" value="{{ request('search') }}" placeholder="Cari laporan"
                                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100 xl:col-span-2">
                                <input name="date_start" type="date" value="{{ request('date_start', $dateStart) }}"
                                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                <input name="date_end" type="date" value="{{ request('date_end', $dateEnd) }}"
                                    class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                <select name="skpd_id" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                    <option value="">Semua SKPD</option>
                                    @foreach ($skpdOptions as $option)
                                        <option value="{{ $option['id'] }}" @selected((string) request('skpd_id') === (string) $option['id'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                <select name="jenis_cuti" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                    <option value="">Semua Jenis</option>
                                    @foreach ($jenisOptions as $jenis)
                                        <option value="{{ $jenis }}" @selected((string) request('jenis_cuti') === (string) $jenis)>{{ $jenis }}</option>
                                    @endforeach
                                </select>
                                <div class="flex gap-2 md:col-span-2 xl:col-span-6 xl:justify-end">
                                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                                        <i data-lucide="search" class="h-4 w-4"></i>
                                        Filter
                                    </button>
                                    <button type="submit" formaction="{{ route('cms.laporan-cuti.export') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                        <i data-lucide="file-down" class="h-4 w-4"></i>
                                        Export Excel
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-[1180px] table-fixed divide-y divide-zinc-200 text-sm xl:min-w-full">
                                <thead class="bg-zinc-50">
                                    <tr>
                                        <th class="w-36 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">SKPD</th>
                                        <th class="w-[360px] whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Pegawai</th>
                                        <th class="w-56 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Cuti</th>
                                        <th class="w-56 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Tanggal</th>
                                        <th class="w-36 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Status</th>
                                        <th class="w-36 whitespace-nowrap px-4 py-3 text-left font-semibold text-zinc-600">Upload</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 bg-white">
                                    @forelse ($reports as $report)
                                        @php
                                            $skpd = $skpdMap[$report->skpd_id] ?? [];
                                            $kodeSkpd = $report->kode_skpd ?: ($skpd['kode'] ?? null);
                                            $namaSkpd = $report->nama_skpd ?: ($skpd['nama'] ?? null);
                                        @endphp
                                        <tr class="hover:bg-cyan-50/50">
                                            <td class="px-4 py-3 text-zinc-700">
                                                <div class="font-medium">{{ $kodeSkpd ?: '-' }}</div>
                                                <div class="text-xs text-zinc-500">{{ $namaSkpd ?: '-' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-zinc-700">
                                                <div class="font-medium">{{ $report->nama_pegawai ?: '-' }}</div>
                                                <div class="break-words text-xs text-zinc-500">{{ $report->nip ?: '' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-zinc-700">{{ $report->jenis_cuti ?: '-' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-zinc-700">
                                                {{ optional($report->tanggal_mulai)->format('Y-m-d') ?: '-' }}
                                                @if ($report->tanggal_selesai)
                                                    <span class="text-zinc-400">s/d</span> {{ $report->tanggal_selesai->format('Y-m-d') }}
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-zinc-700">{{ $report->status ?: '-' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3">
                                                @if ($report->upload_url)
                                                    <a href="{{ $report->upload_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50">
                                                        <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                                                        Lihat Upload
                                                    </a>
                                                @else
                                                    <span class="text-zinc-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center text-sm text-zinc-500">
                                                Belum ada data laporan cuti di database.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-zinc-200 px-5 py-3">
                            {{ $reports->links() }}
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
