<?php

namespace App\Http\Controllers;

use App\Services\TppScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TppScraperController extends Controller
{
    public function __construct(private readonly TppScraperService $scraper)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'name' => 'TPP Scraper',
            'status' => 'ready',
            'endpoints' => [
                'GET /tpp-scraper',
                'POST /tpp-scraper/run',
                'POST /tpp-scraper/login',
                'POST /tpp-scraper/discover',
                'POST /tpp-scraper/analyze',
            ],
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        return response()->json(
            $this->scraper->scrape($data['username'], $data['password'])
        );
    }

    public function login(Request $request): JsonResponse
    {
        return $this->run($request);
    }

    public function discover(Request $request): JsonResponse
    {
        $data = $request->validate([
            'html' => ['nullable', 'string'],
            'source_path' => ['nullable', 'string'],
        ]);

        $html = $data['html'] ?? null;
        $sourcePath = $data['source_path'] ?? null;
        $dashboard = null;

        if ($html === null || trim($html) === '') {
            $dashboard = $this->scraper->getDashboard();

            if (! ($dashboard['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dashboard tidak bisa diakses. Login terlebih dahulu.',
                    'dashboard' => $dashboard,
                ], 422);
            }

            $html = is_string($dashboard['body'] ?? null) ? (string) $dashboard['body'] : null;
            $sourcePath = is_string($dashboard['path'] ?? null) ? (string) $dashboard['path'] : $sourcePath;
        }

        return response()->json([
            'success' => true,
            'dashboard' => $dashboard,
            'discovered_endpoints' => $this->scraper->discoverEndpoints($html, $sourcePath),
        ]);
    }

    public function analyze(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'analyzed_endpoints' => $this->scraper->analyzeEndpoints(),
        ]);
    }
}
