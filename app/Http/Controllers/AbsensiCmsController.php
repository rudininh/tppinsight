<?php

namespace App\Http\Controllers;

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
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'redact' => ['nullable', 'boolean'],
        ]);

        $result = $this->scraper->scrapeCuti(
            $data['username'],
            $data['password'],
            $request->boolean('redact', true)
        );

        return view('absensi-cms.index', [
            'result' => $result,
            'latest' => $this->latestSavedCuti(),
        ]);
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
