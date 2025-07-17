<?php

namespace App\Http\Controllers;

use App\Models\Olt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnuFilterController extends Controller
{
    // Get configured ONUs from selected OLT (all interfaces)
    public function getConfiguredOnus(Request $request)
    {
        $oltId = $request->input('olt_id');
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => '', 'onus' => []];
        
        try {
            // Login to OLT via telnet and get configured ONUs
            $onuData = $this->getConfiguredOnusViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass);
            
            if ($onuData['success']) {
                $result['success'] = true;
                $result['onus'] = $onuData['onus'];
                $result['message'] = 'Berhasil mendapatkan ' . count($onuData['onus']) . ' ONU yang sudah dikonfigurasi';
            } else {
                $result['message'] = $onuData['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Get configured ONUs from selected OLT with card/port filter
    public function getConfiguredOnusFiltered(Request $request)
    {
        $oltId = $request->input('olt_id');
        $card = $request->input('card');
        $port = $request->input('port');
        
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => '', 'onus' => []];
        
        // Validate card and port
        if (!$card || !$port) {
            $result['message'] = 'Card and Port are required';
            return response()->json($result);
        }
        
        try {
            // Login to OLT via telnet and get configured ONUs for specific card/port
            $onuData = $this->getConfiguredOnusFilteredViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass, $card, $port);
            
            if ($onuData['success']) {
                $result['success'] = true;
                $result['onus'] = $onuData['onus'];
                $result['message'] = 'Berhasil mendapatkan ' . count($onuData['onus']) . ' ONU pada Card ' . $card . ', Port ' . $port;
                $result['filter'] = [
                    'card' => $card,
                    'port' => $port
                ];
            } else {
                $result['message'] = $onuData['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Get ONU by Serial Number
    public function getOnuBySn(Request $request)
    {
        $oltId = $request->input('olt_id');
        $serialNumber = $request->input('serial_number');
        
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => '', 'onus' => []];
        
        // Validate serial number
        if (!$serialNumber) {
            $result['message'] = 'Serial Number is required';
            return response()->json($result);
        }
        
        try {
            // Search ONU by Serial Number via telnet
            $onuData = $this->getOnuBySnViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass, $serialNumber);
            
            if ($onuData['success']) {
                $result['success'] = true;
                $result['onus'] = $onuData['onus'];
                $result['message'] = count($onuData['onus']) > 0 
                    ? 'ONU dengan SN ' . $serialNumber . ' ditemukan'
                    : 'ONU dengan SN ' . $serialNumber . ' tidak ditemukan';
            } else {
                $result['message'] = $onuData['message'];
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Delete ONU configuration
    public function deleteOnu(Request $request)
    {
        $oltId = $request->input('olt_id');
        $card = $request->input('card');
        $port = $request->input('port');
        $onuId = $request->input('onu_id');
        
        $olt = Olt::findOrFail($oltId);
        
        $result = ['success' => false, 'message' => ''];
        
        try {
            // Delete ONU configuration via telnet
            $deleteResult = $this->deleteOnuViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass, $card, $port, $onuId);
            
            if ($deleteResult['success']) {
                $result['success'] = true;
                $result['message'] = 'ONU berhasil dihapus';
            } else {
                $result['message'] = $deleteResult['message'];
            }
        } catch (\Exception $e) {
            Log::error('ONU Delete Error', [
                'message' => $e->getMessage(),
                'olt_id' => $oltId,
                'card' => $card,
                'port' => $port,
                'onu_id' => $onuId
            ]);
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Debug method to test real OLT connection
    public function debugRealOlt(Request $request)
    {
        $oltId = $request->input('olt_id');
        $card = $request->input('card', 2);
        $port = $request->input('port', 3);
        
        $olt = Olt::findOrFail($oltId);
        
        $result = [
            'success' => false, 
            'message' => '', 
            'raw_response' => '',
            'parsed_onus' => [],
            'found_onus_count' => 0,
            'command_sent' => '',
            'response_length' => 0,
            'debug_info' => [],
            'olt_info' => [
                'name' => $olt->nama,
                'ip' => $olt->ip,
                'port' => $olt->port
            ]
        ];
        
        try {
            // Test filtered ONU search for debug
            $onuData = $this->getConfiguredOnusFilteredViaTelnet($olt->ip, $olt->port, $olt->user, $olt->pass, $card, $port);
            
            if ($onuData['success']) {
                $result['success'] = true;
                $result['parsed_onus'] = $onuData['onus'];
                $result['found_onus_count'] = count($onuData['onus']);
                $result['command_sent'] = $onuData['debug_info']['command'] ?? '';
                $result['response_length'] = $onuData['debug_info']['response_length'] ?? 0;
                $result['raw_response'] = $onuData['raw_response'] ?? 'No raw response available';
                $result['debug_info'] = [
                    'contains_building_config' => strpos($result['raw_response'], 'Building configuration') !== false,
                    'contains_interface' => strpos($result['raw_response'], 'interface gpon-olt') !== false,
                    'contains_onu' => strpos($result['raw_response'], 'onu ') !== false,
                ];
                $result['message'] = 'Debug test berhasil';
            } else {
                $result['message'] = $onuData['message'];
                $result['raw_response'] = $onuData['raw_response'] ?? 'No response';
            }
        } catch (\Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return response()->json($result);
    }

    // Test methods for development
    public function testConfiguredSample()
    {
        $sampleOnus = [
            [
                'card' => 2,
                'port' => 3,
                'onu_id' => 1,
                'type' => 'ZTE',
                'serial_number' => 'ZTEGC671CB2A',
                'interface' => 'gpon-olt_1/2/3',
                'onu_interface' => 'gpon-onu_1/2/3:1',
                'status' => 'configured'
            ],
            [
                'card' => 2,
                'port' => 3,
                'onu_id' => 5,
                'type' => 'ALL',
                'serial_number' => 'ZTEGD35932E1',
                'interface' => 'gpon-olt_1/2/3',
                'onu_interface' => 'gpon-onu_1/2/3:5',
                'status' => 'configured'
            ],
            [
                'card' => 2,
                'port' => 3,
                'onu_id' => 8,
                'type' => 'ZTE',
                'serial_number' => 'ZTEGC880AB09',
                'interface' => 'gpon-olt_1/2/3',
                'onu_interface' => 'gpon-onu_1/2/3:8',
                'status' => 'configured'
            ]
        ];
        
        return response()->json([
            'success' => true,
            'onus' => $sampleOnus,
            'message' => 'Sample configured ONUs data'
        ]);
    }

    public function testFilteredSample(Request $request)
    {
        $card = $request->get('card', 2);
        $port = $request->get('port', 3);
        
        $sampleOnus = [
            [
                'card' => $card,
                'port' => $port,
                'onu_id' => 1,
                'type' => 'ZTE',
                'serial_number' => 'ZTEGC671CB2A',
                'interface' => "gpon-olt_1/$card/$port",
                'onu_interface' => "gpon-onu_1/$card/$port:1",
                'status' => 'configured'
            ],
            [
                'card' => $card,
                'port' => $port,
                'onu_id' => 5,
                'type' => 'ALL',
                'serial_number' => 'ZTEGD35932E1',
                'interface' => "gpon-olt_1/$card/$port",
                'onu_interface' => "gpon-onu_1/$card/$port:5",
                'status' => 'configured'
            ]
        ];
        
        return response()->json([
            'success' => true,
            'onus' => $sampleOnus,
            'filter' => ['card' => $card, 'port' => $port],
            'message' => "Sample ONUs for Card $card, Port $port"
        ]);
    }

    // Private helper methods

    // Helper method to get configured ONUs via telnet (all interfaces)
    private function getConfiguredOnusViaTelnet($ip, $port, $user, $pass)
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
                $result['message'] = 'Login gagal';
                return $result;
            }
            
            // Get configured ONUs using show run interface command
            $configuredOnus = [];
            
            // First, get all configured interfaces by running show run | include "interface gpon-olt"
            $command = "show run | include \"interface gpon-olt\"\r\n";
            fwrite($fp, $command);
            usleep(1000000); // 1 second delay
            
            $response = '';
            $start_time = time();
            while (time() - $start_time < 10) {
                $data = @fread($fp, 4096);
                if ($data === false || $data === '') {
                    break;
                }
                $response .= $data;
                
                if (strpos($response, '#') !== false || strpos($response, '>') !== false) {
                    break;
                }
            }
            
            // Parse interface lines to get card/port combinations
            $interfaceLines = [];
            $lines = explode("\n", $response);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/interface\s+gpon-olt_1\/(\d+)\/(\d+)/i', $line, $matches)) {
                    $card = $matches[1];
                    $portNum = $matches[2];
                    $interfaceLines[] = ['card' => $card, 'port' => $portNum];
                }
            }
            
            // Remove duplicates
            $interfaceLines = array_unique($interfaceLines, SORT_REGULAR);
            
            // If no interfaces found, try scanning common ranges
            if (empty($interfaceLines)) {
                // Try common card/port combinations
                for ($card = 1; $card <= 8; $card++) {
                    for ($portNum = 1; $portNum <= 16; $portNum++) {
                        $interfaceLines[] = ['card' => $card, 'port' => $portNum];
                    }
                }
            }
            
            // Now get ONU details for each interface
            foreach ($interfaceLines as $interface) {
                $card = $interface['card'];
                $portNum = $interface['port'];
                
                $command = "show run interface gpon-olt_1/$card/$portNum\r\n";
                fwrite($fp, $command);
                usleep(800000); // 0.8 second delay
                
                $response = '';
                $start_time = time();
                while (time() - $start_time < 8) {
                    $data = @fread($fp, 4096);
                    if ($data === false || $data === '') {
                        break;
                    }
                    $response .= $data;
                    
                    // Look for prompt or end of output
                    if (strpos($response, '#') !== false || 
                        strpos($response, '>') !== false ||
                        strpos($response, 'Building configuration') !== false) {
                        break;
                    }
                }
                
                // Parse configured ONUs from response
                $onus = $this->parseConfiguredOnus($response, $card, $portNum);
                if (!empty($onus)) {
                    $configuredOnus = array_merge($configuredOnus, $onus);
                }
            }
            
            fclose($fp);
            
            $result['success'] = true;
            $result['onus'] = $configuredOnus;
            
        } catch (\Exception $e) {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Helper method to get configured ONUs for specific card/port via telnet
    private function getConfiguredOnusFilteredViaTelnet($ip, $port, $user, $pass, $card, $portNum)
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
                $result['message'] = 'Login gagal';
                return $result;
            }
            
            // Get ONUs for specific card/port only
            $command = "show run interface gpon-olt_1/$card/$portNum\r\n";
            fwrite($fp, $command);
            usleep(800000); // 0.8 second delay
            
            $response = '';
            $start_time = time();
            while (time() - $start_time < 8) {
                $data = @fread($fp, 4096);
                if ($data === false || $data === '') {
                    break;
                }
                $response .= $data;
                
                // Look for prompt or end of output
                if (strpos($response, '#') !== false || 
                    strpos($response, '>') !== false ||
                    strpos($response, 'Building configuration') !== false) {
                    break;
                }
            }
            
            fclose($fp);
            
            // Parse configured ONUs from response
            $configuredOnus = $this->parseConfiguredOnus($response, $card, $portNum);
            
            Log::info("Filtered ONU search result", [
                'card' => $card,
                'port' => $portNum,
                'command' => $command,
                'response_length' => strlen($response),
                'found_onus' => count($configuredOnus),
                'response_preview' => substr($response, 0, 500)
            ]);
            
            $result['success'] = true;
            $result['onus'] = $configuredOnus;
            $result['raw_response'] = $response;
            $result['debug_info'] = [
                'command' => $command,
                'response_length' => strlen($response),
                'card' => $card,
                'port' => $portNum
            ];
            
        } catch (\Exception $e) {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Helper method to get ONU by Serial Number via telnet
    private function getOnuBySnViaTelnet($ip, $port, $user, $pass, $serialNumber)
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
                $result['message'] = 'Login gagal';
                return $result;
            }
            
            // Search ONU by Serial Number
            $command = "show gpon onu by sn $serialNumber\r\n";
            fwrite($fp, $command);
            usleep(1000000); // 1 second delay
            
            $response = '';
            $start_time = time();
            while (time() - $start_time < 10) {
                $data = @fread($fp, 4096);
                if ($data === false || $data === '') {
                    break;
                }
                $response .= $data;
                
                // Look for prompt or end of output
                if (strpos($response, '#') !== false || 
                    strpos($response, '>') !== false) {
                    break;
                }
            }
            
            fclose($fp);
            
            // Parse ONU by Serial Number response
            $foundOnus = $this->parseOnuBySn($response, $serialNumber);
            
            Log::info("ONU search by SN result", [
                'serial_number' => $serialNumber,
                'command' => $command,
                'response_length' => strlen($response),
                'found_onus' => count($foundOnus),
                'response_preview' => substr($response, 0, 500)
            ]);
            
            $result['success'] = true;
            $result['onus'] = $foundOnus;
            $result['debug_info'] = [
                'command' => $command,
                'response_length' => strlen($response),
                'serial_number' => $serialNumber
            ];
            
        } catch (\Exception $e) {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Delete ONU configuration via telnet
    private function deleteOnuViaTelnet($ip, $port, $user, $pass, $card, $portNum, $onuId)
    {
        $timeout = 10;
        $result = ['success' => false, 'message' => ''];
        
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
                $result['message'] = 'Login gagal';
                return $result;
            }
            
            // Commands to delete ONU - Fixed for ZTE OLT
            $commands = [
                "conf t",
                "interface gpon-olt_1/$card/$portNum",
                "no onu $onuId",
                "end",
                "write"
            ];
            
            // Commands to delete ONU - ZTE OLT compatible syntax
            $commands = [
                "conf t",
                "interface gpon-olt_1/$card/$portNum",
                "no onu $onuId",
                "end",
                "write"
            ];
            
            $allResponses = [];
            
            foreach ($commands as $index => $command) {
                Log::info("Executing delete command", [
                    'step' => $index + 1,
                    'command' => $command,
                    'card' => $card,
                    'port' => $portNum,
                    'onu_id' => $onuId
                ]);
                
                fwrite($fp, $command . "\r\n");
                usleep(800000); // 0.8 second delay between commands
                
                // Read response
                $response = '';
                $start_time = time();
                while (time() - $start_time < 5) {
                    $data = @fread($fp, 1024);
                    if ($data === false || $data === '') {
                        break;
                    }
                    $response .= $data;
                    
                    if (strpos($response, '#') !== false || strpos($response, '>') !== false) {
                        break;
                    }
                }
                
                $allResponses[$command] = $response;
                
                Log::info("Command response", [
                    'command' => $command,
                    'response' => $response,
                    'response_length' => strlen($response)
                ]);
                
                // Check for specific error patterns
                if (stripos($response, 'invalid input') !== false || 
                    stripos($response, 'invalid parameter') !== false ||
                    stripos($response, 'error 20202') !== false) {
                    
                    Log::warning("Command failed, trying alternative approach", [
                        'failed_command' => $command,
                        'error_response' => $response
                    ]);
                    
                    // If the "no interface" command fails, try alternative commands
                    if (strpos($command, 'no interface gpon-onu') !== false) {
                        Log::info("Trying alternative delete approach");
                        
                        // Alternative approach: just remove from olt interface
                        fwrite($fp, "interface gpon-olt_1/$card/$portNum\r\n");
                        usleep(500000);
                        fread($fp, 1024); // clear buffer
                        
                        fwrite($fp, "no onu $onuId\r\n");
                        usleep(500000);
                        $altResponse = fread($fp, 1024);
                        
                        fwrite($fp, "exit\r\n");
                        usleep(500000);
                        fread($fp, 1024); // clear buffer
                        
                        Log::info("Alternative delete response", ['response' => $altResponse]);
                        
                        // Continue with remaining commands
                        continue;
                    }
                }
                
                // Check for other errors
                if (stripos($response, 'error') !== false && 
                    stripos($response, 'error 20202') === false) {
                    fclose($fp);
                    $result['message'] = "Error executing command '$command': $response";
                    $result['debug_info'] = $allResponses;
                    return $result;
                }
            }
            
            fclose($fp);
            $result['success'] = true;
            
        } catch (\Exception $e) {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $result;
    }

    // Parse configured ONUs from show run interface response
    private function parseConfiguredOnus($response, $card, $portNum)
    {
        $onus = [];
        $lines = explode("\n", $response);
        
        // Debug log
        Log::info("Parsing configured ONUs for card $card port $portNum", [
            'response_lines' => count($lines),
            'first_few_lines' => array_slice($lines, 0, 10)
        ]);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Look for ONU configuration lines with multiple patterns
            // Pattern 1: onu 3 type ZTE sn ZTEGC671CB2A
            if (preg_match('/^\s*onu\s+(\d+)\s+type\s+(\S+)\s+sn\s+(\S+)/i', $line, $matches)) {
                $onuId = $matches[1];
                $onuType = $matches[2];
                $serialNumber = $matches[3];
                
                $onus[] = [
                    'card' => $card,
                    'port' => $portNum,
                    'onu_id' => $onuId,
                    'type' => $onuType,
                    'serial_number' => $serialNumber,
                    'interface' => "gpon-olt_1/$card/$portNum",
                    'onu_interface' => "gpon-onu_1/$card/$portNum:$onuId",
                    'status' => 'configured'
                ];
                
                Log::info("Found ONU", [
                    'card' => $card,
                    'port' => $portNum,
                    'onu_id' => $onuId,
                    'type' => $onuType,
                    'sn' => $serialNumber
                ]);
            }
            // Pattern 2: Alternative format - " onu 3 type ZTE sn ZTEGC671CB2A"
            else if (preg_match('/\s+onu\s+(\d+)\s+type\s+(\S+)\s+sn\s+(\S+)/i', $line, $matches)) {
                $onuId = $matches[1];
                $onuType = $matches[2];
                $serialNumber = $matches[3];
                
                $onus[] = [
                    'card' => $card,
                    'port' => $portNum,
                    'onu_id' => $onuId,
                    'type' => $onuType,
                    'serial_number' => $serialNumber,
                    'interface' => "gpon-olt_1/$card/$portNum",
                    'onu_interface' => "gpon-onu_1/$card/$portNum:$onuId",
                    'status' => 'configured'
                ];
                
                Log::info("Found ONU (pattern 2)", [
                    'card' => $card,
                    'port' => $portNum,
                    'onu_id' => $onuId,
                    'type' => $onuType,
                    'sn' => $serialNumber
                ]);
            }
        }
        
        Log::info("Parsed configured ONUs result", [
            'card' => $card,
            'port' => $portNum,
            'found_onus' => count($onus)
        ]);
        
        return $onus;
    }

    // Parse ONU by Serial Number response
    private function parseOnuBySn($response, $serialNumber)
    {
        $onus = [];
        $lines = explode("\n", $response);
        
        Log::info("Parsing ONU by SN response", [
            'serial_number' => $serialNumber,
            'response_lines' => count($lines),
            'first_few_lines' => array_slice($lines, 0, 10),
            'all_lines' => $lines
        ]);
        
        foreach ($lines as $lineIndex => $line) {
            $originalLine = $line;
            $line = trim($line);
            
            // Skip empty lines and headers
            if (empty($line) || 
                stripos($line, 'search result') !== false || 
                strpos($line, '---') !== false ||
                strpos($line, '#') !== false) {
                continue;
            }
            
            Log::info("Processing SN search line $lineIndex", [
                'original' => $originalLine,
                'trimmed' => $line
            ]);
            
            // Pattern 1: gpon-onu_1/2/4:5 (just the interface, no serial on same line)
            if (preg_match('/^gpon-onu_1\/(\d+)\/(\d+):(\d+)$/i', $line, $matches)) {
                $card = $matches[1];
                $port = $matches[2];
                $onuId = $matches[3];
                
                Log::info("Found gpon-onu interface pattern", [
                    'card' => $card,
                    'port' => $port,
                    'onu_id' => $onuId,
                    'line' => $line
                ]);
                
                $onus[] = [
                    'card' => $card,
                    'port' => $port,
                    'onu_id' => $onuId,
                    'type' => 'ZTE', // Default type for ZTE OLT
                    'serial_number' => $serialNumber, // Use the searched serial number
                    'interface' => "gpon-olt_1/$card/$port",
                    'onu_interface' => "gpon-onu_1/$card/$port:$onuId",
                    'status' => 'found_by_sn'
                ];
                
                Log::info("Found ONU by Serial Number (simple pattern)", [
                    'card' => $card,
                    'port' => $port,
                    'onu_id' => $onuId,
                    'sn' => $serialNumber
                ]);
            }
            // Pattern 2: gpon-onu_1/2/4:5         ZTEGC76F6812        unknown (with serial)
            else if (preg_match('/gpon-onu_1\/(\d+)\/(\d+):(\d+)\s+(\S+)/i', $line, $matches)) {
                $card = $matches[1];
                $port = $matches[2];
                $onuId = $matches[3];
                $foundSn = $matches[4];
                
                Log::info("Found gpon-onu interface with SN pattern", [
                    'card' => $card,
                    'port' => $port,
                    'onu_id' => $onuId,
                    'found_sn' => $foundSn,
                    'target_sn' => $serialNumber
                ]);
                
                // Verify the serial number matches
                if (strtoupper($foundSn) === strtoupper($serialNumber)) {
                    $onus[] = [
                        'card' => $card,
                        'port' => $port,
                        'onu_id' => $onuId,
                        'type' => 'ZTE',
                        'serial_number' => $foundSn,
                        'interface' => "gpon-olt_1/$card/$port",
                        'onu_interface' => "gpon-onu_1/$card/$port:$onuId",
                        'status' => 'found_by_sn'
                    ];
                    
                    Log::info("Found ONU by Serial Number (with SN pattern)", [
                        'card' => $card,
                        'port' => $port,
                        'onu_id' => $onuId,
                        'sn' => $foundSn
                    ]);
                }
            }
        }
        
        Log::info("Final parsed ONU by SN result", [
            'serial_number' => $serialNumber,
            'found_onus' => count($onus),
            'onus' => $onus
        ]);
        
        return $onus;
    }

    // Helper method for telnet login
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
}
