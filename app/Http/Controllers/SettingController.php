<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
class SettingController extends Controller
{
    public function index()
    {
        $setting = Setting::first();
        if (!$setting) {
            $setting = Setting::create(['name' => 'Fans.net', 'logo' => null]);
        }
        $logs = ActivityLog::with('user')->orderByDesc('created_at')->limit(20)->get();
        return view('settings.index', compact('setting', 'logs'));
    }
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);
        $setting = Setting::first();
        if (!$setting) {
            $setting = Setting::create(['name' => $request->name, 'logo' => null]);
        }
        $setting->name = $request->name;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('logos', 'public');
            $setting->logo = $logo;
        }
        $setting->save();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity' => 'Mengubah pengaturan sistem',
            'created_at' => now(),
        ]);
        return back()->with('success', 'Pengaturan berhasil disimpan');
    }
}
