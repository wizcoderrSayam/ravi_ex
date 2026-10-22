/*
 * A row of the old product cards from /api/products. The page waits for how many there are (a
 * one-product request), so the row has exactly that many skeleton cards while the products load,
 * and an empty row is decided before the page shows: with `hideWhenEmpty` its section is left out
 * (the home page's Bestsellers, until products are marked Featured), otherwise it says so.
 */
var root = this;
props = props || {};

var limit = Math.max(1, Math.min(48, parseInt(props.limit, 10) || 4));
var filters = [];
if (props.featured) filters.push('featured=1');
if (props.category) filters.push('category=' + encodeURIComponent(props.category));
var countPath = '/products?per_page=1' + (filters.length ? '&' + filters.join('&') : '');
var listPath = '/products?per_page=' + limit + '&sort=' + encodeURIComponent(props.sort || 'latest') + (filters.length ? '&' + filters.join('&') : '');

Store.need('/categories');
Store.need(countPath);

Store.ready(function () {
  var grid = root.querySelector('[data-role="grid"]');
  var counted = Store.data(countPath);
  var total = counted && counted.meta ? counted.meta.total : limit;
  var expected = Math.min(limit, total);

  if (!expected) {
    if (props.hideWhenEmpty && !Store.inEditor) {
      (root.closest('section') || root).remove();
      return;
    }
    // The editor keeps a hidden section so it can still be edited, and says why visitors don't see it.
    grid.innerHTML = props.hideWhenEmpty && Store.inEditor
      ? '<div class="col-12"><div class="empty-state"><i>✦</i><h3>Hidden on the live site</h3><p>' +
        (props.featured ? 'No product is marked Featured yet. Tick “Featured” on products in the admin panel and they show here.' : 'No product matches yet, so visitors don’t see this section.') +
        '</p></div></div>'
      : '<div class="col-12"><div class="empty-state"><i>✦</i><h3>New pieces are on their way</h3><p>Check back soon, or visit us in store.</p></div></div>';
    return;
  }

  grid.innerHTML = Store.skeletons(expected);

  function load() {
    Store.get(listPath).then(function (json) {
      Store.swap(grid, (json.data || []).map(Store.card).join(''));
    }, function () {
      Store.failure(grid, 'These products could not be loaded.', load, true);
    });
  }
  load();
});
