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
        Schema::table('a_siswa', function (Blueprint $table) {
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->longText('photo_profile')->nullable();
            $table->text('photo_name_ori')->nullable();
            $table->string('religion')->nullable();
            $table->string('email')->nullable();
            $table->string('parent_phone_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('a_siswa', function (Blueprint $table) {
            $table->dropColumn('facebook');
            $table->dropColumn('instagram');
            $table->dropColumn('linkedin');
            $table->dropColumn('photo_profile');
            $table->dropColumn('photo_name_ori');
            $table->dropColumn('religion');
            $table->dropColumn('email');
            $table->dropColumn('parent_phone_number');
        });
    }
};
