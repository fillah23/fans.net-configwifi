<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VlanProfile;
use App\Models\Olt;

class VlanProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first OLT for sample data
        $olt = Olt::first();
        
        if (!$olt) {
            $this->command->info('No OLT found. Please create an OLT first.');
            return;
        }

        // Sample VLAN profiles based on user requirement
        $profiles = [
            [
                'olt_id' => $olt->id,
                'profile_name' => 'LOKALGR3',
                'profile_id' => 1,
                'vlan_data' => [
                    [
                        'vlan_id' => 1100,
                        'description' => 'CVLAN: 1100'
                    ]
                ],
                'vlan_count' => 1,
                'last_updated' => now(),
            ],
            [
                'olt_id' => $olt->id,
                'profile_name' => 'INTERNET_PROFILE',
                'profile_id' => 2,
                'vlan_data' => [
                    [
                        'vlan_id' => 1200,
                        'description' => 'CVLAN: 1200'
                    ]
                ],
                'vlan_count' => 1,
                'last_updated' => now(),
            ],
            [
                'olt_id' => $olt->id,
                'profile_name' => 'VOIP_PROFILE',
                'profile_id' => 3,
                'vlan_data' => [
                    [
                        'vlan_id' => 1300,
                        'description' => 'CVLAN: 1300'
                    ]
                ],
                'vlan_count' => 1,
                'last_updated' => now(),
            ]
        ];

        foreach ($profiles as $profile) {
            VlanProfile::updateOrCreate(
                [
                    'olt_id' => $profile['olt_id'],
                    'profile_name' => $profile['profile_name']
                ],
                $profile
            );
        }

        $this->command->info('VLAN profiles seeded successfully!');
    }
}
