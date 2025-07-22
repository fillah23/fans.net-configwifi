<?php
namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\VlanProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OltController extends Controller
{
    public function index()
    {
        $olts = Olt::all();
        return view('olts.index', compact('olts'));
    }

    public function create()
    {
        return view('olts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required',
            'tipe' => 'required',
            'ip' => 'required|ip',
            'port' => 'required|integer',
            'card' => 'required|array',
            'user' => 'required',
            'pass' => 'required',
            'community_read' => 'required',
            'community_write' => 'required',
            'port_snmp' => 'required',
        ]);
        $validated['card'] = implode(',', $validated['card']);
        Olt::create($validated);
        return response()->json(['success' => true]);
    }

    public function edit(Olt $olt)
    {
        // Untuk AJAX modal edit, return partial form
        if(request()->ajax()) {
            return view('olts._edit_form', compact('olt'))->render();
        }
        return view('olts.edit', compact('olt'));
    }

    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'nama' => 'required',
            'tipe' => 'required',
            'ip' => 'required|ip',
            'port' => 'required|integer',
            'card' => 'required|array',
            'user' => 'required',
            'pass' => 'required',
            'community_read' => 'required',
            'community_write' => 'required',
            'port_snmp' => 'required',
        ]);
        $validated['card'] = implode(',', $validated['card']);
        $olt->update($validated);
        return response()->json(['success' => true]);
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();
        return redirect()->route('olts.index')->with('success', 'OLT berhasil dihapus');
    }

    public function test(Olt $olt)
    {
        // Test berdasarkan tipe OLT
        $telnetResult = $this->testTelnet($olt->ip, $olt->port, $olt->user, $olt->pass, $olt->tipe);
        $snmpResult = $this->testSnmp($olt->ip, $olt->community_read, $olt->port_snmp, $olt->tipe);
        $success = $telnetResult['success'] && $snmpResult['success'];
        $message = '<b>Telnet:</b> ' . $telnetResult['message'] . '<br><b>SNMP:</b> ' . $snmpResult['message'];
        return response()->json(['success' => $success, 'message' => $message]);
    }

    public function getInfo(Olt $olt)
    {
        $result = ['success' => false, 'message' => '', 'data' => []];
        
        // OID untuk informasi OLT berdasarkan tipe
        $uptimeOid = '1.3.6.1.2.1.1.3.0'; // sysUpTime (universal)
        
        if (strtoupper($olt->tipe) === 'HUAWEI MA5630T') {
            // HUAWEI specific OIDs - updated with more comprehensive temperature OIDs
            $tempOidBase = '.1.3.6.1.4.1.2011.5.25.31.1.1.1.1.11.13237380'; // HUAWEI temperature base
            $tempOidGeneric = '.1.3.6.1.4.1.2011.5.25.31.1.1.1.1.11.13242060'; // Alternative HUAWEI temperature
            $tempOidAlternate = '1.3.6.1.4.1.2011.6.3.3.4.1.1.3'; // Another HUAWEI temperature OID
            $tempOidSystem = '1.3.6.1.4.1.2011.6.3.1.1.1.9'; // HUAWEI system temperature
            $ontTableOid = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.1'; // HUAWEI ONT table base
        } else {
            // ZTE specific OIDs (default)
            $tempOidBase = '1.3.6.1.4.1.3902.1012.3.28.1.1.3'; // OLT temperature base (ZTE specific)
            $tempOidGeneric = '1.3.6.1.4.1.3902.1012.3.50.12.2.1.8'; // Alternative temperature OID
            $ontTableOid = '1.3.6.1.4.1.3902.1012.3.13.1.1.1'; // ONT table base (ZTE specific)
        }
        
        try {
            // Get uptime
            $uptimeRaw = @snmp2_get($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $uptimeOid, 1000000, 2);
            $uptime = $this->formatUptime($uptimeRaw);
            
            // Get temperature using snmpwalk - try multiple OIDs for HUAWEI
            $temperature = 'N/A';
            if (strtoupper($olt->tipe) === 'HUAWEI MA5630T') {
                // Try multiple HUAWEI temperature OIDs
                $huaweiTempOids = [$tempOidBase, $tempOidGeneric, $tempOidAlternate, $tempOidSystem];
                foreach ($huaweiTempOids as $tempOid) {
                    $tempData = @snmp2_walk($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $tempOid, 1000000, 2);
                    if ($tempData && !empty($tempData)) {
                        $temperature = $this->parseTemperatureFromWalk($tempData, $olt->tipe);
                        if ($temperature !== 'N/A') {
                            break; // Found valid temperature, stop trying other OIDs
                        }
                    }
                }
                
                // If still N/A, try individual SNMP get for specific temperature OIDs
                if ($temperature === 'N/A') {
                    $specificOids = [
                        '1.3.6.1.4.1.2011.6.3.3.2.1.1.9.0',    // Specific temperature OID
                        '1.3.6.1.4.1.2011.6.3.3.4.1.1.3.0',    // Alternative specific OID
                        '1.3.6.1.4.1.2011.6.3.1.1.1.9.0'       // System temperature OID
                    ];
                    
                    foreach ($specificOids as $oid) {
                        $tempValue = @snmp2_get($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $oid, 1000000, 2);
                        if ($tempValue !== false) {
                            $temperature = $this->parseTemperatureValue($tempValue);
                            if ($temperature !== 'N/A') {
                                break;
                            }
                        }
                    }
                }
            } else {
                // ZTE logic (existing)
                $tempData = @snmp2_walk($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $tempOidBase, 1000000, 2);
                if (!$tempData || empty($tempData)) {
                    // Try alternative temperature OID
                    $tempData = @snmp2_walk($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $tempOidGeneric, 1000000, 2);
                }
                $temperature = $this->parseTemperatureFromWalk($tempData, $olt->tipe);
            }
            
            // Get ONT count using snmpwalk
            $ontData = @snmp2_walk($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $ontTableOid, 1000000, 2);
            $activePorts = $this->countActivePorts($ontData, $olt->tipe);
            
            if ($uptimeRaw !== false || $tempData !== false || $ontData !== false) {
                $result['success'] = true;
                $result['data'] = [
                    'uptime' => $uptime ?: 'N/A',
                    'temperature' => $temperature ?: 'N/A',
                    'active_ports' => $activePorts ?: '0',
                    'olt_type' => $olt->tipe,
                ];
            } else {
                $result['message'] = 'Gagal mengambil data SNMP untuk ' . $olt->tipe;
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Helper method untuk format uptime
    private function formatUptime($uptimeRaw)
    {
        if (!$uptimeRaw) return 'N/A';
        
        // Extract timeticks value
        preg_match('/\((\d+)\)/', $uptimeRaw, $matches);
        if (isset($matches[1])) {
            $ticks = intval($matches[1]);
            $seconds = $ticks / 100; // Timeticks are in centiseconds
            
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            
            return sprintf('%dd %dh %dm', $days, $hours, $minutes);
        }
        
        return 'N/A';
    }

    // Helper method untuk parse temperature dari snmpwalk
    private function parseTemperatureFromWalk($tempData, $oltType = 'ZTE')
    {
        if (!$tempData || !is_array($tempData)) return 'N/A';
        
        // Different parsing patterns based on OLT type
        if (strtoupper($oltType) === 'HUAWEI MA5630T') {
            // HUAWEI temperature patterns - more comprehensive
            $patterns = [
                '/INTEGER:\s*(-?\d+)/',              // INTEGER: 45 or INTEGER: -5
                '/Gauge32:\s*(\d+)/',                // Gauge32: 45
                '/Counter32:\s*(\d+)/',              // Counter32: 45
                '/(\d+)\s*degrees?C?/i',             // 45 degrees or 45 degreesC
                '/temp.*?(-?\d+)/i',                 // temp: 45
                '/(-?\d{1,2})\s*C$/i',               // 45C or -5C
                '/\b(-?\d{1,3})\b/'                  // Just digits (1-3 digits, including negative)
            ];
            
            // HUAWEI typically returns temperature in degrees Celsius directly
            $validRange = [-20, 90]; // HUAWEI equipment valid range (broader range)
        } else {
            // ZTE temperature patterns (existing)
            $patterns = [
                '/INTEGER:\s*(\d+)/',               // INTEGER: 45
                '/(\d+)\s*degrees?/',               // 45 degrees
                '/(\d+)\s*°C/',                     // 45°C
                '/temp.*?(\d+)/',                   // temp: 45
                '/(\d{1,2})/'                       // Just digits (1-2 digits)
            ];
            
            $validRange = [15, 85]; // ZTE equipment valid range
        }
        
        // Loop through temperature data
        foreach ($tempData as $oid => $value) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value, $matches)) {
                    $temp = intval($matches[1]);
                    // Check if temperature is in valid range for the OLT type
                    if ($temp >= $validRange[0] && $temp <= $validRange[1]) {
                        return $temp;
                    }
                }
            }
        }
        
        // If no valid temperature found in range, try with broader range
        foreach ($tempData as $oid => $value) {
            if (preg_match('/(-?\d+)/', $value, $matches)) {
                $temp = intval($matches[1]);
                if ($temp > -50 && $temp < 150) { // Very broad range
                    return $temp;
                }
            }
        }
        
        return 'N/A';
    }

    

    // Helper method untuk parse temperature (kept for backward compatibility)
    private function parseTemperature($tempRaw)
    {
        if (!$tempRaw) return 'N/A';
        
        // Extract temperature value (implementation depends on OLT model)
        preg_match('/(\d+)/', $tempRaw, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }
        
        return 'N/A';
    }

    

    // Telnet test dengan dukungan multi-vendor
    private function testTelnet($ip, $port, $user, $pass, $oltType = 'ZTE')
    {
        $timeout = 5; // Increased timeout for HUAWEI
        $result = ['success' => false, 'message' => ''];
        
        // Test koneksi socket
        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (!$fp) {
            $result['message'] = "Gagal koneksi ke $ip:$port ($errstr)";
            return $result;
        }
        
        stream_set_timeout($fp, $timeout);
        $allResponse = '';
        
        try {
            // Different login sequences based on OLT type
            if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                $result = $this->huaweiTelnetLogin($fp, $user, $pass, $allResponse);
            } else {
                $result = $this->zteTelnetLogin($fp, $user, $pass, $allResponse);
            }
        } catch (\Exception $e) {
            $result['message'] = 'Login error: ' . $e->getMessage();
        } finally {
            fclose($fp);
        }
        
        return $result;
    }
    
    // HUAWEI specific telnet login
    private function huaweiTelnetLogin($fp, $user, $pass, &$allResponse)
    {
        $result = ['success' => false, 'message' => ''];
        
        // Read initial prompt
        usleep(1000000); // 1 second - HUAWEI might be slower
        $welcome = fread($fp, 4096);
        $allResponse .= $welcome;
        
        // HUAWEI usually shows "Username:" prompt
        if (stripos($welcome, 'username') !== false || stripos($welcome, 'user name') !== false) {
            // Send username
            fwrite($fp, "$user\r\n");
            fflush($fp);
            
            usleep(800000); // 0.8 second
            $userResponse = fread($fp, 4096);
            $allResponse .= $userResponse;
            
            // Send password
            fwrite($fp, "$pass\r\n");
            fflush($fp);
            
            // Wait for login completion - HUAWEI might show additional prompts
            $maxAttempts = 8;
            $attempts = 0;
            while ($attempts < $maxAttempts) {
                usleep(500000); // 0.5 second
                $response = fread($fp, 4096);
                if ($response !== false && $response !== '') {
                    $allResponse .= $response;
                    
                    // HUAWEI success indicators
                    if (strpos($response, '>') !== false || 
                        strpos($response, '#') !== false ||
                        stripos($response, 'MA5630T') !== false ||
                        stripos($response, 'huawei') !== false) {
                        break;
                    }
                }
                $attempts++;
            }
        } else {
            // Try generic login if no username prompt
            fwrite($fp, "$user\r\n");
            fflush($fp);
            usleep(1000000);
            
            $userResponse = fread($fp, 4096);
            $allResponse .= $userResponse;
            
            fwrite($fp, "$pass\r\n");
            fflush($fp);
            usleep(1000000);
            
            $loginResponse = fread($fp, 4096);
            $allResponse .= $loginResponse;
        }
        
        // Check for HUAWEI specific error messages
        $huaweiErrors = [
            'login failed', 'invalid user', 'invalid password', 
            'authentication failed', 'access denied', 'incorrect password',
            'user name error', 'password error'
        ];
        
        $hasError = false;
        foreach ($huaweiErrors as $error) {
            if (stripos($allResponse, $error) !== false) {
                $hasError = true;
                break;
            }
        }
        
        // Check for HUAWEI success indicators
        $huaweiSuccess = [
            '>', '#', 'MA5630T', 'huawei', 'welcome', 'successful',
            'system view', 'user view'
        ];
        
        $hasSuccess = false;
        foreach ($huaweiSuccess as $indicator) {
            if (stripos($allResponse, $indicator) !== false) {
                $hasSuccess = true;
                break;
            }
        }
        
        if ($hasSuccess && !$hasError) {
            $result['success'] = true;
            $result['message'] = 'Koneksi Telnet HUAWEI berhasil';
        } else if ($hasError) {
            $result['message'] = 'Koneksi Telnet HUAWEI gagal - Username/Password salah';
        } else {
            $result['message'] = 'Koneksi Telnet HUAWEI gagal - Timeout atau tidak dapat login';
        }
        
        return $result;
    }
    
    // ZTE specific telnet login (existing logic)
    private function zteTelnetLogin($fp, $user, $pass, &$allResponse)
    {
        $result = ['success' => false, 'message' => ''];
        
        // Baca welcome message
        $welcome = fgets($fp, 1024);
        $allResponse .= $welcome;
        
        // Tunggu prompt username dan kirim user
        usleep(500000); // 0.5 detik
        fwrite($fp, "$user\r\n");
        fflush($fp);
        
        // Baca respons setelah username
        usleep(500000);
        $userResponse = fgets($fp, 1024);
        $allResponse .= $userResponse;
        
        // Kirim password
        fwrite($fp, "$pass\r\n");
        fflush($fp);
        
        // Baca respons setelah password dengan timeout yang lebih ketat
        $maxAttempts = 5;
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            usleep(200000); // 0.2 detik
            $response = fgets($fp, 1024);
            if ($response !== false) {
                $allResponse .= $response;
                // Jika sudah dapat prompt sukses, break
                if (strpos($response, '>') !== false || strpos($response, '#') !== false) {
                    break;
                }
            }
            $attempts++;
        }
        
        // Cek kata-kata yang menandakan error/gagal login
        $errorKeywords = ['incorrect', 'invalid', 'fail', 'authentication failed', 'login failed', 'access denied', 'bad password', 'wrong password'];
        $hasError = false;
        foreach ($errorKeywords as $keyword) {
            if (stripos($allResponse, $keyword) !== false) {
                $hasError = true;
                break;
            }
        }
        
        // Cek kata-kata yang menandakan sukses
        $successIndicators = [
            '>', '#', 'welcome', 'logged in', 'login successful'
        ];
        $hasSuccess = false;
        foreach ($successIndicators as $indicator) {
            if (stripos($allResponse, $indicator) !== false) {
                $hasSuccess = true;
                break;
            }
        }
        
        if ($hasSuccess && !$hasError) {
            $result['success'] = true;
            $result['message'] = 'Koneksi Telnet ZTE berhasil';
        } else if ($hasError) {
            $result['message'] = 'Koneksi Telnet ZTE gagal - Username/Password salah';
        } else {
            $result['message'] = 'Koneksi Telnet ZTE gagal - Tidak dapat login atau timeout';
        }
        
        return $result;
    }

    // SNMP test dengan dukungan multi-vendor
    private function testSnmp($ip, $community, $port, $oltType = 'ZTE')
    {
        $result = ['success' => false, 'message' => ''];
        $oid = '1.3.6.1.2.1.1.1.0'; // sysDescr OID (universal)
        
        try {
            $snmp = @snmp2_get($ip . ":$port", $community, $oid, 1000000, 2);
            if ($snmp !== false) {
                // Verify that the response matches the expected OLT type
                $responseText = strtolower($snmp);
                
                if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                    // Check for HUAWEI identifiers in SNMP response
                    if (strpos($responseText, 'huawei') !== false || 
                        strpos($responseText, 'ma5630') !== false ||
                        strpos($responseText, '5630t') !== false) {
                        $result['success'] = true;
                        $result['message'] = 'Koneksi SNMP HUAWEI berhasil: ' . htmlspecialchars(substr($snmp, 0, 100));
                    } else {
                        // SNMP works but might not be HUAWEI
                        $result['success'] = true;
                        $result['message'] = 'Koneksi SNMP berhasil (tipe belum terverifikasi): ' . htmlspecialchars(substr($snmp, 0, 100));
                    }
                } else {
                    // For ZTE or other types
                    if (strpos($responseText, 'zte') !== false || 
                        strpos($responseText, 'c300') !== false ||
                        strpos($responseText, 'c320') !== false) {
                        $result['success'] = true;
                        $result['message'] = 'Koneksi SNMP ZTE berhasil: ' . htmlspecialchars(substr($snmp, 0, 100));
                    } else {
                        // SNMP works but might not be ZTE
                        $result['success'] = true;
                        $result['message'] = 'Koneksi SNMP berhasil: ' . htmlspecialchars(substr($snmp, 0, 100));
                    }
                }
            } else {
                $result['message'] = 'Koneksi SNMP gagal - Periksa community string dan port SNMP';
            }
        } catch (\Exception $e) {
            $result['message'] = 'SNMP Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Helper method untuk menghitung total port aktif berdasarkan tipe OLT
    private function countActivePorts($ontData, $oltType = 'ZTE')
    {
        if (!$ontData || !is_array($ontData)) return '0';
        
        $activePorts = 0;
        
        if (strtoupper($oltType) === 'HUAWEI MA5630T') {
            // HUAWEI specific parsing
            foreach ($ontData as $oid => $value) {
                // HUAWEI uses different status values
                if (preg_match('/INTEGER:\s*1/', $value) ||           // Status 1 (online)
                    preg_match('/INTEGER:\s*2/', $value) ||           // Status 2 (working)
                    stripos($value, 'online') !== false ||           // Contains "online"
                    stripos($value, 'working') !== false ||          // Contains "working"
                    stripos($value, 'activated') !== false ||        // Contains "activated"
                    preg_match('/\bonline\b/i', $value) ||           // Word "online"
                    preg_match('/\bactivated\b/i', $value)) {        // Word "activated"
                    $activePorts++;
                }
            }
        } else {
            // ZTE specific parsing (existing logic)
            foreach ($ontData as $oid => $value) {
                // Check for active/online port status
                if (preg_match('/INTEGER:\s*1/', $value) ||           // Status 1 (active)
                    stripos($value, 'online') !== false ||           // Contains "online"
                    stripos($value, 'active') !== false ||           // Contains "active"
                    stripos($value, 'up') !== false ||               // Contains "up"
                    preg_match('/\bup\b/i', $value) ||               // Word "up"
                    preg_match('/\bonline\b/i', $value)) {           // Word "online"
                    $activePorts++;
                }
            }
        }
        
        return (string)$activePorts;
    }

    public function syncVlans(Olt $olt)
    {
        $result = ['success' => false, 'message' => '', 'data' => []];
        
        try {
            // Check if OLT type contains HUAWEI
            if (stripos($olt->tipe, 'HUAWEI') !== false) {
                $syncResult = $this->syncHuaweiVlanProfiles($olt->ip, $olt->port, $olt->user, $olt->pass, $olt->id);
                
                if ($syncResult['success']) {
                    $result['success'] = true;
                    $result['message'] = 'HUAWEI VLAN profiles berhasil disinkronisasi';
                    $result['data'] = $syncResult['data'];
                } else {
                    $result['message'] = 'Gagal sync HUAWEI VLAN profiles: ' . $syncResult['message'];
                }
            } else {
                // For non-HUAWEI OLT (existing logic)
                $vlanData = $this->getVlanProfilesViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass, $olt->tipe);
                
                if ($vlanData['success']) {
                    // Clear existing VLAN profiles for this OLT
                    VlanProfile::where('olt_id', $olt->id)->delete();
                    
                    $savedProfiles = 0;
                    foreach ($vlanData['profiles'] as $profile) {
                        VlanProfile::create([
                            'olt_id' => $olt->id,
                            'profile_name' => $profile['name'],
                            'profile_id' => $profile['id'],
                            'vlan_data' => $profile['vlans'],
                            'vlan_count' => count($profile['vlans']),
                            'last_updated' => now(),
                        ]);
                        $savedProfiles++;
                    }
                    
                    $result['success'] = true;
                    $result['message'] = "Berhasil menyimpan {$savedProfiles} VLAN profile";
                    $result['data'] = ['profiles_count' => $savedProfiles];
                } else {
                    $result['message'] = $vlanData['message'];
                }
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    public function getVlanProfiles(Olt $olt)
    {
        $profiles = VlanProfile::where('olt_id', $olt->id)
            ->orderBy('profile_name')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $profiles
        ]);
    }

    // Helper method untuk mengambil VLAN profiles via Telnet
    private function getVlanProfilesViaTelnet($ip, $port, $user, $pass, $oltType = 'ZTE')
    {
        $timeout = 5; // Reduced timeout
        $result = ['success' => false, 'message' => '', 'profiles' => []];
        
        try {
            // Test koneksi socket
            $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if (!$fp) {
                $result['message'] = "Gagal koneksi ke $ip:$port ($errstr)";
                return $result;
            }
            
            stream_set_timeout($fp, $timeout);
            
            // Login process
            $loginResult = $this->telnetLogin($fp, $user, $pass);
            if (!$loginResult) {
                fclose($fp);
                $result['message'] = 'Gagal login ke OLT ' . $oltType;
                return $result;
            }
            
            // Send enable command for HUAWEI before other commands
            if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                fwrite($fp, "enable\r\n");
                fflush($fp);
                usleep(1000000); // Wait for enable to process
                $enableResp = fread($fp, 4096);
            }
            
            // Send appropriate command based on OLT type
            if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                // HUAWEI commands for VLAN profile
                $commands = [
                    "display vlan all\r\n",                          // Main VLAN command
                    "display ont-lineprofile gpon all\r\n",           // ONT line profiles
                    "display service-profile all\r\n",               // Service profiles
                    "display ont-profile service all\r\n"            // Another alternative
                ];
            } else {
                // ZTE commands (existing)
                $commands = ["show gpon onu profile vlan\r\n"];
            }
            
            $response = '';
            foreach ($commands as $command) {
                fwrite($fp, $command);
                fflush($fp);
                
                // Add extra delay for HUAWEI commands
                if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                    usleep(1500000); // 1.5 seconds for HUAWEI
                }
                
                // Read response with improved logic
                $cmdResponse = $this->readTelnetResponse($fp, $timeout, $oltType);
                $response .= $cmdResponse;
                
                // If we got substantial response, break
                if (strlen($cmdResponse) > 200) {
                    break;
                }
                
                usleep(1000000); // 1 second between commands
            }
            
            fclose($fp);
            
            // Debug: Log the response
            error_log($oltType . ' VLAN Response Length: ' . strlen($response));
            error_log($oltType . ' VLAN Response Preview: ' . substr($response, 0, 500));
            
            // Parse the response based on OLT type
            if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                $profiles = $this->parseHuaweiVlanProfileResponse($response);
            } else {
                $profiles = $this->parseVlanProfileResponse($response);
            }
            
            if (!empty($profiles)) {
                $result['success'] = true;
                $result['profiles'] = $profiles;
                $result['message'] = 'VLAN profiles ' . $oltType . ' berhasil diambil (' . count($profiles) . ' profiles)';
            } else {
                $result['message'] = 'Tidak ada VLAN profile ditemukan untuk ' . $oltType . '. Response length: ' . strlen($response);
            }
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    // Helper method untuk membaca response telnet berdasarkan tipe OLT
    private function readTelnetResponse($fp, $timeout, $oltType)
    {
        $response = '';
        $maxWait = ($oltType === 'HUAWEI MA5630T') ? 20 : 10; // HUAWEI might be slower
        $waited = 0;
        $lastDataTime = time();
        
        while ($waited < $maxWait) {
            $data = fread($fp, 4096);
            if ($data !== false && $data !== '') {
                $response .= $data;
                $lastDataTime = time();
                
                // Check for completion based on OLT type
                if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                    // HUAWEI prompts and more indicators - more patterns
                    if (preg_match('/[\#\>]\s*$/', $response) || 
                        strpos($response, '---- More ----') !== false ||
                        strpos($response, 'Press any key to continue') !== false ||
                        strpos($response, '<Fans.Net-') !== false ||
                        strpos($response, 'Total:') !== false) {
                        
                        if (strpos($response, '---- More ----') !== false || 
                            strpos($response, 'Press any key to continue') !== false) {
                            fwrite($fp, " "); // Send space to continue
                            fflush($fp);
                            usleep(500000); // Wait longer for HUAWEI
                        } else {
                            // Check if we have substantial content before breaking
                            if (strlen($response) > 100) {
                                break; // Command completed
                            }
                        }
                    }
                } else {
                    // ZTE prompts (existing logic)
                    if (preg_match('/[\#\>]\s*$/', $response) || 
                        strpos($response, 'More:') !== false ||
                        strpos($response, '--More--') !== false) {
                        
                        if (strpos($response, 'More:') !== false || strpos($response, '--More--') !== false) {
                            fwrite($fp, " ");
                            fflush($fp);
                        } else {
                            break;
                        }
                    }
                }
            }
            
            // Break if no data received for too long
            if (time() - $lastDataTime > 8) { // Increased timeout for HUAWEI
                break;
            }
            
            usleep(300000); // Increased wait time for HUAWEI
            $waited++;
        }
        
        // Debug log
        error_log("readTelnetResponse for $oltType: Length = " . strlen($response) . ", Waited = $waited");
        
        return $response;
    }

    // Helper method untuk login telnet dengan dukungan multi-vendor
    private function telnetLogin($fp, $user, $pass, $oltType = 'ZTE')
    {
        try {
            if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                return $this->huaweiTelnetLoginSimple($fp, $user, $pass);
            } else {
                return $this->zteTelnetLoginSimple($fp, $user, $pass);
            }
        } catch (\Exception $e) {
            return false;
        }
    }
    
    // Simplified HUAWEI login for internal use
    private function huaweiTelnetLoginSimple($fp, $user, $pass)
    {
        // Read initial message
        usleep(1000000); // 1 second
        $welcome = fread($fp, 4096);
        
        // Send username
        fwrite($fp, "$user\r\n");
        fflush($fp);
        
        usleep(800000); // 0.8 second
        $userResponse = fread($fp, 4096);
        
        // Send password
        fwrite($fp, "$pass\r\n");
        fflush($fp);
        
        usleep(1500000); // 1.5 seconds - HUAWEI might be slower
        $loginResponse = fread($fp, 4096);
        
        // Check for successful login
        $allResponse = $welcome . $userResponse . $loginResponse;
        
        if (strpos($allResponse, '#') !== false || 
            strpos($allResponse, '>') !== false ||
            stripos($allResponse, 'MA5630T') !== false ||
            stripos($allResponse, 'welcome') !== false) {
            
            return true;
        }
        
        return false;
    }
    
    // Simplified ZTE login for internal use
    private function zteTelnetLoginSimple($fp, $user, $pass)
    {
        try {
            // Read welcome message
            usleep(500000); // 0.5 second
            $welcome = fread($fp, 4096);
            
            // Send username
            fwrite($fp, "$user\r\n");
            fflush($fp);
            
            usleep(500000); // 0.5 second
            $userResponse = fread($fp, 4096);
            
            // Send password
            fwrite($fp, "$pass\r\n");
            fflush($fp);
            
            usleep(1000000); // 1 second
            $loginResponse = fread($fp, 4096);
            
            // Check for successful login indicators
            $allResponse = $welcome . $userResponse . $loginResponse;
            
            // Look for success indicators
            if (strpos($allResponse, '#') !== false || 
                strpos($allResponse, '>') !== false ||
                stripos($allResponse, 'welcome') !== false) {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    // Helper method untuk parse response VLAN profile
    private function parseVlanProfileResponse($response)
    {
        $profiles = [];
        
        // If response is too short, create a dummy profile for testing
        if (strlen($response) < 50) {
            return [[
                'name' => 'default',
                'id' => 'default',
                'vlans' => [
                    ['vlan_id' => 100, 'description' => 'Default VLAN 100'],
                    ['vlan_id' => 200, 'description' => 'Default VLAN 200']
                ]
            ]];
        }
        
        $lines = explode("\n", $response);
        $currentProfile = null;
        $inVlanSection = false;
          foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Look for direct "name: ProfileName" pattern first
            if (preg_match('/^name\s*[:\-]\s*(.+)/i', $line, $nameMatches)) {
                // Save previous profile if exists
                if ($currentProfile) {
                    $profiles[] = $currentProfile;
                }
                
                $profileName = trim($nameMatches[1]);
                $currentProfile = [
                    'name' => $profileName,
                    'id' => $profileName,
                    'vlans' => []
                ];
                $inVlanSection = false;
                continue;
            }

            // Look for profile headers - various patterns
            if (preg_match('/(?:ONU-Profile|Profile|profile)\s*[:\-]?\s*(.+)/i', $line, $matches)) {
                // Save previous profile if exists
                if ($currentProfile) {
                    $profiles[] = $currentProfile;
                }
                
                $profileName = trim($matches[1]);
                
                // Remove "name:" prefix if present (fix for the issue)
                if (preg_match('/^name\s*[:\-]\s*(.+)/i', $profileName, $nameMatches)) {
                    $profileName = trim($nameMatches[1]);
                }
                
                $currentProfile = [
                    'name' => $profileName,
                    'id' => $profileName,
                    'vlans' => []
                ];
                $inVlanSection = false;
                continue;
            }
            
            // Look for VLAN section start
            if (stripos($line, 'vlan') !== false) {
                $inVlanSection = true;
            }
            
            // Parse VLAN entries
            if ($inVlanSection) {
                // Multiple VLAN patterns
                $patterns = [
                    '/vlan\s*[:\-]?\s*(\d+)/i',     // vlan: 100, vlan-100, vlan 100
                    '/(\d+)\s*vlan/i',              // 100 vlan
                    '/^\s*(\d+)\s*$/',              // Just number on line
                    '/id\s*[:\-]?\s*(\d+)/i'        // id: 100
                ];
                
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $line, $matches)) {
                        $vlanId = intval($matches[1]);
                        if ($vlanId > 0 && $vlanId <= 4094) {
                            // Start new profile if none exists
                            if (!$currentProfile) {
                                $currentProfile = [
                                    'name' => 'default-profile',
                                    'id' => 'default-profile',
                                    'vlans' => []
                                ];
                            }
                            
                            // Avoid duplicates
                            $exists = false;
                            foreach ($currentProfile['vlans'] as $existingVlan) {
                                if ($existingVlan['vlan_id'] == $vlanId) {
                                    $exists = true;
                                    break;
                                }
                            }
                            
                            if (!$exists) {
                                $currentProfile['vlans'][] = [
                                    'vlan_id' => $vlanId,
                                    'description' => $line
                                ];
                            }
                            break; // Found VLAN, no need to check other patterns
                        }
                    }
                }
            }
        }
        
        // Save last profile
        if ($currentProfile) {
            $profiles[] = $currentProfile;
        }
        
        // If no profiles found, create a test profile
        if (empty($profiles) && strlen($response) > 50) {
            $profiles[] = [
                'name' => 'parsed-data',
                'id' => 'parsed-data',
                'vlans' => [
                    ['vlan_id' => 999, 'description' => 'Parsed from: ' . substr($response, 0, 100)]
                ]
            ];
        }
        
        return $profiles;
    }

    // Helper method untuk parse response VLAN profile HUAWEI
    private function parseHuaweiVlanProfileResponse($response)
    {
        $profiles = [];
        
        // If response is too short, create a dummy profile for testing
        if (strlen($response) < 50) {
            return [[
                'name' => 'default-huawei',
                'id' => 'default-huawei',
                'vlans' => [
                    ['vlan_id' => 100, 'description' => 'Default HUAWEI VLAN 100'],
                    ['vlan_id' => 200, 'description' => 'Default HUAWEI VLAN 200']
                ]
            ]];
        }
        
        $lines = explode("\n", $response);
        $currentProfile = null;
        $inVlanSection = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // HUAWEI VLAN patterns
            // Look for VLAN entries in HUAWEI format
            if (preg_match('/VLAN\s*(\d+)/i', $line, $matches)) {
                $vlanId = intval($matches[1]);
                if ($vlanId > 0 && $vlanId <= 4094) {
                    // Start new profile if none exists
                    if (!$currentProfile) {
                        $currentProfile = [
                            'name' => 'huawei-profile',
                            'id' => 'huawei-profile',
                            'vlans' => []
                        ];
                    }
                    
                    // Avoid duplicates
                    $exists = false;
                    foreach ($currentProfile['vlans'] as $existingVlan) {
                        if ($existingVlan['vlan_id'] == $vlanId) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $currentProfile['vlans'][] = [
                            'vlan_id' => $vlanId,
                            'description' => $line
                        ];
                    }
                }
            }
            
            // Look for service profile patterns
            if (preg_match('/service-profile\s+(\w+)/i', $line, $matches) || 
                preg_match('/ont-lineprofile\s+(\w+)/i', $line, $matches)) {
                // Save previous profile if exists
                if ($currentProfile) {
                    $profiles[] = $currentProfile;
                }
                
                $profileName = trim($matches[1]);
                $currentProfile = [
                    'name' => $profileName,
                    'id' => $profileName,
                    'vlans' => []
                ];
                continue;
            }
        }
        
        // Save last profile
        if ($currentProfile) {
            $profiles[] = $currentProfile;
        }
        
        // If no profiles found, create a test profile
        if (empty($profiles) && strlen($response) > 50) {
            $profiles[] = [
                'name' => 'huawei-parsed-data',
                'id' => 'huawei-parsed-data',
                'vlans' => [
                    ['vlan_id' => 999, 'description' => 'HUAWEI Parsed from: ' . substr($response, 0, 100)]
                ]
            ];
        }
        
        return $profiles;
    }

    public function testVlanCommand(Olt $olt)
    {
        $result = ['success' => false, 'message' => '', 'data' => []];
        
        try {
            // Quick test with simple command first
            $testResult = $this->quickTelnetTest($olt->ip, $olt->port, $olt->user, $olt->pass, $olt->tipe);
            
            if ($testResult['success']) {
                $result['success'] = true;
                $result['message'] = 'Koneksi telnet berhasil. Response: ' . substr($testResult['response'], 0, 200) . '...';
                $result['data'] = ['response_length' => strlen($testResult['response'])];
            } else {
                $result['message'] = $testResult['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Quick telnet test untuk debugging dengan dukungan multi-vendor
    private function quickTelnetTest($ip, $port, $user, $pass, $oltType = 'ZTE')
    {
        $timeout = 3;
        $result = ['success' => false, 'message' => '', 'response' => ''];
        
        try {
            $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if (!$fp) {
                $result['message'] = "Gagal koneksi ke $ip:$port ($errstr)";
                return $result;
            }
            
            stream_set_timeout($fp, $timeout);
            
            // Login
            $loginResult = $this->telnetLogin($fp, $user, $pass, $oltType);
            if (!$loginResult) {
                fclose($fp);
                $result['message'] = 'Gagal login ke ' . $oltType;
                return $result;
            }
            
            // Send appropriate test command based on OLT type
            if (strtoupper($oltType) === 'HUAWEI MA5630T') {
                // Send enable first for HUAWEI
                fwrite($fp, "enable\r\n");
                fflush($fp);
                usleep(1000000); // Wait for enable
                $enableResp = fread($fp, 4096);
                
                fwrite($fp, "display vlan all\r\n");
                fflush($fp);
                usleep(1500000); // 1.5 seconds wait for HUAWEI to process
            } else {
                fwrite($fp, "show version\r\n");
                fflush($fp);
            }
            
            // Read response
            usleep(2000000); // 2 seconds
            $response = fread($fp, 8192); // Increased buffer for HUAWEI
            
            fclose($fp);
            
            $result['success'] = true;
            $result['response'] = $response;
            $result['message'] = 'Test ' . $oltType . ' berhasil';
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    public function getVlanProfilesView(Olt $olt)
    {
        $profiles = VlanProfile::where('olt_id', $olt->id)
            ->orderBy('profile_type')
            ->orderBy('profile_name')
            ->get();
            
        if ($profiles->count() > 0) {
            $html = '<div class="table-responsive">';
            $html .= '<table class="table table-bordered table-striped">';
            $html .= '<thead><tr><th>Type</th><th>Profile Name</th><th>Profile ID</th><th>Count/Info</th><th>Last Updated</th><th>Details</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($profiles as $profile) {
                $html .= '<tr>';
                
                // Profile Type with badge
                $typeColor = match($profile->profile_type ?? 'vlan') {
                    'vlan' => 'bg-primary',
                    'line_profile' => 'bg-success', 
                    'service_profile' => 'bg-warning'
                };
                $html .= '<td><span class="badge ' . $typeColor . '">' . ucfirst(str_replace('_', ' ', $profile->profile_type ?? 'vlan')) . '</span></td>';
                
                // Clean profile name for display (remove TYPE suffix)
                $displayName = preg_replace('/_TYPE_(vlan|line_profile|service_profile)$/', '', $profile->profile_name);
                $html .= '<td>' . htmlspecialchars($displayName) . '</td>';
                $html .= '<td>' . htmlspecialchars($profile->profile_id) . '</td>';
                
                // Count/Info column based on type
                if ($profile->profile_type === 'vlan') {
                    $html .= '<td>' . $profile->vlan_count . ' VLANs</td>';
                } else {
                    $data = $profile->vlan_data;
                    if (is_array($data) && isset($data['binding_times'])) {
                        $html .= '<td>' . $data['binding_times'] . ' bindings</td>';
                    } else {
                        $html .= '<td>-</td>';
                    }
                }
                
                $html .= '<td>' . ($profile->last_updated ? $profile->last_updated->format('d/m/Y H:i:s') : 'N/A') . '</td>';
                $html .= '<td>';
                
                // Details column based on type
                if ($profile->profile_type === 'vlan' && $profile->vlan_data && count($profile->vlan_data) > 0) {
                    $html .= '<div class="vlan-list">';
                    foreach ($profile->vlan_data as $vlan) {
                        if (isset($vlan['vlan_id'])) {
                            $html .= '<span class="badge bg-primary me-1 mb-1">VLAN ' . $vlan['vlan_id'] . '</span>';
                        } else {
                            // For HUAWEI VLAN format
                            $html .= '<span class="badge bg-primary me-1 mb-1">VLAN ' . ($vlan['id'] ?? 'N/A') . '</span>';
                        }
                    }
                    $html .= '</div>';
                } elseif (in_array($profile->profile_type, ['line_profile', 'service_profile']) && $profile->vlan_data) {
                    $data = $profile->vlan_data;
                    if (is_array($data)) {
                        $html .= '<small>';
                        if (isset($data['binding_times'])) {
                            $html .= 'Bindings: ' . $data['binding_times'];
                        }
                        if (isset($data['type'])) {
                            $html .= '<br>Type: ' . $data['type'];
                        }
                        if (isset($data['attribute'])) {
                            $html .= '<br>Attribute: ' . $data['attribute'];
                        }
                        $html .= '</small>';
                    }
                } else {
                    $html .= '<span class="text-muted">No details</span>';
                }
                
                $html .= '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></div>';
            
            // Add summary for HUAWEI
            if (stripos($olt->tipe, 'HUAWEI') !== false) {
                $vlanCount = $profiles->where('profile_type', 'vlan')->count();
                $lineCount = $profiles->where('profile_type', 'line_profile')->count();
                $serviceCount = $profiles->where('profile_type', 'service_profile')->count();
                
                $html .= '<div class="mt-3">';
                $html .= '<h6>Summary:</h6>';
                $html .= '<div class="row">';
                $html .= '<div class="col-md-4"><span class="badge bg-primary">' . $vlanCount . '</span> VLANs</div>';
                $html .= '<div class="col-md-4"><span class="badge bg-success">' . $lineCount . '</span> Line Profiles</div>';
                $html .= '<div class="col-md-4"><span class="badge bg-warning">' . $serviceCount . '</span> Service Profiles</div>';
                $html .= '</div></div>';
            }
            
            return $html;
        } else {
            return '<div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Belum ada VLAN profiles tersimpan. Silakan sync VLAN profiles terlebih dahulu.
                    </div>';
        }
    }

    // Helper method untuk parse single temperature value
    private function parseTemperatureValue($tempValue)
    {
        if (!$tempValue) return 'N/A';
        
        // Patterns to extract temperature value
        $patterns = [
            '/INTEGER:\s*(-?\d+)/',              // INTEGER: 45 or INTEGER: -5
            '/Gauge32:\s*(\d+)/',                // Gauge32: 45
            '/(\d+)\s*degrees?C?/i',             // 45 degrees or 45 degreesC
            '/temp.*?(-?\d+)/i',                 // temp: 45
            '/(-?\d{1,2})\s*C$/i',               // 45C or -5C
            '/(-?\d{1,3})/'                      // Just digits (1-3 digits, including negative)
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $tempValue, $matches)) {
                $temp = intval($matches[1]);
                // Check if temperature is in reasonable range
                if ($temp >= -40 && $temp <= 100) {
                    return $temp;
                }
            }
        }
        
        return 'N/A';
    }

    // Helper method untuk sync HUAWEI VLAN profiles dengan 3 perintah
    private function syncHuaweiVlanProfiles($ip, $port, $user, $pass, $oltId)
    {
        $timeout = 10; // Increased timeout for HUAWEI
        $result = ['success' => false, 'message' => '', 'data' => []];
        
        try {
            $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
            if (!$fp) {
                $result['message'] = "Gagal koneksi ke $ip:$port ($errstr)";
                return $result;
            }
            
            stream_set_timeout($fp, $timeout);
            
            // Login to HUAWEI OLT
            if (!$this->huaweiTelnetLoginSimple($fp, $user, $pass)) {
                fclose($fp);
                $result['message'] = 'Login gagal ke HUAWEI OLT';
                return $result;
            }
            
            // Send enable command first for HUAWEI
            fwrite($fp, "enable\r\n");
            fflush($fp);
            usleep(1000000); // Wait 1 second for enable to process
            $enableResponse = fread($fp, 4096);
            
            // Log enable response for debugging
            Log::info("HUAWEI Enable Response: " . $enableResponse);
            
            // Try to enter system view for some commands that might need it
            fwrite($fp, "system-view\r\n");
            fflush($fp);
            usleep(500000); // Wait 0.5 seconds
            $systemViewResponse = fread($fp, 4096);
            Log::info("HUAWEI System View Response: " . $systemViewResponse);
            
            // Return to user view 
            fwrite($fp, "quit\r\n");
            fflush($fp);
            usleep(500000);
            $quitResponse = fread($fp, 4096);
            
            // Array untuk menyimpan hasil dari 3 perintah
            $commands = [
                'display vlan all',
                'display ont-lineprofile gpon all', 
                'display ont-srvprofile gpon all'
            ];
            
            $commandResults = [];
            $allData = [];
            
            foreach ($commands as $index => $command) {
                // Log command being executed
                Log::info("=== HUAWEI Command Execution ===");
                Log::info("Command Index: $index");
                Log::info("Command: $command");
                Log::info("OLT IP: $ip");
                Log::info("Timestamp: " . date('Y-m-d H:i:s'));
                Log::info("Sending command to telnet session...");
                
                // Send command
                fwrite($fp, "$command\r\n");
                fflush($fp);
                Log::info("Command sent, waiting for response...");
                
                // Wait and read response
                usleep(2000000); // 2 seconds for HUAWEI to process
                Log::info("Reading response from telnet...");
                $response = $this->readTelnetResponse($fp, $timeout, 'HUAWEI MA5630T');
                Log::info("Response received, length: " . strlen($response) . " characters");
                
                // Log detailed response information
                Log::info("=== Command Response Details ===");
                Log::info("Response Length: " . strlen($response));
                Log::info("Response Preview (first 500 chars): " . substr($response, 0, 500));
                Log::info("Response Preview (last 500 chars): " . substr($response, -500));
                
                // Log the RAW TERMINAL OUTPUT with proper formatting
                Log::info("=== RAW TERMINAL OUTPUT START ===");
                Log::info("Command: $command");
                Log::info("Raw Output:");
                Log::info($response);
                Log::info("=== RAW TERMINAL OUTPUT END ===");
                
                // Log formatted output for better readability
                Log::info("=== FORMATTED OUTPUT START ===");
                $lines = explode("\n", $response);
                foreach ($lines as $lineNum => $line) {
                    Log::info("Line " . ($lineNum + 1) . ": " . trim($line));
                }
                Log::info("=== FORMATTED OUTPUT END ===");
                
                Log::info("Response contains 'VLAN': " . (stripos($response, 'VLAN') !== false ? 'YES' : 'NO'));
                Log::info("Response contains 'Profile': " . (stripos($response, 'Profile') !== false ? 'YES' : 'NO'));
                Log::info("Response contains 'Error': " . (stripos($response, 'Error') !== false ? 'YES' : 'NO'));
                Log::info("Response contains 'Total:': " . (stripos($response, 'Total:') !== false ? 'YES' : 'NO'));
                Log::info("=== End Command Response ===");
                
                
                
                // Create readable terminal output log
                $readableOutput = $this->formatTerminalOutput($command, $response);
                Log::info("=== READABLE TERMINAL OUTPUT ===");
                Log::info($readableOutput);
                Log::info("=== END READABLE OUTPUT ===");
                
                // Save terminal output to file for easier viewing
                $this->saveTerminalOutputToFile($command, $response, $ip, $index);
                
                // Detect and format table output specifically
                if (stripos($command, 'display vlan all') !== false) {
                    $this->logVlanTableFormat($response);
                } elseif (stripos($command, 'display ont-lineprofile') !== false) {
                    $this->logLineProfileTableFormat($response);
                } elseif (stripos($command, 'display ont-srvprofile') !== false) {
                    $this->logServiceProfileTableFormat($response);
                }
                
                // Log command and response for debugging
                Log::info("=== HUAWEI Command Summary ===");
                Log::info("HUAWEI Command: $command");
                Log::info("HUAWEI Response Length: " . strlen($response));
                Log::info("HUAWEI Response Preview: " . substr($response, 0, 300));
                Log::info("HUAWEI Response Full (for analysis): " . $response); // Full response for debugging
                
                // Log line count and character analysis
                $lines = explode("\n", $response);
                Log::info("HUAWEI Response Line Count: " . count($lines));
                Log::info("HUAWEI Response First 5 Lines: " . implode(" | ", array_slice($lines, 0, 5)));
                Log::info("HUAWEI Response Last 5 Lines: " . implode(" | ", array_slice($lines, -5)));
                
                // Log specific patterns found
                $vlanMatches = preg_match_all('/VLAN\s*(\d+)/i', $response, $vlanMatchesArray);
                $profileMatches = preg_match_all('/Profile.*?(\d+)/i', $response, $profileMatchesArray);
                Log::info("HUAWEI VLAN patterns found: $vlanMatches");
                Log::info("HUAWEI Profile patterns found: $profileMatches");
                if ($vlanMatches > 0) {
                    Log::info("HUAWEI VLAN IDs found: " . implode(", ", array_slice($vlanMatchesArray[1], 0, 10)));
                }
                Log::info("=== End Command Summary ===");
                
                $commandResults[$index] = [
                    'command' => $command,
                    'response' => $response,
                    'length' => strlen($response)
                ];
                
                // Parse based on command type
                if ($index === 0) {
                    // Parse VLAN data from 'display vlan all'
                    Log::info("=== PARSING VLAN DATA (Command 0) ===");
                    $vlans = $this->parseHuaweiVlanData($response);
                    $allData['vlans'] = $vlans;
                    Log::info("HUAWEI Command 0 (VLAN): Parsed " . count($vlans) . " VLANs");
                    if (!empty($vlans)) {
                        Log::info("HUAWEI Sample VLANs: " . json_encode(array_slice($vlans, 0, 3)));
                    } else {
                        Log::warning("HUAWEI WARNING: No VLANs parsed from response");
                    }
                    Log::info("=== END VLAN PARSING ===");
                } elseif ($index === 1) {
                    // Parse line profiles from 'display ont-lineprofile gpon all'
                    Log::info("=== PARSING LINE PROFILES (Command 1) ===");
                    $lineProfiles = $this->parseHuaweiLineProfiles($response);
                    $allData['line_profiles'] = $lineProfiles;
                    Log::info("HUAWEI Command 1 (Line Profiles): Parsed " . count($lineProfiles) . " profiles");
                    if (!empty($lineProfiles)) {
                        Log::info("HUAWEI Sample Line Profiles: " . json_encode(array_slice($lineProfiles, 0, 3)));
                    } else {
                        Log::warning("HUAWEI WARNING: No Line Profiles parsed from response");
                    }
                    Log::info("=== END LINE PROFILE PARSING ===");
                } elseif ($index === 2) {
                    // Parse service profiles from 'display ont-srvprofile gpon all'
                    Log::info("=== PARSING SERVICE PROFILES (Command 2) ===");
                    $srvProfiles = $this->parseHuaweiSrvProfiles($response);
                    $allData['service_profiles'] = $srvProfiles;
                    Log::info("HUAWEI Command 2 (Service Profiles): Parsed " . count($srvProfiles) . " profiles");
                    if (!empty($srvProfiles)) {
                        Log::info("HUAWEI Sample Service Profiles: " . json_encode(array_slice($srvProfiles, 0, 3)));
                    } else {
                        Log::warning("HUAWEI WARNING: No Service Profiles parsed from response");
                    }
                    Log::info("=== END SERVICE PROFILE PARSING ===");
                }
            }
            
            // Log final summary of all commands
            Log::info("=== HUAWEI SYNC COMMAND SUMMARY ===");
            Log::info("Total commands executed: " . count($commands));
            Log::info("Total VLANs found: " . count($allData['vlans'] ?? []));
            Log::info("Total Line Profiles found: " . count($allData['line_profiles'] ?? []));
            Log::info("Total Service Profiles found: " . count($allData['service_profiles'] ?? []));
            foreach ($commandResults as $idx => $cmdResult) {
                Log::info("Command $idx ('{$cmdResult['command']}'): {$cmdResult['length']} chars response");
            }
            Log::info("=== END SYNC SUMMARY ===");
            
            fclose($fp);
            
            // Clear existing VLAN profiles for this OLT using transaction for data consistency
            DB::transaction(function() use ($oltId) {
                VlanProfile::where('olt_id', $oltId)->delete();
            });
            
            $savedProfiles = 0;
            
            // Save VLAN data with duplicate handling
            if (!empty($allData['vlans'])) {
                foreach ($allData['vlans'] as $vlan) {
                    try {
                        $vlanProfile = VlanProfile::updateOrCreate(
                            [
                                'olt_id' => $oltId,
                                'profile_name' => 'VLAN_' . $vlan['id'] . '_TYPE_vlan'
                            ],
                            [
                                'profile_id' => $vlan['id'],
                                'vlan_data' => $vlan,
                                'vlan_count' => 1,
                                'profile_type' => 'vlan',
                                'last_updated' => now(),
                            ]
                        );
                        $savedProfiles++;
                        Log::info("HUAWEI VLAN Saved: " . $vlanProfile->profile_name);
                    } catch (\Exception $e) {
                        Log::error("HUAWEI VLAN Save Error: " . $e->getMessage());
                    }
                }
            }
            
            // Save line profiles with duplicate handling
            if (!empty($allData['line_profiles'])) {
                foreach ($allData['line_profiles'] as $profile) {
                    try {
                        $lineProfile = VlanProfile::updateOrCreate(
                            [
                                'olt_id' => $oltId,
                                'profile_name' => $profile['name'] . '_TYPE_line_profile'
                            ],
                            [
                                'profile_id' => $profile['id'],
                                'vlan_data' => $profile,
                                'vlan_count' => 0,
                                'profile_type' => 'line_profile',
                                'last_updated' => now(),
                            ]
                        );
                        $savedProfiles++;
                        Log::info("HUAWEI Line Profile Saved: " . $lineProfile->profile_name);
                    } catch (\Exception $e) {
                        Log::error("HUAWEI Line Profile Save Error: " . $e->getMessage());
                    }
                }
            }
            
            // Save service profiles with duplicate handling
            if (!empty($allData['service_profiles'])) {
                foreach ($allData['service_profiles'] as $profile) {
                    try {
                        $serviceProfile = VlanProfile::updateOrCreate(
                            [
                                'olt_id' => $oltId,
                                'profile_name' => $profile['name'] . '_TYPE_service_profile'
                            ],
                            [
                                'profile_id' => $profile['id'],
                                'vlan_data' => $profile,
                                'vlan_count' => 0,
                                'profile_type' => 'service_profile',
                                'last_updated' => now(),
                            ]
                        );
                        $savedProfiles++;
                        Log::info("HUAWEI Service Profile Saved: " . $serviceProfile->profile_name);
                    } catch (\Exception $e) {
                        Log::error("HUAWEI Service Profile Save Error: " . $e->getMessage());
                    }
                }
            }
            
            $result['success'] = true;
            $result['message'] = "Berhasil sync {$savedProfiles} profiles dari HUAWEI OLT";
            $result['data'] = [
                'profiles_count' => $savedProfiles,
                'vlans_count' => count($allData['vlans'] ?? []),
                'line_profiles_count' => count($allData['line_profiles'] ?? []),
                'service_profiles_count' => count($allData['service_profiles'] ?? []),
                'command_results' => $commandResults,
                'enable_response' => substr($enableResponse ?? '', 0, 100), // First 100 chars of enable response
                'debug_info' => [
                    'total_vlans_parsed' => count($allData['vlans'] ?? []),
                    'total_line_profiles_parsed' => count($allData['line_profiles'] ?? []),
                    'total_service_profiles_parsed' => count($allData['service_profiles'] ?? []),
                    'vlan_response_length' => $commandResults[0]['length'] ?? 0,
                    'line_profile_response_length' => $commandResults[1]['length'] ?? 0,
                    'service_profile_response_length' => $commandResults[2]['length'] ?? 0,
                    'vlan_sample_data' => array_slice($allData['vlans'] ?? [], 0, 3),
                    'line_profile_sample_data' => array_slice($allData['line_profiles'] ?? [], 0, 3),
                    'service_profile_sample_data' => array_slice($allData['service_profiles'] ?? [], 0, 3)
                ]
            ];
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    // Helper method untuk parse VLAN data dari HUAWEI 'display vlan all'
    private function parseHuaweiVlanData($response)
    {
        $vlans = [];
        $lines = explode("\n", $response);
        
        Log::info("HUAWEI VLAN Parsing - Total lines: " . count($lines));
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            
            Log::debug("HUAWEI VLAN Line $lineNum: '$line'");
            
            // Skip header lines and empty lines
            if (empty($line) || 
                stripos($line, 'VLAN') === 0 && stripos($line, 'Type') !== false ||
                stripos($line, '---') !== false ||
                stripos($line, 'Total:') !== false ||
                stripos($line, 'Command:') !== false ||
                stripos($line, 'Error:') !== false ||
                stripos($line, 'Invalid') !== false) {
                continue;
            }
            
            // Parse VLAN line format: VLAN_ID Type Attribute STND-Port_NUM SERV-Port_NUM VLAN-Con_NUM
            // Example: "     1   smart     common                 4               0             -"
            if (preg_match('/^\s*(\d+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(\d+)\s+(.*)$/', $line, $matches)) {
                $vlans[] = [
                    'id' => $matches[1],
                    'type' => $matches[2],
                    'attribute' => $matches[3],
                    'stnd_port_num' => $matches[4],
                    'serv_port_num' => $matches[5],
                    'vlan_con_num' => trim($matches[6])
                ];
                Log::info("HUAWEI VLAN Found: VLAN " . $matches[1]);
            }
            // More flexible pattern - just number at start of line
            elseif (preg_match('/^\s*(\d+)\s+(.*)$/', $line, $matches)) {
                $vlanId = $matches[1];
                if ($vlanId > 0 && $vlanId <= 4094 && strlen(trim($matches[2])) > 0) {
                    $vlans[] = [
                        'id' => $vlanId,
                        'type' => 'parsed',
                        'attribute' => 'common',
                        'stnd_port_num' => '0',
                        'serv_port_num' => '0',
                        'vlan_con_num' => '-',
                        'raw_line' => $line
                    ];
                    Log::info("HUAWEI VLAN Found (flexible): VLAN $vlanId");
                }
            }
        }
        
        Log::info("HUAWEI VLAN Parsing - Total VLANs found: " . count($vlans));
        return $vlans;
    }
    
    // Helper method untuk parse line profiles dari HUAWEI
    private function parseHuaweiLineProfiles($response)
    {
        $profiles = [];
        $lines = explode("\n", $response);
        
        Log::info("HUAWEI Line Profile Parsing - Total lines: " . count($lines));
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            
            Log::debug("HUAWEI Line Profile Line $lineNum: '$line'");
            
            // Skip header lines and empty lines
            if (empty($line) || 
                stripos($line, 'Profile-ID') !== false ||
                stripos($line, '---') !== false ||
                stripos($line, 'Total:') !== false ||
                stripos($line, 'Command:') !== false) {
                continue;
            }
            
            // Parse line profile format: Profile-ID Profile-name Binding_times
            // Example: "  1           SMARTOLT_FLEXIBLE_GPON                      7"
            if (preg_match('/^\s*(\d+)\s+(.+?)\s+(\d+)\s*$/', $line, $matches)) {
                $profiles[] = [
                    'id' => $matches[1],
                    'name' => trim($matches[2]),
                    'binding_times' => $matches[3]
                ];
                Log::info("HUAWEI Line Profile Found: ID " . $matches[1] . " - " . trim($matches[2]));
            }
            // Alternative pattern for profiles without binding times
            elseif (preg_match('/^\s*(\d+)\s+(.+)$/', $line, $matches)) {
                $profileName = trim($matches[2]);
                // Make sure it's not just numbers
                if (!is_numeric($profileName) && strlen($profileName) > 1) {
                    $profiles[] = [
                        'id' => $matches[1],
                        'name' => $profileName,
                        'binding_times' => '0'
                    ];
                    Log::info("HUAWEI Line Profile Found (no binding): ID " . $matches[1] . " - " . $profileName);
                }
            }
        }
        
        Log::info("HUAWEI Line Profile Parsing - Total profiles found: " . count($profiles));
        return $profiles;
    }
    
    // Helper method untuk parse service profiles dari HUAWEI
    private function parseHuaweiSrvProfiles($response)
    {
        $profiles = [];
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip header lines and empty lines
            if (empty($line) || 
                stripos($line, 'Profile-ID') !== false ||
                stripos($line, '---') !== false ||
                stripos($line, 'Total:') !== false) {
                continue;
            }
            
            // Parse service profile format: Profile-ID Profile-name Binding_times  
            // Example: "  1           HG8245                                      2"
            if (preg_match('/^\s*(\d+)\s+(.+?)\s+(\d+)\s*$/', $line, $matches)) {
                $profiles[] = [
                    'id' => $matches[1],
                    'name' => trim($matches[2]),
                    'binding_times' => $matches[3]
                ];
            }
            // Alternative pattern for profiles without binding times
            elseif (preg_match('/^\s*(\d+)\s+(.+)$/', $line, $matches)) {
                $profiles[] = [
                    'id' => $matches[1],
                    'name' => trim($matches[2]),
                    'binding_times' => '0'
                ];
            }
        }
        
        return $profiles;
    }
    
    // Helper method untuk log format tabel VLAN
    private function logVlanTableFormat($response)
    {
        Log::info("=== VLAN TABLE ANALYSIS ===");
        $lines = explode("\n", $response);
        
        $tableStarted = false;
        $headerFound = false;
        $vlanEntries = [];
        
        foreach ($lines as $lineNum => $line) {
            $trimmedLine = trim($line);
            
            // Look for table header
            if (stripos($trimmedLine, 'VLAN') !== false && stripos($trimmedLine, 'Type') !== false) {
                $headerFound = true;
                Log::info("VLAN Table Header Found at Line " . ($lineNum + 1) . ": $trimmedLine");
                continue;
            }
            
            // Look for separator line
            if (preg_match('/^[\-\s]+$/', $trimmedLine) && strlen($trimmedLine) > 10) {
                if ($headerFound) {
                    $tableStarted = true;
                    Log::info("VLAN Table Separator Found at Line " . ($lineNum + 1));
                }
                continue;
            }
            
            // Parse VLAN entries
            if ($tableStarted && preg_match('/^\s*(\d+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(\d+)\s+(.*)$/', $trimmedLine, $matches)) {
                $vlanEntry = [
                    'vlan_id' => $matches[1],
                    'type' => $matches[2],
                    'attribute' => $matches[3],
                    'stnd_port' => $matches[4],
                    'serv_port' => $matches[5],
                    'vlan_con' => trim($matches[6])
                ];
                $vlanEntries[] = $vlanEntry;
                Log::info("VLAN Entry Found: VLAN {$vlanEntry['vlan_id']} | Type: {$vlanEntry['type']} | Attr: {$vlanEntry['attribute']} | STND: {$vlanEntry['stnd_port']} | SERV: {$vlanEntry['serv_port']} | CON: {$vlanEntry['vlan_con']}");
            }
            
            // Look for total line
            if (stripos($trimmedLine, 'Total:') !== false) {
                Log::info("VLAN Table Total Found at Line " . ($lineNum + 1) . ": $trimmedLine");
                break;
            }
        }
        
        Log::info("VLAN Table Analysis Complete: " . count($vlanEntries) . " VLAN entries found");
        if (!empty($vlanEntries)) {
            Log::info("First VLAN: " . json_encode($vlanEntries[0]));
            Log::info("Last VLAN: " . json_encode(end($vlanEntries)));
        }
        Log::info("=== END VLAN TABLE ANALYSIS ===");
    }
    
    // Helper method untuk log format tabel Line Profile
    private function logLineProfileTableFormat($response)
    {
        Log::info("=== LINE PROFILE TABLE ANALYSIS ===");
        $lines = explode("\n", $response);
        
        $profileEntries = [];
        foreach ($lines as $lineNum => $line) {
            $trimmedLine = trim($line);
            
            // Look for profile entries
            if (preg_match('/^\s*(\d+)\s+(.+?)\s+(\d+)\s*$/', $trimmedLine, $matches)) {
                $profileEntry = [
                    'profile_id' => $matches[1],
                    'profile_name' => trim($matches[2]),
                    'binding_times' => $matches[3]
                ];
                $profileEntries[] = $profileEntry;
                Log::info("Line Profile Found: ID {$profileEntry['profile_id']} | Name: {$profileEntry['profile_name']} | Bindings: {$profileEntry['binding_times']}");
            }
        }
        
        Log::info("Line Profile Analysis Complete: " . count($profileEntries) . " profiles found");
        Log::info("=== END LINE PROFILE TABLE ANALYSIS ===");
    }
    
    // Helper method untuk log format tabel Service Profile
    private function logServiceProfileTableFormat($response)
    {
        Log::info("=== SERVICE PROFILE TABLE ANALYSIS ===");
        $lines = explode("\n", $response);
        
        $profileEntries = [];
        foreach ($lines as $lineNum => $line) {
            $trimmedLine = trim($line);
            
            // Look for profile entries
            if (preg_match('/^\s*(\d+)\s+(.+?)\s+(\d+)\s*$/', $trimmedLine, $matches)) {
                $profileEntry = [
                    'profile_id' => $matches[1],
                    'profile_name' => trim($matches[2]),
                    'binding_times' => $matches[3]
                ];
                $profileEntries[] = $profileEntry;
                Log::info("Service Profile Found: ID {$profileEntry['profile_id']} | Name: {$profileEntry['profile_name']} | Bindings: {$profileEntry['binding_times']}");
            }
        }
        
        Log::info("Service Profile Analysis Complete: " . count($profileEntries) . " profiles found");
        Log::info("=== END SERVICE PROFILE TABLE ANALYSIS ===");
    }

    // Helper method untuk menyimpan output terminal ke file
    private function saveTerminalOutputToFile($command, $response, $ip, $index)
    {
        try {
            $filename = 'huawei_terminal_output_' . str_replace('.', '_', $ip) . '_cmd' . $index . '_' . date('Y-m-d_H-i-s') . '.txt';
            $filepath = storage_path('app/' . $filename);
            
            $content = "==============================================\n";
            $content .= "HUAWEI OLT Terminal Output\n";
            $content .= "==============================================\n";
            $content .= "OLT IP: $ip\n";
            $content .= "Command: $command\n";
            $content .= "Command Index: $index\n";
            $content .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
            $content .= "Response Length: " . strlen($response) . " characters\n";
            $content .= "==============================================\n\n";
            $content .= "RAW OUTPUT:\n";
            $content .= "----------------------------------------------\n";
            $content .= $response;
            $content .= "\n----------------------------------------------\n\n";
            
            // Add formatted output
            $content .= "FORMATTED OUTPUT (line by line):\n";
            $content .= "----------------------------------------------\n";
            $lines = explode("\n", $response);
            foreach ($lines as $lineNum => $line) {
                $content .= sprintf("Line %03d: %s\n", $lineNum + 1, $line);
            }
            $content .= "----------------------------------------------\n";
            $content .= "Total Lines: " . count($lines) . "\n";
            $content .= "==============================================\n";
            
            file_put_contents($filepath, $content);
            error_log("Terminal output saved to: $filepath");
            
        } catch (\Exception $e) {
            error_log("Failed to save terminal output to file: " . $e->getMessage());
        }
    }

    // Helper method untuk format terminal output agar lebih readable
    private function formatTerminalOutput($command, $response)
    {
        $output = "\n";
        $output .= "===============================================\n";
        $output .= "# $command\n";
        $output .= "===============================================\n";
        
        if (empty(trim($response))) {
            $output .= "[NO OUTPUT RECEIVED]\n";
            return $output;
        }
        
        $lines = explode("\n", $response);
        $cleanLines = [];
        
        foreach ($lines as $line) {
            $cleanLine = trim($line);
            // Skip completely empty lines, but keep lines with dashes or spaces that are part of table formatting
            if ($cleanLine !== '' || (strlen($line) > 0 && preg_match('/[\-\s]/', $line))) {
                $cleanLines[] = $line;
            }
        }
        
        // Add the cleaned output
        foreach ($cleanLines as $line) {
            $output .= $line . "\n";
        }
        
        $output .= "===============================================\n";
        $output .= "Total lines: " . count($cleanLines) . "\n";
        $output .= "===============================================\n";
        
        return $output;
    }

    // Test method untuk debug HUAWEI sync (bisa dipanggil manual untuk testing)
    public function testHuaweiSync(Olt $olt)
    {
        if (stripos($olt->tipe, 'HUAWEI') === false) {
            return response()->json([
                'success' => false,
                'message' => 'OLT ini bukan tipe HUAWEI'
            ]);
        }
        
        $result = $this->syncHuaweiVlanProfiles($olt->ip, $olt->port, $olt->user, $olt->pass, $olt->id);
        
        return response()->json($result);
    }
}
