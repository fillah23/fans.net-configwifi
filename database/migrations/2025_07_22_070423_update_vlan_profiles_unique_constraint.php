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
            // Drop the old unique constraint
            $table->dropUnique(['olt_id', 'profile_name']);
            
            // Add new unique constraint that includes profile_type
            $table->unique(['olt_id', 'profile_name', 'profile_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vlan_profiles', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique(['olt_id', 'profile_name', 'profile_type']);
            
            // Restore the old unique constraint
            $table->unique(['olt_id', 'profile_name']);
        });
    }
};
