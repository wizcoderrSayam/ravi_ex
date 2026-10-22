/*
 * The storefront's client, shared by every component: window.Store.
 * Components queue their start-up with `(window.storeQueue = window.storeQueue || []).push(fn)`;
 * the queue runs once the store's feature flags and the cart are loaded, so they never wait on script order.
 */
(function () {
  if (window.Store) return;

  var queue = window.storeQueue || [];
  var listeners = {};

  function token() {
    var key = 'cart_token';
    try {
      var saved = localStorage.getItem(key);
      if (saved) return saved;
      saved = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2) + 'aaaaaaaa';
      localStorage.setItem(key, saved);
      return saved;
    } catch (e) {
      return window.__cartToken || (window.__cartToken = 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2) + 'aaaaaaaa');
    }
  }

  function firstError(json) {
    if (json.errors) return json.errors[Object.keys(json.errors)[0]][0];
    return json.message || 'Something went wrong.';
  }

  function request(method, path, body) {
    return fetch('/api' + path, {
      method: method,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Cart-Token': token() },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) {
        if (!res.ok) {
          var error = new Error(firstError(json));
          error.status = res.status;
          error.errors = json.errors || {};
          throw error;
        }
        return json;
      });
    });
  }

  function on(name, fn) {
    (listeners[name] = listeners[name] || []).push(fn);
  }

  function emit(name, detail) {
    (listeners[name] || []).forEach(function (fn) { fn(detail); });
  }

  function setCart(json) {
    Store.cart = json.data;
    emit('cart', Store.cart);
    return Store.cart;
  }

  var Store = window.Store = {
    flags: {},
    cart: { count: 0, items: [] },
    request: request,
    on: on,
    emit: emit,

    esc: function (value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    },

    toast: function (message, isError) {
      var el = document.createElement('div');
      el.className = 'fixed bottom-4 left-1/2 z-[60] -translate-x-1/2 rounded-md px-4 py-2 text-sm font-medium text-white shadow-lg ' + (isError ? 'bg-rose-600' : 'bg-slate-900');
      el.textContent = message;
      document.body.appendChild(el);
      setTimeout(function () { el.remove(); }, 3500);
    },

    refreshCart: function () {
      return Store.flags.cart ? request('GET', '/cart').then(setCart) : Promise.resolve();
    },

    add: function (productId, variantId, quantity) {
      return request('POST', '/cart/items', { product_id: productId, variant_id: variantId || null, quantity: quantity || 1 })
        .then(setCart)
        .then(function () { Store.openCart(); })
        .catch(function (error) { Store.toast(error.message, true); });
    },

    setQuantity: function (itemId, quantity) {
      return request('PATCH', '/cart/items/' + itemId, { quantity: quantity }).then(setCart)
        .catch(function (error) { Store.toast(error.message, true); });
    },

    remove: function (itemId) {
      return request('DELETE', '/cart/items/' + itemId).then(setCart);
    },

    openCart: function () { emit('open'); },
    closeCart: function () { emit('close'); }
  };

  request('GET', '/store')
    .then(function (json) {
      Store.flags = json.data;
      return Store.refreshCart();
    })
    .catch(function () {})
    .then(function () {
      window.storeQueue = { push: function (fn) { fn(); } };
      queue.forEach(function (fn) { fn(); });
    });
})();
