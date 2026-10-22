var root = this;
props = props || {};

function init() {
  var esc = window.Store.esc;
  var name = window.Store.flags.name || 'Store';

  root.querySelector('[data-role="brand"]').textContent = name;
  root.querySelector('[data-role="copyright"]').textContent = name;
  root.querySelector('[data-role="year"]').textContent = new Date().getFullYear();

  fetch('/api/cms/menus/' + encodeURIComponent(props.menu || 'footer'), { headers: { Accept: 'application/json' } })
    .then(function (res) { return res.json(); })
    .then(function (body) {
      root.querySelector('[data-role="links"]').innerHTML = ((body.data && body.data.items) || []).map(function (item) {
        return '<li><a href="' + esc(item.url) + '" class="text-slate-600 hover:text-teal-700">' + esc(item.label) + '</a></li>';
      }).join('');
    })
    .catch(function () {});
}

(window.storeQueue = window.storeQueue || []).push(init);
