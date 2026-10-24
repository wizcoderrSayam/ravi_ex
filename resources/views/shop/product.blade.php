@extends('layouts.app')

@php
  $sizes = explode('|', $card->sizes);
  $summary = $product->meta_description ?: ($product->short_description ?: \Illuminate\Support\Str::limit((string) $product->description, 160));
  $photo = $gallery['images'][$gallery['active']] ?? null;
@endphp

@section('title', $product->meta_title ?: "{$product->name} — Ravi Exclusive | Amritsar")
@section('description', $summary)
@section('page', 'product')

{{-- WhatsApp and other apps build the link preview from these when an order message carries this page's link. --}}
@push('meta')
  <link rel="canonical" href="{{ $card->url }}">
  <meta property="og:type" content="product">
  <meta property="og:site_name" content="Ravi Exclusive">
  <meta property="og:title" content="{{ $product->name }} · {{ $card->money($card->price) }}">
  <meta property="og:description" content="{{ $summary }}">
  <meta property="og:url" content="{{ $card->url }}">
  @if ($photo)
    {{-- A JPEG copy of the photo: WhatsApp shows the site logo instead of WebP or large images. --}}
    <meta property="og:image" content="{{ route('shop.product.preview', $product->slug) }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:alt" content="{{ $product->name }}">
  @endif
@endpush

@section('content')
<div class="container">
  <nav class="crumb" aria-label="Breadcrumb" data-aos="fade-up">
    <a href="{{ route('shop.home') }}">Home</a>
    @if ($categoryUrl)
      / <a href="{{ $categoryUrl }}">{{ $card->categoryName }}</a>
      @if ($card->subcategoryName)
        / <a href="{{ $categoryUrl }}#{{ $card->subcategorySlug }}">{{ $card->subcategoryName }}</a>
      @endif
    @endif
    / {{ $product->name }}
  </nav>

  {{-- The data-* attributes match a product card's, so public/assets/js/main.js reads both the same way. --}}
  <div class="row g-4 g-lg-5 pd" id="pd" data-id="{{ $product->id }}" data-code="{{ $card->code }}" data-name="{{ $product->name }}"
    data-cat-name="{{ $card->categoryName }}" data-sub="{{ $card->subcategoryName }}" data-price="{{ (int) round($card->price) }}"
    data-mrp="{{ (int) round($card->comparePrice ?? 0) }}" data-fabric="{{ $card->fabric }}" data-hex="{{ $card->tint }}"
    data-sizes="{{ $card->sizes }}" data-url="{{ $card->url }}">
    <div class="col-md-6" data-aos="fade-up">
      <div class="pd-photo" style="background:linear-gradient(160deg,{{ $card->tint }}22,{{ $card->tint }}44)">
        @if ($photo)
          <img class="ph" id="pdImg" src="{{ $photo }}" alt="{{ $product->name }}">
        @else
          <span class="art-fb" style="color:{{ $card->tint }}"><svg viewBox="0 0 100 120"><path fill="currentColor" d="M36 8h28l22 14-9 16-9-6v80H32V32l-9 6-9-16z"/><path fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="1.4" d="M50 10v34M46 18h8M46 26h8M46 34h8"/></svg></span>
        @endif
      </div>
      @if (count($gallery['images']) > 1)
        <div class="pd-thumbs" id="pdThumbs">
          @foreach ($gallery['images'] as $index => $image)
            <button type="button" @class(['on' => $index === $gallery['active']]) data-photo="{{ $image }}" aria-label="Show photo {{ $index + 1 }}"><img src="{{ $image }}" alt="" loading="lazy"></button>
          @endforeach
        </div>
      @endif
    </div>

    <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
      <div class="pd-info">
        <small class="eyebrow">{{ $card->categoryName }}@if ($card->subcategoryName) · {{ $card->subcategoryName }}@endif</small>
        <h1>{{ $product->name }}</h1>
        <div class="price"><b>{{ $card->money($card->price) }}</b><span class="price-from">onwards</span>@if ($card->comparePrice)<s>{{ $card->money($card->comparePrice) }}</s><em>{{ $card->discountPercent }}% off</em>@endif</div>
        <p class="tax-note">Inclusive of all taxes</p>
        @if (! $card->inStock)
          <p class="pd-soldout">Sold out right now. Message us on WhatsApp and we'll tell you when it's back.</p>
        @endif

        {{-- With a single option there is nothing to choose; it stays pre-selected for Add to Bag. With several, the shopper must pick one. --}}
        <div class="opt"{{ count($sizes) === 1 ? ' hidden' : '' }}>
          <h5>Size</h5>
          <div class="sizes" id="pdSizes">
            @foreach ($sizes as $size)
              <button type="button" @class(['on' => count($sizes) === 1]) data-size="{{ $size }}">{{ $size }}</button>
            @endforeach
          </div>
          <div class="err" id="pdSizeErr">Please select a size.</div>
        </div>
        {{-- Hidden for now; orders go out with a quantity of 1. Remove `hidden` to bring it back. --}}
        <div class="opt" hidden>
          <h5>Quantity</h5>
          <div class="qty"><button type="button" data-qty="-1" aria-label="Decrease">−</button><span id="pdQty">1</span><button type="button" data-qty="1" aria-label="Increase">+</button></div>
        </div>
        <div class="mactions">
          <button type="button" class="btn btn-red btn-block" id="pdAdd">Add to Bag</button>
          <button type="button" class="btn btn-wa btn-block" id="pdBuy" data-buy-now>Buy now on WhatsApp</button>
        </div>

        <div class="mdetails">
          <div><b>Colour:</b> As shown (more shades in store)</div>
          <div><b>Product code:</b> {{ $card->code }}</div>
          <div>Need help with size or customisation? Call <a href="tel:+918148146262" class="text-red">+91-814-814-62-62</a></div>
        </div>

        @if (filled($product->description))
          <div class="pd-desc">
            <h2>Product details</h2>
            @foreach (preg_split('/\R{2,}/', trim($product->description)) as $paragraph)
              <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
          </div>
        @endif

        {{-- A second Buy now for shoppers who read down to here; it orders the size and quantity picked above. --}}
        <div class="mactions">
          <button type="button" class="btn btn-wa btn-block" data-buy-now>Buy now on WhatsApp</button>
        </div>
      </div>
    </div>
  </div>
</div>

@if ($related->isNotEmpty())
  <section class="pd-related">
    <div class="container">
      <div class="head" data-aos="fade-up">
        <div>
          <span class="eyebrow">More {{ $card->subcategoryName ?: $card->categoryName }}</span>
          <h2>You may also like</h2>
        </div>
        @if ($categoryUrl)
          <a href="{{ $categoryUrl }}@if ($card->subcategorySlug)#{{ $card->subcategorySlug }}@endif">View all →</a>
        @endif
      </div>
      <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4 product-grid">
        @include('partials.product-grid', ['products' => $related])
      </div>
    </div>
  </section>
@endif
@endsection
