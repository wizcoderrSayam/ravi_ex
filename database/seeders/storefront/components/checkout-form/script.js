var root = this;

function init() {
  var Store = window.Store;
  var esc = Store.esc;
  var f = Store.flags;
  var form = root.querySelector('[data-role="form"]');
  var errorBox = root.querySelector('[data-role="error"]');
  var submit = root.querySelector('[data-role="submit"]');
  var requiredForOrder = ['name', 'email', 'phone', 'address', 'city', 'state', 'postal_code'];
  var mode = f.orders ? 'order' : 'enquiry';

  function show(role, visible, display) {
    var el = root.querySelector('[data-role="' + role + '"]');
    el.classList.toggle('hidden', !visible);
    if (display) el.classList.toggle(display, visible);
  }

  function applyMode() {
    var isOrder = mode === 'order';

    root.querySelector('[data-role="heading"]').textContent = isOrder ? 'Checkout' : 'Send enquiry';
    root.querySelector('[data-role="note-label"]').textContent = isOrder ? 'Order notes (optional)' : 'Message (optional)';
    submit.textContent = isOrder ? 'Place order' : 'Send enquiry';
    show('address', isOrder, 'grid');
    root.querySelectorAll('[data-role="address"] [name]').forEach(function (input) { input.disabled = !isOrder; });
    form.elements.phone.required = isOrder;
    paintSummary();
  }

  function paintSummary() {
    var cart = Store.cart;
    var showPrices = mode === 'order' && f.pricing;

    root.querySelector('[data-role="lines"]').innerHTML = cart.items.map(function (item) {
      return '<li class="flex justify-between gap-3 py-2 text-sm"><span>' + esc(item.name) +
        (item.variant_label ? ' <span class="text-slate-400">(' + esc(item.variant_label) + ')</span>' : '') +
        ' <span class="text-slate-500">&times; ' + item.quantity + '</span></span>' +
        (showPrices ? '<span class="font-medium">' + esc(item.line_total_label) + '</span>' : '') + '</li>';
    }).join('');

    root.querySelector('[data-role="totals"]').innerHTML = showPrices && cart.totals
      ? [['Subtotal', cart.totals.subtotal_label], ['Shipping', cart.totals.shipping_label], ['GST (' + cart.totals.tax_percent + '%)', cart.totals.tax_label]].map(function (row) {
        return '<div class="flex justify-between"><dt class="text-slate-500">' + row[0] + '</dt><dd>' + esc(row[1]) + '</dd></div>';
      }).join('') + '<div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd>' + esc(cart.totals.total_label) + '</dd></div>' +
        '<p class="pt-2 text-xs text-slate-500">Cash on delivery.</p>'
      : '';
  }

  function paint() {
    var hasItems = Store.cart.items.length > 0;

    show('unavailable', !f.cart);
    show('empty', f.cart && !hasItems && root.querySelector('[data-role="done"]').classList.contains('hidden'));
    show('main', f.cart && hasItems, 'grid');
    if (f.cart && hasItems) applyMode();
  }

  if (f.both_checkouts) {
    mode = new URLSearchParams(window.location.search).get('mode') === 'enquiry' ? 'enquiry' : 'order';
    show('modes', true, 'grid');
    form.elements.mode.value = mode;
    form.addEventListener('change', function (event) {
      if (event.target.name === 'mode') {
        mode = event.target.value;
        applyMode();
      }
    });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorBox.classList.add('hidden');

    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });
    var note = data.note;
    delete data.note;
    delete data.mode;
    if (mode === 'order') { data.notes = note; } else { data.message = note; }

    var missing = (mode === 'order' ? requiredForOrder : ['name', 'email']).filter(function (key) { return !String(data[key] || '').trim(); });
    if (missing.length) {
      errorBox.textContent = 'Please fill in: ' + missing.join(', ').replace(/_/g, ' ') + '.';
      errorBox.classList.remove('hidden');
      return;
    }

    submit.disabled = true;
    Store.request('POST', mode === 'order' ? '/orders' : '/enquiries', data)
      .then(function (json) {
        var isOrder = mode === 'order';
        root.querySelector('[data-role="done-title"]').textContent = isOrder ? 'Order placed!' : 'Enquiry sent!';
        root.querySelector('[data-role="done-text"]').textContent = isOrder
          ? 'Your order ' + json.data.number + ' is confirmed (cash on delivery' + (json.data.total_label && f.pricing ? ', ' + json.data.total_label : '') + '). We will be in touch shortly.'
          : 'Your enquiry ' + json.data.number + ' has been received. We will reply by email soon.';
        show('done', true);
        show('main', false, 'grid');
        show('empty', false);
        return Store.refreshCart();
      })
      .catch(function (error) {
        errorBox.textContent = error.message;
        errorBox.classList.remove('hidden');
      })
      .then(function () { submit.disabled = false; });
  });

  Store.on('cart', paint);
  paint();
}

(window.storeQueue = window.storeQueue || []).push(init);
