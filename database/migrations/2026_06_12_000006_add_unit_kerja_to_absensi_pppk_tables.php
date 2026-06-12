<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_pppks', function (Blueprint $table) {
            $table->string('unit_kerja')->nullable()->index()->after('nama_skpd');
        });

        Schema::table('absensi_pppk_reports', function (Blueprint $table) {
            $table->string('unit_kerja')->nullable()->index()->after('nama_skpd');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_pppks', function (Blueprint $table) {
            $table->dropColumn('unit_kerja');
        });

        Schema::table('absensi_pppk_reports', function (Blueprint $table) {
            $table->dropColumn('unit_kerja');
        });
    }
};
