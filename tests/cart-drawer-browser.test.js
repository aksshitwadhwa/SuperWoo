/** Exercise the real cart drawer click handler in headless Chrome with mocked AJAX. */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { pathToFileURL } = require('url');

const chrome = process.env.SUPERWOO_TEST_CHROME || (fs.existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome') ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe');
const localJquery = path.join(os.homedir(), 'superwoo-meta-test', 'wp-includes', 'js', 'jquery', 'jquery.min.js');
const jquery = process.env.SUPERWOO_TEST_JQUERY || (fs.existsSync(localJquery) ? localJquery : 'C:\\Users\\Rakes\\Local Sites\\ggm2\\app\\public\\wp-includes\\js\\jquery\\jquery.min.js');
if (!fs.existsSync(chrome) || !fs.existsSync(jquery)) {
    console.error('UNVERIFIED: Chrome or local WordPress jQuery is unavailable');
    process.exit(2);
}

const drawerScript = pathToFileURL(path.resolve(__dirname, '../public/js/cart-drawer.js')).href;
const html = `<!doctype html><html><head><meta charset="utf-8"></head><body>
<a href="#superwoo-cart" data-superwoo-open-cart id="cart-opener">Open cart</a>
<div data-superwoo-cart-shell class="superwoo-cart-shell is-open" aria-hidden="false">
  <div class="superwoo-cart-drawer" role="dialog" aria-modal="true" aria-label="Shopping cart" tabindex="-1"><div class="superwoo-cart-drawer__inner">
    <div class="superwoo-cart-drawer__header"><button type="button" aria-label="Close cart" data-superwoo-close-cart>×</button><span class="superwoo-cart-count">2</span></div>
    <div class="superwoo-cart-live"></div><div data-superwoo-cart-error hidden></div>
    <div data-superwoo-cart-scroll style="height:80px;overflow:auto">
    <div class="superwoo-cart-item" data-cart-item-key="line-a">
      <button type="button" data-superwoo-qty-minus>-</button>
      <input data-superwoo-qty-input data-superwoo-qty-committed="2" value="2">
      <button type="button" data-superwoo-qty-plus>+</button>
      <span class="superwoo-cart-item__price">INR1555</span>
    </div>
    <div class="superwoo-cross-sells" data-test-recommendation style="height:140px">Recommended products</div></div>
    <div class="superwoo-cart-total">INR1555</div>
    <div class="superwoo-cart-drawer__footer"><div class="superwoo-privacy-consent"><input type="checkbox" data-superwoo-privacy-consent><label>Privacy Policy</label></div><p class="superwoo-privacy-consent__error" hidden>Please acknowledge the Privacy Policy to continue.</p><a class="superwoo-cart-primary checkout" href="#checkout">INR1555</a></div>
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
window.browserTest = {phase:'ok', calls:[], refreshes:0, nonceRequests:0, storeCartFetches:0, initialDrawerRefreshes:0};
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
  } else if (action === 'superwoo_refresh_cart_drawer') {
    browserTest.initialDrawerRefreshes += 1;
    var currentInner = document.querySelector('.superwoo-cart-drawer__inner').outerHTML.replace(/INR1555/g, 'INR1998');
    setTimeout(function () { deferred.resolve({success:true,data:{fragments:{'.superwoo-cart-drawer__inner':currentInner},count:2,offerState:{discounts:{},gifts:{}}}}); }, 10);
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
  assert(browserTest.initialDrawerRefreshes === 1, 'initial drawer was not refreshed from the server exactly once');
  assert(price() === 'INR1998' && total() === 'INR1998', 'stale initial prices were not replaced with server totals');
  var opener=document.getElementById('cart-opener');
  opener.focus();
  opener.click();
  await wait(30);
  var dialog=document.querySelector('.superwoo-cart-drawer');
  var dialogButtons=dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled])');
  var firstFocusable=dialogButtons[0], lastFocusable=dialogButtons[dialogButtons.length-1];
  lastFocusable.focus();
  document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}));
  assert(document.activeElement===firstFocusable,'Tab did not wrap from the final cart control to the first');
  firstFocusable.focus();
  document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',shiftKey:true,bubbles:true,cancelable:true}));
  assert(document.activeElement===lastFocusable,'Shift+Tab did not wrap from the first cart control to the final control');
  opener.focus();
  document.dispatchEvent(new KeyboardEvent('keydown',{key:'Tab',bubbles:true,cancelable:true}));
  assert(document.activeElement===firstFocusable,'Tab did not bring escaped focus back into the open cart');
  document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));
  assert(document.activeElement===opener && document.querySelector('[data-superwoo-cart-shell]').getAttribute('aria-hidden')==='true','Escape did not close the cart and restore trigger focus');
  opener.click();
  await wait(30);
  var checkout=document.querySelector('.superwoo-cart-primary.checkout');
  var checkbox=document.querySelector('[data-superwoo-privacy-consent]');
  var checkoutClicks=0;
  checkout.addEventListener('click',function(){checkoutClicks+=1;});
  checkout.click();
  assert(checkoutClicks===0 && !document.querySelector('.superwoo-privacy-consent__error').hidden && document.activeElement===checkbox,'checkout was not held for privacy acknowledgement');
  checkbox.checked=true;
  checkbox.dispatchEvent(new Event('change',{bubbles:true}));
  checkout.click();
  assert(checkoutClicks===1 && document.querySelector('.superwoo-privacy-consent__error').hidden,'acknowledged shopper could not proceed to checkout');
  document.querySelector('[data-superwoo-cart-scroll]').scrollTop = 30;
  document.querySelector('[data-superwoo-qty-plus]').click();
  assert(qty() === '3' && total() === 'INR1998', 'quantity did not respond immediately or price changed before WooCommerce confirmed it');
  await wait(180);
  assert(qty() === '3' && price() === 'INR2997' && total() === 'INR2997', 'first AJAX update did not refresh quantity and prices');
  assert(document.querySelector('[data-test-recommendation]'), 'compact response removed recommendation cards');
  assert(document.querySelector('[data-superwoo-cart-scroll]').scrollTop === 30, 'cart jumped to the top after quantity update');
  assert(browserTest.refreshes === 0, 'duplicate Woo fragments request raced with the cart response');
  assert(browserTest.calls[0].url === 'mock-wc-ajax-update', 'quantity update did not use the faster WooCommerce AJAX endpoint');
  assert(browserTest.storeCartFetches === 0, 'cart update caused a redundant Store API cart-count request');

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
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'superwoo-cart-browser-profile-'));
try {
    fs.writeFileSync(fixture, html, 'utf8');
    const output = execFileSync(chrome, [
        '--headless=new', '--disable-gpu', '--disable-background-networking',
        '--allow-file-access-from-files', '--no-first-run', '--user-data-dir=' + profile, '--virtual-time-budget=4000',
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
    fs.rmSync(profile, { recursive:true, force:true, maxRetries:10, retryDelay:200 });
}
