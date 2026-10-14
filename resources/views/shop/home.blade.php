@extends('layouts.app')

@section('title', 'Ravi Exclusive — Shop Ethnic & Designer Wear Online | Amritsar')
@section('description', 'Shop designer ethnic wear for Men, Women and Accessories from Ravi Exclusive, Katra Jaimal Singh, Amritsar.')
@section('page', 'home')

@section('content')
<!-- hero -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center hero-grid g-5">
      <div class="col-lg-6 hero-copy" data-aos="fade-right">
        <span class="eyebrow">New Season · Festive Edit</span>
        <h1>Dressed up for every <em>Rasam</em>.</h1>
        <p>
          Hand-embroidered lehengas, statement suits and festive
          silhouettes for every celebration. Fresh in at our Katra Jaimal
          Singh store.
        </p>
        <div class="hero-ctas">
          <a href="{{ route('shop.women') }}" class="btn btn-red">Shop Women</a
          ><a href="{{ route('shop.women') }}#punjabi-suits" class="btn btn-line"
            >Explore Suits</a
          >
        </div>
        <div class="hero-mini">
          <span>✦ Sharara &amp; palazzo suits</span
          ><span>✦ Frock-style suits</span
          ><span>✦ Occasionwear edits</span>
        </div>
      </div>
      <div
        class="col-lg-6 order-first order-lg-last"
        data-aos="fade-left"
        data-aos-delay="150"
      >
        <div class="hero-duo">
          <figure class="hd-main">
            <img
              src="{{ asset('assets') }}/images/hero-a.jpg"
              alt="Embroidered bridal and festive Punjabi outfits from Ravi Exclusive"
            />
          </figure>
          <figure class="hd-side">
            <img
              src="{{ asset('assets') }}/images/product5.jpg"
              alt="Festive Punjabi fashion at the Ravi Exclusive store"
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
            <b>Amritsari style</b
            ><span>Handpicked from Katra Jaimal Singh</span>
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
            <b>Aao ji, visit us</b><span>22, Katra Jaimal Singh</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- categories -->
<section>
  <div class="container">
    <div class="head" data-aos="fade-up">
      <div>
        <span class="eyebrow"
          >Shop by Category · <span class="pa">ਸਾਰੇ ਪਰਿਵਾਰ ਲਈ</span></span
        >
        <h2>For the whole parivaar</h2>
      </div>
    </div>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 cats">
      <div class="col" data-aos="zoom-in" data-aos-delay="0">
        <a class="cat" href="{{ route('shop.boys') }}" style="background: #1f5c55">
          <img
            class="art"
            src="{{ asset('assets') }}/images/categories/boys.jpg"
            alt="Boys"
            loading="lazy"
            onerror="this.remove()"
          />
          <div class="t">
            <!-- <small>Festive · Party · Wedding</small> -->
            <h3>Boys</h3>
          </div>
        </a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="100">
        <a class="cat" href="{{ route('shop.girls') }}" style="background: #d63a7a">
          <img
            class="art"
            src="{{ asset('assets') }}/images/categories/girls.jpg"
            alt="Girls"
            loading="lazy"
            onerror="this.remove()"
          />
          <div class="t">
            <!-- <small>Ages 1–14</small> -->
            <h3>Girls</h3>
          </div>
        </a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="200">
        <a class="cat" href="{{ route('shop.women') }}" style="background: #a91e23">
          <img
            class="art"
            src="{{ asset('assets') }}/images/categories/women.jpg"
            alt="Women"
            loading="lazy"
            onerror="this.remove()"
          />
          <div class="t">
            <!-- <small>Punjabi Suits · Lehengas · Bridal</small> -->
            <h3>Women</h3>
          </div>
        </a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="300">
        <a class="cat" href="{{ route('shop.men') }}" style="background: #1e2f5c">
          <img
            class="art"
            src="{{ asset('assets') }}/images/categories/men.jpg"
            alt="Men"
            loading="lazy"
            onerror="this.remove()"
          />
          <div class="t">
            <!-- <small>Sherwanis · Kurta Pyjama · Pathani</small> -->
            <h3>Men</h3>
          </div>
        </a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="400">
        <a
          class="cat"
          href="{{ route('shop.accessories') }}"
          style="background: #8a6a2e"
        >
          <img
            class="art"
            src="{{ asset('assets') }}/images/categories/accessories.jpg"
            alt="Accessories"
            loading="lazy"
            onerror="this.remove()"
          />
          <div class="t">
            <!-- <small>Pagg · Juttis · Jewellery</small> -->
            <h3>Accessories</h3>
          </div>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- wedding edit -->
