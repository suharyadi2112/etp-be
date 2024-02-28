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
        Schema::create('a_parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_siswa'); // Foreign key ke tabel kelas
            $table->string('name');
            $table->string('address');
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->string('relationship')->comment('Hubungan Ayah, Ibu atau lainnya');
            $table->date('date_of_birth')->nullable();
            $table->text('place_of_birth')->nullable();
            $table->text('occupation')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->text('additional_notes')->nullable();
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
        Schema::dropIfExists('a_parents');
    }
};
