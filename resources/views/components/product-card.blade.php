<article class="pcard" data-id="{{ $product->id }}" data-code="{{ $code }}" data-name="{{ $product->name }}" data-cat="{{ $categorySlug }}" data-cat-name="{{ $categoryName }}"
  data-sub="{{ $subcategoryName }}" data-sub-slug="{{ $subcategorySlug }}" data-price="{{ (int) round($price) }}" data-mrp="{{ (int) round($comparePrice ?? 0) }}" data-new="{{ $isNew ? 1 : 0 }}"
  data-stock="{{ $inStock ? 1 : 0 }}" data-fabric="{{ $fabric }}" data-hex="{{ $tint }}" data-sizes="{{ $sizes }}" data-search="{{ $searchText }}" data-url="{{ $url }}">
  <div class="pimg" style="background:linear-gradient(160deg,{{ $tint }}22,{{ $tint }}44)" data-action="quick-view" role="button" tabindex="0" aria-label="Quick view: {{ $product->name }}">
    @if ($imageUrl)
      <img class="ph" src="{{ $imageUrl }}" alt="{{ $product->name }}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    @endif
    <span class="art-fb" style="{{ $imageUrl ? 'display:none;' : '' }}color:{{ $tint }}"><svg viewBox="0 0 100 120"><path fill="currentColor" d="M36 8h28l22 14-9 16-9-6v80H32V32l-9 6-9-16z"/><path fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="1.4" d="M50 10v34M46 18h8M46 26h8M46 34h8"/></svg></span>
    @if (! $inStock)
      <span class="ptag off">Sold out</span>
    @elseif ($isNew)
      <span class="ptag">New</span>
    @elseif ($discountPercent > 0)
      <span class="ptag off">{{ $discountPercent }}% off</span>
    @endif
    <button class="wish" type="button" data-action="wish" aria-label="Add to wishlist" aria-pressed="false">♡</button>
    <div class="quick">Quick view</div>
  </div>
  <div class="pbody">
    <small>{{ $categoryName }}@if ($subcategoryName) · {{ $subcategoryName }}@endif</small>
    <h4><a href="{{ $url }}">{{ $product->name }}</a></h4>
    <div class="price"><b>{{ $money($price) }}</b>@if ($comparePrice)<s>{{ $money($comparePrice) }}</s><em>{{ $discountPercent }}% off</em>@endif</div>
  </div>
</article>
