@extends('layouts.app')

@section('title', 'Ravi Exclusive — Shop Ethnic & Designer Wear Online | Amritsar')
@section('description', 'Shop designer ethnic wear for Kids, Men and Women from Ravi Exclusive, Katra Jaimal Singh, Amritsar.')
@section('page', 'home')

@section('content')
<!-- hero -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center hero-grid g-5">
      <div class="col-lg-6 hero-copy" data-aos="fade-right">
        <span class="eyebrow">New Season · Festive Edit</span>
        <h1>Celebrating Every Occasion, the <em>Traditional</em> Way.</h1>
        <p>
          From their first festive outfit to the big family wedding, dress
          your little ones in styles made for memories.
        </p>
        <div class="hero-ctas">
          <a href="{{ route('shop.boys') }}" class="btn btn-red">Shop Boys' Wear</a
          ><a href="{{ route('shop.girls') }}" class="btn btn-line"
            >Shop Girls' Wear</a
          >
        </div>
        <div class="hero-mini">
          <span>✦ Dressing Generations Since 1961</span>
        </div>
      </div>
      <div
        class="col-lg-6 order-first order-lg-last"
        data-aos="fade-left"
        data-aos-delay="150"
      >
        <div class="hero-duo">
          <figure class="hd-main hd-full">
            <img
              src="{{ asset('assets') }}/images/hero-home.webp"
              alt="A boy in a cream embroidered sherwani and a girl in a blush embroidered gown, from Ravi Exclusive"
            />
          </figure>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- trust bar -->
<div class="trust">
  <div class="container">
    <div class="row g-3">
      <div
        class="col-12 col-sm-6 col-lg-3"
        data-aos="fade-up"
        data-aos-delay="0"
      >
        <div class="trust-item">
          <i>✦</i>
          <p>
            <b>Punjabi Style </b
            ><span>Handpicked From The Holy City of Amritsar </span>
          </p>
        </div>
      </div>
      <div
        class="col-12 col-sm-6 col-lg-3"
        data-aos="fade-up"
        data-aos-delay="100"
      >
        <div class="trust-item">
          <i>✂</i>
          <p>
            <b>Fitting &amp; alterations</b
            ><span>Ask us before you order</span>
          </p>
        </div>
      </div>
      <div
        class="col-12 col-sm-6 col-lg-3"
        data-aos="fade-up"
        data-aos-delay="200"
      >
        <div class="trust-item">
          <i>☏</i>
          <p><b>Order on WhatsApp</b><span>Personal service, ji</span></p>
        </div>
      </div>
      <div
        class="col-12 col-sm-6 col-lg-3"
        data-aos="fade-up"
        data-aos-delay="300"
      >
        <div class="trust-item">
          <i>⌂</i>
          <p>
            <b>Aao ji, visit us</b><span> 22, Katra Jaimal Singh, Amritsar</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!--
  shop by category, one section per audience. Kids' sections are a carousel of
  category cards; the others are a band: an intro panel (title, line of copy, link)
  alternating sides, beside the subcategories as a lookbook grid.
-->
@foreach ($shopSections as $section)
@php
  $isReversed = $loop->even;
  $tileCount = count($section['tiles']);
  // Up to 4 tiles sit level in one row; 5–6 wrap into staggered rows of 3.
  $gridColumns = $tileCount > 4 ? 3 : $tileCount;
@endphp
@if ($section['layout'] === 'carousel')
<section class="cat-section" aria-labelledby="shop-{{ $loop->index }}" data-carousel>
  <div class="container">
    <div class="head" data-aos="fade-up">
      <div>
        <span class="eyebrow">Shop by category</span>
        <h2 id="shop-{{ $loop->index }}">{{ $section['title'] }}</h2>
      </div>
      <div class="cat-rail-actions">
        <a href="{{ $section['url'] }}">Shop all {{ $section['name'] }} →</a>
        <button type="button" class="cat-rail-btn" data-rail-prev aria-label="Previous {{ $section['name'] }} categories">‹</button>
        <button type="button" class="cat-rail-btn" data-rail-next aria-label="More {{ $section['name'] }} categories">›</button>
      </div>
    </div>
    {{-- --cards: up to 4 sit in view on desktop, any more slide in; narrower screens show fewer. --}}
    <div
      class="cat-rail"
      style="--cards: {{ $tileCount }}"
      data-rail
      data-aos="fade-up"
      data-aos-delay="100"
    >
      @foreach ($section['tiles'] as $tile)
        <a class="cat" href="{{ $tile['url'] }}">
          @if ($tile['image'])
            <img
              class="art"
              src="{{ $tile['image'] }}"
              alt="{{ $tile['name'] }}"
              loading="lazy"
              onerror="this.remove()"
            />
          @endif
          <div class="t"><h3>{{ $tile['name'] }}</h3></div>
        </a>
      @endforeach
    </div>
    {{-- One dot per card position; main.js fills these, as the count depends on how many cards fit. --}}
    <div class="cat-rail-dots" data-rail-dots></div>
  </div>
