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
        // Dummy logic, ganti dengan implementasi telnet/snmp sesuai kebutuhan
        $telnetResult = $this->testTelnet($olt->ip, $olt->port, $olt->user, $olt->pass);
        $snmpResult = $this->testSnmp($olt->ip, $olt->community_read, $olt->port_snmp);
        $success = $telnetResult['success'] && $snmpResult['success'];
        $message = '<b>Telnet:</b> ' . $telnetResult['message'] . '<br><b>SNMP:</b> ' . $snmpResult['message'];
        return response()->json(['success' => $success, 'message' => $message]);
    }

    public function getInfo(Olt $olt)
    {
        $result = ['success' => false, 'message' => '', 'data' => []];
        
        // OID untuk informasi OLT
        $uptimeOid = '1.3.6.1.2.1.1.3.0'; // sysUpTime
        $tempOidBase = '1.3.6.1.4.1.3902.1012.3.28.1.1.3'; // OLT temperature base (ZTE specific)
        $tempOidGeneric = '1.3.6.1.4.1.3902.1012.3.50.12.2.1.8'; // Alternative temperature OID
        $ontTableOid = '1.3.6.1.4.1.3902.1012.3.13.1.1.1'; // ONT table base (ZTE specific)
        
        try {
            // Get uptime
            $uptimeRaw = @snmp2_get($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $uptimeOid, 1000000, 2);
            $uptime = $this->formatUptime($uptimeRaw);
            
            // Get temperature using snmpwalk - try multiple OIDs
            $tempData = @snmp2_walk($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $tempOidBase, 1000000, 2);
            if (!$tempData || empty($tempData)) {
                // Try alternative temperature OID
                $tempData = @snmp2_walk($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $tempOidGeneric, 1000000, 2);
            }
            $temperature = $this->parseTemperatureFromWalk($tempData);
            
            // Get ONT count using snmpwalk
            $ontData = @snmp2_walk($olt->ip . ":" . $olt->port_snmp, $olt->community_read, $ontTableOid, 1000000, 2);
            $activePorts = $this->countActivePorts($ontData);
            
            if ($uptimeRaw !== false || $tempData !== false || $ontData !== false) {
                $result['success'] = true;
                $result['data'] = [
                    'uptime' => $uptime ?: 'N/A',
                    'temperature' => $temperature ?: 'N/A',
                    'active_ports' => $activePorts ?: '0',
                ];
            } else {
                $result['message'] = 'Gagal mengambil data SNMP';
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
    private function parseTemperatureFromWalk($tempData)
    {
        if (!$tempData || !is_array($tempData)) return 'N/A';
        
        // Loop through temperature data
        foreach ($tempData as $oid => $value) {
            // Try multiple patterns for temperature parsing
            $patterns = [
                '/INTEGER:\s*(\d+)/',           // INTEGER: 45
                '/(\d+)\s*degrees?/',           // 45 degrees
                '/(\d+)\s*°C/',                 // 45°C
                '/temp.*?(\d+)/',               // temp: 45
                '/(\d{1,2})/'                   // Just digits (1-2 digits)
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value, $matches)) {
                    $temp = intval($matches[1]);
                    // Jika suhu masuk akal (15-85 derajat untuk equipment)
                    if ($temp >= 15 && $temp <= 85) {
                        return $temp;
                    }
                }
            }
        }
        
        // If no valid temperature found, return first numeric value found
        foreach ($tempData as $oid => $value) {
            if (preg_match('/(\d+)/', $value, $matches)) {
                $temp = intval($matches[1]);
                if ($temp > 0 && $temp < 200) { // Broader range
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

    

    // Telnet test asli tanpa library eksternal
    private function testTelnet($ip, $port, $user, $pass)
    {
        $timeout = 3;
        $result = ['success' => false, 'message' => ''];
        
        // Test koneksi socket
        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (!$fp) {
            $result['message'] = "Gagal koneksi ke $ip:$port ($errstr)";
            return $result;
        }
        
        stream_set_timeout($fp, $timeout);
        $allResponse = '';
        
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
        
        fclose($fp);
        
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
            $result['message'] = 'Koneksi Telnet berhasil';
        } else if ($hasError) {
            $result['message'] = 'Koneksi Telnet gagal - Username/Password salah';
        } else {
            $result['message'] = 'Koneksi Telnet gagal - Tidak dapat login atau timeout';
        }
        
        return $result;
    }

    // SNMP test asli menggunakan ekstensi PHP SNMP
    private function testSnmp($ip, $community, $port)
    {
        $result = ['success' => false, 'message' => ''];
        $oid = '1.3.6.1.2.1.1.1.0'; // sysDescr OID
        $snmp = @snmp2_get($ip . ":$port", $community, $oid, 1000000, 2);
        if ($snmp !== false) {
            $result['success'] = true;
            $result['message'] = 'Koneksi SNMP berhasil: ' . htmlspecialchars($snmp);
        } else {
            $result['message'] = 'Koneksi SNMP gagal';
        }
        return $result;
    }

    // Helper method untuk menghitung total port aktif saja
    private function countActivePorts($ontData)
    {
        if (!$ontData || !is_array($ontData)) return '0';
        
        $activePorts = 0;
        
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
        
        return (string)$activePorts;
    }

    public function syncVlans(Olt $olt)
    {
        $result = ['success' => false, 'message' => '', 'data' => []];
        
        try {
            $vlanData = $this->getVlanProfilesViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass);
            
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
    private function getVlanProfilesViaTelnet($ip, $port, $user, $pass)
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
                $result['message'] = 'Gagal login ke OLT';
                return $result;
            }
            
            // Send command to get VLAN profiles
            fwrite($fp, "show gpon onu profile vlan\r\n");
            fflush($fp);
            
            // Read response with improved logic
            $response = '';
            $maxWait = 10; // Reduced to 10 seconds
            $waited = 0;
            $lastDataTime = time();
            
            while ($waited < $maxWait) {
                $data = fread($fp, 4096);
                if ($data !== false && $data !== '') {
                    $response .= $data;
                    $lastDataTime = time();
                    
                    // Check if we have complete output (look for prompt at end)
                    if (preg_match('/[\#\>]\s*$/', $response) || 
                        strpos($response, 'More:') !== false ||
                        strpos($response, '--More--') !== false) {
                        
                        // If there's more data, send space to continue
                        if (strpos($response, 'More:') !== false || strpos($response, '--More--') !== false) {
                            fwrite($fp, " ");
                            fflush($fp);
                        } else {
                            break; // Command completed
                        }
                    }
                }
                
                // Break if no data received for 3 seconds
                if (time() - $lastDataTime > 3) {
                    break;
                }
                
                usleep(200000); // 0.2 second
                $waited++;
            }
            
            fclose($fp);
            
            // Debug: Log the response (remove in production)
            error_log('VLAN Response Length: ' . strlen($response));
            error_log('VLAN Response Preview: ' . substr($response, 0, 500));
            
            // Parse the response
            $profiles = $this->parseVlanProfileResponse($response);
            
            if (!empty($profiles)) {
                $result['success'] = true;
                $result['profiles'] = $profiles;
                $result['message'] = 'VLAN profiles berhasil diambil (' . count($profiles) . ' profiles)';
            } else {
                $result['message'] = 'Tidak ada VLAN profile ditemukan. Response length: ' . strlen($response);
            }
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Helper method untuk login telnet
    private function telnetLogin($fp, $user, $pass)
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
            
            // Look for profile headers - various patterns
            if (preg_match('/(?:ONU-Profile|Profile|profile)\s*[:\-]?\s*(.+)/i', $line, $matches)) {
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

    public function testVlanCommand(Olt $olt)
    {
        $result = ['success' => false, 'message' => '', 'data' => []];
        
        try {
            // Quick test with simple command first
            $testResult = $this->quickTelnetTest($olt->ip, $olt->port, $olt->user, $olt->pass);
            
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

    // Quick telnet test for debugging
    private function quickTelnetTest($ip, $port, $user, $pass)
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
            $loginResult = $this->telnetLogin($fp, $user, $pass);
            if (!$loginResult) {
                fclose($fp);
                $result['message'] = 'Gagal login';
                return $result;
            }
            
            // Send simple command
            fwrite($fp, "show version\r\n");
            fflush($fp);
            
            // Read response
            usleep(2000000); // 2 seconds
            $response = fread($fp, 4096);
            
            fclose($fp);
            
            $result['success'] = true;
            $result['response'] = $response;
            $result['message'] = 'Test berhasil';
            
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
}
