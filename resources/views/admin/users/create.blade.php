@extends('layouts.app')

@section('content')
<h1 class="h3 mb-1">Add teacher</h1>
<p class="text-body-secondary">
    No password needed — once the account exists, they sign in from the login page by entering
    this email address and clicking the link that arrives.
</p>

<form method="POST" action="{{ route('admin.users.store') }}" class="row g-3" style="max-width: 40rem;">
    @csrf

    <div class="col-12">
        <label for="name" class="form-label">Name</label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name') }}" required autofocus>
    </div>

    <div class="col-12">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email') }}" required>
        <div class="form-text">Login links are sent here, so it has to be an address they read.</div>
    </div>

    <div class="col-12">
        <label for="role_id" class="form-label">Role</label>
        <select name="role_id" id="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
            <option value="" disabled {{ old('role_id') ? '' : 'selected' }}>Choose a role…</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>
                    {{ $role->name }}@if($role->description) — {{ $role->description }}@endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Add teacher</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
    </div>
</form>
@endsection
