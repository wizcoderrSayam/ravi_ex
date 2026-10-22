var root = this;
props = props || {};

if (props.title) root.querySelector('[data-role="title"]').textContent = props.title;

fetch('/api/categories', { headers: { Accept: 'application/json' } })
  .then(function (res) { return res.json(); })
  .then(function (body) {
    root.querySelector('[data-role="list"]').innerHTML = (body.data || []).map(function (category) {
      var name = String(category.name).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; });

      return '<a href="/shop?category=' + encodeURIComponent(category.slug) + '" class="flex h-28 items-center justify-center rounded-lg border border-slate-200 bg-white p-4 text-center font-semibold text-slate-800 transition hover:border-teal-600 hover:text-teal-700">' + name + '</a>';
    }).join('');
  })
  .catch(function () {});
