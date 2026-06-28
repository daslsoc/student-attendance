@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-1">Registration Sync</h2>
    <p class="text-muted mb-4">Students and their class allocations are pulled from the registration app. Routine syncing runs on a schedule (cron); use <strong>Sync now</strong> to pull immediately.</p>

    @if (session('warnings') && count(session('warnings')))
        <div class="alert alert-warning">
            <strong>Some allocations need attention:</strong>
            <ul class="mb-0">
                @foreach (session('warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-4" style="max-width: 32rem;">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-5">Last synced</dt>
                <dd class="col-sm-7">
                    {{ $state->last_synced_at ? $state->last_synced_at->format('j M Y, g:ia') : 'never' }}
                </dd>

                <dt class="col-sm-5">Last checked</dt>
                <dd class="col-sm-7">
                    {{ $state->last_checked_at ? $state->last_checked_at->diffForHumans() : 'never' }}
                </dd>

                <dt class="col-sm-5">Paid students (last seen)</dt>
                <dd class="col-sm-7">{{ $state->last_count ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <form action="{{ route('integration.sync') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary">Sync now</button>
    </form>
</div>
@endsection
