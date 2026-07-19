<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ config('app.name', 'Attendance System') }}</title>

  @include('partials.favicons')

  <!-- Bootstrap 5 (CSS + JS) and the app's own student-selector behaviour, all
       bundled by Vite rather than pulled from a CDN. Built with `npm run build`;
       see docs/deployment.md — public/build is gitignored and must be uploaded. -->
  @vite(['resources/scss/app.scss', 'resources/js/app.js'])

  @stack('styles')
</head>

<body>

  <nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
      <a class="navbar-brand" href="/">
        <img src="/images/logo.png" alt="logo" width="27" height="30" class="d-inline-block align-text-top">
        {{ config('app.name', 'Attendance System') }}
      </a>
      @if(session('teacher_logged_in'))
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      @endif
      @if(session('teacher_logged_in'))
      <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav">
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="attendanceDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Attendance</a>
            <ul class="dropdown-menu" aria-labelledby="attendanceDropdown">
              <li><a class="dropdown-item" href="{{route('attendance.selection')}}">New Attendance</a></li>
              <li><a class="dropdown-item" href="{{route('attendance.edit')}}">Edit Attendance</a></li>
            </ul>
          </div>
          <a class="nav-link" aria-current="page" href="{{route('book_distribution.selection')}}">Book Distribution</a>
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="reportDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Report</a>
            <ul class="dropdown-menu" aria-labelledby="reportDropdown">
              <li><a class="dropdown-item" href="{{route('attendance.summary')}}">Today's Report</a></li>
              <li><a class="dropdown-item" href="{{route('attendance.report')}}">Full Year Report</a></li>
            </ul>
          </div>
          <a class="nav-link" aria-current="page" href="{{route('integration.status')}}">Registration Sync</a>
          <a class="nav-link" aria-current="page" href="{{route('help')}}">Help</a>
        </div>
      </div>
      @endif
    </div>
  </nav>

  <div class="container mt-5">
    @if(session('message'))
    <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
    @endif

    @yield('content')
  </div>
  {{-- Bootstrap's own JS now ships in the Vite bundle (see resources/js/app.js).
       Full jQuery (not slim) stays on a CDN: DataTables on the Edit Attendance /
       Full Year Report pages needs it. --}}
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  @yield('scripts')

  <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 my-4 border-top">
    <div class="col-md-4 d-flex align-items-center">
      <span class="mx-3 mb-3 mb-md-0 text-body-secondary">Developed and supported by CodiPhi Solutions</span>
    </div>
  </footer>
</body>

</html>