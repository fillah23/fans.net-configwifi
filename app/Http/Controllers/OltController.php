<?php
namespace App\Http\Controllers;

use App\Models\Olt;
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
}
