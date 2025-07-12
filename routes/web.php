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
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
});
