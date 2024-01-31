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
        Schema::create('a_base_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_kelas', 200)->notNullable();
            $table->string('ruang_kelas')->nullable();
            $table->longText('deskripsi')->nullable();
            $table->timestamps();
            $table->softDeletes(); // deleted_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('a_base_kelas');
    }
};
