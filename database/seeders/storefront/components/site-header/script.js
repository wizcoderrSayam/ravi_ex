/*
 * The old header: the top bar, the mega menu and the phone menu, both from the menu in `props.menu`
 * (a menu item's children become its dropdown), the search panel and the wishlist and cart badges.
 * The top bar's details come from CMS → Settings (data-setting); the cart button exists only while
 * the cart does (data-requires="cart").
 */
var root = this;
props = props || {};

Store.ready(function () {
  Store.bind(root);
  var esc = Store.esc;
  var items = Store.menu(props.menu || 'header');
  var here = location.pathname.replace(/\/+$/, '') || '/';

  function pathOf(url) {
    try { return new URL(url, location.origin).pathname.replace(/\/+$/, '') || '/'; } catch (e) { return url; }
  }

  function link(item, className) {
    return '<a href="' + esc(item.url) + '"' + (className ? ' class="' + className + '"' : '') +
      (item.target === '_blank' ? ' target="_blank" rel="noopener"' : '') + '>' + esc(item.label) + '</a>';
  }

  root.querySelector('[data-role="mega"]').innerHTML = items.map(function (item) {
    var active = item.url.indexOf('#') === -1 && pathOf(item.url) === here ? 'active' : '';
    var drop = item.children.length
      ? '<div class="drop"><h4>' + esc(item.label) + '</h4>' + item.children.map(function (child) { return link(child); }).join('') +
        link({ url: item.url, label: 'Shop all ' + item.label + ' →' }, 'all') + '</div>'
      : '';
    return '<li>' + link(item, active) + drop + '</li>';
  }).join('');

  // On phones the items with children open like the old menu; the rest go under "More" with the wishlist.
  var groups = items.filter(function (item) { return item.children.length; });
  var singles = items.filter(function (item) { return !item.children.length; });
  root.querySelector('[data-role="mobile"]').innerHTML = groups.map(function (item) {
    return '<details><summary>' + esc(item.label) + '</summary>' + link({ url: item.url, label: 'All ' + item.label }) +
      item.children.map(function (child) { return link(child); }).join('') + '</details>';
  }).join('') + '<details><summary>More</summary>' + singles.map(function (item) { return link(item); }).join('') +
    link({ url: '/wishlist', label: 'Wishlist' }) + '</details>';

  var mnav = root.querySelector('#mnav');
  mnav.addEventListener('click', function (event) {
    // A link may only change the #hash, so the menu closes itself.
    if (event.target.closest('a[href]') && window.bootstrap) bootstrap.Offcanvas.getOrCreateInstance(mnav).hide();
  });

  var panel = root.querySelector('#searchPanel');
  var toggle = root.querySelector('[data-role="search-toggle"]');
  var input = root.querySelector('#q');
  function setSearch(open) {
    panel.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) input.focus();
  }
  toggle.addEventListener('click', function () { setSearch(!panel.classList.contains('is-open')); });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && panel.classList.contains('is-open')) {
      setSearch(false);
      toggle.focus();
    }
  });
  root.querySelector('#search').addEventListener('submit', function (event) {
    if (!input.value.trim()) event.preventDefault();
  });
  if (here === '/search') input.value = new URLSearchParams(location.search).get('q') || '';

  var cartButton = root.querySelector('[data-role="cart-toggle"]');
  var cartCount = root.querySelector('#ccount');
  if (cartButton && Store.flags.cart) {
    cartButton.setAttribute('aria-label', 'Open ' + String(Store.flags.cart_noun).toLowerCase());
    cartButton.addEventListener('click', Store.openCart);
    Store.on('cart', function (cart) {
      cartCount.textContent = cart.count;
      cartCount.hidden = !cart.count;
    });
  }

  var wishCount = root.querySelector('#wcount');
  Store.on('wishlist', function (ids) {
    wishCount.textContent = ids.length;
    wishCount.hidden = !ids.length;
  });
});
