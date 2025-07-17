@extends('layouts.main')

@section('title', 'ONU Management')

@section('main-content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">
            <i class="fas fa-network-wired"></i> ONU Management
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('onus.create') }}" class="btn btn-success">
                <i class="fas fa-plus"></i> Add ONU
            </a>
            <a href="{{ route('olts.index') }}" class="btn btn-secondary">
                <i class="fas fa-server"></i> Manage OLT
            </a>
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle"></i>
            <strong>ONU Configuration Management</strong><br>
            Use this page to configure and manage ONUs on your OLT devices. You can add new ONU configurations with WAN-IP PPPoE or ONU Bridge modes.
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Total OLTs</h5>
                                <h3 class="mb-0">{{ $olts->count() }}</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-server fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Active ONUs</h5>
                                <h3 class="mb-0">-</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-network-wired fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Unconfigured</h5>
                                <h3 class="mb-0">-</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">VLAN Profiles</h5>
                                <h3 class="mb-0">-</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-sitemap fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available OLTs Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Available OLTs for ONU Configuration
                </h5>
            </div>
            <div class="card-body">
                @if($olts->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="oltTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>OLT Name</th>
                                    <th>Type</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($olts as $olt)
                                <tr>
                                    <td>
                                        <i class="fas fa-server text-primary"></i>
                                        {{ $olt->nama }}
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $olt->tipe }}</span>
                                    </td>
                                    <td>
                                        <code>{{ $olt->ip }}:{{ $olt->port }}</code>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> Available
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('onus.create') }}?olt={{ $olt->id }}" class="btn btn-sm btn-success">
                                                <i class="fas fa-plus"></i> Add ONU
                                            </a>
                                            <button class="btn btn-sm btn-info" onclick="testOltConnection({{ $olt->id }})">
                                                <i class="fas fa-plug"></i> Test
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="showOnuList({{ $olt->id }})">
                                                <i class="fas fa-list"></i> View ONUs
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="debugRealOlt({{ $olt->id }})">
                                                <i class="fas fa-bug"></i> Debug
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>No OLTs Available</strong><br>
                        You need to add OLT devices first before you can configure ONUs. 
                        <a href="{{ route('olts.create') }}" class="alert-link">Add OLT here</a>.
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="d-grid">
                            <a href="{{ route('onus.create') }}" class="btn btn-outline-success btn-lg">
                                <i class="fas fa-plus fa-2x"></i><br>
                                <span class="mt-2">Add New ONU</span>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid">
                            <a href="{{ route('olts.index') }}" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-server fa-2x"></i><br>
                                <span class="mt-2">Manage OLTs</span>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid">
                            <button class="btn btn-outline-info btn-lg" onclick="refreshStatus()">
                                <i class="fas fa-sync-alt fa-2x"></i><br>
                                <span class="mt-2">Refresh Status</span>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-grid">
                            <button class="btn btn-outline-warning btn-lg" onclick="testFilteredOnus()">
                                <i class="fas fa-filter fa-2x"></i><br>
                                <span class="mt-2">Test Filter</span>
                            </button>
                        </div>
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
    // Initialize DataTable
    $('#oltTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});

// Test OLT connection
function testOltConnection(oltId) {
    showLoading('Testing OLT connection...');
    
    $.ajax({
        url: '/olts/' + oltId + '/test',
        method: 'GET',
        success: function(response) {
            hideLoading();
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Connection Successful!',
                    html: response.message,
                    timer: 3000
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Failed',
                    html: response.message
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to test connection'
            });
        }
    });
}

// Show ONU list for selected OLT with card/port filter
function showOnuList(oltId) {
    // Show filter modal first
    showOnuFilterModal(oltId);
}

