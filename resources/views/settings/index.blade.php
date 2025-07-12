@extends('layouts.main')
@section('title', 'Pengaturan Sistem')
@section('main-content')
<div class="container mt-4">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Pengaturan Sistem</div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Sistem</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ $setting->name ?? '' }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="logo" class="form-label">Logo</label><br>
                            @if($setting && $setting->logo)
                                <img src="{{ asset('storage/' . $setting->logo) }}" alt="Logo" height="50" class="mb-2">
                            @endif
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Log Aktivitas</div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Aktivitas</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                            <tr>
                                <td>{{ $log->user->name ?? '-' }}</td>
                                <td>{{ $log->activity }}</td>
                                <td>{{ $log->created_at }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
