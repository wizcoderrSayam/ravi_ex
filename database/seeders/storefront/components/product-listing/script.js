/*
 * The old listing pages (shop/category, new-arrivals, search and wishlist) on /api/products.
 * mode "category": a top-level category, with its subcategories as chips; a #hash picks one
 *   (/girls#lehenga), and the old site's hashes (#patiala-farshi-salwar) still find theirs.
 * mode "new": the latest products; "search": the products matching ?q=; "wishlist": the products
 *   saved in this browser.
 * The filter panel, sort and pager work on the listing's products in the browser and keep their
 * state in the query string (?q=&min=&max=&in_stock=1&sort=&per_page=&page=), as the old page did.
 * The page waits for how many products there are, so the skeleton has the listing's own size.
 */
var root = this;
props = props || {};

var mode = props.mode || 'category';
var limit = Math.max(1, Math.min(48, parseInt(props.limit, 10) || 48));
var countPath = mode === 'category'
  ? '/products?per_page=1&category=' + encodeURIComponent(props.category || '')
  : (mode === 'new' ? '/products?per_page=1' : null);

Store.need('/categories');
if (countPath) Store.need(countPath);

Store.ready(function () {
  Store.bind(root);

  var esc = Store.esc;
  var f = Store.flags;
  var index = Store.categories();
  function part(role) { return root.querySelector('[data-role="' + role + '"]'); }
  var grid = part('grid');
  var chips = part('chips');
  var count = part('count');
  var empty = part('empty');
  var pager = part('pager');
  var title = part('title');
  var crumbSub = part('crumb-sub');
  var form = root.querySelector('#filters');
  var side = root.querySelector('#side');
  var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var suffix = props.titleSuffix || 'Ravi Exclusive | Amritsar';
  var pageTitle = document.title;
  var PER_PAGE = [12, 24, 48];
  var SORTS = ['pop', 'new', 'low', 'high'];
  var query = (new URLSearchParams(location.search).get('q') || '').trim();

  var parent = mode === 'category' && index ? index.bySlug[props.category] || null : null;
  var chipParent = mode === 'category' ? parent : { children: index ? index.tree : [] };
  var crumbs = { 'new': 'New Arrivals', search: 'Search', wishlist: 'Wishlist' };
  var heading = props.title || (mode === 'category' ? (parent ? parent.name : '') : ({ 'new': 'New Arrivals', search: 'Search', wishlist: 'Your Wishlist' })[mode]);

  if (mode === 'category' && !parent) {
    title.textContent = 'This collection is not available';
    part('crumb').textContent = 'Shop';
    root.querySelector('.shop-layout').innerHTML = '<div class="col-12"><div class="empty-state"><i>✦</i><h3>Nothing to show here</h3>' +
      '<p>This collection has moved or is no longer online.</p><a class="btn btn-red" href="/new-arrivals">See new arrivals</a></div></div>';
    return;
  }

  if (mode === 'search' && query) {
    heading = 'Results for “' + query + '”';
    document.title = pageTitle = heading + (pageTitle.indexOf(' — ') !== -1 ? pageTitle.slice(pageTitle.indexOf(' — ')) : '');
  }
  title.textContent = heading;
  part('crumb').innerHTML = parent ? '<a href="' + esc(Store.categoryUrl(parent)) + '">' + esc(parent.name) + '</a>' : esc(crumbs[mode] || heading);

  if (mode === 'wishlist') {
    part('side-column').remove();
    part('toolbar').remove();
    pager.remove();
    pager = null;
    chips = null;
    form = null;
    part('main-column').className = 'col-12';
  }

  if (form && f.pricing) {
    var symbol = f.currency_symbol || '';
    part('currency').textContent = symbol;
    form.elements.min.placeholder = 'Min ' + symbol;
    form.elements.max.placeholder = 'Max ' + symbol;
  }

  var skeletonCount = '<span class="sk sk-text"></span>';
  if (chips) {
    var chipItems = (chipParent && chipParent.children) || [];
    chips.setAttribute('aria-label', mode === 'category' ? 'Subcategories' : 'Categories');
    chips.innerHTML = [{ id: '', name: 'All', href: '#all' }].concat(chipItems.map(function (child) {
      return { id: String(child.id), name: child.name, href: '#' + Store.hashOf(child) };
    })).map(function (chip) {
      return '<a class="chip" href="' + esc(chip.href) + '" data-chip="' + chip.id + '">' + esc(chip.name) + ' <span class="chip-count" data-chip-count>' + skeletonCount + '</span></a>';
    }).join('');
  }

  /* ---------- filters, in the query string ---------- */

  function readFilters() {
    var params = new URLSearchParams(location.search);
    function number(key) {
      var value = params.get(key);
      return value === null || value.trim() === '' || isNaN(value) ? null : Math.max(0, +value);
    }
    var min = f.pricing ? number('min') : null;
    var max = f.pricing ? number('max') : null;
    if (min !== null && max !== null && min > max) {
      var swap = min;
      min = max;
      max = swap;
    }
    var sort = SORTS.indexOf(params.get('sort')) !== -1 ? params.get('sort') : 'pop';
    if (!f.pricing && (sort === 'low' || sort === 'high')) sort = 'pop';
    return {
      q: (params.get('q') || '').trim(),
      min: min,
      max: max,
      inStock: !!f.stock && params.get('in_stock') === '1',
      sort: sort,
      perPage: PER_PAGE.indexOf(+params.get('per_page')) !== -1 ? +params.get('per_page') : PER_PAGE[0],
      page: Math.max(1, parseInt(params.get('page'), 10) || 1)
    };
  }

  function writeFilters() {
    var params = new URLSearchParams();
    if (filters.q) params.set('q', filters.q);
    if (filters.min !== null) params.set('min', filters.min);
    if (filters.max !== null) params.set('max', filters.max);
    if (filters.inStock) params.set('in_stock', '1');
    if (filters.sort !== 'pop') params.set('sort', filters.sort);
    if (filters.perPage !== PER_PAGE[0]) params.set('per_page', filters.perPage);
    if (filters.page > 1) params.set('page', filters.page);
    var search = params.toString();
    history.replaceState(null, '', location.pathname + (search ? '?' + search : '') + location.hash);
  }

  function fillForm() {
    if (!form) return;
    form.elements.q.value = filters.q;
    if (form.elements.min) {
      form.elements.min.value = filters.min === null ? '' : filters.min;
      form.elements.max.value = filters.max === null ? '' : filters.max;
    }
    if (form.elements.in_stock) form.elements.in_stock.checked = filters.inStock;
    form.elements.sort.value = filters.sort;
    form.elements.per_page.value = String(filters.perPage);
  }

  var filters = readFilters();
  fillForm();

  /* ---------- drawing ---------- */

  var items = null;

  function currentChip() {
    var hash = decodeURIComponent(location.hash.slice(1));
    return hash && hash !== 'all' && chipParent ? Store.findChild(chipParent, hash) : null;
  }

  function markChip(chip) {
    if (chips) {
      chips.querySelectorAll('[data-chip]').forEach(function (link) {
        var on = link.getAttribute('data-chip') === (chip ? String(chip.id) : '');
        link.classList.toggle('on', on);
        if (on) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
      });
    }
    if (mode === 'category') {
      title.textContent = chip ? chip.name : heading;
      crumbSub.textContent = chip ? ' / ' + chip.name : '';
      document.title = chip ? chip.name + ' · ' + parent.name + ' — ' + suffix : pageTitle;
    }
  }

  function drawCounts(passing) {
    if (!chips) return;
    var counts = {};
    passing.forEach(function (item) { counts[item.key] = (counts[item.key] || 0) + 1; });
    chips.querySelectorAll('[data-chip]').forEach(function (chip) {
      var id = chip.getAttribute('data-chip');
      var n = id ? counts[id] || 0 : passing.length;
      chip.querySelector('[data-chip-count]').textContent = n;
      chip.classList.toggle('is-empty', n === 0);
    });
  }

  function drawEmpty(kind, chip) {
    empty.hidden = !kind;
    if (!kind) {
      empty.innerHTML = '';
      return;
    }
    var mark = '✦';
    var headline;
    var text;
    var action = '';
    if (mode === 'wishlist') {
      mark = '♡';
      headline = 'Your wishlist is empty';
      text = 'Tap ♡ on any product to save it here.';
      action = '<a class="btn btn-red" href="/new-arrivals">See new arrivals</a>';
    } else if (kind === 'none') {
      var name = chip ? chip.name : (parent ? parent.name : '');
      var whatsapp = Store.whatsappUrl('Hi, I visited your website and would like to know which ' + (name ? name + ' ' : '') + 'styles you have in store.');
      headline = 'New pieces are on their way';
      text = 'We are adding ' + (name ? name + ' ' : '') + 'styles to the website. Ask us on WhatsApp what is in store right now.';
      action = whatsapp ? '<a class="btn btn-wa" href="' + esc(whatsapp) + '" target="_blank" rel="noopener">Ask on WhatsApp</a>' : '';
    } else {
      headline = mode === 'search' && filters.q ? 'Nothing matches “' + filters.q + '”' : 'No products match these filters';
      text = mode === 'search' ? 'Try another word, or look through the new arrivals.' : 'Try a wider price range, or fewer words in the search.';
      action = '<button type="button" class="btn btn-line" data-clear>Clear filters</button>';
    }
    empty.innerHTML = '<i>' + mark + '</i><h3>' + esc(headline) + '</h3><p>' + esc(text) + '</p>' + action;
  }

  function drawPager(pages) {
    pager.hidden = pages < 2;
    if (pages < 2) {
      pager.innerHTML = '';
      return;
    }
    var current = filters.page;
    // First, last and the pages around the current one; gaps become an ellipsis.
    var numbers = [1, current - 1, current, current + 1, pages].filter(function (n, i, all) {
      return n >= 1 && n <= pages && all.indexOf(n) === i;
    }).sort(function (a, b) { return a - b; });
    var html = '<button type="button" data-page="' + (current - 1) + '" aria-label="Previous page"' + (current === 1 ? ' disabled' : '') + '>‹</button>';
    numbers.forEach(function (n, i) {
      if (i && n - numbers[i - 1] > 1) html += '<span class="gap">…</span>';
      html += '<button type="button" data-page="' + n + '"' + (n === current ? ' class="on" aria-current="page"' : '') + '>' + n + '</button>';
    });
    html += '<button type="button" data-page="' + (current + 1) + '" aria-label="Next page"' + (current === pages ? ' disabled' : '') + '>›</button>';
    pager.innerHTML = html;
  }

  var ORDER = {
    pop: function (a, b) { return (b.product.is_featured ? 1 : 0) - (a.product.is_featured ? 1 : 0) || a.order - b.order; },
    'new': function (a, b) { return b.product.id - a.product.id; },
    low: function (a, b) { return a.product.price - b.product.price; },
    high: function (a, b) { return b.product.price - a.product.price; }
  };

  function update(first) {
    var chip = currentChip();
    markChip(chip);
    if (!items) return;

    var words = filters.q.toLowerCase().split(/\s+/).filter(Boolean);
    var passing = items.filter(function (item) {
      var price = item.product.price;
      return (filters.min === null || price >= filters.min) &&
        (filters.max === null || price <= filters.max) &&
        (!filters.inStock || item.product.in_stock !== false) &&
        words.every(function (word) { return item.search.indexOf(word.replace(/s$/, '')) !== -1; }) &&
        (mode !== 'wishlist' || Store.wishlist.has(item.product.id));
    }).sort(ORDER[filters.sort]);
    var matches = passing.filter(function (item) { return !chip || item.key === String(chip.id); });
    drawCounts(passing);

    var perPage = pager ? filters.perPage : (matches.length || 1);
    var pages = Math.max(1, Math.ceil(matches.length / perPage));
    filters.page = Math.min(filters.page, pages);
    var start = (filters.page - 1) * perPage;
    var html = matches.slice(start, start + perPage).map(function (item) { return Store.card(item.product); }).join('');
    if (first) Store.swap(grid, html); else grid.innerHTML = html;

    count.textContent = matches.length + (matches.length === 1 ? ' item' : ' items');
    // Nothing at all in this (sub)category reads differently from filters that leave nothing.
    var filtering = filters.q || filters.min !== null || filters.max !== null || filters.inStock;
    drawEmpty(matches.length ? null : (filtering && items.length ? 'filtered' : 'none'), chip);
    if (pager) drawPager(pages);
  }

  /* ---------- loading ---------- */

  var wished = mode === 'wishlist' ? Store.wishlist.ids().slice(0, 48) : [];

  // In the old store's order: oldest first, except the new arrivals.
  function source() {
    if (mode === 'category') return Store.allProducts('sort=oldest&category=' + encodeURIComponent(parent.slug));
    if (mode === 'new') return Store.get('/products?sort=latest&per_page=' + limit).then(function (json) { return json.data || []; });
    if (mode === 'search') return Store.allProducts('sort=oldest');
    return Store.get('/products?per_page=48&sort=oldest&ids=' + wished.join(',')).then(function (json) { return json.data || []; });
  }

  function load() {
    source().then(function (products) {
      items = products.map(function (product, order) {
        var place = Store.placement(product);
        var key = mode === 'category' ? place.sub : place.top;
        return {
          product: product,
          order: order,
          key: key ? String(key.id) : '',
          search: [product.name, product.sku, place.top && place.top.name, place.sub && place.sub.name].filter(Boolean).join(' ').toLowerCase()
        };
      });
      update(true);
    }, function () {
      count.textContent = '';
      Store.failure(grid, 'These products could not be loaded.', function () {
        grid.innerHTML = Store.skeletons(shown);
        load();
      }, true);
    });
  }

  var counted = countPath ? Store.data(countPath) : null;
  var total = counted && counted.meta ? counted.meta.total : null;
  var expected = mode === 'wishlist' ? wished.length : (total === null ? 8 : (mode === 'new' ? Math.min(total, limit) : total));
  var shown = Math.min(expected, pager ? filters.perPage : 48);

  if (!expected) {
    items = [];
    update(true);
  } else {
    grid.innerHTML = Store.skeletons(shown);
    count.innerHTML = skeletonCount;
    markChip(currentChip());
    load();
  }

  /* ---------- events ---------- */

  function scrollToGrid() {
    grid.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' });
  }

  function setSide(open) {
    side.classList.toggle('is-open', open);
    var toggle = part('filter-toggle');
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var params = new URLSearchParams();
      new FormData(form).forEach(function (value, key) {
        if (String(value).trim() !== '') params.append(key, value);
      });
      history.replaceState(null, '', location.pathname + '?' + params.toString() + location.hash);
      filters = readFilters();
      filters.page = 1;
      writeFilters();
      fillForm();
      update();
      if (side.classList.contains('is-open')) setSide(false);
      scrollToGrid();
    });
    part('filter-toggle').addEventListener('click', function () { setSide(!side.classList.contains('is-open')); });
  }

  empty.addEventListener('click', function (event) {
    if (!event.target.closest('[data-clear]')) return;
    filters = { q: '', min: null, max: null, inStock: false, sort: filters.sort, perPage: filters.perPage, page: 1 };
    writeFilters();
    fillForm();
    update();
  });

  if (pager) {
    pager.addEventListener('click', function (event) {
      var button = event.target.closest('button[data-page]');
      if (!button || button.disabled) return;
      filters.page = +button.getAttribute('data-page');
      writeFilters();
      update();
      scrollToGrid();
    });
  }

  // The chip bar scrolls sideways when it has more chips than fit.
  function revealActiveChip() {
    var chip = chips && chips.querySelector('.chip.on');
    if (chip && chips.scrollWidth > chips.clientWidth) chips.scrollLeft = chip.offsetLeft - (chips.clientWidth - chip.offsetWidth) / 2;
  }

  window.addEventListener('hashchange', function () {
    filters.page = 1;
    writeFilters();
    update();
    window.scrollTo(0, 0);
    revealActiveChip();
  });
  revealActiveChip();

  if (mode === 'wishlist') Store.on('wishlist', function () { update(); });
});
