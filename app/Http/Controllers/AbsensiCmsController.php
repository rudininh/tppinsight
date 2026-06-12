<?php

namespace App\Http\Controllers;

use App\Models\AbsensiCutiReport;
use App\Models\AbsensiDailyReport;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiPppk;
use App\Models\AbsensiPppkReport;
use App\Services\AbsensiScraperService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class AbsensiCmsController extends Controller
{
    public function __construct(private readonly AbsensiScraperService $scraper)
    {
    }

    public function index(): View
    {
        return view('absensi-cms.index', [
            'result' => null,
            'latest' => $this->latestSavedCuti(),
        ]);
    }

    public function fetchCuti(Request $request): View
    {
        $data = $request->validate([
            'skpd_id' => ['nullable', 'integer', 'min:1'],
            'redact' => ['nullable', 'boolean'],
        ]);
        $credentials = $this->absensiCredentials();

        $result = $this->scraper->scrapeCuti(
            $credentials['username'],
            $credentials['password'],
            $request->boolean('redact', true),
            (int) ($data['skpd_id'] ?? 1)
        );

        return view('absensi-cms.index', [
            'result' => $result,
            'latest' => $this->latestSavedCuti(),
        ]);
    }

    public function laporanCuti(Request $request): View
    {
        $query = $this->laporanCutiQuery($request)->latest('fetched_at')->latest('id');

        return view('absensi-cms.laporan-cuti', [
            'reports' => $query->paginate(100)->withQueryString(),
            'totalRows' => AbsensiCutiReport::query()->count(),
            'withUploadRows' => AbsensiCutiReport::query()->whereNotNull('upload_url')->count(),
            'lastFetchedAt' => AbsensiCutiReport::query()->max('fetched_at'),
            'skpdOptions' => $this->skpdOptions(),
            'skpdMap' => $this->skpdMap(),
            'jenisOptions' => $this->jenisCutiOptions(),
            'result' => null,
            'dateStart' => $request->input('date_start', '2026-01-01'),
            'dateEnd' => $request->input('date_end', now()->toDateString()),
        ]);
    }

    public function fetchAllCuti(Request $request): View
    {
        $data = $request->validate([
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'start_skpd_id' => ['nullable', 'integer', 'min:1'],
            'end_skpd_id' => ['nullable', 'integer', 'min:1', 'gte:start_skpd_id'],
            'redact' => ['nullable', 'boolean'],
        ]);
        $credentials = $this->absensiCredentials();

        $result = $this->scraper->scrapeAllSkpdCuti(
            $credentials['username'],
            $credentials['password'],
            $request->boolean('redact', false),
            $data['date_start'],
            $data['date_end'],
            (int) ($data['start_skpd_id'] ?? 1),
            (int) ($data['end_skpd_id'] ?? 35)
        );

        $query = AbsensiCutiReport::query()->latest('fetched_at')->latest('id');

        return view('absensi-cms.laporan-cuti', [
            'reports' => $query->paginate(100),
            'totalRows' => AbsensiCutiReport::query()->count(),
            'withUploadRows' => AbsensiCutiReport::query()->whereNotNull('upload_url')->count(),
            'lastFetchedAt' => AbsensiCutiReport::query()->max('fetched_at'),
            'skpdOptions' => $this->skpdOptions(),
            'skpdMap' => $this->skpdMap(),
            'jenisOptions' => $this->jenisCutiOptions(),
            'result' => $result,
            'dateStart' => $data['date_start'],
            'dateEnd' => $data['date_end'],
        ]);
    }

    public function exportLaporanCuti(Request $request): Response
    {
        $reports = $this->laporanCutiQuery($request)
            ->orderBy('skpd_id')
            ->orderBy('tanggal_mulai')
            ->orderBy('nama_pegawai')
            ->get();

        $filenameParts = array_filter([
            'laporan-cuti',
            $request->filled('date_start') ? $request->input('date_start') : null,
            $request->filled('date_end') ? $request->input('date_end') : null,
            $request->filled('skpd_id') ? 'skpd-' . $request->input('skpd_id') : null,
            $request->filled('jenis_cuti') ? Str::slug((string) $request->input('jenis_cuti')) : null,
        ]);
        $filename = implode('-', $filenameParts) . '.xls';

        return response($this->excelTable($reports), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate, proxy-revalidate',
        ]);
    }

    public function pegawai(Request $request): View
    {
        $query = $this->pegawaiQuery($request)->latest('fetched_at')->latest('id');

        return view('absensi-cms.pegawai', [
            'pegawai' => $query->paginate(100)->withQueryString(),
            'totalRows' => AbsensiPegawai::query()->count(),
            'withDeviceRows' => AbsensiPegawai::query()->whereNotNull('device_id')->count(),
            'lastFetchedAt' => AbsensiPegawai::query()->max('fetched_at'),
            'skpdOptions' => $this->pegawaiSkpdOptions(),
            'result' => null,
        ]);
    }

    public function fetchPegawai(): View
    {
        $credentials = $this->absensiCredentials();
        if ($credentials === null) {
            $query = AbsensiPegawai::query()->latest('fetched_at')->latest('id');

            return view('absensi-cms.pegawai', [
                'pegawai' => $query->paginate(100),
                'totalRows' => AbsensiPegawai::query()->count(),
                'withDeviceRows' => AbsensiPegawai::query()->whereNotNull('device_id')->count(),
                'lastFetchedAt' => AbsensiPegawai::query()->max('fetched_at'),
                'skpdOptions' => $this->pegawaiSkpdOptions(),
                'result' => [
                    'success' => false,
                    'message' => 'ABSENSI_USERNAME dan ABSENSI_PASSWORD belum diatur di .env.',
                ],
            ]);
        }

        $result = $this->scraper->scrapePegawai(
            $credentials['username'],
            $credentials['password']
        );

        $query = AbsensiPegawai::query()->latest('fetched_at')->latest('id');

        return view('absensi-cms.pegawai', [
            'pegawai' => $query->paginate(100),
            'totalRows' => AbsensiPegawai::query()->count(),
            'withDeviceRows' => AbsensiPegawai::query()->whereNotNull('device_id')->count(),
            'lastFetchedAt' => AbsensiPegawai::query()->max('fetched_at'),
            'skpdOptions' => $this->pegawaiSkpdOptions(),
            'result' => $result,
        ]);
    }

    public function laporanAbsensiHarian(Request $request): View
    {
        $query = $this->dailyReportQuery($request)->latest('tanggal')->latest('id');

        return view('absensi-cms.laporan-absensi-harian', [
            'reports' => $query->paginate(100)->withQueryString(),
            'totalRows' => AbsensiDailyReport::query()->count(),
            'apelRows' => AbsensiDailyReport::query()->whereNotNull('apel')->where('apel', '<>', '-')->count(),
            'lastFetchedAt' => AbsensiDailyReport::query()->max('fetched_at'),
            'skpdOptions' => $this->skpdOptions(),
            'skpdMap' => $this->skpdMap(),
            'result' => null,
            'dateStart' => $request->input('date_start', now()->toDateString()),
            'dateEnd' => $request->input('date_end', now()->toDateString()),
        ]);
    }

    public function fetchLaporanAbsensiHarian(Request $request): View
    {
        $data = $request->validate([
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'start_skpd_id' => ['nullable', 'integer', 'min:1'],
            'end_skpd_id' => ['nullable', 'integer', 'min:1', 'gte:start_skpd_id'],
        ]);
        $credentials = $this->absensiCredentials();

        $result = $this->scraper->scrapeDailyReports(
            $credentials['username'],
            $credentials['password'],
            $data['date_start'],
            $data['date_end'],
            (int) ($data['start_skpd_id'] ?? 1),
            (int) ($data['end_skpd_id'] ?? 35)
        );

        $query = AbsensiDailyReport::query()->latest('tanggal')->latest('id');

        return view('absensi-cms.laporan-absensi-harian', [
            'reports' => $query->paginate(100),
            'totalRows' => AbsensiDailyReport::query()->count(),
            'apelRows' => AbsensiDailyReport::query()->whereNotNull('apel')->where('apel', '<>', '-')->count(),
            'lastFetchedAt' => AbsensiDailyReport::query()->max('fetched_at'),
            'skpdOptions' => $this->skpdOptions(),
            'skpdMap' => $this->skpdMap(),
            'result' => $result,
            'dateStart' => $data['date_start'],
            'dateEnd' => $data['date_end'],
        ]);
    }

    public function laporanPppk(Request $request): View
    {
        $query = $this->pppkReportQuery($request)->latest('tanggal')->latest('id');

        return view('absensi-cms.laporan-pppk', [
            'reports' => $query->paginate(100)->withQueryString(),
            'pppk' => $this->pppkDataQuery($request)->latest('fetched_at')->latest('id')->paginate(50, ['*'], 'pppk_page')->withQueryString(),
            'totalRows' => AbsensiPppkReport::query()->count(),
            'totalPppk' => AbsensiPppk::query()->count(),
            'presentRows' => AbsensiPppkReport::query()
                ->whereNotNull('jam_masuk')
                ->where('jam_masuk', '<>', '00:00:00')
                ->count(),
            'lastFetchedAt' => AbsensiPppkReport::query()->max('fetched_at') ?? AbsensiPppk::query()->max('fetched_at'),
            'skpdOptions' => $this->skpdOptions(),
            'skpdMap' => $this->skpdMap(),
            'result' => null,
            'dateStart' => $request->input('date_start', now()->toDateString()),
            'dateEnd' => $request->input('date_end', now()->toDateString()),
        ]);
    }

    public function fetchLaporanPppk(Request $request): View
    {
        $data = $request->validate([
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'start_skpd_id' => ['nullable', 'integer', 'min:1'],
            'end_skpd_id' => ['nullable', 'integer', 'min:1', 'gte:start_skpd_id'],
        ]);
        $credentials = $this->absensiCredentials();

        $result = $this->scraper->scrapePppkReports(
            $credentials['username'],
            $credentials['password'],
            $data['date_start'],
            $data['date_end'],
            (int) ($data['start_skpd_id'] ?? 1),
            (int) ($data['end_skpd_id'] ?? 35)
        );

        $query = AbsensiPppkReport::query()->latest('tanggal')->latest('id');

        return view('absensi-cms.laporan-pppk', [
            'reports' => $query->paginate(100),
            'pppk' => AbsensiPppk::query()->latest('fetched_at')->latest('id')->paginate(50, ['*'], 'pppk_page'),
            'totalRows' => AbsensiPppkReport::query()->count(),
            'totalPppk' => AbsensiPppk::query()->count(),
            'presentRows' => AbsensiPppkReport::query()
                ->whereNotNull('jam_masuk')
                ->where('jam_masuk', '<>', '00:00:00')
                ->count(),
            'lastFetchedAt' => AbsensiPppkReport::query()->max('fetched_at') ?? AbsensiPppk::query()->max('fetched_at'),
            'skpdOptions' => $this->skpdOptions(),
            'skpdMap' => $this->skpdMap(),
            'result' => $result,
            'dateStart' => $data['date_start'],
            'dateEnd' => $data['date_end'],
        ]);
    }

    public function laporanBalaiKota(Request $request): View
    {
        $date = (string) $request->input('date', now()->toDateString());
        $rows = $this->balaiKotaReportRows($date);

        return view('absensi-cms.laporan-balai-kota', [
            'date' => $date,
            'rows' => $rows,
            'totals' => $this->balaiKotaTotals($rows),
            'details' => $this->balaiKotaDetailRows($date),
            'result' => null,
        ]);
    }

    public function fetchLaporanBalaiKota(Request $request): View
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);
        $credentials = $this->absensiCredentials();
        $results = [];
        $stored = 0;
        $successCount = 0;
        $failedCount = 0;

        foreach ($this->balaiKotaSkpdIds() as $skpdId) {
            $result = $this->scraper->scrapeDailyReports(
                $credentials['username'],
                $credentials['password'],
                $data['date'],
                $data['date'],
                $skpdId,
                $skpdId
            );

            $summary = $result['summary'] ?? [];
            $stored += (int) ($summary['stored_rows'] ?? 0);
            $successCount += (int) ($summary['success_count'] ?? 0);
            $failedCount += (int) ($summary['failed_count'] ?? 0);
            $results[] = [
                'skpd_id' => $skpdId,
                'success' => (bool) ($result['success'] ?? false),
                'summary' => $summary,
            ];
        }

        $rows = $this->balaiKotaReportRows($data['date']);

        return view('absensi-cms.laporan-balai-kota', [
            'date' => $data['date'],
            'rows' => $rows,
            'totals' => $this->balaiKotaTotals($rows),
            'details' => $this->balaiKotaDetailRows($data['date']),
            'result' => [
                'success' => $failedCount === 0,
                'summary' => [
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'stored_rows' => $stored,
                ],
                'results' => $results,
            ],
        ]);
    }

    private function laporanCutiQuery(Request $request)
    {
        $query = AbsensiCutiReport::query();

        if ($request->filled('skpd_id')) {
            $query->where('skpd_id', (int) $request->input('skpd_id'));
        }

        if ($request->filled('jenis_cuti')) {
            $query->where('jenis_cuti', (string) $request->input('jenis_cuti'));
        }

        if ($request->filled('date_start')) {
            $query->whereDate('tanggal_selesai', '>=', (string) $request->input('date_start'));
        }

        if ($request->filled('date_end')) {
            $query->whereDate('tanggal_mulai', '<=', (string) $request->input('date_end'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nama_pegawai', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('nama_skpd', 'like', '%' . $search . '%')
                    ->orWhere('jenis_cuti', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function pegawaiQuery(Request $request)
    {
        $query = AbsensiPegawai::query();

        if ($request->filled('skpd')) {
            $query->where('skpd', (string) $request->input('skpd'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nama', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('skpd', 'like', '%' . $search . '%')
                    ->orWhere('unit_kerja', 'like', '%' . $search . '%')
                    ->orWhere('jabatan', 'like', '%' . $search . '%')
                    ->orWhere('pangkat_golongan', 'like', '%' . $search . '%')
                    ->orWhere('device_id', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function dailyReportQuery(Request $request)
    {
        $query = AbsensiDailyReport::query();

        if ($request->filled('skpd_id')) {
            $query->where('skpd_id', (int) $request->input('skpd_id'));
        }

        if ($request->filled('date_start')) {
            $query->whereDate('tanggal', '>=', (string) $request->input('date_start'));
        }

        if ($request->filled('date_end')) {
            $query->whereDate('tanggal', '<=', (string) $request->input('date_end'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nama_pegawai', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('nama_skpd', 'like', '%' . $search . '%')
                    ->orWhere('jabatan', 'like', '%' . $search . '%')
                    ->orWhere('pangkat', 'like', '%' . $search . '%')
                    ->orWhere('pagi', 'like', '%' . $search . '%')
                    ->orWhere('pulang', 'like', '%' . $search . '%')
                    ->orWhere('apel', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function pppkReportQuery(Request $request)
    {
        $query = AbsensiPppkReport::query();

        if ($request->filled('skpd_id')) {
            $query->where('skpd_id', (int) $request->input('skpd_id'));
        }

        if ($request->filled('date_start')) {
            $query->whereDate('tanggal', '>=', (string) $request->input('date_start'));
        }

        if ($request->filled('date_end')) {
            $query->whereDate('tanggal', '<=', (string) $request->input('date_end'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nama_pegawai', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('nama_skpd', 'like', '%' . $search . '%')
                    ->orWhere('unit_kerja', 'like', '%' . $search . '%')
                    ->orWhere('jabatan', 'like', '%' . $search . '%')
                    ->orWhere('keterangan', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function pppkDataQuery(Request $request)
    {
        $query = AbsensiPppk::query();

        if ($request->filled('skpd_id')) {
            $query->where('skpd_id', (int) $request->input('skpd_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nama', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('nama_skpd', 'like', '%' . $search . '%')
                    ->orWhere('unit_kerja', 'like', '%' . $search . '%')
                    ->orWhere('jabatan', 'like', '%' . $search . '%')
                    ->orWhere('jenis_presensi', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function balaiKotaReportRows(string $date): array
    {
        return collect($this->balaiKotaUnits())
            ->map(function (array $unit, int $index) use ($date) {
                $dailyRows = $this->balaiKotaDailyRows($unit, $date);
                $pegawaiRows = $this->balaiKotaPegawaiRows($unit);
                $pppkRows = $this->balaiKotaPppkRows($unit, $date);
                $pppkMasterRows = $this->balaiKotaPppkMasterRows($unit);
                $masterNips = $pegawaiRows
                    ->filter(fn (AbsensiPegawai $pegawai) => $this->hasActiveJabatan($pegawai->jabatan))
                    ->pluck('nip')
                    ->map(fn ($nip) => $this->normalizeNip($nip))
                    ->filter()
                    ->unique()
                    ->values();
                $pppkNips = $pppkRows
                    ->pluck('nip')
                    ->merge($pppkMasterRows->pluck('nip'))
                    ->map(fn ($nip) => $this->normalizeNip($nip))
                    ->filter()
                    ->unique()
                    ->values();
                $dailyNips = $dailyRows
                    ->filter(fn (AbsensiDailyReport $row) => $this->hasActiveJabatan($row->jabatan))
                    ->pluck('nip')
                    ->map(fn ($nip) => $this->normalizeNip($nip))
                    ->filter()
                    ->unique()
                    ->values();
                $pppkParuhWaktuNips = $this->pppkParuhWaktuNips($dailyRows);
                $nips = ($masterNips->isNotEmpty() ? $masterNips : $dailyNips)
                    ->merge($pppkNips)
                    ->diff($pppkParuhWaktuNips)
                    ->unique()
                    ->values();
                $jumlahAsn = $nips->count();
                $hadirNips = $dailyRows
                    ->filter(fn (AbsensiDailyReport $row) => $this->isValidApelValue($row->apel))
                    ->pluck('nip')
                    ->map(fn ($nip) => $this->normalizeNip($nip))
                    ->filter()
                    ->intersect($nips)
                    ->unique()
                    ->values();
                $hadirNips = $hadirNips
                    ->merge($pppkRows
                        ->filter(fn (AbsensiPppkReport $row) => $this->isValidApelValue($row->jam_masuk))
                        ->pluck('nip')
                        ->map(fn ($nip) => $this->normalizeNip($nip))
                        ->filter())
                    ->intersect($nips)
                    ->unique()
                    ->values();
                $hadir = $hadirNips->count();
                $tidakHadirNips = $nips->diff($hadirNips)->values();
                $tugasCuti = $this->balaiKotaTugasCutiCount($unit, $date, $tidakHadirNips->all());
                $tidakHadir = max(0, $jumlahAsn - $hadir);
                $tanpaKeterangan = max(0, $tidakHadir - $tugasCuti);

                return [
                    'no' => $index + 1,
                    'unit_kerja' => $unit['label'],
                    'jumlah_asn' => $jumlahAsn,
                    'tanpa_keterangan' => $tanpaKeterangan,
                    'tugas_cuti' => $tugasCuti,
                    'tidak_hadir' => $tidakHadir,
                    'hadir' => $hadir,
                    'persentase' => $jumlahAsn > 0 ? round(($hadir / $jumlahAsn) * 100) : 0,
                ];
            })
            ->all();
    }

    private function balaiKotaDailyRows(array $unit, string $date)
    {
        $query = AbsensiDailyReport::query()
            ->whereDate('tanggal', $date)
            ->whereIn('skpd_id', $unit['skpd_ids']);

        if (isset($unit['unit_kerja'])) {
            $nips = AbsensiPegawai::query()
                ->where('unit_kerja', $unit['unit_kerja'])
                ->pluck('nip')
                ->map(fn ($nip) => $this->normalizeNip($nip))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $query->whereIn('nip', $nips);
        } elseif (isset($unit['jabatan_contains'])) {
            $needles = (array) $unit['jabatan_contains'];
            $query->where(function ($builder) use ($needles) {
                foreach ($needles as $needle) {
                    $builder->orWhere('jabatan', 'like', '%' . $needle . '%');
                }
            });
        }

        return $query->get();
    }

    private function balaiKotaPegawaiRows(array $unit)
    {
        $query = AbsensiPegawai::query()
            ->whereNotNull('nip')
            ->where(function ($builder) use ($unit) {
                foreach ($unit['skpd_ids'] as $skpdId) {
                    $skpd = $this->skpdMap()[$skpdId] ?? [];
                    if (! empty($skpd['kode'])) {
                        $builder->orWhere('skpd', 'like', '%' . $skpd['kode'] . '%');
                    }

                    if (! empty($skpd['nama'])) {
                        $builder->orWhere('skpd', 'like', '%' . $skpd['nama'] . '%');
                    }
                }
            });

        if (isset($unit['unit_kerja'])) {
            $query->where('unit_kerja', $unit['unit_kerja']);
        } elseif (isset($unit['jabatan_contains'])) {
            $needles = (array) $unit['jabatan_contains'];
            $query->where(function ($builder) use ($needles) {
                foreach ($needles as $needle) {
                    $builder->orWhere('jabatan', 'like', '%' . $needle . '%');
                }
            });
        }

        return $query->get();
    }

    private function balaiKotaPppkRows(array $unit, string $date)
    {
        $query = AbsensiPppkReport::query()
            ->whereDate('tanggal', $date)
            ->whereIn('skpd_id', $unit['skpd_ids']);

        if (isset($unit['unit_kerja'])) {
            $mappedNips = $this->setdaPppkNipsForUnit($unit['unit_kerja']);
            $query->where(function ($builder) use ($unit, $mappedNips) {
                $label = (string) $unit['unit_kerja'];
                $short = trim((string) str_replace('Sekretariat Daerah - Bagian ', '', $label));
                $short = trim((string) str_replace('Sekretariat Daerah - Asisten ', '', $short));

                if ($mappedNips !== []) {
                    $builder->orWhereIn('nip', $mappedNips);
                }

                $builder
                    ->orWhere('unit_kerja', $label)
                    ->orWhere('jabatan', 'like', '%' . $label . '%')
                    ->orWhere('jabatan', 'like', '%' . $short . '%');
            });
        }

        return $query->get();
    }

    private function balaiKotaPppkMasterRows(array $unit)
    {
        $query = AbsensiPppk::query()
            ->whereNotNull('nip')
            ->whereIn('skpd_id', $unit['skpd_ids']);

        if (isset($unit['unit_kerja'])) {
            $mappedNips = $this->setdaPppkNipsForUnit($unit['unit_kerja']);
            $query->where(function ($builder) use ($unit, $mappedNips) {
                $label = (string) $unit['unit_kerja'];
                $short = trim((string) str_replace('Sekretariat Daerah - Bagian ', '', $label));
                $short = trim((string) str_replace('Sekretariat Daerah - Asisten ', '', $short));

                if ($mappedNips !== []) {
                    $builder->orWhereIn('nip', $mappedNips);
                }

                $builder
                    ->orWhere('unit_kerja', $label)
                    ->orWhere('jabatan', 'like', '%' . $label . '%')
                    ->orWhere('jabatan', 'like', '%' . $short . '%');
            });
        }

        return $query->get();
    }

    private function balaiKotaTugasCutiCount(array $unit, string $date, array $nips): int
    {
        if ($nips === []) {
            return 0;
        }

        $cutiNips = $this->balaiKotaCutiRows($unit, $date)
            ->pluck('nip')
            ->map(fn ($nip) => $this->normalizeNip($nip))
            ->filter()
            ->unique()
            ->values();

        return $cutiNips->intersect($nips)->count();
    }

    private function balaiKotaCutiRows(array $unit, string $date)
    {
        return AbsensiCutiReport::query()
            ->whereIn('skpd_id', $unit['skpd_ids'])
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)
            ->where(function ($query) {
                $query
                    ->where('jenis_cuti', 'like', '%Tugas%')
                    ->orWhere('jenis_cuti', 'like', '%TL%')
                    ->orWhere('jenis_cuti', 'like', '%Belajar%')
                    ->orWhere('jenis_cuti', 'like', '%Cuti%')
                    ->orWhere('jenis_cuti', 'like', '%Sakit%')
                    ->orWhere('jenis_cuti', 'like', '%Training%')
                    ->orWhere('jenis_cuti', 'like', '%Diklat%');
            })
            ->get();
    }

    private function balaiKotaDetailRows(string $date): array
    {
        return collect($this->balaiKotaUnits())
            ->map(function (array $unit, int $index) use ($date) {
                $dailyRows = $this->balaiKotaDailyRows($unit, $date)
                    ->keyBy(fn (AbsensiDailyReport $row) => $this->normalizeNip($row->nip) ?? (string) $row->id);
                $pegawaiRows = $this->balaiKotaPegawaiRows($unit);
                $pppkRows = $this->balaiKotaPppkRows($unit, $date)
                    ->keyBy(fn (AbsensiPppkReport $row) => $this->normalizeNip($row->nip) ?? (string) $row->id);
                $pppkMasterRows = $this->balaiKotaPppkMasterRows($unit)
                    ->keyBy(fn (AbsensiPppk $row) => $this->normalizeNip($row->nip) ?? (string) $row->id);
                $cutiRows = $this->balaiKotaCutiRows($unit, $date)
                    ->groupBy(fn (AbsensiCutiReport $row) => $this->normalizeNip($row->nip) ?? (string) $row->id);

                $people = $pegawaiRows->isNotEmpty()
                    ? $pegawaiRows->map(function (AbsensiPegawai $pegawai) {
                        $nip = $this->normalizeNip($pegawai->nip);

                        return [
                            'nip' => $nip,
                            'nama' => (string) $pegawai->nama,
                            'jabatan' => (string) $pegawai->jabatan,
                            'pangkat' => (string) $pegawai->pangkat_golongan,
                            'source' => 'PNS',
                        ];
                    })
                    : $dailyRows->map(function (AbsensiDailyReport $daily) {
                        $nip = $this->normalizeNip($daily->nip);

                        return [
                            'nip' => $nip,
                            'nama' => (string) $daily->nama_pegawai,
                            'jabatan' => (string) $daily->jabatan,
                            'pangkat' => (string) $daily->pangkat,
                            'source' => 'PNS',
                        ];
                    })->values();
                $people = collect($people->values());
                $pppkPeople = collect($pppkMasterRows->values())->map(function (AbsensiPppk $pppk) {
                    return [
                        'nip' => $this->normalizeNip($pppk->nip),
                        'nama' => (string) $pppk->nama,
                        'jabatan' => (string) $pppk->jabatan,
                        'pangkat' => 'PPPK',
                        'source' => 'PPPK',
                    ];
                })->merge(collect($pppkRows->values())->map(function (AbsensiPppkReport $pppk) {
                    return [
                        'nip' => $this->normalizeNip($pppk->nip),
                        'nama' => (string) $pppk->nama_pegawai,
                        'jabatan' => (string) $pppk->jabatan,
                        'pangkat' => 'PPPK',
                        'source' => 'PPPK',
                    ];
                }));
                $people = $pppkPeople->merge($people);

                $rows = $people
                    ->filter(fn (array $person) => ! empty($person['nip']))
                    ->unique('nip')
                    ->map(function (array $person) use ($dailyRows, $pppkRows, $pppkMasterRows, $cutiRows) {
                        $daily = $dailyRows->get($person['nip']);
                        $pppk = $pppkRows->get($person['nip']);
                        $pppkMaster = $pppkMasterRows->get($person['nip']);
                        $cuti = $cutiRows->get($person['nip'], collect())->first();
                        $hadir = ($daily instanceof AbsensiDailyReport && $this->isValidApelValue($daily->apel))
                            || ($pppk instanceof AbsensiPppkReport && $this->isValidApelValue($pppk->jam_masuk));
                        $jabatan = $person['jabatan'] !== '' ? $person['jabatan'] : (string) optional($daily)->jabatan;
                        $pangkat = $this->isFilledValue($person['pangkat'] ?? null)
                            ? (string) $person['pangkat']
                            : (string) optional($daily)->pangkat;
                        $source = match (true) {
                            ($person['source'] ?? 'PNS') === 'PPPK' || $pppkMaster instanceof AbsensiPppk || $pppk instanceof AbsensiPppkReport => 'PPPK',
                            $this->isPppkParuhWaktuPangkat($pangkat) || $this->isPppkParuhWaktuPangkat((string) optional($daily)->pangkat) => 'PPPK Paruh Waktu',
                            default => 'PNS',
                        };
                        $isActive = $this->hasActiveJabatan($jabatan);
                        $status = $source === 'PPPK Paruh Waktu'
                            ? 'Belum Wajib Absen'
                            : ($isActive
                            ? ($hadir ? 'Hadir' : ($cuti instanceof AbsensiCutiReport ? 'Tugas/Cuti' : 'Tanpa Keterangan'))
                            : 'Pegawai Tidak Aktif');

                        return [
                            'nip' => $person['nip'],
                            'nama' => $person['nama'] !== '' ? $person['nama'] : (string) optional($daily)->nama_pegawai,
                            'jabatan' => $jabatan,
                            'pangkat' => $pangkat,
                            'source' => $source,
                            'apel' => $daily instanceof AbsensiDailyReport ? (string) $daily->apel : ($pppk instanceof AbsensiPppkReport ? (string) $pppk->jam_masuk : '-'),
                            'jenis_cuti' => $cuti instanceof AbsensiCutiReport ? (string) $cuti->jenis_cuti : '-',
                            'tanggal_cuti' => $cuti instanceof AbsensiCutiReport
                                ? trim(optional($cuti->tanggal_mulai)->format('Y-m-d') . ' s/d ' . optional($cuti->tanggal_selesai)->format('Y-m-d'))
                                : '-',
                            'status' => $status,
                        ];
                    })
                    ->reject(fn (array $row) => $row['status'] === 'Pegawai Tidak Aktif')
                    ->sort(fn (array $a, array $b) => $this->compareBalaiKotaPegawaiRows($a, $b))
                    ->values()
                    ->all();

                return [
                    'id' => 'unit-' . ($index + 1),
                    'label' => $unit['label'],
                    'rows' => $rows,
                    'summary' => [
                        'jumlah_asn' => collect($rows)->whereNotIn('status', ['Pegawai Tidak Aktif', 'Belum Wajib Absen'])->count(),
                        'hadir' => collect($rows)->where('status', 'Hadir')->count(),
                        'tugas_cuti' => collect($rows)->where('status', 'Tugas/Cuti')->count(),
                        'tanpa_keterangan' => collect($rows)->where('status', 'Tanpa Keterangan')->count(),
                        'belum_wajib_absen' => collect($rows)->where('status', 'Belum Wajib Absen')->count(),
                    ],
                ];
            })
            ->all();
    }

    private function pppkParuhWaktuNips($dailyRows)
    {
        return $dailyRows
            ->filter(fn (AbsensiDailyReport $row) => $this->isPppkParuhWaktuPangkat($row->pangkat))
            ->pluck('nip')
            ->map(fn ($nip) => $this->normalizeNip($nip))
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeNip($value): ?string
    {
        $value = (string) $value;

        if (preg_match('/\d{18}/', $value, $matches)) {
            return $matches[0];
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    private function hasActiveJabatan(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '-';
    }

    private function isFilledValue(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && $value !== '-';
    }

    private function isPppkParuhWaktuPangkat(?string $value): bool
    {
        $value = trim((string) $value);

        return $value === '()';
    }

    private function compareBalaiKotaPegawaiRows(array $a, array $b): int
    {
        return $this->compareSortValues(
            [
                $this->sourceSortRank($a['source'] ?? 'PNS'),
                $this->jabatanSortRank($a['jabatan'] ?? ''),
                -$this->pangkatSortRank($a['pangkat'] ?? ''),
                $this->nipBirthDateSortValue($a['nip'] ?? ''),
                (string) ($a['nip'] ?? ''),
                str($a['nama'] ?? '')->lower()->toString(),
            ],
            [
                $this->sourceSortRank($b['source'] ?? 'PNS'),
                $this->jabatanSortRank($b['jabatan'] ?? ''),
                -$this->pangkatSortRank($b['pangkat'] ?? ''),
                $this->nipBirthDateSortValue($b['nip'] ?? ''),
                (string) ($b['nip'] ?? ''),
                str($b['nama'] ?? '')->lower()->toString(),
            ]
        );
    }

    private function compareSortValues(array $a, array $b): int
    {
        foreach ($a as $index => $left) {
            $right = $b[$index] ?? null;

            if ($left == $right) {
                continue;
            }

            return $left <=> $right;
        }

        return 0;
    }

    private function sourceSortRank(string $source): int
    {
        return match (Str::of($source)->upper()->toString()) {
            'PPPK' => 1,
            'PPPK PARUH WAKTU' => 2,
            default => 0,
        };
    }

    private function jabatanSortRank(string $jabatan): int
    {
        $value = Str::of($jabatan)->lower()->squish()->toString();

        return match (true) {
            str_contains($value, 'sekretaris daerah') => 0,
            str_contains($value, 'asisten ') => 1,
            str_contains($value, 'staf ahli') => 2,
            str_contains($value, 'inspektur') && ! str_contains($value, 'pembantu') => 3,
            str_contains($value, 'kepala badan') || str_contains($value, 'kepala dinas') => 4,
            $value === 'sekretaris' || str_starts_with($value, 'sekretaris ') => 5,
            str_contains($value, 'kepala bagian') || str_contains($value, 'kepala bidang') => 6,
            str_contains($value, 'kepala sub bagian')
                || str_contains($value, 'kepala subbag')
                || str_contains($value, 'kepala sub bidang')
                || str_contains($value, 'kepala subbidang')
                || str_contains($value, 'kepala seksi') => 7,
            str_contains($value, 'sub koordinator') || str_contains($value, 'koordinator') => 8,
            str_contains($value, 'ahli madya') || str_contains($value, 'madya') => 9,
            str_contains($value, 'ahli muda') || str_contains($value, 'muda') => 10,
            str_contains($value, 'ahli pertama') || str_contains($value, 'pertama') => 11,
            default => 12,
        };
    }

    private function pangkatSortRank(string $pangkat): int
    {
        $value = Str::of($pangkat)->upper()->replace('\\', '/')->squish()->toString();

        if (preg_match('/\b(IV|III|II|I)\s*\/\s*([A-E])\b/', $value, $matches) === 1) {
            $roman = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4][$matches[1]] ?? 0;
            $letter = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5][$matches[2]] ?? 0;

            return ($roman * 10) + $letter;
        }

        return 0;
    }

    private function nipBirthDateSortValue(string $nip): string
    {
        $nip = $this->normalizeNip($nip) ?? '';

        if (strlen($nip) >= 8) {
            return substr($nip, 0, 8);
        }

        return '99999999';
    }

    private function balaiKotaTotals(array $rows): array
    {
        $totals = [
            'jumlah_asn' => array_sum(array_column($rows, 'jumlah_asn')),
            'tanpa_keterangan' => array_sum(array_column($rows, 'tanpa_keterangan')),
            'tugas_cuti' => array_sum(array_column($rows, 'tugas_cuti')),
            'tidak_hadir' => array_sum(array_column($rows, 'tidak_hadir')),
            'hadir' => array_sum(array_column($rows, 'hadir')),
        ];
        $totals['persentase'] = $totals['jumlah_asn'] > 0
            ? round(($totals['hadir'] / $totals['jumlah_asn']) * 100)
            : 0;

        return $totals;
    }

    private function isValidApelValue(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== ''
            && $value !== '-'
            && $value !== '00:00:00';
    }

    private function setdaPppkNipsForUnit(string $unitKerja): array
    {
        return collect($this->setdaPppkUnitKerjaByNip())
            ->filter(fn (string $mappedUnit) => $mappedUnit === $unitKerja)
            ->keys()
            ->values()
            ->all();
    }

    private function setdaPppkUnitKerjaByNip(): array
    {
        return [
            '199305112024212030' => 'Sekretariat Daerah - Bagian Pemerintahan',
            '199205312024211006' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa',
            '199802062024211003' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa',
            '198708192025212001' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa',
            '199808292025211003' => 'Sekretariat Daerah - Bagian Umum',
            '199305252025211010' => 'Sekretariat Daerah - Bagian Pemerintahan',
            '197801012025212009' => 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat',
            '199901212025211003' => 'Sekretariat Daerah - Bagian Umum',
            '200008312025211001' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan',
            '199610302025211002' => 'Sekretariat Daerah - Bagian Organisasi',
            '199911112025211003' => 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam',
            '199410232025212002' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan',
            '198304112025211007' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan',
            '199502282025211004' => 'Sekretariat Daerah - Bagian Umum',
            '199610282025211004' => 'Sekretariat Daerah - Bagian Umum',
            '198909292025212008' => 'Sekretariat Daerah - Bagian Umum',
        ];
    }

    private function balaiKotaSkpdIds(): array
    {
        return collect($this->balaiKotaUnits())
            ->flatMap(fn (array $unit) => $unit['skpd_ids'])
            ->unique()
            ->values()
            ->all();
    }

    private function balaiKotaUnits(): array
    {
        return [
            ['label' => 'Sekretariat Daerah - Bagian Umum', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Umum'],
            ['label' => 'Sekretariat Daerah - Bagian Administrasi Pembangunan', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Administrasi Pembangunan'],
            ['label' => 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Kesejahteraan Rakyat'],
            ['label' => 'Sekretariat Daerah - Bagian Pemerintahan', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Pemerintahan'],
            ['label' => 'Sekretariat Daerah - Bagian Hukum', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Hukum'],
            ['label' => 'Sekretariat Daerah - Bagian Organisasi', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Organisasi'],
            ['label' => 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Perekonomian dan Sumber Daya Alam'],
            ['label' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Pengadaan Barang dan Jasa'],
            ['label' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan', 'skpd_ids' => [20], 'unit_kerja' => 'Sekretariat Daerah - Bagian Protokol dan Komunikasi Pimpinan'],
            ['label' => 'Dinas Lingkungan Hidup', 'skpd_ids' => [9]],
            ['label' => 'Dinas Komunikasi Informatika dan Statistik', 'skpd_ids' => [13]],
            ['label' => 'Dinas Perumahan Rakyat dan Kawasan Permukiman', 'skpd_ids' => [3]],
            ['label' => 'Dinas Pemadam Kebakaran dan Penyelamatan', 'skpd_ids' => [34]],
            ['label' => 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia', 'skpd_ids' => [24]],
            ['label' => 'Badan Perencanaan Pembangunan Daerah, Penelitian dan Pengembangan', 'skpd_ids' => [31]],
            ['label' => 'Badan Kesatuan Bangsa dan Politik', 'skpd_ids' => [5]],
            ['label' => 'Badan Penanggulangan Bencana Daerah', 'skpd_ids' => [25]],
        ];
    }

    private function excelTable($reports): string
    {
        $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = [
            '<html><head><meta charset="UTF-8"></head><body>',
            '<table border="1">',
            '<thead><tr>'
                . '<th>No</th>'
                . '<th>Kode SKPD</th>'
                . '<th>Nama SKPD</th>'
                . '<th>Nama Pegawai</th>'
                . '<th>NIP</th>'
                . '<th>Jenis</th>'
                . '<th>Tanggal Mulai</th>'
                . '<th>Tanggal Selesai</th>'
                . '<th>Status</th>'
                . '<th>Upload URL</th>'
                . '<th>Fetched At</th>'
                . '</tr></thead><tbody>',
        ];

        foreach ($reports as $index => $report) {
            $rows[] = '<tr>'
                . '<td>' . ($index + 1) . '</td>'
                . '<td>' . $escape($report->kode_skpd) . '</td>'
                . '<td>' . $escape($report->nama_skpd) . '</td>'
                . '<td>' . $escape($report->nama_pegawai) . '</td>'
                . '<td style="mso-number-format:\'\\@\';">' . $escape($report->nip) . '</td>'
                . '<td>' . $escape($report->jenis_cuti) . '</td>'
                . '<td>' . $escape(optional($report->tanggal_mulai)->format('Y-m-d')) . '</td>'
                . '<td>' . $escape(optional($report->tanggal_selesai)->format('Y-m-d')) . '</td>'
                . '<td>' . $escape($report->status) . '</td>'
                . '<td>' . $escape($report->upload_url) . '</td>'
                . '<td>' . $escape(optional($report->fetched_at)->format('Y-m-d H:i:s')) . '</td>'
                . '</tr>';
        }

        $rows[] = '</tbody></table></body></html>';

        return "\xEF\xBB\xBF" . implode('', $rows);
    }

    private function absensiCredentials(): ?array
    {
        $username = trim((string) config('services.absensi.username'));
        $password = (string) config('services.absensi.password');

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    private function skpdOptions(): array
    {
        return collect($this->skpdMap())
            ->map(fn (array $skpd, int $id) => [
                'id' => $id,
                'label' => trim(implode(' - ', array_filter([
                    $skpd['kode'] ?? null,
                    $skpd['nama'] ?? null,
                ]))),
            ])
            ->values()
            ->all();
    }

    private function jenisCutiOptions(): array
    {
        return AbsensiCutiReport::query()
            ->whereNotNull('jenis_cuti')
            ->distinct()
            ->orderBy('jenis_cuti')
            ->pluck('jenis_cuti')
            ->filter()
            ->values()
            ->all();
    }

    private function pegawaiSkpdOptions(): array
    {
        return AbsensiPegawai::query()
            ->whereNotNull('skpd')
            ->distinct()
            ->orderBy('skpd')
            ->pluck('skpd')
            ->filter()
            ->values()
            ->all();
    }

    private function skpdMap(): array
    {
        $configured = config('services.absensi.skpd', []);

        return is_array($configured) ? $configured : [];
    }

    private function latestSavedCuti(): ?array
    {
        $path = storage_path('scraping/absensi_cuti.json');

        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }
}
