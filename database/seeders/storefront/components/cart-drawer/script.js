var root = this;

function init() {
  var Store = window.Store;
  var esc = Store.esc;
  var drawer = root.querySelector('[data-role="drawer"]');
  var items = root.querySelector('[data-role="items"]');
  var footer = root.querySelector('[data-role="footer"]');
  var button = 'block w-full rounded-md px-4 py-2.5 text-center text-sm font-semibold ';

  if (!Store.flags.cart) return;

  root.querySelector('[data-role="title"]').textContent = Store.flags.cart_noun;

  function open() { drawer.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
  function close() { drawer.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }

  Store.on('open', open);
  Store.on('close', close);
  root.querySelector('[data-role="overlay"]').addEventListener('click', close);
  root.querySelector('[data-role="close"]').addEventListener('click', close);
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });

  function line(item) {
    var thumb = item.image
      ? '<img src="' + esc(item.image) + '" alt="" class="h-16 w-16 shrink-0 rounded-md object-cover">'
      : '<div class="h-16 w-16 shrink-0 rounded-md bg-slate-100"></div>';
    var price = Store.flags.pricing && item.line_total_label
      ? '<div class="text-sm font-semibold">' + esc(item.line_total_label) + '</div>' : '';
    var variant = item.variant_label ? '<div class="text-xs text-slate-500">' + esc(item.variant_label) + '</div>' : '';
    var stepper = '<div class="inline-flex items-center rounded border border-slate-300 text-sm">' +
      '<button type="button" data-action="dec" data-id="' + item.id + '" data-qty="' + item.quantity + '" class="px-2 py-0.5 hover:bg-slate-100" aria-label="Less">&minus;</button>' +
      '<span class="min-w-6 text-center">' + item.quantity + '</span>' +
      '<button type="button" data-action="inc" data-id="' + item.id + '" data-qty="' + item.quantity + '" class="px-2 py-0.5 hover:bg-slate-100" aria-label="More">+</button></div>';

    return '<div class="flex gap-3 border-b border-slate-100 py-4">' + thumb +
      '<div class="min-w-0 flex-1"><a href="' + esc(item.url) + '" class="block truncate text-sm font-medium hover:text-teal-700">' + esc(item.name) + '</a>' + variant +
      '<div class="mt-2 flex items-center justify-between">' + stepper + price + '</div></div>' +
      '<button type="button" data-action="remove" data-id="' + item.id + '" class="self-start p-1 text-slate-400 hover:text-rose-600" aria-label="Remove"><i class="fa-solid fa-trash-can"></i></button></div>';
  }

  function paint(cart) {
    if (!cart.items.length) {
      items.innerHTML = '<p class="py-12 text-center text-slate-500">Your ' + esc(String(Store.flags.cart_noun).toLowerCase()) + ' is empty.</p>';
      footer.innerHTML = '<a href="/shop" class="' + button + 'bg-slate-900 text-white hover:bg-slate-700">Continue shopping</a>';
      return;
    }

    items.innerHTML = cart.items.map(line).join('');

    var html = '';
    if (Store.flags.pricing && cart.totals) {
      html += '<div class="flex justify-between text-sm"><span class="text-slate-500">Subtotal</span><span class="font-semibold">' + esc(cart.totals.subtotal_label) + '</span></div>' +
        (Store.flags.orders ? '<p class="text-xs text-slate-500">Shipping and tax are added at checkout.</p>' : '');
    }
    html += '<a href="/checkout" class="' + button + 'bg-teal-600 text-white hover:bg-teal-700">' + esc(Store.flags.checkout_label) + '</a>';
    if (Store.flags.both_checkouts) {
      html += '<a href="/checkout?mode=enquiry" class="' + button + 'border border-slate-300 text-slate-700 hover:bg-slate-50">Send enquiry instead</a>';
    }
    footer.innerHTML = html;
  }

  items.addEventListener('click', function (event) {
    var target = event.target.closest('[data-action]');
    if (!target) return;

    var action = target.getAttribute('data-action');
    var id = target.getAttribute('data-id');
    var quantity = parseInt(target.getAttribute('data-qty'), 10);

    if (action === 'remove' || (action === 'dec' && quantity <= 1)) {
      Store.remove(id);
    } else {
      Store.setQuantity(id, quantity + (action === 'inc' ? 1 : -1));
    }
  });

  Store.on('cart', paint);
  paint(Store.cart);
}

(window.storeQueue = window.storeQueue || []).push(init);
