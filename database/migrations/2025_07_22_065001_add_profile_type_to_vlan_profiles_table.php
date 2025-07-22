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
        Schema::table('vlan_profiles', function (Blueprint $table) {
            $table->string('profile_type')->default('vlan')->after('vlan_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vlan_profiles', function (Blueprint $table) {
            $table->dropColumn('profile_type');
        });
    }
};
