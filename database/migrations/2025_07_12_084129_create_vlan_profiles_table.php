<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vlan_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->onDelete('cascade');
            $table->string('profile_name');
            $table->string('profile_id');
            $table->text('vlan_data')->nullable(); // JSON data for VLANs
            $table->integer('vlan_count')->default(0);
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
            
            $table->unique(['olt_id', 'profile_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlan_profiles');
    }
};
