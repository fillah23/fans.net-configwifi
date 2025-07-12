@extends('layouts.main')
@section('title', 'Manajemen User')
@section('main-content')
<div class="container mt-4">
    <div class="card">
        <div class="card-header">Manajemen User</div>
        <div class="card-body">
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#userModal">Tambah User</button>
            <table id="userTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <button class="btn btn-sm btn-warning editUser" data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}">Edit</button>
                            <button class="btn btn-sm btn-danger deleteUser" data-id="{{ $user->id }}">Hapus</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Modal User -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Tambah/Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" id="userId" name="userId">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
$(document).ready(function() {
    $('#userTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print', 'colvis'
        ]
    });
    // Tambah/Edit User
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#userId').val();
        var url = id ? '/users/' + id : '/users';
        var formData = $(this).serializeArray();
        formData.push({name: '_token', value: '{{ csrf_token() }}'});
        if (id) {
            formData.push({name: '_method', value: 'PUT'});
        }
        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            success: function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'User berhasil disimpan!',
                    timer: 1500,
                    showConfirmButton: false
                });
                setTimeout(function(){ location.reload(); }, 1600);
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal menyimpan user: ' + xhr.responseText
                });
            }
        });
    });
    // Edit button
    $('.editUser').on('click', function() {
        $('#userId').val($(this).data('id'));
        $('#name').val($(this).data('name'));
        $('#email').val($(this).data('email'));
        $('#password').val('');
        $('#userModal').modal('show');
    });
    // Delete button
    $('.deleteUser').on('click', function() {
        if(confirm('Yakin ingin menghapus user ini?')) {
            $.ajax({
                url: '/users/' + $(this).data('id'),
                method: 'POST',
                data: {'_token': '{{ csrf_token() }}', '_method': 'DELETE'},
                success: function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'User berhasil dihapus!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(function(){ location.reload(); }, 1600);
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Gagal menghapus user: ' + xhr.responseText
                    });
                }
            });
        }
    });
    // Reset modal
    $('#userModal').on('hidden.bs.modal', function () {
        $('#userId').val('');
        $('#userForm')[0].reset();
    });
});
</script>
@endpush
