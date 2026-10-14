@extends('layouts.app')

@section('title', 'Your Wishlist — Ravi Exclusive')
@section('description', 'Products you\'ve saved at Ravi Exclusive.')
@section('page', 'wishlist')

@section('content')
<div class="container">
  <nav class="crumb" aria-label="Breadcrumb"><a href="{{ route('shop.home') }}">Home</a> / Wishlist</nav>
  <h1 class="shop-title">Your Wishlist</h1>
  <div class="wish-wrap">
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4 product-grid" id="plist">
      @include('partials.product-grid', ['products' => $products, 'hidden' => true])
    </div>
    <div class="empty" id="emptyMsg" @if ($products->isNotEmpty()) hidden @endif>Your wishlist is empty. Tap ♡ on any product to save it here.</div>
  </div>
</div>
@endsection
