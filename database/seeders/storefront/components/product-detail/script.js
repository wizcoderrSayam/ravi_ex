var root = this;

function init() {
  var Store = window.Store;
  var esc = Store.esc;
  var view = root.querySelector('[data-role="view"]');
  var id = window.CMS_PARAMS && window.CMS_PARAMS.id;

  if (!id) {
    view.innerHTML = '<p class="text-slate-500">The product opens here, at /product/{id}.</p>';
    return;
  }

  function notFound() {
    view.innerHTML = '<div class="py-16 text-center"><h1 class="text-2xl font-semibold">Product not found</h1>' +
      '<a href="/shop" class="mt-4 inline-block text-teal-700 hover:underline">Back to the shop</a></div>';
  }

  function render(product) {
    var f = Store.flags;
    var variants = product.variants || [];
    var selected = variants.filter(function (v) { return v.id === product.default_variant_id; })[0] || variants[0] || null;
    var quantity = 1;
    var images = product.images.length ? product.images : (product.image ? [product.image] : []);
    var shown = images[0] || null;

    document.title = product.name + ' · ' + (f.name || '');

    function current() { return selected || product; }

    function available() {
      return f.stock ? current().in_stock !== false : true;
    }

    function paint() {
      var item = current();
      var main = shown;
      var thumbs = images.length > 1 ? '<div class="mt-3 flex gap-2">' + images.map(function (src) {
        return '<button type="button" data-thumb="' + esc(src) + '" class="h-16 w-16 overflow-hidden rounded-md border ' + (src === main ? 'border-teal-600' : 'border-slate-200') + '"><img src="' + esc(src) + '" alt="" class="h-full w-full object-cover"></button>';
      }).join('') + '</div>' : '';
      var gallery = '<div><div class="aspect-square overflow-hidden rounded-lg border border-slate-200 bg-slate-100">' +
        (main ? '<img src="' + esc(main) + '" alt="' + esc(product.name) + '" class="h-full w-full object-cover">' : '<div class="flex h-full items-center justify-center text-slate-300"><i class="fa-regular fa-image text-6xl"></i></div>') +
        '</div>' + thumbs + '</div>';

      var categories = product.categories.map(function (c) {
        return '<a href="/shop?category=' + esc(c.slug) + '" class="hover:text-teal-700">' + esc(c.name) + '</a>';
      }).join(' &middot; ');
      var price = f.pricing && item.price_label
        ? '<div class="mt-3 flex items-baseline gap-3"><span class="text-2xl font-bold">' + esc(item.price_label) + '</span>' +
          (item.compare_price_label ? '<span class="text-slate-400 line-through">' + esc(item.compare_price_label) + '</span>' : '') +
          (product.offer_label ? '<span class="rounded bg-rose-600 px-2 py-0.5 text-xs font-semibold text-white">' + esc(product.offer_label) + '</span>' : '') + '</div>' : '';
      var stock = '';
      if (f.stock && item.in_stock !== undefined) {
        stock = item.in_stock
          ? '<p class="mt-2 text-sm text-emerald-600"><i class="fa-solid fa-circle-check"></i> In stock (' + item.stock + ' available)</p>'
          : '<p class="mt-2 text-sm text-rose-600"><i class="fa-solid fa-circle-xmark"></i> Out of stock</p>';
      }
      var options = product.has_variants ? '<div class="mt-5"><p class="mb-2 text-sm font-medium">Options</p><div class="flex flex-wrap gap-2">' + variants.map(function (v) {
        var active = selected && v.id === selected.id;
        return '<button type="button" data-variant="' + v.id + '" class="rounded-md border px-3 py-1.5 text-sm ' + (active ? 'border-teal-600 bg-teal-50 text-teal-800' : 'border-slate-300 hover:bg-slate-50') + '">' + esc(v.label) + '</button>';
      }).join('') + '</div></div>' : '';

      var action = '';
      if (f.cart) {
        action = '<div class="mt-6 flex items-center gap-3">' +
          '<div class="inline-flex items-center rounded-md border border-slate-300">' +
          '<button type="button" data-qty="-1" class="px-3 py-2 hover:bg-slate-100" aria-label="Less">&minus;</button>' +
          '<span class="min-w-8 text-center">' + quantity + '</span>' +
          '<button type="button" data-qty="1" class="px-3 py-2 hover:bg-slate-100" aria-label="More">+</button></div>' +
          '<button type="button" data-add ' + (f.orders && !available() ? 'disabled' : '') + ' class="flex-1 rounded-md bg-teal-600 px-5 py-2.5 font-semibold text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:bg-slate-300">' +
          (f.orders && !available() ? 'Out of stock' : esc(f.add_label)) + '</button></div>';
      } else {
        action = '<p class="mt-6 text-sm text-slate-500">Want this? <a href="/contact-us" class="text-teal-700 hover:underline">Get in touch</a>.</p>';
      }

      view.innerHTML = '<div class="grid gap-10 md:grid-cols-2">' + gallery + '<div>' +
        '<p class="text-xs uppercase tracking-wide text-slate-400">' + categories + '</p>' +
        '<h1 class="mt-1 text-3xl font-bold">' + esc(product.name) + '</h1>' +
        (product.brand ? '<p class="text-sm text-slate-500">by ' + esc(product.brand) + '</p>' : '') +
        price + stock +
        (product.short_description ? '<p class="mt-4 text-slate-600">' + esc(product.short_description) + '</p>' : '') +
        options + action +
        (product.description ? '<div class="mt-8 border-t border-slate-200 pt-6"><h2 class="mb-2 font-semibold">Details</h2><p class="whitespace-pre-line text-slate-600">' + esc(product.description) + '</p></div>' : '') +
        '</div></div>';
    }

    view.addEventListener('click', function (event) {
      var thumb = event.target.closest('[data-thumb]');
      var variant = event.target.closest('[data-variant]');
      var step = event.target.closest('[data-qty]');

      if (thumb) {
        shown = thumb.getAttribute('data-thumb');
        paint();
      } else if (variant) {
        selected = variants.filter(function (v) { return v.id === parseInt(variant.getAttribute('data-variant'), 10); })[0];
        shown = selected.image || shown;
        paint();
      } else if (step) {
        quantity = Math.max(1, quantity + parseInt(step.getAttribute('data-qty'), 10));
        paint();
      } else if (event.target.closest('[data-add]')) {
        Store.add(product.id, selected ? selected.id : null, quantity);
      }
    });

    paint();
  }

  fetch('/api/products?ids=' + encodeURIComponent(id), { headers: { Accept: 'application/json' } })
    .then(function (res) { return res.json(); })
    .then(function (body) {
      if (body.data && body.data[0]) render(body.data[0]); else notFound();
    })
    .catch(notFound);
}

(window.storeQueue = window.storeQueue || []).push(init);
