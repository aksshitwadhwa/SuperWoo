/** Exercise the real cart drawer click handler in headless Chrome with mocked AJAX. */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { pathToFileURL } = require('url');

const chrome = process.env.SUPERWOO_TEST_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const jquery = process.env.SUPERWOO_TEST_JQUERY || 'C:\\Users\\Rakes\\Local Sites\\ggm2\\app\\public\\wp-includes\\js\\jquery\\jquery.min.js';
if (!fs.existsSync(chrome) || !fs.existsSync(jquery)) {
    console.error('UNVERIFIED: Chrome or local WordPress jQuery is unavailable');
    process.exit(2);
}

const drawerScript = pathToFileURL(path.resolve(__dirname, '../public/js/cart-drawer.js')).href;
const html = `<!doctype html><html><head><meta charset="utf-8"></head><body>
<div data-superwoo-cart-shell class="superwoo-cart-shell is-open" aria-hidden="false">
  <div class="superwoo-cart-drawer"><div class="superwoo-cart-drawer__inner">
    <div class="superwoo-cart-drawer__header"><span class="superwoo-cart-count">2</span></div>
    <div class="superwoo-cart-live"></div><div data-superwoo-cart-error hidden></div>
    <div data-superwoo-cart-scroll style="height:80px;overflow:auto">
    <div class="superwoo-cart-item" data-cart-item-key="line-a">
      <button type="button" data-superwoo-qty-minus>-</button>
      <input data-superwoo-qty-input data-superwoo-qty-committed="2" value="2">
      <button type="button" data-superwoo-qty-plus>+</button>
      <span class="superwoo-cart-item__price">INR1998</span>
    </div>
    <div class="superwoo-cross-sells" data-test-recommendation style="height:140px">Recommended products</div></div>
    <div class="superwoo-cart-total">INR1998</div><div class="superwoo-cart-primary">INR1998</div>
  </div></div>
</div><pre id="result">PENDING</pre>
<script src="${pathToFileURL(jquery).href}"></script>
<script>
window.SuperWooCart = {
  ajaxUrl: 'mock-admin-ajax', wcAjaxUrls: {superwoo_update_cart_item:'mock-wc-ajax-update'},
  cartApiUrl: 'mock-store-cart', cartCount: 2, nonce: 'old',
  offerState: {discounts:{}, gifts:{}}, i18n: {
    updating:'Updating...', error:'Could not update cart', quantityNotSaved:'Quantity was not saved',
    cartItems:'Cart items', chooseOptions:'Choose options'
  }
};
window.browserTest = {phase:'ok', calls:[], refreshes:0, nonceRequests:0, storeCartFetches:0};
window.fetch = function () {
  browserTest.storeCartFetches += 1;
  return Promise.resolve({ok:true,headers:{get:function(){return ''}},json:function(){return Promise.resolve({items_count:2})}});
};
jQuery(document.body).on('wc_fragment_refresh', function () { browserTest.refreshes += 1; });
function fragment(qty) {
  var amount = qty * 999;
  var inner = '<div class="superwoo-cart-drawer__inner">' +
    '<div class="superwoo-cart-drawer__header"><span class="superwoo-cart-count">'+qty+'</span></div>' +
    '<div class="superwoo-cart-live"></div><div data-superwoo-cart-error hidden></div>' +
    '<div data-superwoo-cart-scroll style="height:80px;overflow:auto">' +
    '<div class="superwoo-cart-item" data-cart-item-key="line-a">' +
    '<button type="button" data-superwoo-qty-minus>-</button>' +
    '<input data-superwoo-qty-input data-superwoo-qty-committed="'+qty+'" value="'+qty+'">' +
    '<button type="button" data-superwoo-qty-plus>+</button>' +
    '<span class="superwoo-cart-item__price">INR'+amount+'</span></div></div>' +
    '<div class="superwoo-cart-total">INR'+amount+'</div>' +
    '<div class="superwoo-cart-primary">INR'+amount+'</div></div>';
  return {success:true, data:{
    fragments:{'.superwoo-cart-drawer__inner':inner},
    preserveCrossSells:true, count:qty, updatedQuantity:qty, offerState:{discounts:{},gifts:{}}
  }};
}
jQuery.ajax = function (options) {
  var deferred = jQuery.Deferred(), action = options.data.action;
  if (action === 'superwoo_refresh_cart_nonce') {
    browserTest.nonceRequests += 1;
    setTimeout(function () { deferred.resolve({success:true,data:{nonce:'fresh'}}); }, 20);
  } else if (action === 'superwoo_update_cart_item') {
    browserTest.calls.push({qty:options.data.quantity,nonce:options.data.nonce,url:options.url});
    setTimeout(function () {
      if (browserTest.phase === 'nonce' && options.data.nonce !== 'fresh') {
        deferred.reject({status:403,responseJSON:{data:{code:'superwoo_invalid_nonce',message:'Security check failed'}}});
      } else if (browserTest.phase === 'fail') {
        deferred.reject({status:500,responseJSON:{data:{message:'Server rejected the change'}}});
      } else if (browserTest.phase === 'softfail') {
        deferred.resolve({success:false,data:{message:'Cart update was rejected'}});
      } else {
        deferred.resolve(fragment(Number(options.data.quantity) - (browserTest.phase === 'mismatch' ? 1 : 0)));
      }
    }, 60);
  } else {
    setTimeout(function () { deferred.reject({status:400}); }, 10);
  }
  return deferred.promise();
};
</script>
<script src="${drawerScript}"></script>
<script>
function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
function assert(ok, message) { if (!ok) { throw new Error(message); } }
function qty() { return document.querySelector('[data-superwoo-qty-input]').value; }
function price() { return document.querySelector('.superwoo-cart-item__price').textContent; }
function total() { return document.querySelector('.superwoo-cart-total').textContent; }
async function run() {
  await wait(120);
  document.querySelector('[data-superwoo-cart-scroll]').scrollTop = 30;
  document.querySelector('[data-superwoo-qty-plus]').click();
  assert(qty() === '3' && total() === 'INR1998', 'quantity did not respond immediately or price changed before WooCommerce confirmed it');
  await wait(180);
  assert(qty() === '3' && price() === 'INR2997' && total() === 'INR2997', 'first AJAX update did not refresh quantity and prices');
  assert(document.querySelector('[data-test-recommendation]'), 'compact response removed recommendation cards');
  assert(document.querySelector('[data-superwoo-cart-scroll]').scrollTop === 30, 'cart jumped to the top after quantity update');
  assert(browserTest.refreshes === 0, 'duplicate Woo fragments request raced with the cart response');
  assert(browserTest.calls[0].url === 'mock-wc-ajax-update', 'quantity update did not use the faster WooCommerce AJAX endpoint');
  assert(browserTest.storeCartFetches === 1, 'cart update caused a redundant Store API cart-count request');

  browserTest.phase = 'nonce';
  document.querySelector('[data-superwoo-qty-plus]').click();
  await wait(240);
  assert(qty() === '4' && total() === 'INR3996', 'nonce retry did not apply updated prices');
  assert(browserTest.nonceRequests === 1 && browserTest.calls[browserTest.calls.length - 1].nonce === 'fresh', 'fresh nonce was not used');
  assert(browserTest.calls[browserTest.calls.length - 1].url === 'mock-wc-ajax-update', 'nonce retry left the WooCommerce AJAX route');

  browserTest.phase = 'ok';
  document.querySelector('[data-superwoo-qty-minus]').click();
  await wait(180);
  assert(qty() === '3' && price() === 'INR2997' && total() === 'INR2997', 'decrease did not refresh quantity and prices');

  browserTest.phase = 'fail';
  document.querySelector('[data-superwoo-qty-plus]').click();
  await wait(120);
  assert(qty() === '3' && total() === 'INR2997', 'failed request displayed an unsaved quantity or price');
  assert(!document.querySelector('[data-superwoo-cart-error]').hidden, 'AJAX error was not visible');

  browserTest.phase = 'mismatch';
  document.querySelector('[data-superwoo-qty-plus]').click();
  await wait(180);
  assert(qty() === '3' && total() === 'INR2997', 'mismatched response displayed an unsaved quantity');
  assert(!document.querySelector('[data-superwoo-cart-error]').hidden, 'mismatched response was silently accepted');
  browserTest.phase = 'softfail';
  document.querySelector('[data-superwoo-qty-plus]').click();
  await wait(140);
  assert(qty() === '3' && total() === 'INR2997', 'application-level failure did not restore confirmed quantity');

  browserTest.phase = 'ok';
  delete SuperWooCart.wcAjaxUrls.superwoo_update_cart_item;
  document.querySelector('[data-superwoo-qty-plus]').click();
  await wait(180);
  assert(qty() === '4' && total() === 'INR3996', 'admin AJAX fallback did not update the cart');
  assert(browserTest.calls[browserTest.calls.length - 1].url === 'mock-admin-ajax', 'admin AJAX fallback used the wrong URL');
  var callsBeforeBurst = browserTest.calls.length;
  document.querySelector('[data-superwoo-qty-plus]').click();
  document.querySelector('[data-superwoo-qty-plus]').click();
  document.querySelector('[data-superwoo-qty-plus]').click();
  assert(qty() === '7', 'rapid clicks were blocked before the AJAX response');
  await wait(190);
  assert(qty() === '7' && total() === 'INR6993', 'rapid clicks did not save the final quantity and price');
  assert(browserTest.calls.length === callsBeforeBurst + 1 && browserTest.calls[browserTest.calls.length - 1].qty === 7, 'rapid clicks were not combined into one request');
  callsBeforeBurst = browserTest.calls.length;
  document.querySelector('[data-superwoo-qty-plus]').click();
  await wait(48);
  document.querySelector('[data-superwoo-qty-plus]').click();
  document.querySelector('[data-superwoo-qty-plus]').click();
  assert(qty() === '10', 'clicks during an in-flight request were blocked');
  await wait(220);
  assert(qty() === '10' && total() === 'INR9990', 'queued clicks were not saved after the first response');
  assert(browserTest.calls.length === callsBeforeBurst + 2, 'in-flight clicks caused duplicate or lost requests');
  document.getElementById('result').textContent = 'PASS: fast quantity edits, saved totals, nonce retry, failure rollback, fallback and burst coalescing';
}
run().catch(function (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; });
</script></body></html>`;

const fixture = path.join(os.tmpdir(), `superwoo-cart-browser-${process.pid}.html`);
try {
    fs.writeFileSync(fixture, html, 'utf8');
    const output = execFileSync(chrome, [
        '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-background-networking',
        '--allow-file-access-from-files', '--no-first-run', '--virtual-time-budget=4000',
        '--dump-dom', pathToFileURL(fixture).href
    ], { encoding:'utf8', windowsHide:true, timeout:20000, maxBuffer:1024*1024*10 });
    const result = output.match(/<pre id="result">([^<]*)<\/pre>/);
    if (!result || !result[1].startsWith('PASS:')) {
        console.error(result ? result[1] : 'FAIL: browser result missing');
        process.exitCode = 1;
    } else {
        console.log(result[1]);
    }
} finally {
    if (fs.existsSync(fixture)) { fs.unlinkSync(fixture); }
}
