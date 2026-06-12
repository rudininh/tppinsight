<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Absensi PPPK - TPP Insight CMS</title>
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
                <a href="{{ route('cms.laporan-pppk.index') }}" class="flex items-center gap-3 rounded-md bg-white/10 px-3 py-2 text-sm font-medium">
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
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-[1800px] items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">Laporan Absensi PPPK dan Data PPPK</h1>
                        <p class="mt-1 text-sm text-zinc-500">Data dari menu PPPK portal Absensi.</p>
                    </div>
                    <a href="{{ route('dashboard') }}" class="hidden items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600 hover:bg-white sm:flex">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Dashboard
                    </a>
                </div>
            </header>

            <section class="mx-auto max-w-[1800px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Data PPPK</p>
                            <i data-lucide="id-card" class="h-5 w-5 text-cyan-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($totalPppk) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Data Absensi</p>
                            <i data-lucide="rows-3" class="h-5 w-5 text-indigo-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($totalRows) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Ada Masuk</p>
                            <i data-lucide="badge-check" class="h-5 w-5 text-emerald-600"></i>
                        </div>
                        <p class="mt-3 text-2xl font-semibold">{{ number_format($presentRows) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-500">Terakhir Ambil</p>
                            <i data-lucide="clock-3" class="h-5 w-5 text-amber-600"></i>
                        </div>
                        <p class="mt-3 break-words text-sm font-semibold text-zinc-800">{{ $lastFetchedAt ?? 'Belum ada data' }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 xl:grid-cols-[340px_minmax(0,1fr)]">
                    <form method="POST" action="{{ route('cms.laporan-pppk.fetch') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        @csrf
                        <div class="flex items-center gap-3 border-b border-zinc-200 pb-4">
                            <div class="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-white">
                                <i data-lucide="database-zap" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Ambil PPPK</h2>
                                <p class="text-sm text-zinc-500">Mengambil data PPPK dan absensi bulanan.</p>
                            </div>
                        </div>

                        @if (isset($errors) && $errors->any())
                            <div class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        @if (is_array($result))
                            <div class="mt-4 rounded-md border {{ ($result['success'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }} px-3 py-2 text-sm">
                                <div class="font-medium">Tersimpan {{ number_format($result['summary']['stored_report_rows'] ?? 0) }} baris absensi.</div>
                                <div class="mt-1 text-xs">Data PPPK {{ number_format($result['summary']['stored_pppk_rows'] ?? 0) }} baris. Berhasil {{ $result['summary']['success_count'] ?? 0 }} SKPD, gagal {{ $result['summary']['failed_count'] ?? 0 }} SKPD.</div>
                            </div>
                        @endif

                        <div class="mt-5 grid grid-cols-1 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="pppk_date_start">Tanggal Awal</label>
                                <input id="pppk_date_start" name="date_start" type="date" value="{{ old('date_start', $dateStart) }}" required class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="pppk_date_end">Tanggal Akhir</label>
                                <input id="pppk_date_end" name="date_end" type="date" value="{{ old('date_end', $dateEnd) }}" required class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="pppk_start_skpd_id">SKPD Dari</label>
                                <input id="pppk_start_skpd_id" name="start_skpd_id" type="number" min="1" value="{{ old('start_skpd_id', 24) }}" required class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700" for="pppk_end_skpd_id">SKPD Sampai</label>
                                <input id="pppk_end_skpd_id" name="end_skpd_id" type="number" min="1" value="{{ old('end_skpd_id', 24) }}" required class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                            </div>
                        </div>

                        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800">
                            <i data-lucide="download-cloud" class="h-4 w-4"></i>
                            Ambil & Simpan
                        </button>
                    </form>

                    <div class="min-w-0 space-y-4">
                        <div class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                            <div class="border-b border-zinc-200 px-5 py-4">
                                <h2 class="text-base font-semibold">Laporan Absensi PPPK</h2>
                                <form method="GET" action="{{ route('cms.laporan-pppk.index') }}" class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-6">
                                    <input name="search" type="search" value="{{ request('search') }}" placeholder="Cari nama, NIP, jabatan" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100 xl:col-span-2">
                                    <input name="date_start" type="date" value="{{ request('date_start', $dateStart) }}" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                    <input name="date_end" type="date" value="{{ request('date_end', $dateEnd) }}" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                    <select name="skpd_id" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100">
                                        <option value="">Semua SKPD</option>
                                        @foreach ($skpdOptions as $option)
                                            <option value="{{ $option['id'] }}" @selected((string) request('skpd_id') === (string) $option['id'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                                        <i data-lucide="search" class="h-4 w-4"></i>
                                        Filter
                                    </button>
                                </form>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-[1360px] table-fixed divide-y divide-zinc-200 text-sm xl:min-w-full">
                                    <thead class="bg-zinc-50">
                                        <tr>
                                            <th class="w-32 px-4 py-3 text-left font-semibold text-zinc-600">Tanggal</th>
                                            <th class="w-60 px-4 py-3 text-left font-semibold text-zinc-600">Pegawai</th>
                                            <th class="w-72 px-4 py-3 text-left font-semibold text-zinc-600">Unit Kerja</th>
                                            <th class="w-64 px-4 py-3 text-left font-semibold text-zinc-600">Jabatan</th>
                                            <th class="w-28 px-4 py-3 text-left font-semibold text-zinc-600">Masuk</th>
                                            <th class="w-28 px-4 py-3 text-left font-semibold text-zinc-600">Pulang</th>
                                            <th class="w-56 px-4 py-3 text-left font-semibold text-zinc-600">Keterangan</th>
                                            <th class="w-28 px-4 py-3 text-left font-semibold text-zinc-600">Telat</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 bg-white">
                                        @forelse ($reports as $report)
                                            <tr class="hover:bg-cyan-50/50">
                                                <td class="px-4 py-3 text-zinc-700"><div class="font-medium">{{ optional($report->tanggal)->format('Y-m-d') ?: '-' }}</div><div class="text-xs text-zinc-500">{{ $report->hari ?: '' }}</div></td>
                                                <td class="px-4 py-3 text-zinc-700"><div class="font-medium">{{ $report->nama_pegawai ?: '-' }}</div><div class="text-xs text-zinc-500">{{ $report->nip ?: '' }}</div></td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $report->unit_kerja ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $report->jabatan ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $report->jam_masuk ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $report->jam_pulang ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $report->keterangan ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $report->telat ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="px-6 py-12 text-center text-sm text-zinc-500">Belum ada laporan absensi PPPK.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-zinc-200 px-5 py-3">{{ $reports->links() }}</div>
                        </div>

                        <div class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                            <div class="border-b border-zinc-200 px-5 py-4">
                                <h2 class="text-base font-semibold">Data PPPK</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-[1260px] table-fixed divide-y divide-zinc-200 text-sm xl:min-w-full">
                                    <thead class="bg-zinc-50">
                                        <tr>
                                            <th class="w-48 px-4 py-3 text-left font-semibold text-zinc-600">NIP</th>
                                            <th class="w-64 px-4 py-3 text-left font-semibold text-zinc-600">Nama</th>
                                            <th class="w-72 px-4 py-3 text-left font-semibold text-zinc-600">Unit Kerja</th>
                                            <th class="w-72 px-4 py-3 text-left font-semibold text-zinc-600">Jabatan</th>
                                            <th class="w-40 px-4 py-3 text-left font-semibold text-zinc-600">Jenis Presensi</th>
                                            <th class="w-40 px-4 py-3 text-left font-semibold text-zinc-600">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 bg-white">
                                        @forelse ($pppk as $row)
                                            <tr class="hover:bg-cyan-50/50">
                                                <td class="px-4 py-3 font-medium text-zinc-700">{{ $row->nip ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $row->nama ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $row->unit_kerja ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $row->jabatan ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $row->jenis_presensi ?: '-' }}</td>
                                                <td class="px-4 py-3 text-zinc-700">{{ $row->status_asn ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-zinc-500">Belum ada data PPPK.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-zinc-200 px-5 py-3">{{ $pppk->links() }}</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
