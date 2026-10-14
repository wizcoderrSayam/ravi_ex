<form class="fpanel" id="filters" method="get" action="{{ url()->current() }}" role="search" aria-labelledby="filtersTitle">
  <h5 class="fpanel-title" id="filtersTitle">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 6h11M18 6h3M3 12h3M10 12h11M3 18h13M20 18h1"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>
    Filters
  </h5>

  <label class="fpanel-label" for="fq">Search</label>
  <div class="fpanel-search">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
    <input id="fq" name="q" type="search" value="{{ request('q') }}" placeholder="Search products">
  </div>

  <span class="fpanel-label" id="fpriceLabel">Price (₹)</span>
  <div class="fpanel-range" role="group" aria-labelledby="fpriceLabel">
    <input name="min" type="number" min="0" step="1" inputmode="numeric" value="{{ request('min') }}" placeholder="Min ₹" aria-label="Minimum price">
    <span aria-hidden="true">–</span>
    <input name="max" type="number" min="0" step="1" inputmode="numeric" value="{{ request('max') }}" placeholder="Max ₹" aria-label="Maximum price">
  </div>

  <label class="fpanel-check">
    <input name="in_stock" type="checkbox" value="1" @checked(request()->boolean('in_stock'))>
    In stock only
  </label>

  <label class="fpanel-label" for="fsort">Sort by</label>
  <select id="fsort" name="sort">
    <option value="pop" @selected(request('sort', 'pop') === 'pop')>Featured</option>
    <option value="new" @selected(request('sort') === 'new')>Newest</option>
    <option value="low" @selected(request('sort') === 'low')>Price: Low to High</option>
    <option value="high" @selected(request('sort') === 'high')>Price: High to Low</option>
  </select>

  <label class="fpanel-label" for="fper">Show</label>
  <select id="fper" name="per_page">
    @foreach ([12, 24, 48] as $perPage)
      <option value="{{ $perPage }}" @selected((int) request('per_page', 12) === $perPage)>{{ $perPage }} per page</option>
    @endforeach
  </select>

  <button type="submit" class="fpanel-apply">Apply</button>
</form>
