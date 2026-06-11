<?php

namespace App\Http\Controllers;

use App\Models\AbsensiCutiReport;
use App\Services\AbsensiScraperService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
        $query = AbsensiCutiReport::query()->latest('fetched_at')->latest('id');

        if ($request->filled('skpd_id')) {
            $query->where('skpd_id', (int) $request->input('skpd_id'));
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

        return view('absensi-cms.laporan-cuti', [
            'reports' => $query->paginate(100)->withQueryString(),
            'totalRows' => AbsensiCutiReport::query()->count(),
            'withUploadRows' => AbsensiCutiReport::query()->whereNotNull('upload_url')->count(),
            'lastFetchedAt' => AbsensiCutiReport::query()->max('fetched_at'),
            'skpdOptions' => $this->skpdOptions(),
            'skpdMap' => $this->skpdMap(),
            'result' => null,
            'dateStart' => '2026-01-01',
            'dateEnd' => now()->toDateString(),
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
            'result' => $result,
            'dateStart' => $data['date_start'],
            'dateEnd' => $data['date_end'],
        ]);
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
