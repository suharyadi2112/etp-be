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
        Schema::create('a_mata_pelajaran', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->string('subject_name', 100)->notNullable(); // Nama mata pelajaran 
            $table->text('subject_description')->nullable(); // Deskripsi mata pelajaran
            $table->string('education_level')->nullable(); // Tingkat pendidikan
            $table->string('subject_code')->unique()->nullable(); // Kode mata pelajaran
            $table->timestamps(); // Waktu pembuatan dan pembaruan
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
        Schema::dropIfExists('mata_pelajaran');
    }
};