</section>
@else
<section class="shop-section" aria-labelledby="shop-{{ $loop->index }}">
  <div class="container">
    <div class="shop-band @if ($isReversed) is-reversed @endif @if ($tileCount <= 2) is-compact @endif">
      <div class="shop-intro" data-aos="{{ $isReversed ? 'fade-left' : 'fade-right' }}">
        <h2 id="shop-{{ $loop->index }}">{{ $section['title'] }}</h2>
        @if ($section['description'])
          <p>{{ $section['description'] }}</p>
        @endif
        <a class="btn btn-red" href="{{ $section['url'] }}">Shop all {{ $section['name'] }}</a>
        <span class="shop-intro-count">{{ $tileCount }} {{ \Illuminate\Support\Str::plural('category', $tileCount) }}</span>
      </div>
      <div
        class="shop-grid cols-{{ $gridColumns }}{{ $tileCount <= 4 ? ' is-level' : '' }}"
        data-aos="{{ $isReversed ? 'fade-right' : 'fade-left' }}"
        data-aos-delay="120"
      >
        @foreach ($section['tiles'] as $tile)
          <a class="shop-tile" href="{{ $tile['url'] }}">
            <span class="shop-tile-media">
              @if ($tile['image'])
                <img
                  src="{{ $tile['image'] }}"
                  alt="{{ $tile['name'] }}"
                  loading="lazy"
                  onerror="this.remove()"
                />
              @endif
              <img
                class="shop-tile-logo"
                src="{{ asset('assets') }}/images/logo.webp"
                alt=""
                aria-hidden="true"
                loading="lazy"
                onerror="this.remove()"
              />
            </span>
            <span class="shop-tile-name">{{ $tile['name'] }}</span>
          </a>
        @endforeach
      </div>
    </div>
  </div>
</section>
@endif
@endforeach

<!-- wedding edit -->
<section class="vyah">
  <div class="container">
    <div class="head center" data-aos="fade-up">
      <div>
        <span class="eyebrow light"><span class="pa">ਵਿਆਹ</span> · The Punjabi Wedding Edit</span>
        <h2>Outfits for every occasion!</h2>
        <p>From Mehndi to the Reception, Outfits That Steal the Show!</p>
      </div>
    </div>
    {{-- Plain text, not links: these only highlight the occasions we dress for. --}}
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 vyah-grid">
      <div class="col" data-aos="fade-up" data-aos-delay="0">
        <div class="vy">
          <span class="pa">ਮਹਿੰਦੀ</span>
          <h3>Mehndi &amp; Sangeet</h3>
          <small>Lehengas · Sharara · Co-ord sets</small>
        </div>
      </div>
      <div class="col" data-aos="fade-up" data-aos-delay="100">
        <div class="vy">
          <span class="pa">ਜਾਗੋ</span>
          <h3>Jaggo &amp; Dhol</h3>
          <small>Patiala suits · Kurta pajamas</small>
        </div>
      </div>
      <div class="col" data-aos="fade-up" data-aos-delay="200">
        <div class="vy">
          <span class="pa">ਸ਼ਗਨ</span>
          <h3>Shagun &amp; Wedding</h3>
          <small>Sherwanis · Jodhpuris · Lehengas</small>
        </div>
      </div>
      <div class="col" data-aos="fade-up" data-aos-delay="300">
        <div class="vy">
          <span class="pa">ਰਿਸੈਪਸ਼ਨ</span>
          <h3>Cocktail &amp; Reception</h3>
          <small>Indo-westerns · Coat suits · Gowns</small>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- new arrivals -->
<section class="pt-64">
  <div class="container">
    <div class="head" data-aos="fade-up">
      <div>
        <span class="eyebrow">Just In</span>
        <h2>New arrivals</h2>
      </div>
      <a href="{{ route('shop.new-arrivals') }}">View all →</a>
    </div>
    <div
      class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4 product-grid"
    >
      @include('partials.product-grid', ['products' => $newArrivals])
    </div>
  </div>
</section>

<!-- bestsellers -->
<section class="bg-white">
  <div class="container">
    <div class="head" data-aos="fade-up">
      <div>
        <span class="eyebrow">Most Loved</span>
        <h2>Bestsellers</h2>
      </div>
    </div>
    <div
      class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 g-md-4 product-grid"
    >
      @include('partials.product-grid', ['products' => $bestsellers])
    </div>
  </div>
</section>

