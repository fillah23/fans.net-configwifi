<?php

namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\VlanProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnuController extends Controller
{
    public function index()
    {
        $olts = Olt::all();
        return view('onus.index', compact('olts'));
    }

    public function create()
    {
        $olts = Olt::all();
        return view('onus.create', compact('olts'));
    }

    // Get unconfigured ONUs from selected OLT
    public function getUnconfiguredOnus(Request $request)
    {
        $oltId = $request->input('olt_id');
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => '', 'onus' => []];
        
        try {
            // Login to OLT via telnet and get unconfigured ONUs
            $onuData = $this->getUnconfiguredOnusViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass);
            
            if ($onuData['success']) {
                $result['success'] = true;
                $result['onus'] = $onuData['onus'];
                $result['message'] = 'Berhasil mendapatkan ' . count($onuData['onus']) . ' ONU yang belum dikonfigurasi';
            } else {
                $result['message'] = $onuData['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Get available slot/port for ONU configuration
    public function getAvailableSlot(Request $request)
    {
        $oltId = $request->input('olt_id');
        $card = $request->input('card');
        $port = $request->input('port');
        
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => '', 'next_onu_id' => null];
        
        try {
            // Get next available ONU ID from OLT
            $slotData = $this->getNextAvailableOnuId($olt->ip, $olt->port, $olt->user, $olt->pass, $card, $port);
            
            if ($slotData['success']) {
                $result['success'] = true;
                $result['next_onu_id'] = $slotData['next_onu_id'];
                $result['message'] = 'Next available ONU ID: ' . $slotData['next_onu_id'];
            } else {
                $result['message'] = $slotData['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Store new ONU configuration
    public function store(Request $request)
    {
        $validated = $request->validate([
            'olt_id' => 'required|exists:olts,id',
            'onu_sn' => 'required|string',
            'card' => 'required|integer',
            'port' => 'required|integer', 
            'onu_id' => 'required|integer',
            'name' => 'required|string',
            'description' => 'required|string',
            'config_type' => 'required|in:wan-ip-pppoe,onu-bridge',
            'pppoe_username' => 'required_if:config_type,wan-ip-pppoe|string',
            'pppoe_password' => 'required_if:config_type,wan-ip-pppoe|string',
            'vlan' => 'required|integer',
            'vlan_profile' => 'required|string'
        ]);

        $olt = Olt::findOrFail($validated['olt_id']);
        
        $result = ['success' => false, 'message' => ''];
        
        try {
            if ($validated['config_type'] === 'wan-ip-pppoe') {
                $configResult = $this->configureWanIpPppoeOnu($olt, $validated);
            } else {
                $configResult = $this->configureOnuBridge($olt, $validated);
            }
            
            if ($configResult['success']) {
                $result['success'] = true;
                $result['message'] = 'ONU berhasil dikonfigurasi';
            } else {
                $result['message'] = $configResult['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Helper method to get unconfigured ONUs via telnet
    private function getUnconfiguredOnusViaTelnet($ip, $port, $user, $pass)
    {
        $timeout = 10;
        $result = ['success' => false, 'message' => '', 'onus' => []];
        
        try {
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
            
            // Send command to get unconfigured ONUs - try multiple command variations
            $commands = [
                "show gpon onu uncfg\r\n",
                "show gpon onu unconfigured\r\n", 
                "show gpon onu state\r\n"
            ];
            
            $response = '';
            foreach ($commands as $cmd) {
                fwrite($fp, $cmd);
                fflush($fp);
                
                // Read response for this command
                $cmdResponse = '';
                $maxWait = 10;
                $waited = 0;
                $lastDataTime = time();
                
                while ($waited < $maxWait) {
                    $data = fread($fp, 4096);
                    if ($data !== false && $data !== '') {
                        $cmdResponse .= $data;
                        $lastDataTime = time();
                        
                        // Check if command completed
                        if (preg_match('/[\#\>]\s*$/', $cmdResponse)) {
                            break;
                        }
                        
                        // Handle more prompts
                        if (strpos($cmdResponse, 'More:') !== false || strpos($cmdResponse, '--More--') !== false) {
                            fwrite($fp, " ");
                            fflush($fp);
                        }
                    }
                    
                    if (time() - $lastDataTime > 3) {
                        break;
                    }
                    
                    usleep(200000);
                    $waited++;
                }
                
                // If we got some meaningful data, use it
                if (strlen($cmdResponse) > 50 && (stripos($cmdResponse, 'onu') !== false || stripos($cmdResponse, 'gpon') !== false)) {
                    $response = $cmdResponse;
                    break;
                }
            }
            
            fclose($fp);
            
            // Debug: Log the response
            error_log('Command Response Length: ' . strlen($response));
            error_log('Command Response Preview: ' . substr($response, 0, 1000));
            
            // Parse unconfigured ONUs
            $onus = $this->parseUnconfiguredOnus($response);
            
            if (!empty($onus)) {
                $result['success'] = true;
                $result['onus'] = $onus;
                $result['debug_info'] = [
                    'response_length' => strlen($response),
                    'lines_count' => count(explode("\n", $response)),
                    'onus_found' => count($onus)
                ];
            } else {
                $result['message'] = 'Tidak ada ONU yang belum dikonfigurasi ditemukan. Response length: ' . strlen($response);
                $result['debug_response'] = substr($response, 0, 500) . '...';
            }
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Helper method to parse unconfigured ONUs response
    private function parseUnconfiguredOnus($response)
    {
        $onus = [];
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Skip header lines
            if (stripos($line, 'OnuIndex') !== false || 
                stripos($line, '---') !== false ||
                stripos($line, 'Sn') !== false ||
                stripos($line, 'State') !== false) {
                continue;
            }
            
            // Parse ONU line based on actual format: gpon-onu_1/2/5:1         ZTGEC7088D03        unknown
            // Pattern 1: gpon-onu_1/2/5:1 format (configured ONUs that show up in uncfg)
            if (preg_match('/gpon-onu_(\d+)\/(\d+)\/(\d+):(\d+)\s+([A-Z0-9]+)/i', $line, $matches)) {
                $onus[] = [
                    'interface' => "gpon-olt_{$matches[1]}/{$matches[2]}/{$matches[3]}",
                    'card' => $matches[2], // Card is the 2nd number in 1/2/5
                    'port' => $matches[3], // Port is the 3rd number in 1/2/5
                    'slot' => $matches[3], // For compatibility, keep slot same as port
                    'onu_id' => $matches[4],
                    'sn' => $matches[5],
                    'full_line' => $line,
                    'type' => 'configured_uncfg' // This means it's configured but showing in uncfg
                ];
            }
            // Pattern 2: gpon-olt_1/1/1 format (truly unconfigured)
            elseif (preg_match('/gpon-olt_(\d+)\/(\d+)\/(\d+)\s+([A-Z0-9]+)/i', $line, $matches)) {
                $onus[] = [
                    'interface' => $matches[0],
                    'card' => $matches[2], // Card is the 2nd number in 1/2/5
                    'port' => $matches[3], // Port is the 3rd number in 1/2/5
                    'slot' => $matches[3], // For compatibility, keep slot same as port
                    'sn' => $matches[4],
                    'full_line' => $line,
                    'type' => 'unconfigured'
                ];
            }
            // Pattern 3: Just card/port/slot format
            elseif (preg_match('/(\d+)\/(\d+)\/(\d+)\s+([A-Z0-9]+)/i', $line, $matches)) {
                $onus[] = [
                    'interface' => "gpon-olt_{$matches[1]}/{$matches[2]}/{$matches[3]}",
                    'card' => $matches[2], // Card is the 2nd number
                    'port' => $matches[3], // Port is the 3rd number
                    'slot' => $matches[3], // For compatibility, keep slot same as port
                    'sn' => $matches[4],
                    'full_line' => $line,
                    'type' => 'unconfigured'
                ];
            }
            // Pattern 4: Any line with at least card/port and serial number
            elseif (preg_match('/(\d+)\/(\d+).*?([A-Z0-9]{10,})/i', $line, $matches)) {
                $onus[] = [
                    'interface' => "gpon-olt_1/{$matches[1]}/{$matches[2]}",
                    'card' => $matches[1], // First number is card
                    'port' => $matches[2], // Second number is port
                    'slot' => $matches[2], // For compatibility, keep slot same as port
                    'sn' => $matches[3],
                    'full_line' => $line,
                    'type' => 'parsed'
                ];
            }
        }
        
        // Debug logging
        error_log('Parsed ONUs count: ' . count($onus));
        error_log('Response lines: ' . count($lines));
        if (!empty($onus)) {
            error_log('First ONU: ' . json_encode($onus[0]));
        }
        
        return $onus;
    }

    // Helper method to get next available ONU ID
    private function getNextAvailableOnuId($ip, $port, $user, $pass, $card, $portNum)
    {
        $timeout = 10;
        $result = ['success' => false, 'message' => '', 'next_onu_id' => 1];
        
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
                $result['message'] = 'Gagal login ke OLT';
                return $result;
            }
            
            // Send command to check existing ONUs on this port
            $command = "show run interface gpon-olt_1/$card/$portNum\r\n";
            fwrite($fp, $command);
            fflush($fp);
            
            // Read response
            $response = '';
            $maxWait = 15;
            $waited = 0;
            $lastDataTime = time();
            
            while ($waited < $maxWait) {
                $data = fread($fp, 4096);
                if ($data !== false && $data !== '') {
                    $response .= $data;
                    $lastDataTime = time();
                    
                    if (preg_match('/[\#\>]\s*$/', $response)) {
                        break;
                    }
                    
                    if (strpos($response, 'More:') !== false || strpos($response, '--More--') !== false) {
                        fwrite($fp, " ");
                        fflush($fp);
                    }
                }
                
                if (time() - $lastDataTime > 5) {
                    break;
                }
                
                usleep(200000);
                $waited++;
            }
            
            fclose($fp);
            
            // Debug: Log the response
            Log::info('GetAvailableSlot Response for gpon-olt_1/' . $card . '/' . $portNum, [
                'response_length' => strlen($response),
                'response_preview' => substr($response, 0, 1000)
            ]);
            
            // Parse existing ONU IDs
            $existingIds = $this->parseExistingOnuIds($response);
            
            Log::info('GetAvailableSlot - Parsing Results', [
                'command' => $command,
                'response_length' => strlen($response),
                'existing_ids' => $existingIds,
                'response_preview' => substr($response, 0, 500)
            ]);
            
            // Find next available ID (start from 1, max usually 128)
            $nextId = 1;
            $maxOnuId = 128; // Common maximum for GPON ports
            
            while ($nextId <= $maxOnuId && in_array($nextId, $existingIds)) {
                $nextId++;
            }
            
            Log::info('GetAvailableSlot - Next ID Calculation', [
                'calculated_next_id' => $nextId,
                'existing_ids_count' => count($existingIds),
                'max_onu_id' => $maxOnuId
            ]);
            
            if ($nextId > $maxOnuId) {
                $result['message'] = "Tidak ada slot ONU yang tersedia di port gpon-olt_1/$card/$portNum (maksimal $maxOnuId ONU)";
                return $result;
            }
            
            $result['success'] = true;
            $result['next_onu_id'] = $nextId;
            $result['existing_onus'] = $existingIds;
            $result['debug_info'] = [
                'command' => $command,
                'response_length' => strlen($response),
                'existing_count' => count($existingIds),
                'next_available' => $nextId
            ];
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Helper method to parse existing ONU IDs
    private function parseExistingOnuIds($response)
    {
        $ids = [];
        $lines = explode("\n", $response);
        
        Log::info('Starting to parse ONU IDs from response', [
            'total_lines' => count($lines),
            'sample_response' => substr($response, 0, 500)
        ]);
        
        foreach ($lines as $lineNumber => $line) {
            $originalLine = $line;
            $line = trim($line);
            if (empty($line)) continue;
            
            // Pattern 1: "onu X type ALL sn ZTEGC6748239" (your exact format)
            if (preg_match('/^\s*onu\s+(\d+)\s+type\s+\w+\s+sn\s+\w+/i', $line, $matches)) {
                $onuId = intval($matches[1]);
                $ids[] = $onuId;
                Log::info("Found ONU ID $onuId from pattern 1", [
                    'line_number' => $lineNumber + 1,
                    'line' => $originalLine,
                    'match' => $matches[1]
                ]);
                continue;
            }
            
            // Pattern 2: "onu X type ZTE sn ZTEGC1E91517" (alternative order)
            if (preg_match('/^\s*onu\s+(\d+)\s+type\s+\w+\s+sn\s+\w+/i', $line, $matches)) {
                $onuId = intval($matches[1]);
                $ids[] = $onuId;
                Log::info("Found ONU ID $onuId from pattern 2", [
                    'line_number' => $lineNumber + 1,
                    'line' => $originalLine,
                    'match' => $matches[1]
                ]);
                continue;
            }
            
            // Pattern 3: Just "onu X" at start of line (simpler match)
            if (preg_match('/^\s*onu\s+(\d+)(?:\s|$)/i', $line, $matches)) {
                $onuId = intval($matches[1]);
                $ids[] = $onuId;
                Log::info("Found ONU ID $onuId from pattern 3", [
                    'line_number' => $lineNumber + 1,
                    'line' => $originalLine,
                    'match' => $matches[1]
                ]);
                continue;
            }
            
            // Pattern 4: "gpon-onu_1/2/3:X" format  
            if (preg_match('/gpon-onu_\d+\/\d+\/\d+:(\d+)/i', $line, $matches)) {
                $onuId = intval($matches[1]);
                $ids[] = $onuId;
                Log::info("Found ONU ID $onuId from pattern 4", [
                    'line_number' => $lineNumber + 1,
                    'line' => $originalLine,
                    'match' => $matches[1]
                ]);
                continue;
            }
            
            // Pattern 5: Interface lines like "interface gpon-onu_1/2/3:X"
            if (preg_match('/interface\s+gpon-onu_\d+\/\d+\/\d+:(\d+)/i', $line, $matches)) {
                $onuId = intval($matches[1]);
                $ids[] = $onuId;
                Log::info("Found ONU ID $onuId from pattern 5", [
                    'line_number' => $lineNumber + 1,
                    'line' => $originalLine,
                    'match' => $matches[1]
                ]);
                continue;
            }
        }
        
        // Remove duplicates and sort
        $ids = array_unique($ids);
        sort($ids);
        
        // Debug logging
        Log::info('Final parsed ONU IDs', [
            'found_ids' => $ids,
            'count' => count($ids),
            'expected_next_id' => $this->findNextAvailableId($ids)
        ]);
        
        return $ids;
    }

    // Configure WAN-IP PPPoE ONU
    private function configureWanIpPppoeOnu($olt, $data)
    {
        $result = ['success' => false, 'message' => ''];
        
        try {
            $fp = @fsockopen($olt->ip, $olt->port, $errno, $errstr, 10);
            if (!$fp) {
                $result['message'] = "Gagal koneksi ke {$olt->ip}:{$olt->port} ($errstr)";
                return $result;
            }
            
            stream_set_timeout($fp, 10);
            
            // Login
            $loginResult = $this->telnetLogin($fp, $olt->user, $olt->pass);
            if (!$loginResult) {
                fclose($fp);
                $result['message'] = 'Gagal login ke OLT';
                return $result;
            }
            
            // Build configuration commands
            $commands = $this->buildWanIpPppoeCommands($olt, $data);
            
            // Execute each command
            foreach ($commands as $command) {
                fwrite($fp, $command . "\r\n");
                fflush($fp);
                usleep(500000); // 0.5 second delay between commands
                
                // Read response to check for errors
                $response = fread($fp, 4096);
                
                // Check for common error messages
                if (stripos($response, 'error') !== false || 
                    stripos($response, 'invalid') !== false ||
                    stripos($response, 'fail') !== false) {
                    fclose($fp);
                    $result['message'] = "Error executing command: $command. Response: $response";
                    return $result;
                }
            }
            
            fclose($fp);
            
            $result['success'] = true;
            $result['message'] = 'ONU WAN-IP PPPoE berhasil dikonfigurasi';
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Build WAN-IP PPPoE configuration commands
    private function buildWanIpPppoeCommands($olt, $data)
    {
        $card = $data['card'];
        $port = $data['port'];
        $onuId = $data['onu_id'];
        $sn = $data['onu_sn'];
        $name = $data['name'];
        $description = $data['description'];
        $username = $data['pppoe_username'];
        $password = $data['pppoe_password'];
        $vlan = $data['vlan'];
        $vlanProfile = $data['vlan_profile'];
        
        return [
            'con t',
            "interface gpon-olt_1/$card/$port",
            "onu $onuId type ALL-GPON sn $sn",
            'exit',
            "interface gpon-onu_1/$card/$port:$onuId",
            "name $name",
            "description $description",
            'tcont 1 name PPPoE profile 1000MBPS',
            'gemport 1 name PPPoE tcont 1',
            'gemport 1 traffic-limit upstream UP1000MBPS downstream DW1000MBPS',
            "service-port 1 vport 1 user-vlan $vlan vlan $vlan",
            'service-port 2 vport 1 user-vlan 100 vlan 100',
            'exit',
            "pon-onu-mng gpon-onu_1/$card/$port:$onuId",
            "service PPPoE gemport 1 vlan $vlan",
            'service ACS gemport 1 vlan 100',
            "wan-ip 1 mode pppoe username $username password $password vlan-profile $vlanProfile host 1",
            'vlan port veip_1 mode hybrid',
            'tr069-mgmt 1 state unlock',
            'tr069-mgmt 1 acs http://10.133.254.2:7547 validate basic username fansnetwork password acsfans',
            'tr069-mgmt 1 tag pri 0 vlan 100',
            'wan-ip 1 ping-response enable traceroute-response enable',
            'security-mgmt 1 state enable mode forward protocol web',
            'end',
            'wr'
        ];
    }

    // Configure ONU Bridge (placeholder for future implementation)
    private function configureOnuBridge($olt, $data)
    {
        $result = ['success' => false, 'message' => 'ONU Bridge configuration not implemented yet'];
        return $result;
    }

    // Helper method for telnet login (reused from OltController)
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

    // Get VLAN profiles for selected OLT
    public function getVlanProfiles(Request $request)
    {
        try {
            $oltId = $request->input('olt_id');
            
            if (!$oltId) {
                return response()->json([
                    'success' => false,
                    'message' => 'OLT ID is required'
                ]);
            }
            
            // Check if OLT exists
            $olt = Olt::find($oltId);
            if (!$olt) {
                return response()->json([
                    'success' => false,
                    'message' => 'OLT not found'
                ]);
            }
            
            $profiles = VlanProfile::where('olt_id', $oltId)
                ->orderBy('profile_name')
                ->get(['id', 'profile_name', 'vlan_data', 'profile_id']);
                
            // Process profiles to include VLAN ID information
            $processedProfiles = $profiles->map(function ($profile) {
                $vlanInfo = '';
                $vlanId = null;
                
                if ($profile->vlan_data && is_array($profile->vlan_data)) {
                    // Extract VLAN ID from vlan_data array
                    if (isset($profile->vlan_data[0]['vlan_id'])) {
                        $vlanId = $profile->vlan_data[0]['vlan_id'];
                        $description = $profile->vlan_data[0]['description'] ?? '';
                        $vlanInfo = "VLAN ID: {$vlanId}";
                        if ($description) {
                            $vlanInfo .= " - {$description}";
                        }
                    }
                }
                
                return [
                    'id' => $profile->id,
                    'profile_name' => $profile->profile_name,
                    'profile_id' => $profile->profile_id,
                    'vlan_id' => $vlanId,
                    'vlan_info' => $vlanInfo,
                    'vlan_data' => $profile->vlan_data,
                    'display_text' => $profile->profile_name . ($vlanInfo ? " ({$vlanInfo})" : '')
                ];
            });
                
            return response()->json([
                'success' => true,
                'profiles' => $processedProfiles,
                'count' => $profiles->count(),
                'olt_name' => $olt->nama
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in getVlanProfiles: ' . $e->getMessage(), [
                'olt_id' => $request->input('olt_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }

    // Test ONU configuration (for debugging)
    public function testConfiguration(Request $request)
    {
        $oltId = $request->input('olt_id');
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => ''];
        
        try {
            // Test telnet connection and basic command
            $testResult = $this->quickTelnetTest($olt->ip, $olt->port, $olt->user, $olt->pass, 'show version');
            
            if ($testResult['success']) {
                $result['success'] = true;
                $result['message'] = 'Test koneksi berhasil. Response: ' . substr($testResult['response'], 0, 200) . '...';
            } else {
                $result['message'] = $testResult['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Quick telnet test helper
    private function quickTelnetTest($ip, $port, $user, $pass, $command)
    {
        $timeout = 5;
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
            
            // Send command
            fwrite($fp, "$command\r\n");
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

    // Debug method to test uncfg command directly
    public function debugUncfgCommand(Request $request)
    {
        $oltId = $request->input('olt_id');
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => '', 'raw_response' => ''];
        
        try {
            $fp = @fsockopen($olt->ip, $olt->port, $errno, $errstr, 10);
            if (!$fp) {
                $result['message'] = "Gagal koneksi ke {$olt->ip}:{$olt->port} ($errstr)";
                return response()->json($result);
            }
            
            stream_set_timeout($fp, 10);
            
            // Login
            $loginResult = $this->telnetLogin($fp, $olt->user, $olt->pass);
            if (!$loginResult) {
                fclose($fp);
                $result['message'] = 'Gagal login ke OLT';
                return response()->json($result);
            }
            
            // Test different commands
            $commands = [
                'show gpon onu uncfg',
                'show gpon onu unconfigured', 
                'show gpon onu state',
                'show onu uncfg'
            ];
            
            $responses = [];
            foreach ($commands as $cmd) {
                fwrite($fp, "$cmd\r\n");
                fflush($fp);
                
                usleep(2000000); // 2 seconds
                $response = fread($fp, 8192);
                
                $responses[$cmd] = $response;
            }
            
            fclose($fp);
            
            $result['success'] = true;
            $result['commands_tested'] = $responses;
            $result['message'] = 'Debug test completed';
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Debug method to test show run interface command
    public function debugShowRunInterface(Request $request)
    {
        $oltId = $request->input('olt_id');
        $card = $request->input('card', 1);
        $port = $request->input('port', 1);
        
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => '', 'raw_response' => ''];
        
        try {
            $fp = @fsockopen($olt->ip, $olt->port, $errno, $errstr, 10);
            if (!$fp) {
                $result['message'] = "Gagal koneksi ke {$olt->ip}:{$olt->port} ($errstr)";
                return response()->json($result);
            }
            
            stream_set_timeout($fp, 10);
            
            // Login
            $loginResult = $this->telnetLogin($fp, $olt->user, $olt->pass);
            if (!$loginResult) {
                fclose($fp);
                $result['message'] = 'Gagal login ke OLT';
                return response()->json($result);
            }
            
            // Test show run interface command
            $command = "show run interface gpon-olt_1/$card/$port";
            fwrite($fp, "$command\r\n");
            fflush($fp);
            
            // Read response with longer timeout for complete output
            $response = '';
            $maxWait = 20;
            $waited = 0;
            $lastDataTime = time();
            
            while ($waited < $maxWait) {
                $data = fread($fp, 4096);
                if ($data !== false && $data !== '') {
                    $response .= $data;
                    $lastDataTime = time();
                    
                    // Check if command completed
                    if (preg_match('/[\#\>]\s*$/', $response)) {
                        break;
                    }
                    
                    // Handle more prompts
                    if (strpos($response, 'More:') !== false || strpos($response, '--More--') !== false) {
                        fwrite($fp, " ");
                        fflush($fp);
                    }
                }
                
                if (time() - $lastDataTime > 5) {
                    break;
                }
                
                usleep(200000);
                $waited++;
            }
            
            fclose($fp);
            
            // Parse existing IDs for testing
            $existingIds = $this->parseExistingOnuIds($response);
            
            $result['success'] = true;
            $result['command'] = $command;
            $result['raw_response'] = $response;
            $result['response_length'] = strlen($response);
            $result['existing_onu_ids'] = $existingIds;
            $result['next_available_id'] = $this->findNextAvailableId($existingIds);
            $result['parsed_lines'] = array_values(array_filter(explode("\n", $response), function($line) {
                return !empty(trim($line));
            }));
            
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }
    
    // Helper to find next available ID
    private function findNextAvailableId($existingIds)
    {
        $nextId = 1;
        $maxOnuId = 128;
        
        while ($nextId <= $maxOnuId && in_array($nextId, $existingIds)) {
            $nextId++;
        }
        
        return $nextId <= $maxOnuId ? $nextId : null;
    }

    public function show($id)
    {
        // For now, redirect to index or return a simple view
        // This method is required by Route::resource
        return redirect()->route('onus.index')->with('info', 'ONU detail view not implemented yet.');
    }

    // Test method to validate parsing with sample data
    public function testParseWithSampleData(Request $request)
    {
        // Your sample data
        $sampleResponse = "
OLT-JEMBER-C300#show run interface gpon-olt_1/2/5
Building configuration...
interface gpon-olt_1/2/5
  no shutdown
  linktrap disable
  description OLT - 5
  name OLT - 5
  onu 1 type ALL sn ZTEGC6748239
  onu 2 type ZTE sn ZTEGC1E91517
  onu 5 type ZTE sn ZTEGC0C4B91C
  onu 7 type ZTE sn ZTEGCFEB50AF
  onu 8 type ALL sn ZTEGD35932E1
  onu 9 type ZTE sn ZTEGC880AB09
  onu 10 type ZTE sn ZTEGC0E51B39
";
        
        // Test parsing
        $existingIds = $this->parseExistingOnuIds($sampleResponse);
        $nextAvailableId = $this->findNextAvailableId($existingIds);
        
        return response()->json([
            'success' => true,
            'sample_data' => $sampleResponse,
            'existing_onu_ids' => $existingIds,
            'next_available_id' => $nextAvailableId,
            'expected_next_id' => 3, // Should be 3 based on your data
            'test_result' => $nextAvailableId === 3 ? 'PASS' : 'FAIL',
            'parsing_details' => [
                'found_ids' => $existingIds,
                'available_ids' => array_diff(range(1, 10), $existingIds),
                'all_slots_1_to_10' => range(1, 10)
            ]
        ]);
    }

    // Test method for direct slot calculation with sample data
    public function testSlotCalculationWithSample(Request $request)
    {
        // Sample response from your ZTE OLT
        $sampleResponse = "
OLT-JEMBER-C300#show run interface gpon-olt_1/2/5
Building configuration...
interface gpon-olt_1/2/5
  no shutdown
  linktrap disable
  description OLT - 5
  name OLT - 5
  onu 1 type ALL sn ZTEGC6748239
  onu 2 type ZTE sn ZTEGC1E91517
  onu 5 type ZTE sn ZTEGC0C4B91C
  onu 7 type ZTE sn ZTEGCFEB50AF
  onu 8 type ALL sn ZTEGD35932E1
  onu 9 type ZTE sn ZTEGC880AB09
  onu 10 type ZTE sn ZTEGC0E51B39
";
        
        // Test the actual parsing method
        $existingIds = $this->parseExistingOnuIds($sampleResponse);
        
        // Test the slot calculation logic from getNextAvailableOnuId
        $nextId = 1;
        $maxOnuId = 128;
        
        while ($nextId <= $maxOnuId && in_array($nextId, $existingIds)) {
            $nextId++;
        }
        
        if ($nextId > $maxOnuId) {
            $nextAvailableId = null;
            $message = 'All slots full';
        } else {
            $nextAvailableId = $nextId;
            $message = "Next available slot: $nextId";
        }
        
        return response()->json([
            'success' => true,
            'sample_response' => $sampleResponse,
            'existing_onu_ids' => $existingIds,
            'next_available_id' => $nextAvailableId,
            'expected_result' => 3,
            'test_passed' => $nextAvailableId === 3,
            'message' => $message,
            'calculation_steps' => [
                'starting_from' => 1,
                'existing_ids' => $existingIds,
                'first_available' => $nextAvailableId,
                'logic_check' => "Started from 1, found existing IDs " . implode(', ', $existingIds) . ", so next available is $nextAvailableId"
            ]
        ]);
    }

    // Simulate successful ONU slot calculation for testing
    public function simulateGetAvailableSlot(Request $request)
    {
        // Simulate your real OLT response
        $simulatedResponse = "
OLT-JEMBER-C300#show run interface gpon-olt_1/2/5
Building configuration...
interface gpon-olt_1/2/5
  no shutdown
  linktrap disable
  description OLT - 5
  name OLT - 5
  onu 1 type ALL sn ZTEGC6748239
  onu 2 type ZTE sn ZTEGC1E91517
  onu 5 type ZTE sn ZTEGC0C4B91C
  onu 7 type ZTE sn ZTEGCFEB50AF
  onu 8 type ALL sn ZTEGD35932E1
  onu 9 type ZTE sn ZTEGC880AB09
  onu 10 type ZTE sn ZTEGC0E51B39
";
        
        // Use actual parsing method
        $existingIds = $this->parseExistingOnuIds($simulatedResponse);
        
        // Calculate next available ID
        $nextId = 1;
        $maxOnuId = 128;
        
        while ($nextId <= $maxOnuId && in_array($nextId, $existingIds)) {
            $nextId++;
        }
        
        $result = [
            'success' => true,
            'next_onu_id' => $nextId,
            'message' => "Next available ONU ID: $nextId (simulated)",
            'debug_info' => [
                'existing_ids' => $existingIds,
                'response_length' => strlen($simulatedResponse),
                'simulation' => true
            ]
        ];
        
        return response()->json($result);
    }
}
