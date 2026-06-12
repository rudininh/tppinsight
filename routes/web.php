<?php

use App\Http\Controllers\AbsensiCmsController;
use App\Http\Controllers\AbsensiScraperController;
use App\Http\Controllers\TppScraperController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AbsensiCmsController::class, 'index'])->name('dashboard');

Route::prefix('cms')->name('cms.')->group(function () {
    Route::get('/absensi-cuti', [AbsensiCmsController::class, 'index'])->name('absensi-cuti.index');
    Route::post('/absensi-cuti/fetch', [AbsensiCmsController::class, 'fetchCuti'])->name('absensi-cuti.fetch');
    Route::get('/laporan-cuti', [AbsensiCmsController::class, 'laporanCuti'])->name('laporan-cuti.index');
    Route::get('/laporan-cuti/export', [AbsensiCmsController::class, 'exportLaporanCuti'])->name('laporan-cuti.export');
    Route::post('/laporan-cuti/fetch-all', [AbsensiCmsController::class, 'fetchAllCuti'])->name('laporan-cuti.fetch-all');
    Route::get('/pegawai', [AbsensiCmsController::class, 'pegawai'])->name('pegawai.index');
    Route::post('/pegawai/fetch', [AbsensiCmsController::class, 'fetchPegawai'])->name('pegawai.fetch');
    Route::get('/laporan-absensi-harian', [AbsensiCmsController::class, 'laporanAbsensiHarian'])->name('laporan-absensi-harian.index');
    Route::post('/laporan-absensi-harian/fetch', [AbsensiCmsController::class, 'fetchLaporanAbsensiHarian'])->name('laporan-absensi-harian.fetch');
    Route::get('/laporan-pppk', [AbsensiCmsController::class, 'laporanPppk'])->name('laporan-pppk.index');
    Route::post('/laporan-pppk/fetch', [AbsensiCmsController::class, 'fetchLaporanPppk'])->name('laporan-pppk.fetch');
    Route::get('/laporan-balai-kota', [AbsensiCmsController::class, 'laporanBalaiKota'])->name('laporan-balai-kota.index');
    Route::post('/laporan-balai-kota/fetch', [AbsensiCmsController::class, 'fetchLaporanBalaiKota'])->name('laporan-balai-kota.fetch');
});

Route::prefix('absensi-scraper')->name('absensi-scraper.')->group(function () {
    Route::get('/', [AbsensiScraperController::class, 'index'])->name('index');
    Route::post('/login', [AbsensiScraperController::class, 'login'])->name('login');
    Route::post('/cuti', [AbsensiScraperController::class, 'cuti'])->name('cuti');
});

Route::prefix('tpp-scraper')->name('tpp-scraper.')->group(function () {
    Route::get('/', [TppScraperController::class, 'index'])->name('index');
    Route::post('/run', [TppScraperController::class, 'run'])->name('run');
    Route::post('/login', [TppScraperController::class, 'login'])->name('login');
    Route::post('/discover', [TppScraperController::class, 'discover'])->name('discover');
    Route::post('/analyze', [TppScraperController::class, 'analyze'])->name('analyze');
});
