<div class="offcanvas offcanvas-end drawer" tabindex="-1" id="cartDrawer" aria-labelledby="cartTitle">
    <div class="dhead">
      <h3 id="cartTitle">Your Bag</h3><button type="button" class="close-x" data-bs-dismiss="offcanvas" aria-label="Close">×</button>
    </div>
    <div class="dbody">
      <div class="empty" id="cartEmpty">Your bag is empty.<br><br><a class="btn btn-red" href="{{ route('shop.women') }}">Start shopping</a></div>
      <div id="cartItems"></div>
    </div>
    <form class="dfoot" id="cartFoot" hidden>
      <div class="sum"><span>Subtotal</span><b id="cartTotal">₹0</b></div>
      <input id="cName" placeholder="Your name" autocomplete="name" aria-label="Your name">
      <input id="cPhone" type="tel" placeholder="Mobile number" autocomplete="tel" aria-label="Mobile number">
      <textarea id="cAddr" placeholder="Delivery address with PIN code (or write 'Store pickup')" aria-label="Delivery address"></textarea>
      <button type="submit" class="btn btn-wa btn-block">Place order on WhatsApp</button>
      <p>We'll confirm availability, shipping and payment on WhatsApp.</p>
    </form>
  </div>
