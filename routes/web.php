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
});
