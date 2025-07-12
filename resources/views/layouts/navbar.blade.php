@php
    $setting = \App\Models\Setting::first();
    $logo = $setting && $setting->logo ? asset('storage/' . $setting->logo) : asset('assets/images/logo-dark.png');
    $name = $setting && $setting->name ? $setting->name : 'Fans.net';
@endphp
<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <div class="navbar-brand-box d-flex align-items-center">
                <a href="/dashboard" class="logo logo-light d-flex align-items-center">
                    <span class="logo-sm me-2">
                        <img src="{{ $logo }}" alt="logo-light" height="40" style="margin-left:0;">
                    </span>
                    <span class="logo-lg fw-bold h4 mb-0" style="color:#ffffff; letter-spacing:1px;">
                        {{ $name }}
                    </span>
                </a>
            </div>
            <button type="button" class="btn btn-sm px-3 font-size-24 header-item waves-effect" id="vertical-menu-btn">
                <i class="ri-menu-2-line align-middle"></i>
            </button>
        </div>
        <div class="d-flex">
            <div class="dropdown d-inline-block user-dropdown">
                <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    {{-- <img class="rounded-circle header-profile-user" src="{{ asset('assets/images/users/avatar-1.jpg') }}"
                        alt="Header Avatar"> --}}
                    <span class="d-none d-xl-inline-block ms-1">
                        {{ Auth::user() ? Auth::user()->name : 'User' }}
                    </span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="ri-shut-down-line align-middle me-1 text-danger"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