<!-- heritage -->
<section class="heritage">
  <div class="container">
    <div class="row align-items-center g-5 her-grid">
      <div class="col-lg-5" data-aos="zoom-in">
        <div class="her-panel">
          <span class="pa big">ਅੰਮ੍ਰਿਤਸਰ</span
          ><span class="her-sub">Amritsar da andaaz</span>
        </div>
      </div>
      <div class="col-lg-7" data-aos="fade-left" data-aos-delay="150">
        <span class="eyebrow">From the heart of Amritsar</span>
        <h2>Blending traditional craftsmanship with modern elegance</h2>
        <p>
          Established in 1961, Ravi Exclusive is more than a retail
          showroom—it is a legacy built on trust, quality, and timeless
          style. For generations, we have been dressing families with care,
          offering a curated selection of premium fashion for men, women,
          and children.
        </p>
        <p>
          With over 10 lakh satisfied customers and a strong global
          customer base, our commitment to excellence continues to define
          who we are.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- instagram -->
@php
  $instagramUrl = 'https://www.instagram.com/ravi.exclusif/';
  // Preview clips cut from @ravi.exclusif reels; each links to the profile until its post link is added.
  $reels = collect([
    'Embroidered kurta sets in store',
    "Kids' festive outfits in store",
    'Princess-style party frocks',
    'Pretty suits for girls',
    'Party wear for kids',
    'Party frock collection',
    'Embroidered kurta and palazzo sets for girls',
    "Festive kids' collection in store",
    'Embellished party gowns for girls',
    'Tiered party frocks',
  ])->map(fn (string $alt, int $index): array => [
    'name' => sprintf('reel-%02d', $index + 1),
    'alt' => $alt,
    'url' => $instagramUrl,
  ]);
@endphp
<section class="insta" aria-labelledby="insta-title">
  <div class="container">
    <div class="head" data-aos="fade-up">
      <div>
        <span class="eyebrow">@@ravi.exclusif</span>
        <h2 id="insta-title">Follow us on Instagram</h2>
      </div>
      <div class="cat-rail-actions">
        <a href="{{ $instagramUrl }}" target="_blank" rel="noopener">Follow @@ravi.exclusif →</a>
      </div>
    </div>
    {{-- The reels are listed twice so main.js can glide the row endlessly; the copy is hidden from screen readers and the keyboard. --}}
    <div class="cat-rail reel-rail" style="--cards: 5" data-glide data-aos="fade-up" data-aos-delay="100">
      @foreach ([false, true] as $isCopy)
        @foreach ($reels as $reel)
        <a class="reel" href="{{ $reel['url'] }}" target="_blank" rel="noopener" @if ($isCopy) aria-hidden="true" tabindex="-1" data-glide-copy @else aria-label="{{ $reel['alt'] }}: see @@ravi.exclusif on Instagram (opens in a new tab)" @endif>
          {{-- Muted, inline and looping so browsers allow autoplay; main.js plays it only while it is on screen. --}}
          <video muted loop playsinline preload="none" poster="{{ asset('assets/videos/instagram/'.$reel['name'].'.webp') }}" data-reel aria-hidden="true">
            <source src="{{ asset('assets/videos/instagram/'.$reel['name'].'.mp4') }}" type="video/mp4">
          </video>
          <span class="reel-badge" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
        </a>
        @endforeach
      @endforeach
    </div>
    <div class="text-center mt-4">
      <a class="btn btn-red" href="{{ $instagramUrl }}" target="_blank" rel="noopener" data-aos="fade-up">Follow @@ravi.exclusif</a>
    </div>
  </div>
</section>

<!-- visit the store -->
<section id="store">
  <div class="container">
    <div class="row align-items-center g-4 g-lg-5 store">
      <div class="col-lg-6" data-aos="fade-right">
        <span class="eyebrow"
          >Visit Us · <span class="pa">ਆਓ ਜੀ</span></span
        >
        <h2 class="section-title store-title">
          Aao ji! See the collection in person
        </h2>
        <p class="store-lead">
          Come to our store in the heart of Amritsar's famous Katra Jaimal
          Singh market for personal styling, trials and fittings for the
          whole family.
        </p>
      </div>
      <div class="col-lg-6" data-aos="fade-left" data-aos-delay="150">
        <div class="store-card">
          <h3>M/s Ravi Exclusive</h3>
          <p> 22, Katra Jaimal Singh, Amritsar</p>
          <p>Amritsar, Punjab, India – 143001</p>
          <p class="mt-10">
            Ph: <a href="tel:+918148146262">+91-814-814-62-62</a>
          </p>
          <a
            class="btn"
            href="https://www.google.com/maps/search/?api=1&amp;query=22+Katra+Jaimal+Singh+Amritsar+143001"
            target="_blank"
            rel="noopener"
            >Get Directions</a
          >
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
