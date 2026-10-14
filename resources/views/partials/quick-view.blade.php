<div class="modal fade product-modal" id="productModal" tabindex="-1" aria-labelledby="pmName" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content mbox">
        <button type="button" class="x" data-bs-dismiss="modal" aria-label="Close">×</button>
        <div class="row g-0">
          <div class="col-md-6">
            <div class="mimg" id="pmImg"></div>
          </div>
          <div class="col-md-6">
            <div class="minfo">
              <small class="eyebrow" id="pmEyebrow"></small>
              <h2 id="pmName"></h2>
              <div class="price" id="pmPrice"></div>
              <p class="tax-note">Inclusive of all taxes</p>
              <div class="opt">
                <h5>Size</h5>
                <div class="sizes" id="sizes"></div>
                <div class="err" id="sizeErr">Please select a size.</div>
              </div>
              <div class="opt">
                <h5>Quantity</h5>
                <div class="qty"><button type="button" data-qty="-1" aria-label="Decrease">−</button><span id="mq">1</span><button type="button" data-qty="1" aria-label="Increase">+</button></div>
              </div>
              <div class="mactions">
                <button type="button" class="btn btn-red btn-block" id="pmAdd">Add to Bag</button>
                <button type="button" class="btn btn-wa btn-block" id="pmBuy">Buy now on WhatsApp</button>
              </div>
              <div class="mdetails">
                <div><b>Colour:</b> As shown (more shades in store)</div>
                <div><b>Fabric:</b> <span id="pmFabric"></span></div>
                <div><b>Product code:</b> <span id="pmCode"></span></div>
                <div>Need help with size or customisation? Call <a href="tel:+918148146262" class="text-red">+91-814-814-62-62</a></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
