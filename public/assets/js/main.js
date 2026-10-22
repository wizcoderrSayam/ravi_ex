/* =========================================================
   Ravi Exclusive — site interactions
   (cart, wishlist, listing filters, AOS)
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

  /* ---------- ordering: used by the product page ---------- */
  // a product card and the product page carry the same data-* attributes
  function readCard(card) {
    const d = card.dataset, img = card.querySelector("img.ph"), art = card.querySelector(".art-fb");
    return {
      id: +d.id, code: d.code || code(+d.id), name: d.name, cat: d.catName, sub: d.sub, price: +d.price, mrp: +d.mrp,
      fabric: d.fabric, description: d.description || "", hex: d.hex, sizes: d.sizes.split("|"), url: d.url || "",
      img: img ? img.getAttribute("src") : "", art: art ? art.innerHTML : ""
    };
  }

  // a size + quantity picker; the chosen values live in picker.sel
  function picker({ sizes, err, qtyButtons, qtyOut }) {
    const on = sizes.querySelector("button.on");
    const sel = { size: on ? on.dataset.size : null, qty: 1 };
    sizes.addEventListener("click", e => {
      const b = e.target.closest("button[data-size]"); if (!b) return;
      sel.size = b.dataset.size;
      sizes.querySelectorAll("button").forEach(x => x.classList.toggle("on", x === b));
      err.style.display = "none";
    });
    qtyButtons.forEach(b => b.addEventListener("click", () => {
      sel.qty = Math.max(1, Math.min(10, sel.qty + +b.dataset.qty)); qtyOut.textContent = sel.qty;
    }));
    return {
      sel,
      ready() { if (!sel.size) { err.style.display = "block"; return false } return true }
    };
  }

  function addToBag(p, sel) {
    const ex = cart.find(i => i.id === p.id && i.size === sel.size);
    if (ex) { ex.qty += sel.qty; ex.url = p.url; ex.oneSize = p.sizes.length === 1 }
    // a product with one option has no size to pick, so the cart and order messages leave it out
    else cart.push({ id: p.id, code: p.code, size: sel.size, oneSize: p.sizes.length === 1, qty: sel.qty, name: p.name, price: p.price, img: p.img, hex: p.hex, url: p.url });
    save(); toast("Added to your bag");
  }
  function sendWhatsApp(msg) {
    window.open(`https://wa.me/${STORE.whatsapp}?text=${encodeURIComponent(msg)}`, "_blank");
  }
  // the product link lets the store open exactly what was picked
  function orderMessage(p, sel) {
    const size = p.sizes.length === 1 ? "" : `Size: ${sel.size} · `;
    return `Hello ${STORE.name}, I'd like to order:\n\n${p.name} (${p.code})\n${size}Qty: ${sel.qty}\nPrice: ${inr(p.price * sel.qty)}`
      + (p.url ? `\n${p.url}` : "")
      + `\n\nPlease confirm availability.`;
  }

  /* card clicks (event delegation) */
  document.addEventListener("click", e => {
    const wishBtn = e.target.closest('[data-action="wish"]');
    if (wishBtn) { e.stopPropagation(); toggleWish(wishBtn) }
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
        <div><h4>${esc(it.name)}</h4><small>${it.oneSize ? "" : `Size: ${esc(it.size)} · `}${inr(it.price)}</small>
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
    // items added before product pages existed have no url, so their link is left out
    const lines = cart.map((it, k) => {
      total += it.price * it.qty;
      return `${k + 1}. ${it.name} (${it.code || code(it.id)})\n   ${it.oneSize ? "Qty" : `Size ${it.size} ×`} ${it.qty} = ${inr(it.price * it.qty)}` + (it.url ? `\n   ${it.url}` : "");
    }).join("\n");
    const msg = `Hello ${STORE.name}, I'd like to place an order:\n\n${lines}\n\nSubtotal: ${inr(total)}\n\nName: ${n}\nMobile: ${ph}\nAddress: ${ad}`;
    sendWhatsApp(msg);
  });
  $("cartEmpty").querySelector("a").addEventListener("click", () => drawer.hide());

  /* ---------- product page ---------- */
  const pd = $("pd");
  if (pd) {
    const product = readCard(pd);
    const choice = picker({ sizes: $("pdSizes"), err: $("pdSizeErr"), qtyButtons: pd.querySelectorAll("[data-qty]"), qtyOut: $("pdQty") });
    $("pdAdd").addEventListener("click", () => {
      if (!choice.ready()) return;
      addToBag(product, choice.sel);
      drawer.show();
    });
    // the Buy now under the price and the one under the description send the same order
    pd.querySelectorAll("[data-buy-now]").forEach(b => b.addEventListener("click", () => {
      if (choice.ready()) sendWhatsApp(orderMessage(product, choice.sel));
    }));
    // thumbnails swap the main photo
    const thumbs = $("pdThumbs");
    if (thumbs) thumbs.addEventListener("click", e => {
      const b = e.target.closest("button[data-photo]"); if (!b) return;
      $("pdImg").src = b.dataset.photo;
      thumbs.querySelectorAll("button").forEach(x => x.classList.toggle("on", x === b));
    });
  }

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

  /* ---------- back to top: the ring fills as the page scrolls ---------- */
  const reduceMotion = () => matchMedia("(prefers-reduced-motion: reduce)").matches;
  const toTop = $("toTop"), toTopBar = $("toTopBar");
  let toTopQueued = false;
  function drawToTop() {
    toTopQueued = false;
    const max = document.documentElement.scrollHeight - innerHeight;
    toTopBar.style.strokeDashoffset = 100 - (max > 0 ? Math.min(100, scrollY / max * 100) : 0);
    toTop.classList.toggle("show", scrollY > 300);
  }
  addEventListener("scroll", () => { if (!toTopQueued) { toTopQueued = true; requestAnimationFrame(drawToTop) } }, { passive: true });
  addEventListener("resize", drawToTop);
  // html already has scroll-behavior: smooth, so skip it for people who asked for less motion
  toTop.addEventListener("click", () => scrollTo({ top: 0, behavior: reduceMotion() ? "instant" : "smooth" }));
  drawToTop();

  /* ---------- category carousels: a scroll-snap rail that steps one card at a time, loops, and auto-advances ---------- */
  const CAROUSEL_DELAY = 4500;
  document.querySelectorAll("[data-carousel]").forEach(carousel => {
    const rail = carousel.querySelector("[data-rail]");
    const prev = carousel.querySelector("[data-rail-prev]");
    const next = carousel.querySelector("[data-rail-next]");
    const dots = carousel.querySelector("[data-rail-dots]");
    const cards = rail.children;
    // a step is one card plus the gap after it; a position is where a card snaps to the left edge
    const step = () => cards.length > 1 ? cards[1].offsetLeft - cards[0].offsetLeft : rail.clientWidth;
    const maxScroll = () => rail.scrollWidth - rail.clientWidth;
    const positions = () => maxScroll() > 1 ? Math.round(maxScroll() / step()) + 1 : 1;
    const current = () => Math.min(positions() - 1, Math.round(rail.scrollLeft / step()));
    function go(index) {
      const count = positions();
      const target = (index + count) % count; // past the end wraps to the start, and before the start to the end
      rail.scrollTo({ left: Math.min(target * step(), maxScroll()), behavior: reduceMotion() ? "instant" : "smooth" });
    }

    // dots are rebuilt whenever the number of positions changes (it depends on how many cards fit)
    function drawDots() {
      const count = positions();
      if (dots.children.length !== count) {
        dots.replaceChildren(...Array.from({ length: count }, (_, i) => {
          const dot = document.createElement("button");
          dot.type = "button";
          dot.setAttribute("aria-label", `Show card ${i + 1}`);
          dot.addEventListener("click", () => { go(i); schedule() });
          return dot;
        }));
      }
      const on = current();
      [...dots.children].forEach((dot, i) => {
        dot.classList.toggle("is-on", i === on);
        if (i === on) dot.setAttribute("aria-current", "true"); else dot.removeAttribute("aria-current");
      });
      // nothing to slide when every card fits
      dots.hidden = prev.hidden = next.hidden = count < 2;
    }

    // auto-advance waits while a mouse is over it, keyboard focus is inside, it is off-screen or the tab is hidden;
    // any click or swipe restarts the countdown so it never jumps straight after the visitor moves it
    let hovering = false, focused = false, onScreen = false, timer = 0;
    function schedule() {
      clearTimeout(timer);
      if (reduceMotion() || hovering || focused || !onScreen || document.hidden || positions() < 2) return;
      timer = setTimeout(() => { go(current() + 1); schedule() }, CAROUSEL_DELAY);
    }

    prev.addEventListener("click", () => { go(current() - 1); schedule() });
    next.addEventListener("click", () => { go(current() + 1); schedule() });
    carousel.addEventListener("pointerenter", e => { if (e.pointerType === "mouse") { hovering = true; schedule() } });
    carousel.addEventListener("pointerleave", e => { if (e.pointerType === "mouse") { hovering = false; schedule() } });
    // only keyboard focus pauses it; a mouse click on an arrow should not stop the carousel for good
    carousel.addEventListener("focusin", e => { focused = e.target.matches(":focus-visible"); schedule() });
    carousel.addEventListener("focusout", e => { if (!carousel.contains(e.relatedTarget)) { focused = false; schedule() } });
    rail.addEventListener("pointerdown", schedule, { passive: true });
    document.addEventListener("visibilitychange", schedule);
    new IntersectionObserver(([entry]) => { onScreen = entry.isIntersecting; schedule() }, { threshold: 0.4 }).observe(rail);

    let dotsQueued = false;
    rail.addEventListener("scroll", () => { if (!dotsQueued) { dotsQueued = true; requestAnimationFrame(() => { dotsQueued = false; drawDots() }) } }, { passive: true });
    addEventListener("resize", () => { drawDots(); schedule() });
    drawDots();
  });

  /* ---------- instagram reels: muted previews play only while on screen ---------- */
  // with reduced motion or data saver on, the poster frame stays instead; keyboard focus
  // inside the section pauses every reel, so looping video can always be stopped
  const reels = [...document.querySelectorAll("video[data-reel]")];
  const saveData = navigator.connection && navigator.connection.saveData;
  if (reels.length && !saveData) {
    const reelSection = reels[0].closest(".insta");
    const onScreen = new Set();
    let reelsFocused = false;
    const syncReels = () => reels.forEach(video => {
      if (onScreen.has(video) && !reelsFocused && !reduceMotion()) video.play().catch(() => { }); else video.pause();
    });
    const watcher = new IntersectionObserver(entries => {
      entries.forEach(({ target, isIntersecting }) => isIntersecting ? onScreen.add(target) : onScreen.delete(target));
      syncReels();
    }, { threshold: 0.5 });
    reels.forEach(video => watcher.observe(video));
    reelSection.addEventListener("focusin", e => { reelsFocused = e.target.matches(":focus-visible"); syncReels() });
    reelSection.addEventListener("focusout", e => { if (!reelSection.contains(e.relatedTarget)) { reelsFocused = false; syncReels() } });
    matchMedia("(prefers-reduced-motion: reduce)").addEventListener("change", syncReels);
  }

  /* ---------- instagram reels row: glides continuously and loops ---------- */
  // the row holds the reels twice; passing the end of the first set jumps back by exactly one set, so the loop is seamless.
  // it rests while a mouse is over it, a finger is on it (and briefly after), keyboard focus is inside, it is off-screen or the tab is hidden
  const GLIDE_SPEED = 30; // pixels per second
  document.querySelectorAll("[data-glide]").forEach(rail => {
    const copy = rail.querySelector("[data-glide-copy]");
    if (!copy) return;
    const setWidth = () => copy.offsetLeft - rail.firstElementChild.offsetLeft;
    let pos = rail.scrollLeft, hovering = false, focused = false, onScreen = false, touchedUntil = 0, last = 0;

    function step(now) {
      const dt = last ? Math.min(now - last, 100) / 1000 : 0;
      last = now;
      // a swipe moves the row; carry on from wherever it was left
      if (Math.abs(rail.scrollLeft - pos) > 2) pos = rail.scrollLeft;
      const resting = hovering || focused || !onScreen || document.hidden || reduceMotion() || now < touchedUntil;
      if (!resting) pos += GLIDE_SPEED * dt;
      const width = setWidth();
      if (width > 0 && pos >= width) pos -= width;
      if (!resting || pos !== rail.scrollLeft) rail.scrollLeft = pos;
      requestAnimationFrame(step);
    }

    rail.addEventListener("pointerenter", e => { if (e.pointerType === "mouse") hovering = true });
    rail.addEventListener("pointerleave", e => { if (e.pointerType === "mouse") hovering = false });
    rail.addEventListener("pointerdown", () => { touchedUntil = Infinity });
    addEventListener("pointerup", () => { if (touchedUntil === Infinity) touchedUntil = performance.now() + 2500 });
    addEventListener("pointercancel", () => { if (touchedUntil === Infinity) touchedUntil = performance.now() + 2500 });
    rail.addEventListener("scroll", () => { if (touchedUntil !== Infinity && Math.abs(rail.scrollLeft - pos) > 2) touchedUntil = performance.now() + 2500 }, { passive: true });
    rail.addEventListener("focusin", e => { focused = e.target.matches(":focus-visible") });
    rail.addEventListener("focusout", e => { if (!rail.contains(e.relatedTarget)) focused = false });
    new IntersectionObserver(([entry]) => { onScreen = entry.isIntersecting }).observe(rail);
    requestAnimationFrame(step);
  });

  /* ---------- init ---------- */
  $("yr").textContent = new Date().getFullYear();
  badges();
  // once: true — sections fade in on the way down and never re-animate on the way up
  AOS.init({ duration: 800, easing: "ease-out-cubic", once: true, offset: 60, disable: reduceMotion });
})();
