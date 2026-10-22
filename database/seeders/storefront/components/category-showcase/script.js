/*
 * One "shop for …" section of the old home page, from a category and its subcategories
 * (/api/categories, which the page waits for, so the section is drawn before it shows).
 * `carousel`: a row of category cards that steps card by card with arrows, dots or a swipe.
 * `band`: an intro panel beside the subcategories as a lookbook grid.
 */
var root = this;
props = props || {};
Store.need('/categories');

Store.ready(function () {
  var esc = Store.esc;
  var holder = root.querySelector('[data-role="showcase"]');
  var index = Store.categories();
  var parent = index && index.bySlug[props.category];
  if (!parent) {
    holder.innerHTML = '';
    return;
  }

  var name = parent.name;
  var title = props.title || 'Shop for ' + name;
  var tiles = parent.children || [];
  var id = 'shop-' + parent.slug;
  var url = Store.categoryUrl(parent);

  if ((props.layout || 'carousel') === 'band') {
    var reversed = !!props.reversed;
    var columns = tiles.length > 4 ? 3 : Math.max(tiles.length, 1);
    holder.innerHTML = '<section class="shop-section" aria-labelledby="' + id + '"><div class="container">' +
      '<div class="shop-band' + (reversed ? ' is-reversed' : '') + (tiles.length <= 2 ? ' is-compact' : '') + '">' +
      '<div class="shop-intro" data-aos="' + (reversed ? 'fade-left' : 'fade-right') + '">' +
      '<h2 id="' + id + '">' + esc(title) + '</h2>' + (props.description ? '<p>' + esc(props.description) + '</p>' : '') +
      '<a class="btn btn-red" href="' + esc(url) + '">Shop all ' + esc(name) + '</a>' +
      '<span class="shop-intro-count">' + tiles.length + ' ' + (tiles.length === 1 ? 'category' : 'categories') + '</span></div>' +
      '<div class="shop-grid cols-' + columns + (tiles.length <= 4 ? ' is-level' : '') + '" data-aos="' + (reversed ? 'fade-right' : 'fade-left') + '" data-aos-delay="120">' +
      tiles.map(function (tile) {
        return '<a class="shop-tile" href="' + esc(Store.categoryUrl(parent, tile)) + '"><span class="shop-tile-media">' +
          (tile.image ? '<img src="' + esc(tile.image) + '" alt="' + esc(tile.name) + '" loading="lazy" onerror="this.remove()">' : '') +
          '<img class="shop-tile-logo" src="/media/ravi-exclusive/assets/images/logo.webp" alt="" aria-hidden="true" loading="lazy" onerror="this.remove()"></span>' +
          '<span class="shop-tile-name">' + esc(tile.name) + '</span></a>';
      }).join('') + '</div></div></div></section>';
    return;
  }

  holder.innerHTML = '<section class="cat-section" aria-labelledby="' + id + '" data-carousel><div class="container">' +
    '<div class="head" data-aos="fade-up"><div><span class="eyebrow">Shop by category</span><h2 id="' + id + '">' + esc(title) + '</h2></div>' +
    '<div class="cat-rail-actions"><a href="' + esc(url) + '">Shop all ' + esc(name) + ' →</a>' +
    '<button type="button" class="cat-rail-btn" data-rail-prev aria-label="Previous ' + esc(name) + ' categories">‹</button>' +
    '<button type="button" class="cat-rail-btn" data-rail-next aria-label="More ' + esc(name) + ' categories">›</button></div></div>' +
    '<div class="cat-rail" style="--cards: ' + tiles.length + '" data-rail data-aos="fade-up" data-aos-delay="100">' +
    tiles.map(function (tile) {
      return '<a class="cat" href="' + esc(Store.categoryUrl(parent, tile)) + '">' +
        (tile.image ? '<img class="art" src="' + esc(tile.image) + '" alt="' + esc(tile.name) + '" loading="lazy" onerror="this.remove()">' : '') +
        '<div class="t"><h3>' + esc(tile.name) + '</h3></div></a>';
    }).join('') + '</div><div class="cat-rail-dots" data-rail-dots></div></div></section>';

  var rail = holder.querySelector('[data-rail]');
  var prev = holder.querySelector('[data-rail-prev]');
  var next = holder.querySelector('[data-rail-next]');
  var dots = holder.querySelector('[data-rail-dots]');
  var cards = rail.children;
  // A step is one card plus the gap after it; a position is where a card snaps to the left edge.
  function step() { return cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : rail.clientWidth; }
  function maxScroll() { return rail.scrollWidth - rail.clientWidth; }
  function positions() { return maxScroll() > 1 ? Math.round(maxScroll() / step()) + 1 : 1; }
  function current() { return Math.min(positions() - 1, Math.round(rail.scrollLeft / step())); }
  function go(target) {
    var count = positions();
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    rail.scrollTo({ left: Math.min(((target + count) % count) * step(), maxScroll()), behavior: calm ? 'auto' : 'smooth' });
  }
  function drawDots() {
    var count = positions();
    if (dots.children.length !== count) {
      dots.innerHTML = '';
      for (var i = 0; i < count; i++) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Show card ' + (i + 1));
        dot.addEventListener('click', go.bind(null, i));
        dots.appendChild(dot);
      }
    }
    var on = current();
    Array.prototype.forEach.call(dots.children, function (dot, i) {
      dot.classList.toggle('is-on', i === on);
      if (i === on) dot.setAttribute('aria-current', 'true'); else dot.removeAttribute('aria-current');
    });
    // Nothing to slide when every card fits.
    dots.hidden = prev.hidden = next.hidden = count < 2;
  }
  prev.addEventListener('click', function () { go(current() - 1); });
  next.addEventListener('click', function () { go(current() + 1); });
  var queued = false;
  rail.addEventListener('scroll', function () {
    if (!queued) {
      queued = true;
      requestAnimationFrame(function () { queued = false; drawDots(); });
    }
  }, { passive: true });
  window.addEventListener('resize', drawDots);
  drawDots();
});
