<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('a_pivot_siswa_orang_tua', function (Blueprint $table) {
            $table->id();
            $table->uuid('siswa_id');
            $table->uuid('orang_tua_id');
            $table->string('hubungan')->comment('Hubungan Ayah, Ibu atau lainnya');
            // Tambahkan kolom lain jika diperlukan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('a_pivot_siswa_orang_tua');
    }
};
