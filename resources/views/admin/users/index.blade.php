@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-1">
    <h1 class="h3 mb-1">Teachers</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add teacher</a>
</div>
<p class="text-body-secondary">
    Every teacher holds one role, and that role decides what they can see and do.
    There are no passwords here — people sign in with a link emailed to the address on their account.
    Removing someone <strong>deactivates</strong> them rather than deleting the row:
    no further login links, existing links stop working, and the audit log still shows what they did.
</p>

<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label for="role" class="form-label">Filter by role</label>
        <select name="role" id="role" class="form-select" onchange="this.form.submit()">
            <option value="">All roles</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $roleFilter === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </div>
    @if($roleFilter)
        <div class="col-auto">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Clear</a>
        </div>
    @endif
</form>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr class="{{ $user->isActive() ? '' : 'table-secondary' }}">
                    <td>{{ $user->name }}</td>
                    <td class="small">{{ $user->email }}</td>
                    <td>
                        @if($user->role)
                            <span class="badge text-bg-light">{{ $user->role->name }}</span>
                        @else
                            <span class="badge text-bg-warning">No role</span>
                        @endif
                    </td>
                    <td>
                        @if($user->isActive())
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Deactivated {{ $user->deactivated_at->format('j M Y') }}</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        @if($user->isActive())
                            @if($user->id !== $currentTeacherId)
                                <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" class="d-inline"
                                      onsubmit="return confirm('Deactivate {{ $user->name }}? They will be signed out and unable to sign in.')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                </form>
                            @else
                                <span class="text-body-secondary small ms-2">That's you</span>
                            @endif
                        @else
                            <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-body-secondary">No teachers yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
