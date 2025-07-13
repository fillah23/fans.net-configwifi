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
                    <div class="col-md-4">
                        <div class="d-grid">
                            <a href="{{ route('onus.create') }}" class="btn btn-outline-success btn-lg">
                                <i class="fas fa-plus fa-2x"></i><br>
                                <span class="mt-2">Add New ONU</span>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-grid">
                            <a href="{{ route('olts.index') }}" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-server fa-2x"></i><br>
                                <span class="mt-2">Manage OLTs</span>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-grid">
                            <button class="btn btn-outline-info btn-lg" onclick="refreshStatus()">
                                <i class="fas fa-sync-alt fa-2x"></i><br>
                                <span class="mt-2">Refresh Status</span>
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

// Show ONU list (placeholder)
function showOnuList(oltId) {
    Swal.fire({
        icon: 'info',
        title: 'Feature Coming Soon',
        text: 'ONU listing feature will be available in the next update.',
        footer: '<a href="/onus/create?olt=' + oltId + '">Add ONU for this OLT</a>'
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
</style>
@endpush
