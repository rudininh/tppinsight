<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_cuti_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('skpd_id')->index();
            $table->string('kode_skpd')->nullable()->index();
            $table->string('nama_skpd')->nullable();
            $table->date('tanggal_mulai')->nullable()->index();
            $table->date('tanggal_selesai')->nullable()->index();
            $table->string('jenis_cuti')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('nama_pegawai')->nullable()->index();
            $table->string('nip')->nullable()->index();
            $table->text('upload_url')->nullable();
            $table->text('upload_label')->nullable();
            $table->json('row_data');
            $table->string('row_hash', 64)->unique();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_cuti_reports');
    }
};