<section class="vyah">
  <div class="container">
    <div class="head center" data-aos="fade-up">
      <div>
        <span class="eyebrow light"
          ><span class="pa">ਵਿਆਹ</span> · The Punjabi Wedding Edit</span
        >
        <h2>Dressed for every rasam</h2>
        <p>
          From the mehndi night to the doli: outfits for the bride, the
          groom and the whole baraat.
        </p>
      </div>
    </div>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 vyah-grid">
      <div class="col" data-aos="fade-up" data-aos-delay="0">
        <a class="vy" href="{{ route('shop.women') }}#party-lehengas"
          ><span class="pa">ਮਹਿੰਦੀ</span>
          <h3>Mehndi &amp; Sangeet</h3>
          <small>Party lehengas · Anarkalis</small></a
        >
      </div>
      <div class="col" data-aos="fade-up" data-aos-delay="100">
        <a class="vy" href="{{ route('shop.women') }}#punjabi-suits"
          ><span class="pa">ਸ਼ਗਨ</span>
          <h3>Shagan &amp; Chunni</h3>
          <small>Punjabi suits · Kurtis</small></a
        >
      </div>
      <div class="col" data-aos="fade-up" data-aos-delay="200">
        <a class="vy" href="{{ route('shop.women') }}#bridal-lehengas"
          ><span class="pa">ਲਾੜੀ</span>
          <h3>The Bride</h3>
          <small>Bridal lehengas</small></a
        >
      </div>
      <div class="col" data-aos="fade-up" data-aos-delay="300">
        <a class="vy" href="{{ route('shop.men') }}#sherwanis"
          ><span class="pa">ਲਾੜਾ</span>
          <h3>The Groom</h3>
          <small>Sherwanis · Safas · Juttis</small></a
        >
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
        <h2>Rangeen bazaars, phulkari colours &amp; Punjabi shaan</h2>
        <p>
          Ravi Exclusive sits in Katra Jaimal Singh, one of Amritsar's
          busiest clothing bazaars. We bring that bazaar energy home:
          bright Punjabi suits for Lohri and Visakhi, karva chauth
          lehengas, sherwanis and pagg for the groom, and festive pieces
          for every rasam.
        </p>
        <div class="fest">
          <span><b class="pa">ਲੋਹੜੀ</b> Lohri</span
          ><span><b class="pa">ਵਿਸਾਖੀ</b> Visakhi</span
          ><span><b class="pa">ਕਰਵਾ ਚੌਥ</b> Karva Chauth</span
          ><span><b class="pa">ਵਿਆਹ</b> Wedding season</span>
        </div>
        <a class="btn btn-red mt-22" href="{{ route('shop.women') }}#punjabi-suits"
          >Shop Punjabi Suits</a
        >
      </div>
    </div>
  </div>
</section>

<!-- instagram -->
<section class="insta">
  <div class="container text-center">
    <div data-aos="fade-up">
      <span class="eyebrow">@@ravi.exclusif</span>
      <h2 class="section-title">Follow us on Instagram</h2>
    </div>
    <div class="row row-cols-3 row-cols-md-5 g-2 igrid">
      <div class="col" data-aos="zoom-in" data-aos-delay="0">
        <a
          href="https://www.instagram.com/ravi.exclusif/"
          target="_blank"
          rel="noopener"
          ><img
            src="{{ asset('assets') }}/images/instagram/insta-1.jpg"
            alt="Ravi Exclusive look"
            loading="lazy"
        /></a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="80">
        <a
          href="https://www.instagram.com/ravi.exclusif/"
          target="_blank"
          rel="noopener"
          ><img
            src="{{ asset('assets') }}/images/instagram/insta-2.jpg"
            alt="Ravi Exclusive look"
            loading="lazy"
        /></a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="160">
        <a
          href="https://www.instagram.com/ravi.exclusif/"
          target="_blank"
          rel="noopener"
          ><img
            src="{{ asset('assets') }}/images/instagram/insta-3.jpg"
            alt="Ravi Exclusive look"
            loading="lazy"
        /></a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="240">
        <a
          href="https://www.instagram.com/ravi.exclusif/"
          target="_blank"
          rel="noopener"
          ><img
            src="{{ asset('assets') }}/images/instagram/insta-4.jpg"
            alt="Ravi Exclusive look"
            loading="lazy"
        /></a>
      </div>
      <div class="col" data-aos="zoom-in" data-aos-delay="320">
        <a
          href="https://www.instagram.com/ravi.exclusif/"
          target="_blank"
          rel="noopener"
          ><img
            src="{{ asset('assets') }}/images/instagram/insta-5.jpg"
            alt="Ravi Exclusive look"
            loading="lazy"
        /></a>
      </div>
      <!-- <div class="col" data-aos="zoom-in" data-aos-delay="400">
        <a
          href="https://www.instagram.com/ravi.exclusif/"
          target="_blank"
          rel="noopener"
          ><img
            src="{{ asset('assets') }}/images/instagram/insta-6.jpg"
            alt="Ravi Exclusive look"
            loading="lazy"
        /></a>
      </div> -->
    </div>
    <a
      class="btn btn-red"
      href="https://www.instagram.com/ravi.exclusif/"
      target="_blank"
      rel="noopener"
      data-aos="fade-up"
      >Follow @@ravi.exclusif</a
    >
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
          <p>22, Katra Jaimal Singh</p>
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