// Show card/port filter modal
function showOnuFilterModal(oltId) {
    const filterModalHtml = `
        <div class="row">
            <div class="col-md-12 mb-3">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Choose Filter Method:</strong><br>
                    Filter by Card/Port for browsing specific interface, or search by Serial Number for finding specific ONU.
                </div>
            </div>
        </div>
        
        <!-- Filter by Card/Port -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-sitemap"></i> Filter by Card/Port</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="filterCard" class="form-label">Card:</label>
                            <select class="form-select" id="filterCard">
                                <option value="">Select Card</option>
                                ${generateCardOptions()}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="filterPort" class="form-label">Port:</label>
                            <select class="form-select" id="filterPort">
                                <option value="">Select Port</option>
                                ${generatePortOptions()}
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <button class="btn btn-primary" onclick="loadFilteredOnus(${oltId})">
                        <i class="fas fa-search"></i> Load ONUs by Card/Port
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Filter by Serial Number -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-barcode"></i> Search by Serial Number</h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="filterSerialNumber" class="form-label">Serial Number:</label>
                    <input type="text" class="form-control" id="filterSerialNumber" placeholder="e.g., ZTEGC76F6812" maxlength="20">
                    <small class="form-text text-muted">Enter the complete serial number</small>
                </div>
                <div class="mt-2">
                    <button class="btn btn-success" onclick="searchOnuBySn(${oltId})">
                        <i class="fas fa-search"></i> Search by Serial Number
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Load All Option -->
        <div class="card">
            <div class="card-body text-center">
                <button class="btn btn-outline-secondary" onclick="loadAllOnus(${oltId})">
                    <i class="fas fa-list"></i> Load All ONUs (Slow)
                </button>
            </div>
        </div>
    `;
    
    Swal.fire({
        title: 'Filter ONU List',
        html: filterModalHtml,
        width: '700px',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            container: 'onu-filter-modal'
        }
    });
}

// Generate card options (typically 1-16)
function generateCardOptions() {
    let options = '';
    for (let i = 1; i <= 16; i++) {
        options += `<option value="${i}">Card ${i}</option>`;
    }
    return options;
}

// Generate port options (typically 1-16)
function generatePortOptions() {
    let options = '';
    for (let i = 1; i <= 16; i++) {
        options += `<option value="${i}">Port ${i}</option>`;
    }
    return options;
}

// Load ONUs with card/port filter
function loadFilteredOnus(oltId) {
    const card = document.getElementById('filterCard').value;
    const port = document.getElementById('filterPort').value;
    
    if (!card || !port) {
        Swal.fire({
            icon: 'warning',
            title: 'Filter Required',
            text: 'Please select both Card and Port to filter ONUs.'
        });
        return;
    }
    
    Swal.close(); // Close filter modal
    showLoading(`Loading ONUs for Card ${card}, Port ${port}...`);
    
    $.ajax({
        url: '/onus/get-configured-filtered',
        method: 'POST',
        data: {
            olt_id: oltId,
            card: card,
            port: port,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                displayFilteredOnuList(response.onus, oltId, card, port);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Load ONUs',
                    text: response.message
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load ONU list'
            });
        }
    });
}

// Load all ONUs (original slow method)
function loadAllOnus(oltId) {
    Swal.close(); // Close filter modal
    showLoading('Loading all configured ONUs... This may take a while...');
    
    $.ajax({
        url: '/onus/get-configured',
        method: 'POST',
        data: {
            olt_id: oltId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                displayOnuList(response.onus, oltId);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Load ONUs',
                    text: response.message
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load ONU list'
            });
        }
    });
}

// Load ONUs by serial number
function searchOnuBySn(oltId) {
    const serialNumber = document.getElementById('filterSerialNumber').value.trim();
    
    if (!serialNumber) {
        Swal.fire({
            icon: 'warning',
            title: 'Serial Number Required',
            text: 'Please enter a serial number to search.'
        });
        return;
    }
    
    // Validate serial number format (basic check)
    if (serialNumber.length < 8) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Serial Number',
            text: 'Serial number seems too short. Please check and try again.'
        });
        return;
    }
    
    Swal.close(); // Close filter modal
    showLoading(`Searching for ONU with SN: ${serialNumber}...`);
    
    $.ajax({
        url: '/onus/get-onu-by-sn',
        method: 'POST',
        data: {
            olt_id: oltId,
            serial_number: serialNumber,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                displayOnuSearchResult(response.onus, oltId, serialNumber);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'ONU Not Found',
                    text: response.message
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to search for ONU'
            });
        }
    });
}

