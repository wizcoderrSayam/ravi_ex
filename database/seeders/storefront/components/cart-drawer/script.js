/*
 * The old bag, on the store's cart (/api/cart): lines with quantity and remove, the subtotal while
 * prices show, and a short form that sends the order, or the enquiry, as a WhatsApp message, the
 * way the old store took orders. It exists only while the cart does (an orders or enquiry flow is on).
 */
var root = this;
props = props || {};

Store.ready(function () {
  Store.bind(root);
  var f = Store.flags;
  if (!f.cart) {
    root.innerHTML = '';
    return;
  }

  var esc = Store.esc;
  var drawer = root.querySelector('#cartDrawer');
  var items = root.querySelector('[data-role="items"]');
  var empty = root.querySelector('[data-role="empty"]');
  var form = root.querySelector('[data-role="form"]');
  var total = root.querySelector('[data-role="total"]');
  var address = root.querySelector('[data-role="address"]');
  var noun = String(f.cart_noun || 'Cart');
  var ordering = !!f.orders;
  // Product codes and links for the message; asked for again whenever the lines change.
  var details = {};

  root.querySelector('[data-role="title"]').textContent = 'Your ' + noun;
  root.querySelector('[data-role="empty-text"]').textContent = 'Your ' + noun.toLowerCase() + ' is empty.';
  root.querySelector('[data-role="submit"]').textContent = f.checkout_label + ' on WhatsApp';
  root.querySelector('[data-role="shop-link"]').setAttribute('href', props.shopUrl || '/women');
  address.placeholder = ordering ? "Delivery address with PIN code (or write 'Store pickup')" : 'Anything you would like to ask: sizes, colours, delivery…';
  address.required = ordering;

  function offcanvas() {
    return window.bootstrap ? bootstrap.Offcanvas.getOrCreateInstance(drawer) : null;
  }

  function line(item) {
    var thumb = item.image ? Store.photo(item.image, '') : '';
    var detail = (item.variant_label ? 'Size: ' + esc(item.variant_label) + (f.pricing ? ' · ' : '') : '') + (f.pricing ? esc(Store.moneyLabel(item.unit_price_label)) : '');
    var atMost = f.stock && f.orders && item.max != null ? item.max : 99;
    return '<div class="citem" data-item="' + item.id + '">' +
      '<div class="cthumb' + (item.image ? '' : ' no-photo') + '" style="background:linear-gradient(160deg,#BFAE8E22,#BFAE8E44)">' + thumb + '</div>' +
      '<div><h4><a href="' + esc(item.url) + '">' + esc(item.name) + '</a></h4>' + (detail ? '<small>' + detail + '</small>' : '<small></small>') +
      '<div class="qty"><button type="button" data-step="-1" aria-label="Decrease"' + (item.quantity <= 1 ? ' disabled' : '') + '>−</button><span>' + item.quantity + '</span>' +
      '<button type="button" data-step="1" aria-label="Increase"' + (item.quantity >= atMost ? ' disabled' : '') + '>+</button></div></div>' +
      '<button type="button" class="rm" data-remove>Remove</button></div>';
  }

  function draw(cart) {
    var none = !cart.items.length;
    empty.hidden = !none;
    form.hidden = none;
    items.innerHTML = cart.items.map(line).join('');
    if (total && cart.totals) total.textContent = Store.moneyLabel(cart.totals.subtotal_label);
    if (!none) loadDetails(cart);
  }

  function loadDetails(cart) {
    var ids = cart.items.map(function (item) { return item.product_id; }).filter(function (id) { return !details[id]; });
    if (!ids.length) return;
    Store.get('/products?per_page=48&ids=' + ids.join(',')).then(function (json) {
      (json.data || []).forEach(function (product) { details[product.id] = product; });
    }, function () {});
  }

  items.addEventListener('click', function (event) {
    var row = event.target.closest('[data-item]');
    if (!row) return;
    var id = parseInt(row.getAttribute('data-item'), 10);
    var item = Store.cart.items.filter(function (other) { return other.id === id; })[0];
    var step = event.target.closest('[data-step]');
    var change;
    if (step && item) change = Store.setQuantity(id, item.quantity + parseInt(step.getAttribute('data-step'), 10));
    else if (event.target.closest('[data-remove]')) change = Store.remove(id);
    if (!change) return;
    row.classList.add('is-busy');
    change.catch(function (error) {
      row.classList.remove('is-busy');
      Store.toast(error.message);
    });
  });

  function linkOf(item) {
    var product = details[item.product_id];
    return location.origin + (product ? Store.productUrl(product) : item.url);
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var name = form.elements.name.value.trim();
    var phone = form.elements.phone.value.trim();
    var note = address.value.trim();
    var missing = [form.elements.name, form.elements.phone].concat(ordering ? [address] : []).filter(function (field) { return !field.value.trim(); });
    [form.elements.name, form.elements.phone, address].forEach(function (field) { field.classList.toggle('is-invalid', missing.indexOf(field) !== -1); });
    if (missing.length) {
      Store.toast(ordering ? 'Please fill in name, mobile and address' : 'Please fill in your name and mobile');
      missing[0].focus();
      return;
    }

    var cart = Store.cart;
    var lines = cart.items.map(function (item, index) {
      var product = details[item.product_id];
      var code = product && product.sku ? ' (' + product.sku + ')' : '';
      var amount = f.pricing ? ' = ' + Store.moneyLabel(item.line_total_label) : '';
      return (index + 1) + '. ' + item.name + code + '\n   ' + (item.variant_label ? 'Size ' + item.variant_label + ' ×' : 'Qty') + ' ' + item.quantity + amount + '\n   ' + linkOf(item);
    }).join('\n');
    var store = f.name || '';
    var message = 'Hello ' + store + ', ' + (ordering ? "I'd like to place an order:" : "I'd like to enquire about:") + '\n\n' + lines +
      (f.pricing && cart.totals ? '\n\nSubtotal: ' + Store.moneyLabel(cart.totals.subtotal_label) : '') +
      '\n\nName: ' + name + '\nMobile: ' + phone + (note ? '\n' + (ordering ? 'Address: ' : 'Message: ') + note : '');

    var url = Store.whatsappUrl(message);
    if (!url) {
      Store.toast('WhatsApp ordering is not set up yet.');
      return;
    }
    window.open(url, '_blank', 'noopener');
  });

  Store.on('cart', draw);
  Store.on('cart:open', function () {
    var panel = offcanvas();
    if (panel) panel.show();
  });
  drawer.addEventListener('show.bs.offcanvas', function () { draw(Store.cart); });
});
