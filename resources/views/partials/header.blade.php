<div class="topbar"><span class="pa">ਜੀ ਆਇਆਂ ਨੂੰ</span> · Welcome to Ravi Exclusive, Katra Jaimal Singh, Amritsar ·
    WhatsApp <a href="tel:+918148146262">+91-814-814-62-62</a></div>

  <header class="site-header">
    <div class="container">
      <div class="row align-items-center g-2 hrow">
        <div class="col d-lg-none">
          <button class="icon-btn menu-btn" type="button" aria-label="Menu" data-bs-toggle="offcanvas" data-bs-target="#mnav" aria-controls="mnav"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 6h18M3 12h18M3 18h18" /></svg></button>
        </div>
        <div class="col-auto col-lg">
          <a href="{{ route('shop.home') }}" class="brand"><div class="logo-box"><img class="logo-img" src="{{ asset('assets') }}/images/logo.webp" alt="Ravi Exclusive logo" onerror="this.parentNode.classList.add('fallback')"><div class="emblem"><b>Ravi</b><span>EXCLUSIVE</span></div></div></a>
        </div>
        <nav class="col-auto d-none d-lg-block mainnav" aria-label="Main">
          <ul id="mega">
            <li>
              <a href="{{ route('shop.girls') }}"@if (request()->routeIs('shop.girls')) class="active"@endif>Girls</a>
              <div class="drop">
                <h4>Girls</h4>
                <a href="{{ route('shop.girls') }}#lehenga">Lehenga</a>
                <a href="{{ route('shop.girls') }}#sharara-palazzo">Sharara / Palazzo</a>
                <a href="{{ route('shop.girls') }}#patiala-farshi-salwar">Patiala Salwar Suits / Farshi Salwar</a>
                <a href="{{ route('shop.girls') }}#frocks-gowns">Frocks &amp; Gowns</a>
                <a class="all" href="{{ route('shop.girls') }}">Shop all Girls →</a>
              </div>
            </li>
            <li>
              <a href="{{ route('shop.boys') }}"@if (request()->routeIs('shop.boys')) class="active"@endif>Boys</a>
              <div class="drop">
                <h4>Boys</h4>
                <a href="{{ route('shop.boys') }}#sherwani">Sherwani</a>
                <a href="{{ route('shop.boys') }}#indo-westerns">Indo-Westerns</a>
                <a href="{{ route('shop.boys') }}#coat-suits-tuxedos">Coat Suits / Tuxedos</a>
                <a href="{{ route('shop.boys') }}#jodhpuri">Jodhpuri</a>
                <a href="{{ route('shop.boys') }}#kurta-pajama">Kurta Pajama</a>
                <a href="{{ route('shop.boys') }}#waistcoat-sets">Waistcoat Sets</a>
                <a class="all" href="{{ route('shop.boys') }}">Shop all Boys →</a>
              </div>
            </li>
            <li>
              <a href="{{ route('shop.men') }}"@if (request()->routeIs('shop.men')) class="active"@endif>Men</a>
              <div class="drop">
                <h4>Men</h4>
                <a href="{{ route('shop.men') }}#kurta-pajamas">Kurta Pajamas</a>
                <a href="{{ route('shop.men') }}#waistcoat-sets">Waistcoat Sets</a>
                <a href="{{ route('shop.men') }}#indo-westerns">Indo-Westerns</a>
                <a class="all" href="{{ route('shop.men') }}">Shop all Men →</a>
              </div>
            </li>
            <li>
              <a href="{{ route('shop.women') }}"@if (request()->routeIs('shop.women')) class="active"@endif>Women</a>
              <div class="drop">
                <h4>Women</h4>
                <a href="{{ route('shop.women') }}#co-ord-sets">Co-ord Sets</a>
                <a class="all" href="{{ route('shop.women') }}">Shop all Women →</a>
              </div>
            </li>
            <li><a href="{{ route('shop.new-arrivals') }}"@if (request()->routeIs('shop.new-arrivals')) class="active"@endif>New Arrivals</a></li>
          </ul>
        </nav>
        <div class="col">
          <div class="icons">
            <button class="icon-btn" type="button" aria-label="Search" data-bs-toggle="collapse" data-bs-target="#searchPanel" aria-expanded="false" aria-controls="searchPanel"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg></button>
            <a class="icon-btn" href="{{ route('shop.wishlist') }}" aria-label="Wishlist"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7.5-4.6-9.5-9.3C1.1 8.3 3.3 4.5 7 4.5c2 0 3.4 1.1 5 3 1.6-1.9 3-3 5-3 3.7 0 5.9 3.8 4.5 7.2C19.5 16.4 12 21 12 21z" /></svg><span class="badge" id="wcount" hidden>0</span></a>
            <button class="icon-btn" type="button" aria-label="Cart" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer" aria-controls="cartDrawer"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14l-1.2 12.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9z" /><path d="M9 7a3 3 0 0 1 6 0" /></svg><span class="badge" id="ccount" hidden>0</span></button>
          </div>
        </div>
      </div>
    </div>
    <div class="search-panel collapse" id="searchPanel">
      <div class="container">
        <form class="search" id="search" action="{{ route('shop.search') }}" method="get" role="search">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
          <input id="q" name="q" type="search" placeholder="Search sherwani, lehenga, kurta pajama, co-ord set…" aria-label="Search products">
          <button type="submit" class="btn btn-red">Search</button>
        </form>
      </div>
    </div>
  </header>
