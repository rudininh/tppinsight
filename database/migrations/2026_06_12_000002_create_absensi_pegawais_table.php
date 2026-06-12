<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_pegawais', function (Blueprint $table) {
            $table->id();
            $table->string('pegawai_id')->nullable()->index();
            $table->string('nip')->nullable()->index();
            $table->string('nama')->nullable()->index();
            $table->string('pangkat_golongan')->nullable();
            $table->string('skpd')->nullable()->index();
            $table->string('jabatan')->nullable()->index();
            $table->string('puskesmas')->nullable()->index();
            $table->string('device_id')->nullable()->index();
            $table->string('history_url')->nullable();
            $table->json('row_data')->nullable();
            $table->string('row_hash')->unique();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pegawais');
    }
};
