/* =========================================================
   Ravi Exclusive — site interactions
   (cart, wishlist, quick view, listing filters, AOS)
   All page content lives in the HTML; this file only adds behaviour.
   ========================================================= */
(function () {
  "use strict";

  /* ---------- STORE SETTINGS — edit these ---------- */
  const STORE = {
    name: "Ravi Exclusive",
    whatsapp: "918148146262", // used for checkout orders
    phone: "+91-814-814-62-62"
  };

  /* ---------- helpers ---------- */
  const $ = id => document.getElementById(id);
  const inr = n => "₹" + n.toLocaleString("en-IN");
  const code = id => "RE-" + String(id).padStart(4, "0");
  const tint = hex => `linear-gradient(160deg,${hex}22,${hex}44)`;
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  const store = {
    get(k, d) { try { return JSON.parse(localStorage.getItem("re_" + k)) ?? d } catch (e) { return d } },
    set(k, v) { try { localStorage.setItem("re_" + k, JSON.stringify(v)) } catch (e) { } }
  };
  const page = document.body.dataset.page;

  // cart items carry their own product details, since each page only holds some products
  let cart = store.get("cart", []).filter(i => i && i.name);
  let wish = store.get("wish", []);

  /* ---------- toast ---------- */
  let tt;
  function toast(t) {
    const el = $("toast");
    el.textContent = t; el.classList.add("show");
    clearTimeout(tt); tt = setTimeout(() => el.classList.remove("show"), 2200);
  }

  /* ---------- header badges ---------- */
  function save() { store.set("cart", cart); store.set("wish", wish); badges() }
  function badges() {
    const n = cart.reduce((a, i) => a + i.qty, 0);
    $("ccount").textContent = n; $("ccount").hidden = !n;
    $("wcount").textContent = wish.length; $("wcount").hidden = !wish.length;
  }

  /* ---------- wishlist ---------- */
  function syncWish(btn) {
    const id = +btn.closest(".pcard").dataset.id, on = wish.includes(id);
    btn.classList.toggle("on", on); btn.textContent = on ? "♥" : "♡";
    btn.setAttribute("aria-pressed", on);
    btn.setAttribute("aria-label", on ? "Remove from wishlist" : "Add to wishlist");
  }
  function toggleWish(btn) {
    const id = +btn.closest(".pcard").dataset.id;
    wish = wish.includes(id) ? wish.filter(x => x !== id) : [...wish, id]; save();
    // the same product can appear twice on a page (e.g. new arrivals + bestsellers)
    document.querySelectorAll(`.pcard[data-id="${id}"] .wish`).forEach(syncWish);
    toast(wish.includes(id) ? "Saved to wishlist" : "Removed from wishlist");
    if (page === "wishlist") updateListing();
  }
  document.querySelectorAll(".pcard .wish").forEach(syncWish);

  /* ---------- product quick view ---------- */
  const modalEl = $("productModal");
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  let cur = null, sel = { size: null, qty: 1 };

  function readCard(card) {
    const d = card.dataset, img = card.querySelector("img.ph"), art = card.querySelector(".art-fb");
    return {
      id: +d.id, code: d.code || code(+d.id), name: d.name, cat: d.catName, sub: d.sub, price: +d.price, mrp: +d.mrp,
      fabric: d.fabric, hex: d.hex, sizes: d.sizes.split("|"),
      img: img ? img.getAttribute("src") : "", art: art ? art.innerHTML : ""
    };
  }
  function priceHTML(p) {
    const off = p.mrp ? Math.round((1 - p.price / p.mrp) * 100) : 0;
    return `<b>${inr(p.price)}</b>` + (p.mrp ? `<s>${inr(p.mrp)}</s><em>${off}% off</em>` : "");
  }
  function openProduct(card) {
    const p = cur = readCard(card);
    sel = { size: p.sizes.length === 1 ? p.sizes[0] : null, qty: 1 };
    $("pmImg").style.background = tint(p.hex);
    $("pmImg").innerHTML = p.img
      ? `<img class="ph" src="${esc(p.img)}" alt="${esc(p.name)}">`
      : `<span class="art-fb" style="color:${p.hex}">${p.art}</span>`;
    $("pmEyebrow").textContent = `${p.cat} · ${p.sub}`;
    $("pmName").textContent = p.name;
    $("pmPrice").innerHTML = priceHTML(p);
    $("pmFabric").textContent = p.fabric;
    $("pmCode").textContent = p.code;
    $("sizes").innerHTML = p.sizes.map(s => `<button type="button" class="${sel.size === s ? "on" : ""}" data-size="${esc(s)}">${esc(s)}</button>`).join("");
    $("sizeErr").style.display = "none";
    $("mq").textContent = 1;
    modal.show();
  }
  function needSize() { if (!sel.size) { $("sizeErr").style.display = "block"; return true } return false }

  $("sizes").addEventListener("click", e => {
    const b = e.target.closest("button[data-size]"); if (!b) return;
    sel.size = b.dataset.size;
    $("sizes").querySelectorAll("button").forEach(x => x.classList.toggle("on", x === b));
    $("sizeErr").style.display = "none";
  });
  modalEl.querySelectorAll("[data-qty]").forEach(b => b.addEventListener("click", () => {
    sel.qty = Math.max(1, Math.min(10, sel.qty + +b.dataset.qty)); $("mq").textContent = sel.qty;
  }));
  $("pmAdd").addEventListener("click", () => {
    if (needSize()) return;
    const ex = cart.find(i => i.id === cur.id && i.size === sel.size);
    if (ex) ex.qty += sel.qty;
    else cart.push({ id: cur.id, code: cur.code, size: sel.size, qty: sel.qty, name: cur.name, price: cur.price, img: cur.img, hex: cur.hex });
    save(); toast("Added to your bag");
    modalEl.addEventListener("hidden.bs.modal", () => drawer.show(), { once: true });
    modal.hide();
  });
  $("pmBuy").addEventListener("click", () => {
    if (needSize()) return;
    const msg = `Hello ${STORE.name}, I'd like to order:\n\n${cur.name} (${cur.code})\nSize: ${sel.size} · Qty: ${sel.qty}\nPrice: ${inr(cur.price * sel.qty)}\n\nPlease confirm availability.`;
    window.open(`https://wa.me/${STORE.whatsapp}?text=${encodeURIComponent(msg)}`, "_blank");
  });

  /* card clicks (event delegation) */
  document.addEventListener("click", e => {
    const wishBtn = e.target.closest('[data-action="wish"]');
    if (wishBtn) { e.stopPropagation(); toggleWish(wishBtn); return }
    const qv = e.target.closest('[data-action="quick-view"]');
    if (qv) openProduct(qv.closest(".pcard"));
  });
  document.addEventListener("keydown", e => {
    if ((e.key === "Enter" || e.key === " ") && e.target.matches('.pimg[data-action="quick-view"]')) {
      e.preventDefault(); openProduct(e.target.closest(".pcard"));
    }
  });

  /* ---------- cart drawer ---------- */
  const drawerEl = $("cartDrawer");
  const drawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
  drawerEl.addEventListener("show.bs.offcanvas", drawCart);

  function drawCart() {
    const empty = !cart.length;
    $("cartEmpty").hidden = !empty;
    $("cartFoot").hidden = empty;
    let total = 0;
    $("cartItems").innerHTML = cart.map((it, i) => {
      total += it.price * it.qty;
      return `<div class="citem">
        <div class="cthumb" style="background:${tint(it.hex)}">${it.img ? `<img class="ph" src="${esc(it.img)}" alt="">` : ""}</div>
        <div><h4>${esc(it.name)}</h4><small>Size: ${esc(it.size)} · ${inr(it.price)}</small>
          <div class="qty"><button type="button" data-cart="${i}" data-d="-1" aria-label="Decrease">−</button><span>${it.qty}</span><button type="button" data-cart="${i}" data-d="1" aria-label="Increase">+</button></div></div>
        <button type="button" class="rm" data-cart="${i}" data-d="-${it.qty}">Remove</button></div>`;
    }).join("");
    $("cartTotal").textContent = inr(total);
    if (!empty) {
      $("cName").value ||= store.get("cname", "");
      $("cPhone").value ||= store.get("cphone", "");
      $("cAddr").value ||= store.get("caddr", "");
    }
  }
  $("cartItems").addEventListener("click", e => {
    const b = e.target.closest("[data-cart]"); if (!b) return;
    const i = +b.dataset.cart; cart[i].qty += +b.dataset.d;
    if (cart[i].qty < 1) cart.splice(i, 1);
    save(); drawCart();
  });
  $("cartFoot").addEventListener("submit", e => {
    e.preventDefault();
    const n = $("cName").value.trim(), ph = $("cPhone").value.trim(), ad = $("cAddr").value.trim();
    if (!n || !ph || !ad) { toast("Please fill in name, mobile and address"); return }
    store.set("cname", n); store.set("cphone", ph); store.set("caddr", ad);
    let total = 0;
    const lines = cart.map((it, k) => { total += it.price * it.qty; return `${k + 1}. ${it.name} (${it.code || code(it.id)})\n   Size ${it.size} × ${it.qty} = ${inr(it.price * it.qty)}` }).join("\n");
    const msg = `Hello ${STORE.name}, I'd like to place an order:\n\n${lines}\n\nSubtotal: ${inr(total)}\n\nName: ${n}\nMobile: ${ph}\nAddress: ${ad}`;
    window.open(`https://wa.me/${STORE.whatsapp}?text=${encodeURIComponent(msg)}`, "_blank");
  });
  $("cartEmpty").querySelector("a").addEventListener("click", () => drawer.hide());

  /* ---------- search panel + mobile menu ---------- */
  const searchPanel = $("searchPanel");
  searchPanel.addEventListener("shown.bs.collapse", () => $("q").focus());
  document.addEventListener("keydown", e => {
    if (e.key === "Escape" && searchPanel.classList.contains("show")) bootstrap.Collapse.getOrCreateInstance(searchPanel).hide();
  });
  $("search").addEventListener("submit", e => { if (!$("q").value.trim()) e.preventDefault() });
  // links in the mobile menu may only change the #hash, so close the menu ourselves
  $("mnav").addEventListener("click", e => {
    if (e.target.closest("a[href]")) bootstrap.Offcanvas.getOrCreateInstance($("mnav")).hide();
  });

  /* ---------- listing pages: subcategory, filter panel, sort, pagination ---------- */
  const plist = $("plist");
  const cols = plist ? [...plist.children] : [];
  cols.forEach((c, i) => c.dataset.order = i);
  const PER_PAGE = [12, 24, 48];
  const SORTS = ["pop", "new", "low", "high"];

  // filter state lives in the query string (?q=&min=&max=&in_stock=1&sort=&per_page=&page=),
  // so a filtered page can be shared or reloaded, and the server can read the same params later
  function readFilters() {
    const p = new URLSearchParams(location.search);
    const num = k => { const v = p.get(k); return v === null || v.trim() === "" || isNaN(v) ? null : Math.max(0, +v) };
    let min = num("min"), max = num("max");
    if (min !== null && max !== null && min > max) [min, max] = [max, min];
    return {
      q: (p.get("q") || "").trim(),
      min, max,
      inStock: p.get("in_stock") === "1",
      sort: SORTS.includes(p.get("sort")) ? p.get("sort") : "pop",
      perPage: PER_PAGE.includes(+p.get("per_page")) ? +p.get("per_page") : PER_PAGE[0],
      page: Math.max(1, parseInt(p.get("page"), 10) || 1)
    };
  }
  let filters = readFilters();
  const query = filters.q;

  function writeFilters() {
    const p = new URLSearchParams();
    if (filters.q) p.set("q", filters.q);
    if (filters.min !== null) p.set("min", filters.min);
    if (filters.max !== null) p.set("max", filters.max);
    if (filters.inStock) p.set("in_stock", "1");
    if (filters.sort !== "pop") p.set("sort", filters.sort);
    if (filters.perPage !== PER_PAGE[0]) p.set("per_page", filters.perPage);
    if (filters.page > 1) p.set("page", filters.page);
    const qs = p.toString();
    history.replaceState(null, "", location.pathname + (qs ? "?" + qs : "") + location.hash);
  }

  if (page === "search") {
    $("q").value = query;
    if (query) {
      $("shopTitle").textContent = `Results for “${query}”`;
      document.title = `Results for “${query}” — Ravi Exclusive`;
    }
  }

  // the chip bar filters by subcategory on category pages, and by top-level category elsewhere
  const chipKey = page === "category" ? "subSlug" : "cat";

  function currentSub() {
    const h = decodeURIComponent(location.hash.slice(1));
    return h && document.querySelector(`[data-sub-link="${CSS.escape(h)}"]`) ? h : "";
  }

  function updateListing() {
    if (!plist) return;
    const sub = currentSub();
    document.querySelectorAll("[data-sub-link]").forEach(a => {
      const on = a.dataset.subLink === sub;
      a.classList.toggle("on", on);
      if (on) a.setAttribute("aria-current", "true"); else a.removeAttribute("aria-current");
    });

    if (page === "category") {
      const catName = document.body.dataset.catName;
      const link = sub && document.querySelector(`[data-sub-link="${CSS.escape(sub)}"]`);
      const subName = link ? link.dataset.subName : "";
      $("shopTitle").textContent = subName || catName;
      $("crumbSub").textContent = subName ? " / " + subName : "";
      document.title = `${subName ? subName + " · " : ""}${catName} — Ravi Exclusive | Amritsar`;
    }

    const words = filters.q.toLowerCase().split(/\s+/).filter(Boolean);
    const cmp = {
      pop: (a, b) => a.dataset.order - b.dataset.order,
      new: (a, b) => (b.firstElementChild.dataset.new - a.firstElementChild.dataset.new) || (b.firstElementChild.dataset.id - a.firstElementChild.dataset.id),
      low: (a, b) => a.firstElementChild.dataset.price - b.firstElementChild.dataset.price,
      high: (a, b) => b.firstElementChild.dataset.price - a.firstElementChild.dataset.price
    }[filters.sort];
    cols.slice().sort(cmp).forEach(c => plist.appendChild(c));

    // cards without data-stock count as in stock; mark sold-out ones with data-stock="0"
    const passesFilters = cols.filter(col => {
      const d = col.firstElementChild.dataset, price = +d.price;
      return (filters.min === null || price >= filters.min)
        && (filters.max === null || price <= filters.max)
        && (!filters.inStock || d.stock !== "0")
        && words.every(w => d.search.includes(w.replace(/s$/, "")))
        && (page !== "wishlist" || wish.includes(+d.id));
    });
    const matches = passesFilters.filter(col => !sub || col.firstElementChild.dataset[chipKey] === sub);
    drawChipCounts(passesFilters);

    // the wishlist has no filter panel, so it lists everything that matches
    const pager = $("pager");
    const perPage = pager ? filters.perPage : matches.length || 1;
    const pages = Math.max(1, Math.ceil(matches.length / perPage));
    filters.page = Math.min(filters.page, pages);
    const first = (filters.page - 1) * perPage;
    const visible = new Set(matches.slice(first, first + perPage));
    cols.forEach(col => col.classList.toggle("d-none", !visible.has(col)));

    if ($("count")) $("count").textContent = matches.length + (matches.length === 1 ? " item" : " items");
    $("emptyMsg").hidden = matches.length > 0;
    if (pager) drawPager(pager, pages);
    if (window.AOS) AOS.refresh();
  }

  // each chip shows how many products it would list with the other filters as they are
  function drawChipCounts(passesFilters) {
    const counts = {};
    passesFilters.forEach(col => { const k = col.firstElementChild.dataset[chipKey]; counts[k] = (counts[k] || 0) + 1 });
    document.querySelectorAll(".chip[data-sub-link]").forEach(chip => {
      const n = chip.dataset.subLink ? counts[chip.dataset.subLink] || 0 : passesFilters.length;
      chip.querySelector("[data-chip-count]").textContent = n;
      chip.classList.toggle("is-empty", n === 0);
    });
  }

  function drawPager(pager, pages) {
    pager.hidden = pages < 2;
    if (pages < 2) { pager.innerHTML = ""; return }
    const cur = filters.page;
    // first, last and the pages around the current one; gaps become an ellipsis
    const nums = [...new Set([1, cur - 1, cur, cur + 1, pages])].filter(n => n >= 1 && n <= pages).sort((a, b) => a - b);
    let html = `<button type="button" data-page="${cur - 1}" aria-label="Previous page" ${cur === 1 ? "disabled" : ""}>‹</button>`;
    nums.forEach((n, i) => {
      if (i && n - nums[i - 1] > 1) html += `<span class="gap">…</span>`;
      html += `<button type="button" data-page="${n}" class="${n === cur ? "on" : ""}" ${n === cur ? 'aria-current="page"' : ""}>${n}</button>`;
    });
    html += `<button type="button" data-page="${cur + 1}" aria-label="Next page" ${cur === pages ? "disabled" : ""}>›</button>`;
    pager.innerHTML = html;
  }

  if (plist) {
    const form = $("filters");
    if (form) form.addEventListener("submit", e => {
      e.preventDefault();
      const f = new FormData(form);
      history.replaceState(null, "", location.pathname + "?" + new URLSearchParams([...f].filter(([, v]) => String(v).trim() !== "")) + location.hash);
      filters = { ...readFilters(), page: 1 };
      writeFilters();
      // reflect cleaned-up values (e.g. swapped min/max) back into the form
      form.elements.min.value = filters.min ?? "";
      form.elements.max.value = filters.max ?? "";
      updateListing();
      // on phones the panel is a collapsible drawer; close it so the results are visible
      const side = $("side");
      if (side && side.classList.contains("show")) bootstrap.Collapse.getOrCreateInstance(side).hide();
      plist.scrollIntoView({ behavior: "smooth", block: "start" });
    });
    if ($("pager")) $("pager").addEventListener("click", e => {
      const b = e.target.closest("button[data-page]"); if (!b || b.disabled) return;
      filters.page = +b.dataset.page; writeFilters(); updateListing();
      plist.scrollIntoView({ behavior: "smooth", block: "start" });
    });
    window.addEventListener("hashchange", () => { filters.page = 1; writeFilters(); updateListing(); window.scrollTo(0, 0); revealActiveChip() });
    updateListing();
    revealActiveChip();
  }

  // the chip bar scrolls sideways when it has more chips than fit
  function revealActiveChip() {
    const chip = document.querySelector(".chip.on");
    const bar = chip && chip.parentElement;
    if (bar && bar.scrollWidth > bar.clientWidth) bar.scrollLeft = chip.offsetLeft - (bar.clientWidth - chip.offsetWidth) / 2;
  }

  /* ---------- init ---------- */
  $("yr").textContent = new Date().getFullYear();
  badges();
  AOS.init({ duration: 800, easing: "ease-out-cubic", once: true, offset: 60 });
})();
