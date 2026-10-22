/*
 * The old product page (shop/product) for /product/{slug}: the product comes from
 * /api/products/{slug or id} while a skeleton of the page holds its place. Photos with thumbnails,
 * the size and quantity pickers, Add to cart while the cart is on, "… on WhatsApp" with the
 * order message the old store used, the product's details and "You may also like" from its
 * subcategory. Prices, offers and stock follow the store's flags.
 */
var root = this;
props = props || {};
Store.need('/categories');

Store.ready(function () {
  Store.bind(root);
  var esc = Store.esc;
  var f = Store.flags;
  var detail = root.querySelector('[data-role="detail"]');
  var related = root.querySelector('[data-role="related"]');
  var relatedGrid = root.querySelector('[data-role="related-grid"]');
  var skeleton = detail.innerHTML;
  var suffix = props.titleSuffix || 'Ravi Exclusive | Amritsar';
  var relatedCount = Math.max(0, Math.min(12, parseInt(props.related, 10) || 4));
  var params = window.CMS_PARAMS || {};
  var key = params.slug || params.id || decodeURIComponent((location.pathname.match(/\/product\/([^/]+)/) || [])[1] || '');
  var buyLabel = f.orders ? 'Buy now on WhatsApp' : (f.enquiry ? 'Enquire on WhatsApp' : 'Ask on WhatsApp');

  if (relatedCount) relatedGrid.innerHTML = Store.skeletons(relatedCount); else related.remove();

  function description(text) {
    if (!text || !String(text).trim()) return '';
    return '<div class="pd-desc"><h2>Product details</h2>' + String(text).trim().split(/(?:\r?\n){2,}/).map(function (paragraph) {
      return '<p>' + esc(paragraph).replace(/\r?\n/g, '<br>') + '</p>';
    }).join('') + '</div>';
  }

  function setMeta(text) {
    var tag = document.querySelector('meta[name="description"]');
    if (!tag) {
      tag = document.createElement('meta');
      tag.name = 'description';
      document.head.appendChild(tag);
    }
    tag.content = text;
  }

  function missing() {
    document.title = 'Product not found — ' + suffix;
    if (related.parentNode) related.remove();
    Store.swap(detail, '<nav class="crumb" aria-label="Breadcrumb"><a href="/">Home</a> / Not found</nav>' +
      '<div class="pd-missing"><div class="empty-state"><i>✦</i><h3>This piece is no longer online</h3>' +
      '<p>It may have sold out or moved. Have a look at what has just come in.</p><a class="btn btn-red" href="/new-arrivals">See new arrivals</a></div></div>');
  }

  function render(product) {
    var place = Store.placement(product);
    var top = place.top;
    var sub = place.sub;
    var images = product.images && product.images.length ? product.images : (product.image ? [product.image] : []);
    var color = Store.tint(product);
    var off = f.pricing ? Store.discount(product) : 0;
    var variants = product.variants || [];
    var options = variants.filter(function (variant) { return variant.label && variant.label !== 'Default'; });
    var selected = variants.filter(function (variant) { return variant.id === product.default_variant_id; })[0] || variants[0] || null;
    var soldOut = f.stock && product.in_stock === false;
    var whatsapp = !!Store.whatsappUrl('');

    document.title = product.name + ' — ' + suffix;
    setMeta(product.short_description || String(product.description || '').replace(/\s+/g, ' ').trim().slice(0, 160));

    var crumb = '<a href="/">Home</a>' + (top
      ? ' / <a href="' + esc(Store.categoryUrl(top)) + '">' + esc(top.name) + '</a>' + (sub ? ' / <a href="' + esc(Store.categoryUrl(top, sub)) + '">' + esc(sub.name) + '</a>' : '')
      : '') + ' / ' + esc(product.name);

    var html = '<nav class="crumb" aria-label="Breadcrumb">' + crumb + '</nav>' +
      '<div class="row g-4 g-lg-5 pd" id="pd">' +
      '<div class="col-md-6"><div class="pd-photo' + (images.length ? '' : ' no-photo') + '" style="background:linear-gradient(160deg,' + color + '22,' + color + '44);color:' + color + '">' +
      (images.length ? '<img class="ph" id="pdImg" src="' + esc(images[0]) + '" alt="' + esc(product.name) + '" fetchpriority="high" onload="this.classList.add(\'is-loaded\')" onerror="this.parentNode.classList.add(\'no-photo\');this.remove()">' : '') +
      Store.fallbackArt + '</div>' +
      (images.length > 1 ? '<div class="pd-thumbs" id="pdThumbs">' + images.map(function (src, i) {
        return '<button type="button"' + (i === 0 ? ' class="on"' : '') + ' data-photo="' + esc(src) + '" aria-label="Show photo ' + (i + 1) + '"><img src="' + esc(src) + '" alt="" loading="lazy" onload="this.classList.add(\'is-loaded\')"></button>';
      }).join('') + '</div>' : '') +
      '</div>' +
      '<div class="col-md-6"><div class="pd-info">' +
      '<small class="eyebrow">' + esc(top ? top.name + (sub ? ' · ' + sub.name : '') : '') + '</small>' +
      '<h1>' + esc(product.name) + '</h1>' +
      (f.pricing && product.price != null
        ? '<div class="price"><b>' + esc(Store.money(product.price)) + '</b><span class="price-from">onwards</span>' +
          (off > 0 ? '<s>' + esc(Store.moneyLabel(product.compare_price_label)) + '</s><em>' + off + '% off</em>' : '') + '</div>' +
          '<p class="tax-note">Inclusive of all taxes</p>'
        : '') +
      (soldOut ? '<p class="pd-soldout">Sold out right now. Message us on WhatsApp and we\'ll tell you when it\'s back.</p>' : '') +
      '<div class="opt"' + (options.length ? '' : ' hidden') + '><h5>Size</h5><div class="sizes" id="pdSizes">' + options.map(function (variant) {
        return '<button type="button" data-variant="' + variant.id + '"' + (selected && variant.id === selected.id ? ' class="on"' : '') + '>' + esc(variant.label) + '</button>';
      }).join('') + '</div><div class="err" id="pdSizeErr">Please select a size.</div></div>' +
      '<div class="opt"><h5>Quantity</h5><div class="qty"><button type="button" data-qty="-1" aria-label="Decrease">−</button><span id="pdQty">1</span><button type="button" data-qty="1" aria-label="Increase">+</button></div></div>' +
      '<div class="mactions">' +
      (f.cart ? '<button type="button" class="btn btn-red btn-block" id="pdAdd">' + esc(f.add_label) + '</button>' : '') +
      (whatsapp ? '<button type="button" class="btn btn-wa btn-block" id="pdBuy">' + esc(buyLabel) + '</button>' : '') +
      '</div>' +
      '<div class="mdetails"><div><b>Colour:</b> As shown (more shades in store)</div>' +
      (product.sku ? '<div><b>Product code:</b> ' + esc(product.sku) + '</div>' : '') +
      '<div data-requires-setting="phone">Need help with size or customisation? Call <a data-setting-href="tel:{phone}" data-setting="phone_display" class="text-red"></a></div></div>' +
      description(product.description) +
      '</div></div></div>';

    Store.swap(detail, html, function () {
      Store.bind(detail);
      wire(product, options, selected);
    });

    if (relatedCount) loadRelated(product, top, sub);
  }

  function wire(product, options, selected) {
    var quantity = 1;
    var sizes = detail.querySelector('#pdSizes');
    var error = detail.querySelector('#pdSizeErr');
    var quantityOut = detail.querySelector('#pdQty');
    var add = detail.querySelector('#pdAdd');
    var buy = detail.querySelector('#pdBuy');
    var image = detail.querySelector('#pdImg');
    var thumbs = detail.querySelector('#pdThumbs');
    // Stock only limits real orders; an enquiry can ask about anything.
    function most() {
      var stock = selected && selected.stock != null ? selected.stock : product.stock;
      return f.stock && f.orders && stock != null ? Math.max(1, Math.min(10, stock)) : 10;
    }
    function syncAdd() {
      if (!add) return;
      var gone = f.stock && f.orders && (selected ? selected.in_stock === false : product.in_stock === false);
      add.disabled = gone;
      add.textContent = gone ? 'Sold out' : f.add_label;
    }
    function ready() {
      if (options.length && !selected) {
        error.style.display = 'block';
        return false;
      }
      return true;
    }

    sizes.addEventListener('click', function (event) {
      var button = event.target.closest('button[data-variant]');
      if (!button) return;
      var id = parseInt(button.getAttribute('data-variant'), 10);
      selected = options.filter(function (variant) { return variant.id === id; })[0] || null;
      sizes.querySelectorAll('button').forEach(function (other) { other.classList.toggle('on', other === button); });
      error.style.display = 'none';
      quantity = Math.min(quantity, most());
      quantityOut.textContent = quantity;
      syncAdd();
    });

    detail.querySelectorAll('[data-qty]').forEach(function (button) {
      button.addEventListener('click', function () {
        quantity = Math.max(1, Math.min(most(), quantity + parseInt(button.getAttribute('data-qty'), 10)));
        quantityOut.textContent = quantity;
      });
    });

    if (thumbs && image) {
      thumbs.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-photo]');
        if (!button) return;
        image.classList.remove('is-loaded');
        image.src = button.getAttribute('data-photo');
        thumbs.querySelectorAll('button').forEach(function (other) { other.classList.toggle('on', other === button); });
      });
    }

    if (add) {
      syncAdd();
      add.addEventListener('click', function () {
        if (!ready()) return;
        add.disabled = true;
        Store.add(product.id, selected ? selected.id : null, quantity).catch(function (failure) {
          Store.toast(failure.message);
        }).then(syncAdd);
      });
    }

    if (buy) {
      buy.addEventListener('click', function () {
        if (!ready()) return;
        var size = options.length && selected ? 'Size: ' + selected.label + ' · ' : '';
        var unit = selected && selected.price != null ? selected.price : product.price;
        var message = 'Hello ' + (f.name || '') + ', ' + (f.orders ? "I'd like to order:" : "I'd like to know more about:") + '\n\n' +
          product.name + (product.sku ? ' (' + product.sku + ')' : '') + '\n' + size + 'Qty: ' + quantity +
          (f.pricing && unit != null ? '\nPrice: ' + Store.money(unit * quantity) : '') +
          '\n' + location.origin + Store.productUrl(product) + '\n\nPlease confirm availability.';
        window.open(Store.whatsappUrl(message), '_blank', 'noopener');
      });
    }
  }

  function loadRelated(product, top, sub) {
    var category = sub || top;
    if (!category) {
      related.remove();
      return;
    }
    root.querySelector('[data-role="related-eyebrow"]').textContent = 'More ' + category.name;
    var link = root.querySelector('[data-role="related-link"]');
    link.href = Store.categoryUrl(top, sub);
    link.hidden = false;

    Store.get('/products?per_page=' + (relatedCount + 1) + '&sort=oldest&category=' + encodeURIComponent(category.slug)).then(function (json) {
      var others = (json.data || []).filter(function (other) { return other.id !== product.id; }).slice(0, relatedCount);
      if (!others.length) {
        related.remove();
        return;
      }
      Store.swap(relatedGrid, others.map(Store.card).join(''));
    }, function () {
      Store.failure(relatedGrid, 'These products could not be loaded.', function () { loadRelated(product, top, sub); }, true);
    });
  }

  function load() {
    Store.get('/products/' + encodeURIComponent(key)).then(function (json) {
      render(json.data);
    }, function (error) {
      if (error.status === 404) {
        missing();
        return;
      }
      Store.failure(detail, 'This product could not be loaded.', function () {
        detail.innerHTML = skeleton;
        Store.bind(detail);
        load();
      });
    });
  }

  if (key) load(); else missing();
});
