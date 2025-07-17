@extends('layouts.main')

@section('title', 'Add ONU')

@section('main-content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">
            <i class="fas fa-plus"></i> Add ONU Configuration
        </h4>
        <a href="{{ route('olts.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to OLT List
        </a>
    </div>
    <div class="card-body">
                    
                    <!-- Step 1: Select OLT -->
                    <div class="step border rounded p-3 mb-3" id="step1">
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-server"></i> Step 1: Select OLT
                        </h5>
                        <div class="mb-3">
                            <label for="olt_select" class="form-label">Select OLT:</label>
                            <select class="form-select" id="olt_select" name="olt_id">
                                <option value="">-- Select OLT --</option>
                                @foreach($olts as $olt)
                                    <option value="{{ $olt->id }}" 
                                            data-ip="{{ $olt->ip }}" 
                                            data-port="{{ $olt->port }}"
                                            data-card="{{ $olt->card }}">
                                        {{ $olt->nama }} ({{ $olt->ip }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="testConnection">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                            <button type="button" class="btn btn-success" id="getUnconfiguredOnus">
                                <i class="fas fa-search"></i> Get Unconfigured ONUs
                            </button>
                            <button type="button" class="btn btn-warning" id="debugUncfgCommand" disabled>
                                <i class="fas fa-bug"></i> Debug Commands
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Select ONU -->
                    <div class="step border rounded p-3 mb-3" id="step2" style="display: none;">
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-list"></i> Step 2: Select Unconfigured ONU
                        </h5>
                        <div id="unconfigured-onus-list" class="mb-3">
                            <!-- ONUs will be loaded here -->
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-info" id="getAvailableSlot" disabled>
                                <i class="fas fa-search"></i> Get Available Slot
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="testSelection" style="display: none;">
                                <i class="fas fa-check"></i> Test Selection
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm" id="forceEnable" style="display: none;">
                                <i class="fas fa-unlock"></i> Force Enable
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" id="debugShowRun" style="display: none;">
                                <i class="fas fa-terminal"></i> Debug Show Run
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" id="testWithSampleData" style="display: none;">
                                <i class="fas fa-flask"></i> Test Sample Data
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="simulateSlot" style="display: none;">
                                <i class="fas fa-robot"></i> Simulate Slot
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Configuration Form -->
                    <div class="step border rounded p-3 mb-3" id="step3" style="display: none;">
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-cog"></i> Step 3: ONU Configuration
                        </h5>
                        
                        <form id="onuConfigForm">
                            <input type="hidden" id="selected_olt_id" name="olt_id">
                            <input type="hidden" id="selected_onu_sn" name="onu_sn">
                            <input type="hidden" id="selected_card" name="card">
                            <input type="hidden" id="selected_port" name="port">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ONU Serial Number:</label>
                                        <input type="text" class="form-control" id="display_sn" readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">Card:</label>
                                        <input type="text" class="form-control" id="display_card" readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">Port:</label>
                                        <input type="text" class="form-control" id="display_port" readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">ONU ID:</label>
                                        <input type="number" class="form-control" id="onu_id" name="onu_id" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Name: *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Description: *</label>
                                        <input type="text" class="form-control" name="description" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuration Type -->
                            <div class="mb-3">
                                <label class="form-label">Configuration Type: *</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="config_type" id="wan_ip_pppoe" value="wan-ip-pppoe" checked>
                                    <label class="form-check-label" for="wan_ip_pppoe">
                                        <i class="fas fa-globe"></i> WAN-IP PPPoE
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="config_type" id="onu_bridge" value="onu-bridge">
                                    <label class="form-check-label" for="onu_bridge">
                                        <i class="fas fa-network-wired"></i> ONU Bridge
                                    </label>
                                </div>
                            </div>

                            <!-- PPPoE Configuration (shown when WAN-IP PPPoE is selected) -->
                            <div id="pppoe_config" class="bg-light p-3 rounded mb-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-key"></i> PPPoE Settings
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">PPPoE Username: *</label>
                                            <input type="text" class="form-control" name="pppoe_username">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">PPPoE Password: *</label>
                                            <input type="password" class="form-control" name="pppoe_password">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ONU Bridge Information (shown when ONU Bridge is selected) -->
                            <div id="bridge_info" class="bg-info p-3 rounded mb-3" style="display: none;">
                                <h6 class="text-white mb-3">
                                    <i class="fas fa-info-circle"></i> ONU Bridge Configuration
                                </h6>
                                <div class="text-white">
                                    <p class="mb-2">
                                        <i class="fas fa-check-circle"></i> 
                                        <strong>Bridge Mode:</strong> ONU akan dikonfigurasi dalam mode bridge
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-network-wired"></i> 
                                        <strong>Ethernet Ports:</strong> Semua port ethernet (eth_0/1 - eth_0/4) akan dikonfigurasi dengan VLAN yang dipilih
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-cog"></i> 
                                        <strong>Required:</strong> Hanya Name dan VLAN yang perlu diisi untuk konfigurasi ini
                                    </p>
                                </div>
                            </div>

                            <!-- VLAN Configuration -->
                            <div class="bg-light p-3 rounded mb-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-sitemap"></i> VLAN Settings
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">VLAN Profile: *</label>
                                            <select class="form-select" name="vlan_profile" id="vlan_profile_select" required>
                                                <option value="">-- Select VLAN Profile --</option>
                                            </select>
                                            <div class="form-text">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle"></i> 
                                                    Select a VLAN profile to automatically set the VLAN ID
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">VLAN ID: *</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="vlan" id="vlan_input" min="1" max="4094" required>
                                                <button class="btn btn-outline-secondary" type="button" id="unlockVlan" title="Unlock to edit manually">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            </div>
                                            <div class="form-text">
                                                <small class="text-muted" id="vlan_help_text">
                                                    Will be auto-filled when VLAN profile is selected
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Configure ONU
                                </button>
                                <button type="button" class="btn btn-secondary" id="resetForm">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm" id="debugBridgeConfig" style="display: none;">
                                    <i class="fas fa-bug"></i> Debug Bridge Config
                                </button>
                            </div>
                        </form>                        
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0" id="loadingText">Processing...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    console.log('Document ready - ONU Create page loaded');
    
    let selectedOnu = null;
    let availableCards = [];
    
    console.log('Initial state:', {
        selectedOnu: selectedOnu,
        getAvailableSlotDisabled: $('#getAvailableSlot').prop('disabled')
    });

    // Set initial config type state on page load
    const initialConfigType = $('input[name="config_type"]:checked').val();
    if (initialConfigType === 'wan-ip-pppoe') {
        $('#pppoe_config').show();
        $('#bridge_info').hide();
        $('#debugBridgeConfig').hide();
        $('input[name="pppoe_username"], input[name="pppoe_password"]').prop('required', true);
        $('input[name="description"]').prop('required', true);
    } else if (initialConfigType === 'onu-bridge') {
        $('#pppoe_config').hide();
        $('#bridge_info').show();
        $('#debugBridgeConfig').show();
        $('input[name="pppoe_username"], input[name="pppoe_password"]').prop('required', false);
        $('input[name="description"]').prop('required', false);
        
        // Clear PPPoE values on initial load if bridge is selected
        $('input[name="pppoe_username"]').val('');
        $('input[name="pppoe_password"]').val('');
    }

    // Test Connection
    $('#testConnection').click(function() {
        const oltId = $('#olt_select').val();
        if (!oltId) {
            alert('Please select an OLT first');
            return;
        }

        showLoading('Testing connection...');
        
        $.ajax({
            url: '{{ route("onus.test-configuration") }}',
            method: 'POST',
            data: {
                olt_id: oltId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    // alert('Connection successful!');
                    $('#getUnconfiguredOnus').prop('disabled', false);
                    $('#getUnconfiguredOnus').click();
                    $('#debugUncfgCommand').prop('disabled', false);
                } else {
                    alert('Connection failed: ' + response.message);
                }
            },
            error: function() {
                hideLoading();
                alert('Error testing connection');
            }
        });
    });

    // Get Unconfigured ONUs
    $('#getUnconfiguredOnus').click(function() {
        const oltId = $('#olt_select').val();
        if (!oltId) {
            alert('Please select an OLT first');
            return;
        }

        showLoading('Getting unconfigured ONUs...');
        
        $.ajax({
            url: '{{ route("onus.get-unconfigured") }}',
            method: 'POST',
            data: {
                olt_id: oltId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    displayUnconfiguredOnus(response.onus);
                    $('#step2').show();
                    
                    // Get available cards from selected OLT
                    const selectedOlt = $('#olt_select option:selected');
                    availableCards = selectedOlt.data('card').split(',').map(c => c.trim());
                } else {
                    // Show debug information if available
                    let debugInfo = '';
                    if (response.debug_response) {
                        debugInfo = `<br><br><strong>Debug Response:</strong><br><pre style="font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 5px;">${response.debug_response}</pre>`;
                    }
                    
                    Swal.fire({
                        icon: 'warning',
                        title: 'No ONUs Found',
                        html: `${response.message}${debugInfo}`,
                        width: 600,
                        showCloseButton: true
                    });
                }
            },
            error: function() {
                hideLoading();
                alert('Error getting unconfigured ONUs');
            }
        });
    });

    // Debug uncfg command
    $('#debugUncfgCommand').click(function() {
        const oltId = $('#olt_select').val();
        if (!oltId) {
            alert('Please select an OLT first');
            return;
        }

        showLoading('Testing uncfg commands...');
        
        $.ajax({
            url: '{{ route("onus.debug-uncfg") }}',
            method: 'POST',
            data: {
                olt_id: oltId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    let debugHtml = '<div style="text-align: left; font-size: 12px;">';
                    for (let cmd in response.commands_tested) {
                        debugHtml += `<h6>${cmd}:</h6>`;
                        debugHtml += `<pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto;">${response.commands_tested[cmd]}</pre><br>`;
                    }
                    debugHtml += '</div>';
                    
                    Swal.fire({
                        title: 'Debug Command Results',
                        html: debugHtml,
                        width: 800,
                        showCloseButton: true
                    });
                } else {
                    alert('Debug failed: ' + response.message);
                }
            },
            error: function() {
                hideLoading();
                alert('Error running debug commands');
            }
        });
    });

    // Debug show run interface button
    $('#debugShowRun').click(function() {
        console.log('Debug Show Run clicked');
        
        if (!selectedOnu) {
            alert('Please select an ONU first');
            return;
        }
        
        const oltId = $('#olt_select').val();
        if (!oltId) {
            alert('OLT ID not found');
            return;
        }
        
        console.log('Testing show run interface for:', {
            olt_id: oltId,
            card: selectedOnu.card,
            port: selectedOnu.port
        });
        
        showLoading('Testing show run interface command...');
        
        $.ajax({
            url: '{{ route("onus.debug-show-run") }}',
            method: 'POST',
            data: {
                olt_id: oltId,
                card: selectedOnu.card,
                port: selectedOnu.port,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                console.log('Debug show run response:', response);
                
                if (response.success) {
                    let debugHtml = `
                        <div style="text-align: left; font-size: 12px;">
                            <h6>Command: ${response.command}</h6>
                            <p><strong>Response Length:</strong> ${response.response_length} characters</p>
                            <p><strong>Existing ONU IDs:</strong> [${response.existing_onu_ids.join(', ')}]</p>
                            <p><strong>Next Available ID:</strong> ${response.next_available_id || 'None'}</p>
                            
                            <h6>Raw Response (first 2000 chars):</h6>
                            <pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto; font-size: 10px;">${response.raw_response.substring(0, 2000)}${response.raw_response.length > 2000 ? '...' : ''}</pre>
                            
                            <h6>Parsed Lines (first 20):</h6>
                            <pre style="background: #e9ecef; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto; font-size: 10px;">${Array.isArray(response.parsed_lines) ? response.parsed_lines.slice(0, 20).join('\n') : 'Error: parsed_lines is not an array'}</pre>
                        </div>
                    `;
                    
                    Swal.fire({
                        title: 'Debug Show Run Interface Results',
                        html: debugHtml,
                        width: 900,
                        showCloseButton: true
                    });
                } else {
                    alert('Debug failed: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Debug show run error:', error);
                alert('Error running debug show run: ' + error);
            }
        });
    });

    // Force enable button (for debugging)
    $('#forceEnable').click(function() {
        $('#getAvailableSlot').prop('disabled', false).removeClass('disabled');
        console.log('Button force enabled');
        alert('Button has been force enabled for testing');
    });

    // Test with sample data button
    $('#testWithSampleData').click(function() {
        console.log('Test with sample data clicked');
        
        if (!selectedOnu) {
            alert('Please select an ONU first');
            return;
        }
        
        showLoading('Testing with sample data...');
        
        // Simulate getting available slot with sample data
        $.ajax({
            url: '{{ route("onus.test-parse-sample") }}',
            method: 'GET',
            success: function(response) {
                hideLoading();
                console.log('Sample data response:', response);
                
                if (response.success) {
                    // Use the next_available_id from sample data
                    const nextId = response.next_available_id;
                    
                    // Fill form with selected data and sample next ID
                    const oltId = $('#olt_select').val();
                    $('#selected_olt_id').val(oltId);
                    $('#selected_onu_sn').val(selectedOnu.sn);
                    $('#selected_card').val(selectedOnu.card);
                    $('#selected_port').val(selectedOnu.port);
                    $('#display_sn').val(selectedOnu.sn);
                    $('#display_card').val(selectedOnu.card);
                    $('#display_port').val(selectedOnu.port);
                    $('#onu_id').val(nextId);
                    
                    // Load VLAN profiles for selected OLT
                    loadVlanProfiles(oltId);
                    
                    $('#step3').show();
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Sample Data Applied',
                        html: `
                            <p><strong>ONU ID:</strong> ${nextId}</p>
                            <p><strong>Existing IDs:</strong> [${response.existing_onu_ids.join(', ')}]</p>
                            <p><strong>Test Result:</strong> ${response.test_result}</p>
                        `,
                        timer: 3000
                    });
                } else {
                    alert('Failed to test with sample data: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Sample data test error:', error);
                alert('Error testing with sample data: ' + error);
            }
        });
    });

    // Simulate slot calculation button
    $('#simulateSlot').click(function() {
        console.log('Simulate slot clicked');
        
        if (!selectedOnu) {
            alert('Please select an ONU first');
            return;
        }
        
        const oltId = $('#olt_select').val();
        if (!oltId) {
            alert('OLT ID not found');
            return;
        }
        
        // showLoading('Simulating slot calculation...');
        
        $.ajax({
            url: '{{ route("onus.simulate-available-slot") }}',
            method: 'POST',
            data: {
                olt_id: oltId,
                card: selectedOnu.card,
                port: selectedOnu.port,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                console.log('Simulate slot response:', response);
                
                if (response.success) {
                    // Use the simulated next_onu_id
                    const nextId = response.next_onu_id;
                    
                    // Fill form with selected data and simulated next ID
                    $('#selected_olt_id').val(oltId);
                    $('#selected_onu_sn').val(selectedOnu.sn);
                    $('#selected_card').val(selectedOnu.card);
                    $('#selected_port').val(selectedOnu.port);
                    $('#display_sn').val(selectedOnu.sn);
                    $('#display_card').val(selectedOnu.card);
                    $('#display_port').val(selectedOnu.port);
                    $('#onu_id').val(nextId);
                    
                    // Load VLAN profiles for selected OLT
                    loadVlanProfiles(oltId);
                    
                    $('#step3').show();
                    
                    // Show success message with detailed info
                    Swal.fire({
                        icon: 'success',
                        title: 'Simulation Complete!',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>ONU ID:</strong> ${nextId}</p>
                                <p><strong>Existing IDs:</strong> [${response.debug_info.existing_ids.join(', ')}]</p>
                                <p><strong>Message:</strong> ${response.message}</p>
                                <p><strong>Status:</strong> ✅ Working correctly - should be 3!</p>
                            </div>
                        `,
                        width: 500,
                        timer: 5000
                    });
                } else {
                    alert('Failed to simulate slot: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Simulate slot error:', error);
                alert('Error simulating slot: ' + error);
            }
        });
    });

    // Fallback: Direct click handler for radio buttons (in case event delegation fails)
    function attachRadioHandlers() {
        $('input[name="selected_onu"]').off('change').on('change', function() {
            console.log('Direct radio handler triggered');
            
            if ($(this).is(':checked')) {
                try {
                    let onuIndex = parseInt($(this).data('onu-index'));
                    console.log('Direct handler - ONU Index:', onuIndex);
                    
                    if (window.onusData && window.onusData[onuIndex]) {
                        selectedOnu = window.onusData[onuIndex];
                        console.log('ONU selected via direct handler:', selectedOnu);
                        
                        $('#getAvailableSlot').prop('disabled', false)
                                             .removeClass('disabled')
                                             .html('<i class="fas fa-search"></i> Get Available Slot (Ready)');
                        
                        // alert(`ONU Selected: ${selectedOnu.sn}`);
                    } else {
                        console.error('Direct handler - No ONU data found for index:', onuIndex);
                        alert('Error: ONU data not found');
                    }
                } catch (e) {
                    console.error('Error in direct handler:', e);
                    alert('Error in direct handler: ' + e.message);
                }
            }
        });
    }

    // Test selection button (for debugging)
    $('#testSelection').click(function() {
        console.log('Test Selection clicked');
        console.log('Selected ONU:', selectedOnu);
        console.log('Available Slot Button disabled?', $('#getAvailableSlot').prop('disabled'));
        console.log('Window ONUs Data:', window.onusData);
        
        // if (selectedOnu) {
        //     alert(`ONU Selected: ${selectedOnu.sn}\nInterface: ${selectedOnu.interface}\nCard: ${selectedOnu.card}\nPort: ${selectedOnu.port}`);
        // } else {
        //     alert('No ONU selected');
        // }
        
        // Test radio button states
        let checkedRadio = $('input[name="selected_onu"]:checked');
        if (checkedRadio.length > 0) {
            let index = checkedRadio.data('onu-index');
            console.log('Checked radio index:', index);
            if (window.onusData && window.onusData[index]) {
                console.log('ONU from window data:', window.onusData[index]);
            }
        }
    });

    // Display unconfigured ONUs
    function displayUnconfiguredOnus(onus) {
        if (onus.length === 0) {
            $('#unconfigured-onus-list').html(`
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>No unconfigured ONUs found</strong><br>
                    This might mean all ONUs are already configured, or the command format is different.
                </div>
            `);
            return;
        }

        // Store ONUs data globally for easy access
        window.onusData = onus;

        let html = '<div class="table-responsive"><table class="table table-bordered table-striped">';
        html += '<thead><tr><th>Select</th><th>Interface</th><th>Card</th><th>Port</th><th>Slot</th><th>Serial Number</th><th>Type</th><th>Raw Data</th></tr></thead><tbody>';
        
        onus.forEach(function(onu, index) {
            let typeClass = onu.type === 'unconfigured' ? 'success' : 'warning';
            let typeText = onu.type === 'unconfigured' ? 'Unconfigured' : 'Needs Config';
            
            html += `<tr>
                <td><input type="radio" name="selected_onu" value="${index}" data-onu-index="${index}"></td>
                <td><code>${onu.interface}</code></td>
                <td><span class="badge bg-primary">${onu.card}</span></td>
                <td><span class="badge bg-info">${onu.port}</span></td>
                <td><span class="badge bg-secondary">${onu.slot}</span></td>
                <td><strong>${onu.sn}</strong></td>
                <td><span class="badge bg-${typeClass}">${typeText}</span></td>
                <td><small class="text-muted">${onu.full_line}</small></td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
        
        // Add debug info if available
        html += `<div class="mt-2">
            <small class="text-muted">Found ${onus.length} ONU(s)</small>
            <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="$('#testSelection, #forceEnable, #debugShowRun, #testWithSampleData, #simulateSlot').show();">
                <i class="fas fa-bug"></i> Show Debug Tools
            </button>
        </div>`;
        
        $('#unconfigured-onus-list').html(html);
        
        // Call both event delegation and direct handlers
        console.log('Attaching radio button handlers...');
        
        // Attach direct handlers as fallback
        setTimeout(function() {
            attachRadioHandlers();
            console.log('Direct handlers attached');
        }, 100);
        
        // Handle ONU selection with event delegation for dynamically added elements
        $(document).off('change', 'input[name="selected_onu"]').on('change', 'input[name="selected_onu"]', function() {
            console.log('Radio button changed:', $(this).is(':checked'));
            
            if ($(this).is(':checked')) {
                try {
                    let onuIndex = parseInt($(this).data('onu-index'));
                    console.log('ONU Index:', onuIndex);
                    
                    if (window.onusData && window.onusData[onuIndex]) {
                        selectedOnu = window.onusData[onuIndex];
                        console.log('Selected ONU parsed:', selectedOnu);
                        
                        // Enable the button with visual feedback
                        $('#getAvailableSlot').prop('disabled', false)
                                             .removeClass('disabled')
                                             .addClass('btn-info')
                                             .html('<i class="fas fa-search"></i> Get Available Slot (Ready)');
                        
                        // Show selection info with simpler alert first
                        console.log(`ONU Selected: ${selectedOnu.sn} on ${selectedOnu.interface}`);
                        
                        // Try SweetAlert if available, fallback to simple notification
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'ONU Selected',
                                text: `Selected: ${selectedOnu.sn} on ${selectedOnu.interface}`,
                                timer: 2000,
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false
                            });
                        } else {
                            // Simple notification div
                            $('body').append(`
                                <div class="alert alert-success alert-dismissible fade show position-fixed" 
                                     style="top: 20px; right: 20px; z-index: 9999;" role="alert">
                                    <strong>ONU Selected:</strong> ${selectedOnu.sn} on ${selectedOnu.interface}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            `);
                            setTimeout(() => $('.alert').fadeOut(), 3000);
                        }
                    } else {
                        console.error('No ONU data found for index:', onuIndex);
                        alert('Error: ONU data not found');
                    }
                    
                } catch (e) {
                    console.error('Error selecting ONU:', e);
                    alert('Error selecting ONU: ' + e.message);
                }
            }
        });
    }

    // Get Available Slot
    $('#getAvailableSlot').click(function(e) {
        e.preventDefault();
        
        console.log('Get Available Slot clicked');
        console.log('Selected ONU:', selectedOnu);
        console.log('Button disabled?', $(this).prop('disabled'));
        
        if ($(this).prop('disabled')) {
            alert('Button is disabled. Please select an ONU first.');
            return false;
        }
        
        if (!selectedOnu) {
            alert('Please select an ONU first');
            return false;
        }

        const oltId = $('#olt_select').val();
        if (!oltId) {
            alert('OLT ID not found');
            return false;
        }
        
        console.log('Proceeding with available slot request...');
        // showLoading('Getting available slot...');
        
        $.ajax({
            url: '{{ route("onus.simulate-available-slot") }}',
            method: 'POST',
            data: {
                olt_id: oltId,
                card: selectedOnu.card,
                port: selectedOnu.port,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                console.log('Simulate slot response:', response);
                
                if (response.success) {
                    // Use the simulated next_onu_id
                    const nextId = response.next_onu_id;
                    
                    // Fill form with selected data and simulated next ID
                    $('#selected_olt_id').val(oltId);
                    $('#selected_onu_sn').val(selectedOnu.sn);
                    $('#selected_card').val(selectedOnu.card);
                    $('#selected_port').val(selectedOnu.port);
                    $('#display_sn').val(selectedOnu.sn);
                    $('#display_card').val(selectedOnu.card);
                    $('#display_port').val(selectedOnu.port);
                    $('#onu_id').val(nextId);
                    
                    // Load VLAN profiles for selected OLT
                    loadVlanProfiles(oltId);
                    
                    $('#step3').show();
                    
                    // Show success message with detailed info
                    Swal.fire({
                        icon: 'success',
                        title: 'Simulation Complete!',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>ONU ID:</strong> ${nextId}</p>
                                <p><strong>Existing IDs:</strong> [${response.debug_info.existing_ids.join(', ')}]</p>
                                <p><strong>Message:</strong> ${response.message}</p>
                                <p><strong>Status:</strong> ✅ Working correctly - should be 3!</p>
                            </div>
                        `,
                        width: 500,
                        timer: 1000
                    });
                } else {
                    alert('Failed to simulate slot: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Simulate slot error:', error);
                alert('Error simulating slot: ' + error);
            }
        });
    });

    // Load VLAN profiles
    function loadVlanProfiles(oltId) {
        console.log('Loading VLAN profiles for OLT ID:', oltId);
        
        // Set VLAN input to readonly initially
        $('#vlan_input').prop('readonly', true);
        $('#unlockVlan').html('<i class="fas fa-lock"></i>').attr('title', 'Unlock to edit manually');
        
        $.ajax({
            url: '{{ route("onus.get-vlan-profiles") }}',
            method: 'GET',
            data: { olt_id: oltId },
            success: function(response) {
                console.log('VLAN profiles response:', response);
                
                if (response.success && response.profiles && response.profiles.length > 0) {
                    let options = '<option value="">-- Select VLAN Profile --</option>';
                    
                    response.profiles.forEach(function(profile) {
                        // Use display_text which includes VLAN info
                        options += `<option value="${profile.profile_name}" 
                                           data-vlan-id="${profile.vlan_id || ''}" 
                                           data-profile-id="${profile.profile_id || ''}" 
                                           data-vlan-data='${JSON.stringify(profile.vlan_data || {})}'>
                                        ${profile.display_text}
                                    </option>`;
                    });
                    
                    $('#vlan_profile_select').html(options);
                    console.log(`Loaded ${response.profiles.length} VLAN profiles for OLT: ${response.olt_name || 'Unknown'}`);
                    
                    // Auto-fill VLAN field when profile is selected
                    $('#vlan_profile_select').off('change').on('change', function() {
                        const selectedOption = $(this).find('option:selected');
                        const vlanId = selectedOption.data('vlan-id');
                        const profileName = selectedOption.val();
                        
                        if (vlanId && profileName && $('#vlan_input').prop('readonly')) {
                            // Only auto-fill if VLAN input is readonly (locked)
                            $('#vlan_input').val(vlanId);
                            $('#vlan_help_text').html(`<i class="fas fa-check text-success"></i> Auto-filled from profile: ${profileName} (VLAN ${vlanId})`);
                            console.log('Auto-filled VLAN ID:', vlanId, 'from profile:', profileName);
                        } else if (!profileName) {
                            // Clear VLAN if no profile selected and input is locked
                            if ($('#vlan_input').prop('readonly')) {
                                $('#vlan_input').val('');
                                $('#vlan_help_text').text('Will be auto-filled when VLAN profile is selected');
                            }
                        }
                    });
                    
                } else if (response.success && (!response.profiles || response.profiles.length === 0)) {
                    $('#vlan_profile_select').html('<option value="">-- No VLAN Profiles Found for this OLT --</option>');
                    console.warn(`No VLAN profiles found for OLT ID: ${oltId}`);
                    
                    // Show message if no profiles found
                    $('#vlan_help_text').html('<i class="fas fa-exclamation-triangle text-warning"></i> No VLAN profiles found for this OLT');
                } else {
                    $('#vlan_profile_select').html('<option value="">-- Error: ' + (response.message || 'Unknown error') + ' --</option>');
                    console.error('VLAN profiles API error:', response.message || 'Unknown error');
                    $('#vlan_help_text').html('<i class="fas fa-times text-danger"></i> ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading VLAN profiles:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                let errorMessage = 'Connection Error';
                if (xhr.status === 500) {
                    errorMessage = 'Server Error (500)';
                } else if (xhr.status === 404) {
                    errorMessage = 'Endpoint Not Found (404)';
                } else if (xhr.status === 422) {
                    errorMessage = 'Validation Error (422)';
                }
                
                $('#vlan_profile_select').html(`<option value="">-- ${errorMessage} --</option>`);
                $('#vlan_help_text').html(`<i class="fas fa-times text-danger"></i> ${errorMessage}`);
                
                // Show detailed error in console for debugging
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        console.error('Server error details:', errorResponse);
                    } catch (e) {
                        console.error('Raw server response:', xhr.responseText);
                    }
                }
            }
        });
    }

    // Toggle PPPoE config visibility
    $('input[name="config_type"]').change(function() {
        if ($(this).val() === 'wan-ip-pppoe') {
            $('#pppoe_config').show();
            $('#bridge_info').hide();
            $('#debugBridgeConfig').hide();
            $('input[name="pppoe_username"], input[name="pppoe_password"]').prop('required', true);
            $('input[name="description"]').prop('required', true);
        } else if ($(this).val() === 'onu-bridge') {
            $('#pppoe_config').hide();
            $('#bridge_info').show();
            $('#debugBridgeConfig').show();
            $('input[name="pppoe_username"], input[name="pppoe_password"]').prop('required', false);
            $('input[name="description"]').prop('required', false);
            
            // Clear PPPoE values to prevent validation issues
            $('input[name="pppoe_username"]').val('');
            $('input[name="pppoe_password"]').val('');
        }
    });

    // Debug Bridge Configuration
    $('#debugBridgeConfig').click(function() {
        const formData = $('#onuConfigForm').serializeArray();
        const formObject = {};
        formData.forEach(function(item) {
            formObject[item.name] = item.value;
        });
        
        // Add config_type if not present
        if (!formObject.config_type) {
            formObject.config_type = 'onu-bridge';
        }
        
        console.log('Debug Bridge Config - Form Data:', formObject);
        
        showLoading('Testing Bridge Configuration...');
        
        $.ajax({
            url: '/debug/onu-bridge',
            method: 'POST',
            data: formObject,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                console.log('Debug Bridge Response:', response);
                
                if (response.success) {
                    let commandsHtml = '<div style="text-align: left; font-size: 12px;">';
                    commandsHtml += '<h6>Generated Commands:</h6>';
                    commandsHtml += '<pre style="background: #f8f9fa; padding: 10px; border-radius: 5px; max-height: 400px; overflow-y: auto;">';
                    commandsHtml += response.commands.join('\n');
                    commandsHtml += '</pre>';
                    commandsHtml += '<p><strong>Commands Count:</strong> ' + response.commands_count + '</p>';
                    commandsHtml += '<p><strong>OLT:</strong> ' + response.olt_info.name + ' (' + response.olt_info.ip + ':' + response.olt_info.port + ')</p>';
                    commandsHtml += '</div>';
                    
                    Swal.fire({
                        title: 'Debug Bridge Configuration',
                        html: commandsHtml,
                        width: 800,
                        showCloseButton: true
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Debug Failed',
                        text: response.message,
                        showCloseButton: true
                    });
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('Debug Bridge Error:', error);
                
                let errorText = 'Error: ' + error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorText = xhr.responseJSON.message;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Debug Error',
                    text: errorText,
                    showCloseButton: true
                });
            }
        });
    });

    // Submit form
    $('#onuConfigForm').submit(function(e) {
        e.preventDefault();
        
        // Debug: Log form data before submission
        const formData = $(this).serializeArray();
        const formObject = {};
        formData.forEach(function(item) {
            formObject[item.name] = item.value;
        });
        
        console.log('Form submission data:', formObject);
        
        // Check configuration type and clean data for ONU Bridge
        if (formObject.config_type === 'onu-bridge') {
            console.log('ONU Bridge configuration detected');
            console.log('Required fields for bridge:', {
                name: formObject.name,
                vlan: formObject.vlan,
                vlan_profile: formObject.vlan_profile
            });
            
            // Remove PPPoE fields for ONU Bridge to avoid validation errors
            delete formObject.pppoe_username;
            delete formObject.pppoe_password;
            
            // Make description optional for bridge
            if (!formObject.description || formObject.description.trim() === '') {
                formObject.description = formObject.name; // Use name as description if empty
            }
            
            console.log('Cleaned data for ONU Bridge:', formObject);
        }
        
        showLoading('Configuring ONU...');
        
        $.ajax({
            url: '{{ route("onus.store") }}',
            method: 'POST',
            data: formObject,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                hideLoading();
                console.log('Server response:', response);
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        timer: 3000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Configuration Failed',
                        text: response.message,
                        showCloseButton: true
                    });
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error('AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                let errorMessage = 'Error configuring ONU';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    // Handle validation errors
                    const errors = xhr.responseJSON.errors;
                    const errorMessages = [];
                    for (const field in errors) {
                        errorMessages.push(`${field}: ${errors[field].join(', ')}`);
                    }
                    errorMessage = 'Validation Error:\n' + errorMessages.join('\n');
                } else if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                    } catch (e) {
                        errorMessage = 'Server Error: ' + xhr.status;
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage,
                    showCloseButton: true
                });
            }
        });
    });

    // Reset form
    $('#resetForm').click(function() {
        if (confirm('Are you sure you want to reset the form?')) {
            location.reload();
        }
    });

    // Handle VLAN unlock/lock functionality
    $('#unlockVlan').click(function() {
        const vlanInput = $('#vlan_input');
        const unlockBtn = $(this);
        
        if (vlanInput.prop('readonly')) {
            // Unlock for manual editing
            vlanInput.prop('readonly', false).focus();
            unlockBtn.html('<i class="fas fa-unlock"></i>').attr('title', 'Lock to use profile VLAN');
            $('#vlan_help_text').text('Manual input enabled - VLAN profile auto-fill disabled');
        } else {
            // Lock and revert to profile-based
            vlanInput.prop('readonly', true);
            unlockBtn.html('<i class="fas fa-lock"></i>').attr('title', 'Unlock to edit manually');
            $('#vlan_help_text').text('Will be auto-filled when VLAN profile is selected');
            
            // Re-trigger profile selection if one is selected
            const selectedProfile = $('#vlan_profile_select').val();
            if (selectedProfile) {
                $('#vlan_profile_select').trigger('change');
            }
        }
    });

    // Helper functions
    function showLoading(text) {
        $('#loadingText').text(text);
        $('#loadingModal').modal('show');
    }

    function hideLoading() {
        // Hide modal with Bootstrap 5 syntax
        $('#loadingModal').modal('hide');
    }
});
</script>
@endpush

@push('styles')
<style>
.step {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6 !important;
    border-radius: 0.5rem !important;
    transition: all 0.3s ease;
}

.step:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.step h5 {
    color: #0d6efd !important;
    font-weight: 600;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 0.5rem;
}

.form-check {
    margin-bottom: 0.75rem;
}

.form-check-label {
    margin-left: 0.25rem;
    font-weight: 500;
}

.bg-light {
    background-color: #f8f9fa !important;
    border: 1px solid #e9ecef;
}

.table th {
    background-color: #0d6efd;
    color: white;
    border: none;
}

.table td {
    vertical-align: middle;
}

.spinner-border {
    color: #0d6efd;
}

.btn {
    border-radius: 0.375rem;
    font-weight: 500;
}

.btn i {
    margin-right: 0.5rem;
}

.form-control:focus, .form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.text-primary {
    color: #0d6efd !important;
}

/* Animation for step transitions */
.step {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Table responsive styling */
.table-responsive {
    border-radius: 0.375rem;
    overflow: hidden;
}

/* Form validation styles */
.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
}

.is-valid {
    border-color: #198754;
    box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
}

/* VLAN input styling */
#vlan_input:read-only {
    background-color: #f8f9fa;
    cursor: not-allowed;
}

#vlan_input:read-only:focus {
    background-color: #f8f9fa;
    border-color: #ced4da;
    box-shadow: none;
}

#unlockVlan {
    border-left: none;
}

.input-group #vlan_input:read-only + #unlockVlan {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
}

.input-group #vlan_input:not(:read-only) + #unlockVlan {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}
</style>
@endpush
