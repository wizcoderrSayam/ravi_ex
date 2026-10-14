<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Ravi Exclusive — Shop Ethnic & Designer Wear Online | Amritsar')</title>
  <meta name="description" content="@yield('description')">
  <link rel="icon" href="{{ asset('assets/images/logo.webp') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/aos/aos.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>

<body data-page="@yield('page')"@hasSection('category') data-cat="@yield('category')" data-cat-name="@yield('category_name')"@endif>
  @include('partials.header')

  <!-- mobile menu -->
  @include('partials.mobile-nav')

  <main id="app">
    @yield('content')
  </main>

  <!-- product quick view -->
  @include('partials.quick-view')

  <!-- cart drawer -->
  @include('partials.cart-drawer')

  @include('partials.footer')

  <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('assets/js/main.js') }}"></script>
</body>
</html>
