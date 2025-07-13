<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Test routes outside auth middleware for debugging
Route::get('onus/test-parse-sample', [App\Http\Controllers\OnuController::class, 'testParseWithSampleData'])->name('onus.test-parse-sample');
Route::get('onus/test-slot-calculation', [App\Http\Controllers\OnuController::class, 'testSlotCalculationWithSample'])->name('onus.test-slot-calculation');
Route::post('onus/simulate-available-slot', [App\Http\Controllers\OnuController::class, 'simulateGetAvailableSlot'])->name('onus.simulate-available-slot');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    });
    Route::resource('users', UserController::class);
    Route::resource('olts', App\Http\Controllers\OltController::class);
    Route::get('olts/{olt}/test', [App\Http\Controllers\OltController::class, 'test'])->name('olts.test');
    Route::get('olts/{olt}/info', [App\Http\Controllers\OltController::class, 'getInfo'])->name('olts.info');
    Route::post('olts/{olt}/sync-vlans', [App\Http\Controllers\OltController::class, 'syncVlans'])->name('olts.sync-vlans');
    Route::get('olts/{olt}/vlan-profiles', [App\Http\Controllers\OltController::class, 'getVlanProfiles'])->name('olts.vlan-profiles');
    Route::get('olts/{olt}/vlan-profiles-view', [App\Http\Controllers\OltController::class, 'getVlanProfilesView'])->name('olts.vlan-profiles-view');
    Route::get('olts/{olt}/test-vlan', [App\Http\Controllers\OltController::class, 'testVlanCommand'])->name('olts.test-vlan');
    
    // ONU Routes - Custom routes first, then resource routes
    Route::post('onus/get-unconfigured', [App\Http\Controllers\OnuController::class, 'getUnconfiguredOnus'])->name('onus.get-unconfigured');
    Route::post('onus/get-available-slot', [App\Http\Controllers\OnuController::class, 'getAvailableSlot'])->name('onus.get-available-slot');
    Route::get('onus/get-vlan-profiles', [App\Http\Controllers\OnuController::class, 'getVlanProfiles'])->name('onus.get-vlan-profiles');
    Route::post('onus/test-configuration', [App\Http\Controllers\OnuController::class, 'testConfiguration'])->name('onus.test-configuration');
    Route::post('onus/debug-uncfg', [App\Http\Controllers\OnuController::class, 'debugUncfgCommand'])->name('onus.debug-uncfg');
    Route::post('onus/debug-show-run', [App\Http\Controllers\OnuController::class, 'debugShowRunInterface'])->name('onus.debug-show-run');
    Route::resource('onus', App\Http\Controllers\OnuController::class);
    
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Debug route for testing VLAN profiles directly
    Route::get('debug/vlan-profiles/{olt_id}', function($olt_id) {
        try {
            $profiles = \App\Models\VlanProfile::where('olt_id', $olt_id)->get();
            return response()->json([
                'olt_id' => $olt_id,
                'profiles_count' => $profiles->count(),
                'profiles' => $profiles,
                'sample_data' => $profiles->take(2),
                'message' => 'Debug data for VLAN profiles'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'olt_id' => $olt_id
            ]);
        }
    });

    // Debug route for testing ONU Bridge configuration
    Route::post('debug/onu-bridge', function(\Illuminate\Http\Request $request) {
        try {
            $data = $request->all();
            
            // Log the incoming data
            \Log::info('Debug ONU Bridge Request', $data);
            
            // Validate basic required fields
            $required = ['olt_id', 'onu_sn', 'card', 'port', 'onu_id', 'name', 'vlan'];
            $missing = [];
            
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields: ' . implode(', ', $missing),
                    'received_data' => $data
                ]);
            }
            
            // Try to get OLT
            $olt = \App\Models\Olt::find($data['olt_id']);
            if (!$olt) {
                return response()->json([
                    'success' => false,
                    'message' => 'OLT not found',
                    'olt_id' => $data['olt_id']
                ]);
            }
            
            // Generate commands for testing
            $commands = [
                'con t',
                "interface gpon-olt_1/{$data['card']}/{$data['port']}",
                "onu {$data['onu_id']} type ALL-GPON sn {$data['onu_sn']}",
                'exit',
                "interface gpon-onu_1/{$data['card']}/{$data['port']}:{$data['onu_id']}",
                "name {$data['name']}",
                'tcont 1 name BRIDGE profile 1000MBPS',
                'gemport 1 name Bridge tcont 1',
                'gemport 1 traffic-limit upstream UP1000MBPS downstream DW1000MBPS',
                "service-port 1 vport 1 user-vlan {$data['vlan']} vlan {$data['vlan']}",
                'port-identification format DSL-FORUM-PON sport 1',
                'pppoe-intermediate-agent enable sport 1',
                'exit',
                "pon-onu-mng gpon-onu_1/{$data['card']}/{$data['port']}:{$data['onu_id']}",
                "service BRIDGE gemport 1 vlan {$data['vlan']}",
                'vlan port veip_1 mode hybrid',
                "vlan port eth_0/1 mode tag vlan {$data['vlan']}",
                "vlan port eth_0/2 mode tag vlan {$data['vlan']}",
                "vlan port eth_0/3 mode tag vlan {$data['vlan']}",
                "vlan port eth_0/4 mode tag vlan {$data['vlan']}",
                'dhcp-ip ethuni eth_0/1 from-internet',
                'dhcp-ip ethuni eth_0/2 from-internet',
                'dhcp-ip ethuni eth_0/3 from-internet',
                'dhcp-ip ethuni eth_0/4 from-internet',
                'security-mgmt 998 state enable mode forward ingress-type lan protocol web https',
                'end',
                'wr'
            ];
            
            return response()->json([
                'success' => true,
                'message' => 'Commands generated successfully',
                'olt_info' => [
                    'name' => $olt->nama,
                    'ip' => $olt->ip,
                    'port' => $olt->port
                ],
                'config_data' => $data,
                'commands' => $commands,
                'commands_count' => count($commands)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Debug ONU Bridge Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Debug error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    });
});
