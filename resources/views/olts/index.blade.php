@extends('layouts.main')
@section('title', 'Daftar OLT')
@section('main-content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Daftar OLT</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('onus.create') }}" class="btn btn-success">
                <i class="fas fa-plus"></i> Add ONU
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalOlt">
                <i class="fas fa-server"></i> Tambah OLT
            </button>
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <table class="table table-bordered table-striped" id="oltTable">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Tipe</th>
                    <th>IP</th>               
                    <th>Uptime</th>
                    <th>Suhu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($olts as $olt)
                <tr data-olt-id="{{ $olt->id }}">
                    <td>{{ $olt->nama }}</td>
                    <td>{{ $olt->tipe }}</td>
                    <td>{{ $olt->ip }}</td>
                    <td class="uptime-cell">
                        <span class="loading-indicator">
                            <i class="fa fa-spinner fa-spin"></i> Loading...
                        </span>
                    </td>
                    <td class="temperature-cell">
                        <span class="loading-indicator">
                            <i class="fa fa-spinner fa-spin"></i> Loading...
                        </span>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Aksi
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <button class="dropdown-item editOltBtn" data-id="{{ $olt->id }}" data-bs-toggle="modal" data-bs-target="#modalOltEdit">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item testOltBtn" data-id="{{ $olt->id }}">
                                        <i class="fas fa-network-wired"></i> Tes Koneksi
                                    </button>
                                </li>
                               
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item syncVlanBtn" data-id="{{ $olt->id }}">
                                        <i class="fas fa-sync-alt"></i> Sync VLAN Profiles
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item viewVlanBtn" data-id="{{ $olt->id }}" data-bs-toggle="modal" data-bs-target="#modalVlanProfiles">
                                        <i class="fas fa-list"></i> Lihat VLAN Profiles
                                    </button>
                                </li>
                                {{-- <li>
                                    <button class="dropdown-item debugVlanBtn" data-id="{{ $olt->id }}">
                                        <i class="fas fa-code"></i> Debug VLAN Data
                                    </button>
                                </li> --}}
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('olts.destroy', $olt) }}" method="POST" class="deleteForm">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger deleteOltBtn">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<!-- Modal Tambah OLT -->
<div class="modal fade" id="modalOlt" tabindex="-1" aria-labelledby="modalOltLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="formTambahOlt">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="modalOltLabel">Tambah OLT</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
        <label class="fw-bold">TELNET</label>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Nama</label>
              <input type="text" name="nama" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Tipe</label>
              <select name="tipe" class="form-control" id="tipeOlt" required>
                <option value="">Pilih Tipe</option>
                <option value="ZTE C300">ZTE C300</option>
                <option value="ZTE C320">ZTE C320</option>
                <option value="HUAWEI MA5630T">HUAWEI MA5630T</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label>IP</label>
              <input type="text" name="ip" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Port</label>
              <input type="number" name="port" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Card</label>
              <div id="cardCheckboxes">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="0" id="card0">
                  <label class="form-check-label" for="card0">0</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="1" id="card1">
                  <label class="form-check-label" for="card1">1</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="2" id="card2">
                  <label class="form-check-label" for="card2">2</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="3" id="card3">
                  <label class="form-check-label" for="card3">3</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="4" id="card4">
                  <label class="form-check-label" for="card4">4</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="5" id="card5">
                  <label class="form-check-label" for="card5">5</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="6" id="card6">
                  <label class="form-check-label" for="card6">6</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="7" id="card7">
                  <label class="form-check-label" for="card7">7</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="8" id="card8">
                  <label class="form-check-label" for="card8">8</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="9" id="card9">
                  <label class="form-check-label" for="card9">9</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="10" id="card10">
                  <label class="form-check-label" for="card10">10</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="11" id="card11">
                  <label class="form-check-label" for="card11">11</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="12" id="card12">
                  <label class="form-check-label" for="card12">12</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="13" id="card13">
                  <label class="form-check-label" for="card13">13</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="14" id="card14">
                  <label class="form-check-label" for="card14">14</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="15" id="card15">
                  <label class="form-check-label" for="card15">15</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="16" id="card16">
                  <label class="form-check-label" for="card16">16</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="17" id="card17">
                  <label class="form-check-label" for="card17">17</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="card[]" value="18" id="card18">
                  <label class="form-check-label" for="card18">18</label>
                </div>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <label>User</label>
              <input type="text" name="user" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label>Password</label>
              <input type="password" name="pass" class="form-control" required>
            </div>
            <div class="col-12 mb-3">
              <label class="fw-bold">SNMP</label>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label>Community Read</label>
                  <input type="text" name="community_read" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                  <label>Community Write</label>
                  <input type="text" name="community_write" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                  <label>Port SNMP</label>
                  <input type="text" name="port_snmp" class="form-control" required>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Simpan</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Modal Edit OLT (dummy, implementasi AJAX/JS diperlukan untuk isi data) -->
