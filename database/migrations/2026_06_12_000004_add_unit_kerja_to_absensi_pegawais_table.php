<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_pegawais', function (Blueprint $table) {
            $table->string('unit_kerja')->nullable()->after('skpd')->index();
        });
    }

    public function down(): void
    {
        Schema::table('absensi_pegawais', function (Blueprint $table) {
            $table->dropColumn('unit_kerja');
        });
    }
};
