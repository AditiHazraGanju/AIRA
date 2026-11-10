@extends('layout.master2')

@section('content')
<div class="row w-100 mx-0 auth-page">
  <div class="col-md-8 col-xl-6 mx-auto d-flex flex-column align-items-center">
    <img src="{{ url('build/images/others/404.svg') }}" class="img-fluid mb-2" alt="404">
    <h1 class="fw-bolder mb-22 mt-2 fs-80px text-secondary">404</h1>
    <h4 class="mb-2">Page Not Found</h4>
    <h6 class="text-secondary mb-3 text-center">Oops!! The page you were looking for doesn't exist.</h6>
    <a href="{{ url('/') }}"><i data-lucide="arrow-left" class="w-15px"></i> Back to home</a>
  </div>
</div>
@endsection