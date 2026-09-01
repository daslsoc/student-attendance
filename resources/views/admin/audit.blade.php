@extends('layouts.app')

@section('content')
<h1 class="h3 mb-1">Audit log</h1>
<p class="text-body-secondary">Who changed what, and when. This log is append-only — nothing here can be edited or deleted.</p>

<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label for="action" class="form-label">Filter by action</label>
        <select name="action" id="action" class="form-select" onchange="this.form.submit()">
            <option value="">All actions</option>
            @foreach($actions as $code)
                <option value="{{ $code }}" @selected($action === $code)>{{ $code }}</option>
            @endforeach
        </select>
    </div>
    @if($action)
        <div class="col-auto">
            <a href="{{ route('admin.audit') }}" class="btn btn-outline-secondary">Clear</a>
        </div>
    @endif
</form>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
            <tr><th>When</th><th>Who</th><th>Action</th><th>What happened</th><th>Detail</th></tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td class="small text-nowrap">{{ $entry->created_at->format('j M Y H:i') }}</td>
                    <td class="small">{{ $entry->user_name ?? '—' }}</td>
                    <td class="small"><code>{{ $entry->action }}</code></td>
                    <td>{{ $entry->description }}</td>
                    <td class="small text-body-secondary">
                        @if($entry->properties)
                            <pre class="mb-0 small">{{ json_encode($entry->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-body-secondary">Nothing logged yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $entries->links() }}
@endsection
