<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ config('app.name', 'Attendance System') }}</title>

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

  <!-- App JS bundle (student-selector behaviour). Built with `npm run build`;
       see docs/deployment.md — public/build is gitignored and must be uploaded. -->
  @vite(['resources/js/app.js'])
</head>

<body>

  <nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
      <a class="navbar-brand" href="/">
        <img src="/images/logo.png" alt="logo" width="27" height="30" class="d-inline-block align-text-top">
        Dhamma and Sinhala School of Canberra
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav">
          <a class="nav-link" aria-current="page" href="{{route('attendance.selection')}}">New Attendance</a>
          <a class="nav-link" aria-current="page" href="{{route('book_distribution.selection')}}">Book Distribution</a>
          <a class="nav-link" aria-current="page" href="{{route('attendance.summary')}}">Summary</a>
        </div>
      </div>
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js"></script>

  @yield('scripts')

  <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 my-4 border-top">
    <div class="col-md-4 d-flex align-items-center">
      <span class="mx-3 mb-3 mb-md-0 text-body-secondary">Developed and supported by CodiPhi Solutions</span>
    </div>
  </footer>
</body>

</html>