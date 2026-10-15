@extends('layouts.app')

@section('title', $category->meta_title ?: "{$category->name} — Ravi Exclusive | Amritsar")
@section('description', $category->meta_description ?: $category->description)
@section('page', 'category')
@section('category', $category->slug)
@section('category_name', $category->name)

@section('content')
<div class="container">
  <nav class="crumb" aria-label="Breadcrumb" data-aos="fade-up"><a href="{{ route('shop.home') }}">Home</a> / <a href="{{ route('shop.'.$category->slug) }}">{{ $category->name }}</a><span id="crumbSub"></span></nav>
  <h1 class="shop-title" id="shopTitle" data-aos="fade-up" data-aos-delay="100">{{ $category->name }}</h1>
  <div class="row g-4 shop-layout">
    <div class="col-lg-3" data-aos="fade-up">
      <aside class="side collapse d-lg-block" id="side">
        @include('partials.filter-panel')
      </aside>
    </div>
    <div class="col-lg-9">
      <div class="toolbar" data-aos="fade-up" data-aos-delay="100">
        <nav class="chips" aria-label="Subcategories">
          <a class="chip on" href="#all" data-sub-link="">All <span class="chip-count" data-chip-count></span></a>
          @foreach ($category->children as $subcategory)
            @php($subcategorySlug = \Illuminate\Support\Str::after($subcategory->slug, $category->slug.'-'))
            <a class="chip" href="#{{ $subcategorySlug }}" data-sub-link="{{ $subcategorySlug }}" data-sub-name="{{ $subcategory->name }}">{{ $subcategory->name }} <span class="chip-count" data-chip-count></span></a>
          @endforeach
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