// Display filtered ONU list in modal
function displayFilteredOnuList(onus, oltId, card, port) {
    let tableHtml = '';
    
    if (onus.length === 0) {
        tableHtml = `<div class="alert alert-info">No configured ONUs found for Card ${card}, Port ${port}.</div>`;
    } else {
        tableHtml = `
            <div class="alert alert-success">
                <i class="fas fa-info-circle"></i>
                Found ${onus.length} ONU(s) on Card ${card}, Port ${port}
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Interface</th>
                            <th>ONU ID</th>
                            <th>Type</th>
                            <th>Serial Number</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        onus.forEach(function(onu) {
            tableHtml += `
                <tr>
                    <td><code>${onu.interface}</code></td>
                    <td><span class="badge bg-primary">${onu.onu_id}</span></td>
                    <td><span class="badge bg-secondary">${onu.type}</span></td>
                    <td><code>${onu.serial_number}</code></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteOnu(${oltId}, ${onu.card}, ${onu.port}, ${onu.onu_id}, '${onu.serial_number}')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tableHtml += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    // Add filter again button
    tableHtml += `
        <div class="mt-3 text-center">
            <button class="btn btn-outline-primary" onclick="showOnuFilterModal(${oltId})">
                <i class="fas fa-filter"></i> Filter Again
            </button>
            <button class="btn btn-outline-secondary" onclick="loadAllOnus(${oltId})">
                <i class="fas fa-list"></i> Load All ONUs
            </button>
        </div>
    `;
    
    Swal.fire({
        title: `ONUs on Card ${card}, Port ${port}`,
        html: tableHtml,
        width: '90%',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            container: 'onu-list-modal'
        }
    });
}

// Display ONU list in modal (updated with filter option)
function displayOnuList(onus, oltId) {
    let tableHtml = '';
    
    if (onus.length === 0) {
        tableHtml = '<div class="alert alert-info">No configured ONUs found for this OLT.</div>';
    } else {
        tableHtml = `
            <div class="alert alert-success">
                <i class="fas fa-info-circle"></i>
                Found ${onus.length} configured ONU(s) across all cards and ports
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Interface</th>
                            <th>ONU ID</th>
                            <th>Type</th>
                            <th>Serial Number</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        onus.forEach(function(onu) {
            tableHtml += `
                <tr>
                    <td><code>${onu.interface}</code></td>
                    <td><span class="badge bg-primary">${onu.onu_id}</span></td>
                    <td><span class="badge bg-secondary">${onu.type}</span></td>
                    <td><code>${onu.serial_number}</code></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteOnu(${oltId}, ${onu.card}, ${onu.port}, ${onu.onu_id}, '${onu.serial_number}')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tableHtml += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    // Add filter option
    tableHtml += `
        <div class="mt-3 text-center">
            <button class="btn btn-outline-primary" onclick="showOnuFilterModal(${oltId})">
                <i class="fas fa-filter"></i> Filter by Card/Port
            </button>
        </div>
    `;
    
    Swal.fire({
        title: 'All Configured ONUs',
        html: tableHtml,
        width: '90%',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            container: 'onu-list-modal'
        }
    });
}

// Display ONU search result by Serial Number
function displayOnuSearchResult(onus, oltId, serialNumber) {
    let tableHtml = '';
    
    if (onus.length === 0) {
        tableHtml = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>ONU Not Found</strong><br>
                No ONU found with Serial Number: <code>${serialNumber}</code>
            </div>
        `;
    } else {
        tableHtml = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Found ONU with Serial Number: <code>${serialNumber}</code>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Interface</th>
                            <th>ONU ID</th>
                            <th>Type</th>
                            <th>Serial Number</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        onus.forEach(function(onu) {
            tableHtml += `
                <tr class="table-success">
                    <td><code>${onu.interface}</code></td>
                    <td><span class="badge bg-primary">${onu.onu_id}</span></td>
                    <td><span class="badge bg-secondary">${onu.type}</span></td>
                    <td><code class="text-success">${onu.serial_number}</code></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteOnu(${oltId}, ${onu.card}, ${onu.port}, ${onu.onu_id}, '${onu.serial_number}')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>
            `;
        });
        
        tableHtml += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    // Add search again button
    tableHtml += `
        <div class="mt-3 text-center">
            <button class="btn btn-outline-primary" onclick="showOnuFilterModal(${oltId})">
                <i class="fas fa-search"></i> Search Again
            </button>
            <button class="btn btn-outline-secondary" onclick="loadAllOnus(${oltId})">
                <i class="fas fa-list"></i> Load All ONUs
            </button>
        </div>
    `;
    
    Swal.fire({
        title: `Search Result: ${serialNumber}`,
        html: tableHtml,
        width: '90%',
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
            container: 'onu-list-modal'
        }
    });
}

// Confirm delete ONU
function confirmDeleteOnu(oltId, card, port, onuId, serialNumber) {
    Swal.fire({
        title: 'Confirm Delete ONU',
        html: `
            <div class="text-left">
                <p><strong>Are you sure you want to delete this ONU?</strong></p>
                <ul class="list-unstyled">
                    <li><strong>Interface:</strong> gpon-olt_1/${card}/${port}</li>
                    <li><strong>ONU ID:</strong> ${onuId}</li>
                    <li><strong>Serial Number:</strong> ${serialNumber}</li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This action cannot be undone and will remove the ONU configuration from the OLT.
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash"></i> Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            deleteOnu(oltId, card, port, onuId);
        }
    });
}