<div class="modal fade" id="modalOltEdit" tabindex="-1" aria-labelledby="modalOltEditLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="formEditOlt">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title" id="modalOltEditLabel">Edit OLT</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="editOltBody">
          <!-- Isi form edit akan diisi via JS -->
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Update</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Modal View VLAN Profiles -->
<div class="modal fade" id="modalVlanProfiles" tabindex="-1" aria-labelledby="modalVlanProfilesLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalVlanProfilesLabel">VLAN Profiles</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="vlanProfilesBody">
        <div class="text-center">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Memuat VLAN profiles...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script>
$(function() {
    // DataTables
    $('#oltTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print', 'colvis'
        ]
    });

    // Real-time OLT monitoring
    function loadOltInfo() {
        $('tr[data-olt-id]').each(function() {
            var row = $(this);
            var oltId = row.data('olt-id');
            var uptimeCell = row.find('.uptime-cell');
            var temperatureCell = row.find('.temperature-cell');
            var portsCell = row.find('.ports-cell');
            
            $.get('/olts/' + oltId + '/info', function(res) {
                if (res.success) {
                    uptimeCell.html('<span class="text-success">' + res.data.uptime + '</span>');
                    temperatureCell.html('<span class="text-info">' + res.data.temperature + '°C</span>');
                    portsCell.html('<span class="text-primary">' + res.data.active_ports + '</span>');
                } else {
                    uptimeCell.html('<span class="text-danger">Error</span>');
                    temperatureCell.html('<span class="text-danger">Error</span>');
                    portsCell.html('<span class="text-danger">Error</span>');
                }
            }).fail(function() {
                uptimeCell.html('<span class="text-muted">N/A</span>');
                temperatureCell.html('<span class="text-muted">N/A</span>');
                portsCell.html('<span class="text-muted">N/A</span>');
            });
        });
    }

    // Load data immediately when page loads
    loadOltInfo();

    // Refresh data every 30 seconds
    setInterval(loadOltInfo, 30000);

    // Test VLAN Command
    $(document).on('click', '.testVlanBtn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Test VLAN Command',
            text: 'Sedang test koneksi dan command...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                $.get('/olts/' + id + '/test-vlan', function(res) {
                    Swal.close();
                    Swal.fire({
                        icon: res.success ? 'success' : 'error',
                        title: res.success ? 'Test Berhasil' : 'Test Gagal',
                        html: res.message,
                        width: 600
                    });
                }).fail(function(xhr) {
                    Swal.close();
                    Swal.fire('Gagal', 'Test command gagal dijalankan', 'error');
                });
            }
        });
    });

    // Test VLAN Display Command
    $(document).on('click', '.testVlanDisplayBtn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Test VLAN Display Command',
            text: 'Sedang test display vlan all command...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                $.get('/olts/' + id + '/test-vlan-display', function(res) {
                    Swal.close();
                    var resultHtml = res.message;
                    if (res.success && res.data) {
                        resultHtml += '<br><br><strong>Details:</strong>';
                        resultHtml += '<br>• VLANs Found: ' + (res.data.vlans_found || 0);
                        resultHtml += '<br>• Response Length: ' + (res.data.response_length || 0) + ' chars';
                        resultHtml += '<br>• OLT Type: ' + (res.data.olt_type || 'Unknown');
                        
                        if (res.data.response_preview) {
                            resultHtml += '<br><br><strong>Response Preview:</strong>';
                            resultHtml += '<pre style="text-align: left; max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px;">';
                            resultHtml += res.data.response_preview.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            resultHtml += '</pre>';
                        }
                    }
                    
                    Swal.fire({
                        icon: res.success ? 'success' : 'error',
                        title: res.success ? 'Test VLAN Display Berhasil' : 'Test VLAN Display Gagal',
                        html: resultHtml,
                        width: 800,
                        showCloseButton: true
                    });
                }).fail(function(xhr) {
                    Swal.close();
                    Swal.fire('Gagal', 'Test VLAN display command gagal dijalankan', 'error');
                });
            }
        });
    });

    // Sync VLAN Profiles
    $(document).on('click', '.syncVlanBtn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Sync VLAN Profiles',
            text: 'Sedang mengambil VLAN profiles dari OLT...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
                
                // Set timeout untuk request
                var xhr = $.post('/olts/' + id + '/sync-vlans', {
                    _token: '{{ csrf_token() }}'
                }, function(res) {
                    Swal.close();
                    Swal.fire({
                        icon: res.success ? 'success' : 'error',
                        title: res.success ? 'Berhasil' : 'Gagal',
                        html: res.message,
                        timer: res.success ? 3000 : null
                    });
                }).fail(function(xhr, status, error) {
                    Swal.close();
                    if (status === 'timeout') {
                        Swal.fire('Timeout', 'Request timeout. Coba periksa koneksi ke OLT.', 'warning');
                    } else {
                        Swal.fire('Gagal', 'Sync VLAN profiles gagal: ' + error, 'error');
                    }
                });
                
                // Set timeout 30 detik
                xhr.timeout = 30000;
            }
        });
    });

    // Debug VLAN Data
    $(document).on('click', '.debugVlanBtn', function() {
        var id = $(this).data('id');
        $.get('/olts/' + id + '/vlan-profiles', function(res) {
            Swal.fire({
                title: 'Debug VLAN Data',
                html: '<pre style="text-align: left; font-size: 12px;">' + JSON.stringify(res, null, 2) + '</pre>',
                width: 800,
                showCloseButton: true
            });
        }).fail(function(xhr) {
            Swal.fire('Debug Error', 'Gagal mengambil data debug', 'error');
        });
    });

    // View VLAN Profiles
    $(document).on('click', '.viewVlanBtn', function() {
        var id = $(this).data('id');
        $('#vlanProfilesBody').html(`
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Memuat VLAN profiles...</p>
            </div>
        `);
        
        // Debug: Log the request
        console.log('Loading VLAN profiles for OLT ID:', id);
        
        $.get('/olts/' + id + '/vlan-profiles-view', function(data) {
            console.log('VLAN profiles loaded successfully');
            $('#vlanProfilesBody').html(data);
        }).fail(function(xhr, status, error) {
            console.log('Failed to load VLAN profiles:', status, error);
            $('#vlanProfilesBody').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Gagal memuat VLAN profiles: ` + error + `
                </div>
            `);
        });
    });

    // Tes koneksi OLT
    $(document).on('click', '.testOltBtn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Tes Koneksi OLT',
            text: 'Sedang melakukan tes koneksi...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                $.get('/olts/' + id + '/test', function(res) {
                    Swal.close();
                    Swal.fire({
                        icon: res.success ? 'success' : 'error',
                        title: res.success ? 'Berhasil' : 'Gagal',
                        html: res.message
                    });
                }).fail(function(xhr) {
                    Swal.close();
                    Swal.fire('Gagal', 'Tes koneksi gagal dijalankan', 'error');
                });
            }
        });
    });

    // AJAX submit tambah OLT
    $('#formTambahOlt').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        $.ajax({
            url: "{{ route('olts.store') }}",
            method: "POST",
            data: form.serialize(),
            success: function(res) {
                $('#modalOlt').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'OLT berhasil ditambahkan',
                    showConfirmButton: false,
                    timer: 1200
                });
                setTimeout(function(){ location.reload(); }, 1300);
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan';
                if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Gagal!', msg, 'error');
            }
        });
    });

    // AJAX ambil data OLT dan isi modal edit
    $(document).on('click', '.editOltBtn', function() {
        var id = $(this).data('id');
        $.get('/olts/' + id + '/edit', function(res) {
            $('#formEditOlt').attr('action', '/olts/' + id);
            $('#editOltBody').html(res);
        });
    });

    // AJAX submit edit OLT
    $('#formEditOlt').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var action = form.attr('action');
        $.ajax({
            url: action,
            method: 'POST',
            data: form.serialize(),
            success: function(res) {
                $('#modalOltEdit').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'OLT berhasil diupdate',
                    showConfirmButton: false,
                    timer: 1200
                });
                setTimeout(function(){ location.reload(); }, 1300);
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan';
                if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Gagal!', msg, 'error');
            }
        });
    });

    // AJAX hapus OLT
    $(document).on('click', '.deleteOltBtn', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title: 'Yakin hapus?',
            text: 'Data OLT akan dihapus!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
                    success: function(res) {
                        Swal.fire('Berhasil!', 'OLT berhasil dihapus', 'success').then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        let msg = 'Terjadi kesalahan';
                        if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        Swal.fire('Gagal!', msg, 'error');
                    }
                });
            }
        });
    });

    // Card otomatis sesuai tipe
    $('#tipeOlt').on('change', function() {
        var tipe = $(this).val();
        // Uncheck all first
        $('#cardCheckboxes input[type=checkbox]').prop('checked', false);
        if(tipe === 'ZTE C300') {
            // Card 2-9 dan 12-18
            for(let i=2;i<=9;i++) $('#card'+i).prop('checked', true);
            for(let i=12;i<=18;i++) $('#card'+i).prop('checked', true);
        } else if(tipe === 'ZTE C320') {
            $('#card1').prop('checked', true);
            $('#card2').prop('checked', true);
        }
    });
});
</script>
@endpush
@push('styles')
<style>
.loading-indicator {
    color: #6c757d;
    font-size: 0.875rem;
}

.uptime-cell, .temperature-cell, .ports-cell {
    min-width: 120px;
    text-align: center;
}

.text-success { color: #28a745 !important; }
.text-info { color: #17a2b8 !important; }
.text-primary { color: #007bff !important; }
.text-danger { color: #dc3545 !important; }
.text-muted { color: #6c757d !important; }

/* Animation for smooth updates */
.uptime-cell, .temperature-cell, .ports-cell {
    transition: all 0.3s ease;
}

.fa-spin {
    animation: fa-spin 2s infinite linear;
}

@keyframes fa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Dropdown styling */
.dropdown-item {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.dropdown-item i {
    width: 16px;
    margin-right: 8px;
}

.deleteForm {
    margin: 0;
}

.dropdown-item.text-danger:hover {
    background-color: #dc3545;
    color: white !important;
}

/* VLAN styling */
.vlan-list {
    max-height: 100px;
    overflow-y: auto;
}

.vlan-list .badge {
    font-size: 0.75rem;
}

.modal-xl .table {
    font-size: 0.875rem;
}

.modal-xl .table th {
    background-color: #f8f9fa;
    font-weight: 600;
}
</style>
@endpush
@endsection
