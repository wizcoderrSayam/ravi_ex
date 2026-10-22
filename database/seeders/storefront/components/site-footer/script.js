/*
 * The old footer: its link columns and the policy links come from CMS menus (`props.shopMenu`,
 * `props.helpMenu`, `props.policiesMenu`), the about text and the address from CMS → Settings
 * (data-setting). It also owns the WhatsApp button, the back-to-top ring and the toast.
 */
var root = this;
props = props || {};

Store.ready(function () {
  Store.bind(root);
  var esc = Store.esc;

  function list(slug) {
    return Store.menu(slug).map(function (item) {
      return '<li><a href="' + esc(item.url) + '"' + (item.target === '_blank' ? ' target="_blank" rel="noopener"' : '') + '>' + esc(item.label) + '</a></li>';
    }).join('');
  }

  [['shop', props.shopMenu || 'footer-shop'], ['help', props.helpMenu || 'footer-help'], ['policies', props.policiesMenu || 'footer-policies']].forEach(function (column) {
    var html = list(column[1]);
    var holder = root.querySelector('[data-role="' + column[0] + '"]');
    var wrapper = root.querySelector('[data-role="' + column[0] + '-column"]');
    if (html) holder.innerHTML = html;
    else (wrapper || holder).remove();
  });

  root.querySelector('[data-role="year"]').textContent = new Date().getFullYear();
  root.querySelector('[data-role="store-name"]').textContent = Store.flags.name || '';

  if (Store.inEditor) return;

  // Back to top: the ring fills as the page scrolls.
  var toTop = root.querySelector('#toTop');
  var bar = root.querySelector('#toTopBar');
  var queued = false;
  function draw() {
    queued = false;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    bar.style.strokeDashoffset = 100 - (max > 0 ? Math.min(100, window.scrollY / max * 100) : 0);
    toTop.classList.toggle('show', window.scrollY > 300);
  }
  window.addEventListener('scroll', function () {
    if (!queued) {
      queued = true;
      requestAnimationFrame(draw);
    }
  }, { passive: true });
  window.addEventListener('resize', draw);
  toTop.addEventListener('click', function () {
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: calm ? 'auto' : 'smooth' });
  });
  draw();
});
