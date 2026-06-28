@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="mb-4">Teacher Login</h2>
        <form action="{{ route('login.send') }}" method="POST"
              onsubmit="const b = this.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Sending…';">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="btn btn-primary">Send Login Link</button>
            <p class="text-muted small mt-2 mb-0">The link can take a moment to arrive. If you request it more than once, just open the latest email — every link works.</p>
        </form>
    </div>
</div>
@endsection
