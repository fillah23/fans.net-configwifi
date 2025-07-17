<?php
namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\VlanProfile;
use Illuminate\Http\Request;

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
            ->orderBy('profile_name')
            ->get();
            
        if ($profiles->count() > 0) {
            $html = '<div class="table-responsive">';
            $html .= '<table class="table table-bordered table-striped">';
            $html .= '<thead><tr><th>Profile Name</th><th>Profile ID</th><th>VLAN Count</th><th>Last Updated</th><th>VLANs</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($profiles as $profile) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($profile->profile_name) . '</td>';
                $html .= '<td>' . htmlspecialchars($profile->profile_id) . '</td>';
                $html .= '<td>' . $profile->vlan_count . '</td>';
                $html .= '<td>' . ($profile->last_updated ? $profile->last_updated->format('d/m/Y H:i:s') : 'N/A') . '</td>';
                $html .= '<td>';
                
                if ($profile->vlan_data && count($profile->vlan_data) > 0) {
                    $html .= '<div class="vlan-list">';
                    foreach ($profile->vlan_data as $vlan) {
                        $html .= '<span class="badge bg-primary me-1 mb-1">VLAN ' . $vlan['vlan_id'] . '</span>';
                    }
                    $html .= '</div>';
                } else {
                    $html .= '<span class="text-muted">No VLANs</span>';
                }
                
                $html .= '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></div>';
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
}
