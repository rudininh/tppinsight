<?php

namespace App\Http\Controllers;

use App\Models\AbsensiCutiReport;
use App\Models\AbsensiPegawai;
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
                    ->orWhere('jabatan', 'like', '%' . $search . '%')
                    ->orWhere('pangkat_golongan', 'like', '%' . $search . '%')
                    ->orWhere('device_id', 'like', '%' . $search . '%');
            });
        }

        return $query;
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

    private function absensiCredentials(): array
    {
        $username = trim((string) config('services.absensi.username'));
        $password = (string) config('services.absensi.password');

        if ($username === '' || $password === '') {
            abort(500, 'ABSENSI_USERNAME dan ABSENSI_PASSWORD belum diatur di .env.');
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
