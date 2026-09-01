@extends('layouts.app')

@section('content')
<h1 class="h3 mb-1">Add role</h1>
<p class="text-body-secondary">Tick everything this role should be able to do. You can change it later.</p>

<form method="POST" action="{{ route('admin.roles.store') }}">
    @csrf

    <div class="row g-3 mb-4" style="max-width: 40rem;">
        <div class="col-12">
            <label for="name" class="form-label">Role name</label>
            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required autofocus>
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description (optional)</label>
            <input type="text" name="description" id="description" class="form-control"
                   value="{{ old('description') }}" maxlength="255">
        </div>
    </div>

    <h2 class="h5 mb-3">Permissions</h2>
    @include('admin.roles.partials.permission_grid')

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Create role</button>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-link">Cancel</a>
    </div>
</form>
@endsection
