<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('tipe');
            $table->string('ip');
            $table->integer('port');
            $table->string('card');
            $table->string('user');
            $table->string('pass');
            $table->string('community_read');
            $table->string('community_write');
            $table->string('port_snmp');
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('olts');
    }
};
