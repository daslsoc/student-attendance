@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-1">
    <h1 class="h3 mb-1">Roles &amp; permissions</h1>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">Add role</a>
</div>
<p class="text-body-secondary">A role is a set of permissions. Change a role and every teacher in it changes with it.</p>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
            <tr><th>Role</th><th>Description</th><th>Permissions</th><th>Teachers</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
            @foreach($roles as $role)
                <tr>
                    <td>{{ $role->name }}</td>
                    <td class="small text-body-secondary">{{ $role->description }}</td>
                    <td>{{ count($role->atoms()) }}</td>
                    <td>
                        @if($role->users_count > 0)
                            <a href="{{ route('admin.users.index', ['role' => $role->id]) }}">{{ $role->users_count }}</a>
                        @else
                            0
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        @if($role->users_count === 0)
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline"
                                  onsubmit="return confirm('Delete the {{ $role->name }} role?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
