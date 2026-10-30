/** Slow add responses must not delay opening or show invented cart totals. */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { pathToFileURL } = require('url');
const chrome = process.env.SUPERWOO_TEST_CHROME || (fs.existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome') ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : '');
const jquery = process.env.SUPERWOO_TEST_JQUERY;
if (!chrome || !jquery || !fs.existsSync(chrome) || !fs.existsSync(jquery)) {
    console.error('UNVERIFIED: configure SUPERWOO_TEST_CHROME and SUPERWOO_TEST_JQUERY'); process.exit(2);
}
const script = pathToFileURL(path.resolve(__dirname,'../public/js/cart-drawer.js')).href;
const css = pathToFileURL(path.resolve(__dirname,'../public/css/cart-drawer.css')).href;
const html = `<!doctype html><html><head><link rel="stylesheet" href="${css}"></head><body>
<button id="archive-add">Add to cart</button>
<div class="superwoo-cart-shell" data-superwoo-cart-shell aria-hidden="true"><div class="superwoo-cart-drawer"><div class="superwoo-cart-drawer__inner">
<button data-superwoo-close-cart>Close</button><span class="superwoo-cart-count">2</span>
<div class="superwoo-cart-live"></div><div data-superwoo-cart-error hidden></div><div data-superwoo-cart-scroll></div>
<div class="superwoo-cart-total"><div class="superwoo-cart-total__row superwoo-cart-total__row--grand-total">INR200</div></div>
<div class="superwoo-cart-drawer__footer"><div class="superwoo-privacy-consent"><label>Consent</label></div><a class="superwoo-cart-primary checkout" href="#checkout">Checkout</a></div>
<button class="superwoo-cross-sell__add" data-superwoo-add-cross-sell="42">Add</button></div></div></div>
<pre id="result">PENDING</pre><script src="${pathToFileURL(jquery).href}"></script><script>
window.SuperWooCart={ajaxUrl:'mock-admin',wcAjaxUrls:{superwoo_refresh_cart_drawer:'mock-storefront-refresh',superwoo_add_cross_sell:'mock-storefront-add'},autoOpen:true,cartCount:2,nonce:'valid',offerState:{discounts:{},gifts:{}},i18n:{adding:'Adding...',loading:'Loading...',updating:'Updating...',error:'Add failed',cartItems:'Cart items'}};
window.browserTest={requests:[],storeReads:0,mode:'ok',count:2,refreshDelay:120};
window.fetch=function(){browserTest.storeReads++;return Promise.resolve({ok:true,headers:{get:function(){return ''}},json:function(){return Promise.resolve({items_count:browserTest.count})}})};
function fragments(count){
 var old=document.querySelector('.superwoo-cart-drawer__inner').cloneNode(true);
 old.querySelector('.superwoo-cart-count').textContent=count;
 old.querySelector('.superwoo-cart-total__row').textContent='INR'+count*100;
 return {'.superwoo-cart-drawer__inner':old.outerHTML,'.superwoo-cart-count':'<span class="superwoo-cart-count">'+count+'</span>'};
}
jQuery.ajax=function(options){
 var d=jQuery.Deferred();browserTest.requests.push({action:options.data.action,url:options.url});
 var readCount=browserTest.count;
 setTimeout(function(){
  var count=options.data.action==='superwoo_add_cross_sell'?++browserTest.count:readCount;
  d.resolve({success:true,data:{fragments:fragments(count),count:count,offerState:{discounts:{},gifts:{}}}});
 },options.data.action==='superwoo_refresh_cart_drawer'?browserTest.refreshDelay:120);return d.promise();
};
document.getElementById('archive-add').addEventListener('click',function(){
 jQuery(document.body).trigger('adding_to_cart');
 var xhr={status:0};jQuery(document).trigger('ajaxSend',[xhr,{url:'https://example.test/?wc-ajax=add_to_cart'}]);
 var mode=browserTest.mode;
 setTimeout(function(){
  xhr.status=mode==='fail'?500:200;
  if(mode!=='fail'){
   var f=fragments(++browserTest.count);
   jQuery.each(f,function(selector,value){jQuery(selector).replaceWith(value)});
   jQuery(document.body).trigger('wc_fragments_refreshed');
   jQuery(document.body).trigger('added_to_cart',[f,'hash',jQuery('#archive-add')]);
  }
  jQuery(document).trigger('ajaxComplete',[xhr,{url:'https://example.test/?wc-ajax=add_to_cart'}]);
 },400);
});
</script><script src="${script}"></script><script>
function wait(ms){return new Promise(function(resolve){setTimeout(resolve,ms)})}
function assert(ok,message){if(!ok)throw Error(message)}
function shell(){return document.querySelector('[data-superwoo-cart-shell]')}
function total(){return document.querySelector('.superwoo-cart-total__row').textContent}
async function run(){
 await wait(50);
 assert(browserTest.requests.length===0&&browserTest.storeReads===0,'closed drawer sent a competing page-load cart request');
 var start=performance.now();document.getElementById('archive-add').click();
 assert(shell().classList.contains('is-open')&&performance.now()-start<100,'drawer did not open immediately during add');
 assert(!document.querySelector('[data-superwoo-cart-pending]').hidden,'pending feedback missing');
 assert(total()==='INR200','unconfirmed totals were invented');
 var checkout=document.querySelector('.checkout');
 assert(!checkout.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true})),'checkout was allowed before the add completed');
 await wait(180);assert(total()==='INR200'&&!document.querySelector('[data-superwoo-cart-pending]').hidden,'slow response was presented as a confirmed add');
 await wait(300);
 assert(total()==='INR300'&&document.querySelector('[data-superwoo-cart-pending]').hidden,'confirmed add did not finish smoothly');
 assert(browserTest.storeReads===0&&browserTest.requests.length===0,'confirmed native add caused an extra session/count request');
 var consent=getComputedStyle(document.querySelector('.superwoo-privacy-consent'));
 var row=getComputedStyle(document.querySelector('.superwoo-cart-total__row'));
 var add=getComputedStyle(document.querySelector('.superwoo-cross-sell__add'));
 assert(consent.fontSize==='10px'&&row.paddingTop==='3px','requested consent/total styling regressed');
 assert(parseFloat(row.lineHeight)/parseFloat(row.fontSize)===2&&add.fontSize==='15px'&&add.fontWeight==='600','requested totals/Add button typography regressed');
 browserTest.mode='fail';document.getElementById('archive-add').click();await wait(450);
 assert(total()==='INR300'&&document.querySelector('[data-superwoo-cart-pending]').hidden,'failed add did not retain the confirmed cart');
 assert(!document.querySelector('[data-superwoo-cart-error]').hidden,'failed add had no error');
 document.querySelector('[data-superwoo-close-cart]').click();window.SuperWooOpenCart();
 assert(shell().classList.contains('is-open')&&!document.querySelector('[data-superwoo-cart-pending]').hidden,'manual open waited for a lazy cart refresh');
 await wait(170);
 assert(browserTest.requests.length===1&&browserTest.requests[0].url==='mock-storefront-refresh','lazy refresh did not use one storefront request');
 assert(document.querySelector('[data-superwoo-cart-pending]').hidden&&total()==='INR300','lazy refresh did not clear loading with confirmed totals');
 browserTest.mode='ok';document.getElementById('archive-add').click();document.querySelector('[data-superwoo-close-cart]').click();
 await wait(450);assert(!shell().classList.contains('is-open'),'late add response reopened a drawer the user closed');
 window.SuperWooOpenCart();
 document.querySelector('[data-superwoo-add-cross-sell]').click();
 assert(!document.querySelector('[data-superwoo-cart-pending]').hidden,'recommendation add did not show pending feedback');
 await wait(170);
 assert(browserTest.requests.length===2&&browserTest.requests[1].url==='mock-storefront-add','custom add did not use the storefront endpoint');
 assert(total()==='INR500'&&document.querySelector('[data-superwoo-cart-pending]').hidden,'custom add response/count was not applied');
 assert(browserTest.storeReads===0,'custom add fetched an already-confirmed count again');
 browserTest.mode='fail';document.getElementById('archive-add').click();await wait(450);
 document.querySelector('[data-superwoo-close-cart]').click();browserTest.refreshDelay=650;window.SuperWooOpenCart();
 await wait(10);document.querySelector('[data-superwoo-close-cart]').click();browserTest.mode='ok';document.getElementById('archive-add').click();
 await wait(750);
 assert(total()==='INR600','an older cart read overwrote the newer confirmed add');
 assert(document.querySelector('[data-superwoo-cart-pending]').hidden,'overlapping read/add left loading stuck');
 document.getElementById('result').textContent='PASS: immediate open before a 400ms response, truthful loading/totals, error recovery, close intent, no startup/duplicate reads, stale-read rejection, storefront add, consent and typography';
}
run().catch(function(e){document.getElementById('result').textContent='FAIL: '+e.message});
</script></body></html>`;
const fixture=path.join(os.tmpdir(),'superwoo-add-performance-'+process.pid+'.html');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'superwoo-add-performance-profile-'));
try {
    fs.writeFileSync(fixture,html);
    const output=execFileSync(chrome,['--headless=new','--disable-gpu','--disable-background-networking','--allow-file-access-from-files','--no-first-run','--user-data-dir='+profile,'--virtual-time-budget=6000','--dump-dom',pathToFileURL(fixture).href],{encoding:'utf8',timeout:20000,maxBuffer:10*1024*1024});
    const result=output.match(/<pre id="result">([^<]*)<\/pre>/);
    if(!result||!result[1].startsWith('PASS:')){console.error(result?result[1]:'FAIL: missing browser result');process.exitCode=1;}
    else console.log(result[1]);
} finally {if(fs.existsSync(fixture))fs.unlinkSync(fixture);fs.rmSync(profile,{recursive:true,force:true,maxRetries:10,retryDelay:200});}
