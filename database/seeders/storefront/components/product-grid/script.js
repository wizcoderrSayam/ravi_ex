var root = this;
props = props || {};

function init() {
  var Store = window.Store;
  var esc = Store.esc;
  var grid = root.querySelector('[data-role="grid"]');
  var empty = root.querySelector('[data-role="empty"]');
  var pager = root.querySelector('[data-role="pager"]');
  var toolbar = root.querySelector('[data-role="toolbar"]');
  var searchInput = root.querySelector('[data-role="search"]');
  var categorySelect = root.querySelector('[data-role="category"]');
  var sortSelect = root.querySelector('[data-role="sort"]');
  var url = new URLSearchParams(window.location.search);
  var withFilters = !!props.filters;
  var state = {
    search: withFilters ? (url.get('q') || '') : (props.search || ''),
    category: withFilters ? (url.get('category') || '') : (props.category || ''),
    sort: withFilters ? (url.get('sort') || props.sort || 'latest') : (props.sort || 'latest'),
    page: 1
  };
  var button = 'w-full rounded-md px-3 py-2 text-center text-sm font-semibold ';

  function card(product) {
    var f = Store.flags;
    var price = '';
    if (f.pricing && product.price_label) {
      price = '<div class="mt-1 flex items-baseline gap-2"><span class="font-semibold text-slate-900">' +
        (product.has_variants ? 'From ' : '') + esc(product.price_label) + '</span>' +
        (product.compare_price_label ? '<span class="text-sm text-slate-400 line-through">' + esc(product.compare_price_label) + '</span>' : '') + '</div>';
    }
    var badge = f.pricing && product.offer_label
      ? '<span class="absolute left-2 top-2 rounded bg-rose-600 px-2 py-0.5 text-xs font-semibold text-white">' + esc(product.offer_label) + '</span>' : '';
    var stock = '';
    if (f.stock) {
      stock = product.in_stock
        ? '<p class="mt-1 text-xs text-emerald-600">In stock' + (product.stock <= 5 ? ' &middot; only ' + product.stock + ' left' : '') + '</p>'
        : '<p class="mt-1 text-xs text-rose-600">Out of stock</p>';
    }
    var action = '';
    if (f.cart) {
      if (product.has_variants) {
        action = '<a href="' + esc(product.url) + '" class="block ' + button + 'border border-slate-300 text-slate-700 hover:bg-slate-50">Choose options</a>';
      } else if (f.stock && f.orders && !product.in_stock) {
        action = '<button type="button" disabled class="' + button + 'cursor-not-allowed bg-slate-200 text-slate-500">Out of stock</button>';
      } else {
        action = '<button type="button" data-add="' + product.id + '" data-variant="' + (product.default_variant_id || '') + '" class="' + button + 'bg-teal-600 text-white hover:bg-teal-700">' + esc(f.add_label) + '</button>';
      }
    }
    var image = product.image
      ? '<img src="' + esc(product.image) + '" alt="' + esc(product.name) + '" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105">'
      : '<div class="flex h-full items-center justify-center text-slate-300"><i class="fa-regular fa-image text-4xl"></i></div>';
    var category = product.categories.length ? '<p class="text-xs uppercase tracking-wide text-slate-400">' + esc(product.categories[0].name) + '</p>' : '';

    return '<article class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white">' +
      '<a href="' + esc(product.url) + '" class="relative block aspect-square overflow-hidden bg-slate-100">' + image + badge + '</a>' +
      '<div class="flex flex-1 flex-col p-3">' + category +
      '<a href="' + esc(product.url) + '" class="mt-0.5 line-clamp-2 text-sm font-medium text-slate-900 hover:text-teal-700">' + esc(product.name) + '</a>' +
      price + stock + '<div class="mt-auto pt-3">' + action + '</div></div></article>';
  }

  function query() {
    var params = new URLSearchParams();
    if (props.ids) params.set('ids', props.ids);
    if (state.search) params.set('search', state.search);
    if (state.category) params.set('category', state.category);
    if (props.featured) params.set('featured', '1');
    params.set('sort', state.sort);
    params.set('per_page', props.limit || 8);
    params.set('page', state.page);
    return params.toString();
  }

  function load() {
    fetch('/api/products?' + query(), { headers: { Accept: 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (body) {
        var products = body.data || [];
        var last = body.meta ? body.meta.last_page : 1;

        grid.innerHTML = products.map(card).join('');
        empty.classList.toggle('hidden', products.length > 0);
        pager.classList.toggle('hidden', !withFilters || last <= 1);
        pager.classList.toggle('flex', withFilters && last > 1);
        root.querySelector('[data-role="page"]').textContent = 'Page ' + state.page + ' of ' + last;
        root.querySelector('[data-role="prev"]').disabled = state.page <= 1;
        root.querySelector('[data-role="next"]').disabled = state.page >= last;
      })
      .catch(function () {
        empty.textContent = 'Unable to load products right now.';
        empty.classList.remove('hidden');
      });
  }

  function reload() {
    if (withFilters && window.history.replaceState) {
      var params = new URLSearchParams();
      if (state.search) params.set('q', state.search);
      if (state.category) params.set('category', state.category);
      if (state.sort !== 'latest') params.set('sort', state.sort);
      window.history.replaceState(null, '', window.location.pathname + (params.toString() ? '?' + params : ''));
    }
    load();
  }

  grid.addEventListener('click', function (event) {
    var target = event.target.closest('[data-add]');
    if (target) Store.add(parseInt(target.getAttribute('data-add'), 10), parseInt(target.getAttribute('data-variant'), 10) || null, 1);
  });

  if (withFilters) {
    toolbar.classList.remove('hidden');
    toolbar.classList.add('flex');
    searchInput.value = state.search;
    sortSelect.value = state.sort;

    var timer;
    searchInput.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { state.search = searchInput.value.trim(); state.page = 1; reload(); }, 300);
    });
    categorySelect.addEventListener('change', function () { state.category = categorySelect.value; state.page = 1; reload(); });
    sortSelect.addEventListener('change', function () { state.sort = sortSelect.value; state.page = 1; reload(); });
    root.querySelector('[data-role="prev"]').addEventListener('click', function () { state.page -= 1; load(); });
    root.querySelector('[data-role="next"]').addEventListener('click', function () { state.page += 1; load(); });

    fetch('/api/categories', { headers: { Accept: 'application/json' } })
      .then(function (res) { return res.json(); })
      .then(function (body) {
        function options(nodes, depth) {
          return nodes.map(function (node) {
            return '<option value="' + esc(node.slug) + '">' + new Array(depth + 1).join('  ') + esc(node.name) + '</option>' + options(node.children, depth + 1);
          }).join('');
        }
        categorySelect.innerHTML = '<option value="">All categories</option>' + options(body.data || [], 0);
        categorySelect.value = state.category;
      })
      .catch(function () {});
  }

  load();
}

(window.storeQueue = window.storeQueue || []).push(init);
