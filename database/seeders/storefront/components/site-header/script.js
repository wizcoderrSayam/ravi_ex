var root = this;
props = props || {};

function init() {
  var Store = window.Store;
  var esc = Store.esc;
  var menu = root.querySelector('[data-role="menu"]');
  var mobileMenu = root.querySelector('[data-role="mobile-menu"]');
  var cartButton = root.querySelector('[data-role="cart"]');
  var count = root.querySelector('[data-role="count"]');

  root.querySelector('[data-role="brand"]').textContent = Store.flags.name || 'Store';

  if (Store.flags.cart) {
    cartButton.hidden = false;
    cartButton.setAttribute('aria-label', 'Open ' + String(Store.flags.cart_noun).toLowerCase());
    cartButton.addEventListener('click', Store.openCart);
  }

  function paintCount(cart) {
    count.textContent = cart.count;
    count.classList.toggle('hidden', !cart.count);
  }
  Store.on('cart', paintCount);
  paintCount(Store.cart);

  root.querySelector('[data-role="toggle"]').addEventListener('click', function () {
    root.querySelector('[data-role="mobile"]').classList.toggle('hidden');
  });

  function anchor(item, classes) {
    var external = item.target === '_blank' ? ' target="_blank" rel="noopener"' : '';
    return '<a href="' + esc(item.url) + '"' + external + ' class="' + classes + '">' + esc(item.label);
  }

  // Sub-menus open on hover or keyboard focus. Each depth has its own named group, so a flyout
  // only opens for its own parent.
  function desktopItem(item, depth) {
    var children = item.children || [];
    var linkClasses = depth
      ? 'flex items-center justify-between gap-3 whitespace-nowrap px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-teal-700'
      : 'flex items-center gap-1 rounded px-3 py-2 text-sm font-medium text-slate-700 hover:text-teal-700';

    if (!children.length) {
      return '<li>' + anchor(item, linkClasses) + '</a></li>';
    }

    var group = 'l' + depth;
    var position = depth ? 'left-full top-0' : 'left-0 top-full';
    var chevron = '<i class="fa-solid ' + (depth ? 'fa-chevron-right' : 'fa-chevron-down') + ' text-[10px]"></i>';

    return '<li class="group/' + group + ' relative">' + anchor(item, linkClasses) + chevron + '</a>' +
      '<ul class="invisible absolute ' + position + ' z-50 min-w-48 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition ' +
      'group-hover/' + group + ':visible group-hover/' + group + ':opacity-100 group-focus-within/' + group + ':visible group-focus-within/' + group + ':opacity-100">' +
      children.map(function (child) { return desktopItem(child, depth + 1); }).join('') + '</ul></li>';
  }

  function mobileItem(item, depth) {
    var children = item.children || [];
    var classes = 'block py-2 text-sm text-slate-700 hover:text-teal-700' + (depth ? '' : ' font-medium');

    return '<li style="padding-left:' + depth * 16 + 'px">' + anchor(item, classes) + '</a>' +
      (children.length ? '<ul>' + children.map(function (child) { return mobileItem(child, depth + 1); }).join('') + '</ul>' : '') + '</li>';
  }

  fetch('/api/cms/menus/' + encodeURIComponent(props.menu || 'primary'), { headers: { Accept: 'application/json' } })
    .then(function (res) { return res.json(); })
    .then(function (body) {
      var items = (body.data && body.data.items) || [];
      menu.innerHTML = items.map(function (item) { return desktopItem(item, 0); }).join('');
      mobileMenu.innerHTML = items.map(function (item) { return mobileItem(item, 0); }).join('');
    })
    .catch(function () {});
}

(window.storeQueue = window.storeQueue || []).push(init);