// Delete ONU
function deleteOnu(oltId, card, port, onuId) {
    showLoading('Deleting ONU configuration...');
    
    $.ajax({
        url: '/onus/delete-onu',
        method: 'POST',
        data: {
            olt_id: oltId,
            card: card,
            port: port,
            onu_id: onuId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            hideLoading();
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'ONU Deleted!',
                    text: response.message,
                    timer: 3000
                }).then(() => {
                    // Refresh the ONU list
                    showOnuList(oltId);
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Delete Failed',
                    text: response.message
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to delete ONU configuration'
            });
        }
    });
}

// Refresh status
function refreshStatus() {
    showLoading('Refreshing status...');
    setTimeout(function() {
        hideLoading();
        location.reload();
    }, 2000);
}

// Test configured ONUs with sample data
function testConfiguredOnus() {
    showLoading('Testing configured ONU list...');
    
    $.ajax({
        url: '/onus/test-configured-sample',
        method: 'GET',
        success: function(response) {
            hideLoading();
            if (response.success) {
                displayOnuList(response.onus, 'test');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Test Failed',
                    text: response.message
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to run test'
            });
        }
    });
}

// Test filtered ONUs with sample data
function testFilteredOnus() {
    showLoading('Testing filtered ONU list...');
    
    $.ajax({
        url: '/onus/test-filtered-sample?card=2&port=3',
        method: 'GET',
        success: function(response) {
            hideLoading();
            if (response.success) {
                displayFilteredOnuList(response.onus, 'test', response.filter.card, response.filter.port);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Test Failed',
                    text: response.message
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to run test'
            });
        }
    });
}

// Debug real OLT connection
function debugRealOlt(oltId) {
    showLoading('Debugging real OLT connection...');
    
    $.ajax({
        url: '/onus/debug-real-olt',
        method: 'POST',
        data: {
            olt_id: oltId,
            card: 2,
            port: 3,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            hideLoading();
            
            let debugHtml = `
                <div class="text-left">
                    <h6>Debug Results for OLT: ${response.olt_info ? response.olt_info.name : 'Unknown'}</h6>
                    <p><strong>Command sent:</strong> <code>${response.command_sent || 'N/A'}</code></p>
                    <p><strong>Found ONUs:</strong> ${response.found_onus_count || 0}</p>
                    
                    <div class="mt-3">
                        <h6>Raw Response:</h6>
                        <textarea class="form-control" rows="10" readonly>${response.raw_response || 'No response'}</textarea>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Parsed ONUs:</h6>
                        <pre>${JSON.stringify(response.parsed_onus || [], null, 2)}</pre>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Debug Info:</h6>
                        <ul>
                            <li>Response length: ${response.response_length || 0} characters</li>
                            <li>Contains "Building configuration": ${response.debug_info?.contains_building_config ? 'Yes' : 'No'}</li>
                            <li>Contains "interface gpon-olt": ${response.debug_info?.contains_interface ? 'Yes' : 'No'}</li>
                            <li>Contains "onu ": ${response.debug_info?.contains_onu ? 'Yes' : 'No'}</li>
                        </ul>
                    </div>
                </div>
            `;
            
            Swal.fire({
                title: 'Debug Results',
                html: debugHtml,
                width: '90%',
                showConfirmButton: true,
                showCloseButton: true,
                customClass: {
                    container: 'debug-modal'
                }
            });
        },
        error: function() {
            hideLoading();
            Swal.fire({
                icon: 'error',
                title: 'Debug Failed',
                text: 'Failed to debug OLT connection'
            });
        }
    });
}

