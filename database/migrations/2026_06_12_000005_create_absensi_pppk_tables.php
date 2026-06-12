<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_pppks', function (Blueprint $table) {
            $table->id();
            $table->string('pppk_id')->nullable()->index();
            $table->unsignedInteger('skpd_id')->index();
            $table->string('kode_skpd')->nullable()->index();
            $table->string('nama_skpd')->nullable();
            $table->string('nip')->nullable()->index();
            $table->string('nama')->nullable()->index();
            $table->string('jabatan')->nullable()->index();
            $table->string('pangkat')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_presensi')->nullable();
            $table->string('status_asn')->nullable()->index();
            $table->string('presensi_url')->nullable();
            $table->json('row_data')->nullable();
            $table->string('row_hash', 64)->unique();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('absensi_pppk_reports', function (Blueprint $table) {
            $table->id();
            $table->string('pppk_id')->nullable()->index();
            $table->unsignedInteger('skpd_id')->index();
            $table->string('kode_skpd')->nullable()->index();
            $table->string('nama_skpd')->nullable();
            $table->string('nip')->nullable()->index();
            $table->string('nama_pegawai')->nullable()->index();
            $table->string('jabatan')->nullable()->index();
            $table->date('tanggal')->index();
            $table->string('hari')->nullable();
            $table->string('jam_masuk')->nullable();
            $table->string('jam_pulang')->nullable();
            $table->string('keterangan')->nullable()->index();
            $table->integer('telat')->nullable();
            $table->integer('lebih_awal')->nullable();
            $table->json('row_data')->nullable();
            $table->string('row_hash', 64)->unique();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pppk_reports');
        Schema::dropIfExists('absensi_pppks');
    }
};
