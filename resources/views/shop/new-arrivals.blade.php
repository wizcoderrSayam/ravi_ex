@extends('layouts.app')

@section('title', 'New Arrivals — Ravi Exclusive | Amritsar')
@section('description', 'The latest ethnic and designer wear just in at Ravi Exclusive, Katra Jaimal Singh, Amritsar.')
@section('page', 'new')

@section('content')
<div class="container">
  <nav class="crumb" aria-label="Breadcrumb"><a href="{{ route('shop.home') }}">Home</a> / New Arrivals</nav>
  <h1 class="shop-title" id="shopTitle">New Arrivals</h1>
  <div class="row g-4 shop-layout">
    <div class="col-lg-3">
      <aside class="side collapse d-lg-block" id="side">
        @include('partials.filter-panel')
      </aside>
    </div>
    <div class="col-lg-9">
      <div class="toolbar">
        <nav class="chips" aria-label="Categories">
          <a class="chip on" href="#all" data-sub-link="">All <span class="chip-count" data-chip-count></span></a>
          <a class="chip" href="#boys" data-sub-link="boys" data-sub-name="Boys">Boys <span class="chip-count" data-chip-count></span></a>
          <a class="chip" href="#girls" data-sub-link="girls" data-sub-name="Girls">Girls <span class="chip-count" data-chip-count></span></a>
          <a class="chip" href="#women" data-sub-link="women" data-sub-name="Women">Women <span class="chip-count" data-chip-count></span></a>
          <a class="chip" href="#men" data-sub-link="men" data-sub-name="Men">Men <span class="chip-count" data-chip-count></span></a>
          <a class="chip" href="#accessories" data-sub-link="accessories" data-sub-name="Accessories">Accessories <span class="chip-count" data-chip-count></span></a>
        </nav>
        <div class="toolbar-actions">
          <button class="btn btn-line filter-btn d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#side" aria-expanded="false" aria-controls="side">Filters</button>
          <span class="count" id="count">{{ $products->count() }} items</span>
        </div>
      </div>
      <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4 product-grid" id="plist">
        @include('partials.product-grid', ['products' => $products])
      </div>
      <div class="empty" id="emptyMsg" @if ($products->isNotEmpty()) hidden @endif>No products match these filters.</div>
      <nav class="pager" id="pager" aria-label="Product pages" hidden></nav>
    </div>
  </div>
</div>
@endsection