// Helper functions
function showLoading(text) {
    $('#loadingText').text(text);
    $('#loadingModal').modal('show');
}

function hideLoading() {
    $('#loadingModal').modal('hide');
}
</script>
@endpush

@push('styles')
<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.btn-lg {
    padding: 1rem;
    border-radius: 0.5rem;
}

.btn-lg i {
    display: block;
    margin-bottom: 0.5rem;
}

.table th {
    border-top: none;
}

.badge {
    font-size: 0.8em;
}

code {
    color: #e83e8c;
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
}

.alert {
    border: none;
    border-radius: 0.5rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

/* Custom styles for ONU list modal - Full Screen */
.onu-list-modal .swal2-popup {
    max-width: 100vw !important;
    width: 100vw !important;
    height: 100vh !important;
    max-height: 100vh !important;
    margin: 0 !important;
    border-radius: 0 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
}

.onu-list-modal .swal2-html-container {
    max-height: calc(100vh - 120px) !important;
    height: calc(100vh - 120px) !important;
    overflow-y: auto;
    padding: 20px;
}

.onu-list-modal .swal2-header {
    padding: 20px 20px 0 20px;
}

.onu-list-modal .swal2-title {
    font-size: 1.5rem;
    margin-bottom: 0;
}

.onu-list-modal .table {
    margin-bottom: 0;
}

.onu-list-modal .table td {
    vertical-align: middle;
}

.onu-list-modal .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Custom styles for ONU filter modal - Full Screen */
.onu-filter-modal .swal2-popup {
    max-width: 100vw !important;
    width: 100vw !important;
    height: 100vh !important;
    max-height: 100vh !important;
    margin: 0 !important;
    border-radius: 0 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
}

.onu-filter-modal .swal2-html-container {
    max-height: calc(100vh - 120px) !important;
    height: calc(100vh - 120px) !important;
    overflow-y: auto;
    padding: 20px;
}

.onu-filter-modal .swal2-header {
    padding: 20px 20px 0 20px;
}

.onu-filter-modal .swal2-title {
    font-size: 1.5rem;
    margin-bottom: 0;
}

.onu-filter-modal .form-group {
    margin-bottom: 1rem;
}

.onu-filter-modal .form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.onu-filter-modal .form-select {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 0.375rem;
    background-color: #fff;
}

.onu-filter-modal .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.onu-filter-modal .btn {
    margin-right: 0.5rem;
}

/* Custom styles for debug modal - Full Screen */
.debug-modal .swal2-popup {
    max-width: 100vw !important;
    width: 100vw !important;
    height: 100vh !important;
    max-height: 100vh !important;
    margin: 0 !important;
    border-radius: 0 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
}

.debug-modal .swal2-html-container {
    max-height: calc(100vh - 120px) !important;
    height: calc(100vh - 120px) !important;
    overflow-y: auto;
    text-align: left;
    padding: 20px;
}

.debug-modal .swal2-header {
    padding: 20px 20px 0 20px;
}

.debug-modal textarea {
    font-family: 'Courier New', monospace;
    font-size: 0.8rem;
}

.debug-modal pre {
    background-color: #f8f9fa;
    padding: 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    max-height: 200px;
    overflow-y: auto;
}

/* Additional styles for full screen modals */
.swal2-container.onu-list-modal,
.swal2-container.onu-filter-modal,
.swal2-container.debug-modal {
    padding: 0 !important;
}

.onu-list-modal .swal2-actions,
.onu-filter-modal .swal2-actions,
.debug-modal .swal2-actions {
    padding: 0 20px 20px 20px;
    margin: 0;
}

/* Close button styling for full screen */
.onu-list-modal .swal2-close,
.onu-filter-modal .swal2-close,
.debug-modal .swal2-close {
    position: fixed !important;
    top: 15px !important;
    right: 15px !important;
    width: 40px !important;
    height: 40px !important;
    font-size: 24px !important;
    background: rgba(255, 255, 255, 0.9) !important;
    border-radius: 50% !important;
    border: 2px solid #ddd !important;
    z-index: 10000 !important;
}

.onu-list-modal .swal2-close:hover,
.onu-filter-modal .swal2-close:hover,
.debug-modal .swal2-close:hover {
    background: rgba(255, 255, 255, 1) !important;
    border-color: #999 !important;
}
</style>
@endpush
