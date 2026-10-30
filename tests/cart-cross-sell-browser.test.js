/** Verify one-card carousel movement with the real drawer CSS and JavaScript. */
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

const css = pathToFileURL(path.resolve(__dirname, '../public/css/cart-drawer.css')).href;
const script = pathToFileURL(path.resolve(__dirname, '../public/js/cart-drawer.js')).href;
const navigation = pathToFileURL(path.resolve(__dirname, '../public/js/cart-cross-sell.js')).href;
const markup = `<div class="superwoo-cross-sells"><div class="superwoo-cross-sells__header"><h3>You may also like</h3><div class="superwoo-cross-sells__controls"><button type="button" data-superwoo-cross-sell-nav="previous">Previous</button><button type="button" data-superwoo-cross-sell-nav="next">Next</button></div></div><div class="superwoo-cross-sells__row"><div class="superwoo-cross-sell">One</div><div class="superwoo-cross-sell">Two</div><div class="superwoo-cross-sell">Three</div><div class="superwoo-cross-sell">Four</div></div></div>`;
const html = `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="${css}"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}.superwoo-cart-shell{position:static!important;display:block!important;width:440px!important}.superwoo-cart-drawer{position:static!important;display:block!important;width:440px!important;max-width:none!important;transform:none!important}.superwoo-cart-drawer__inner{display:block!important}.superwoo-cross-sells{width:100%!important}</style></head><body><div data-superwoo-cart-shell class="superwoo-cart-shell is-open"><div class="superwoo-cart-drawer"><div class="superwoo-cart-drawer__inner"><div class="superwoo-cart-scroll" data-superwoo-cart-scroll>${markup}</div></div></div></div><pre id="result">PENDING</pre><script src="${pathToFileURL(jquery).href}"></script><script>window.SuperWooCart={offerState:{discounts:{},gifts:{}},i18n:{}};window.__superwooCartDrawerInitialized=true;</script><script src="${script}"></script><script src="${navigation}"></script><script>
function assert(ok,message){if(!ok)throw Error(message)}
function checkTouch(){
 var card=document.querySelector('.superwoo-cross-sell');
 function gesture(x,y){
  var point=new Touch({identifier:1,target:card,clientX:x,clientY:y});
  return new TouchEvent('touchmove',{bubbles:true,cancelable:true,touches:[point],targetTouches:[point],changedTouches:[point]});
 }
 function start(x,y){
  var point=new Touch({identifier:1,target:card,clientX:x,clientY:y});
  card.dispatchEvent(new TouchEvent('touchstart',{bubbles:true,cancelable:true,touches:[point],targetTouches:[point],changedTouches:[point]}));
 }
 start(200,200);
 var horizontal=gesture(140,198);card.dispatchEvent(horizontal);
 assert(!horizontal.defaultPrevented,'horizontal card drag was blocked');
 start(200,200);
 var vertical=gesture(198,140);card.dispatchEvent(vertical);
 assert(vertical.defaultPrevented,'vertical cart scrolling was not handled');
}
function check(){
 var section=document.querySelector('.superwoo-cross-sells'), row=section.querySelector('.superwoo-cross-sells__row');
 var cards=row.querySelectorAll('.superwoo-cross-sell');
 var step=cards[1].offsetLeft-cards[0].offsetLeft;
 var next=section.querySelector('[data-superwoo-cross-sell-nav="next"]');
 var previous=section.querySelector('[data-superwoo-cross-sell-nav="previous"]');
 var rect=next.getBoundingClientRect();
 assert(document.elementFromPoint(rect.left+rect.width/2,rect.top+rect.height/2).closest('[data-superwoo-cross-sell-nav]')===next,'next arrow is covered by another element');
 assert(!next.disabled,'next arrow is disabled before the first click');
 row.scrollLeft=0;
 assert(row.scrollWidth-row.clientWidth >= step*1.8,'not enough scroll distance for one-card steps: '+(row.scrollWidth-row.clientWidth)+' vs '+step);
 document.elementFromPoint(rect.left+rect.width/2,rect.top+rect.height/2).closest('button').click();assert(Math.abs(row.scrollLeft-step)<2,'first pointer-target next did not move one card: '+row.scrollLeft+' vs '+step);
 next.click();assert(Math.abs(row.scrollLeft-step*2)<2,'second next did not move one card: '+row.scrollLeft+' vs '+step*2);
 previous.click();assert(Math.abs(row.scrollLeft-step)<2,'previous did not move one card: '+row.scrollLeft+' vs '+step);
 section.outerHTML=${JSON.stringify(markup)};
 section=document.querySelector('.superwoo-cross-sells');row=section.querySelector('.superwoo-cross-sells__row');
 section.querySelector('[data-superwoo-cross-sell-nav="next"]').click();
 assert(Math.abs(row.scrollLeft-step)<2,'next failed after fragment replacement: '+row.scrollLeft);
 document.getElementById('result').textContent='PASS: carousel moves one card both ways and after fragment replacement';
}
setTimeout(function(){try{
 var initialNext=document.querySelector('[data-superwoo-cross-sell-nav="next"]');
 initialNext.disabled=true;
 setTimeout(function(){try{
 assert(!initialNext.disabled,'stale cart script left next arrow disabled');
 check();
 document.querySelector('.superwoo-cart-shell').style.setProperty('width','375px','important');
 document.querySelector('.superwoo-cart-drawer').style.setProperty('width','375px','important');
 check();
 if(window.__testTouch)checkTouch();
 var section=document.querySelector('.superwoo-cross-sells'),row=section.querySelector('.superwoo-cross-sells__row');
 row.lastElementChild.remove();
 row.scrollLeft=0;
 var step=row.children[1].offsetLeft-row.children[0].offsetLeft;
 section.querySelector('[data-superwoo-cross-sell-nav="next"]').click();
 assert(Math.abs(row.scrollLeft-step)<2,'three-card row did not advance one complete card');
 document.getElementById('result').textContent='PASS: desktop, narrow viewport, stale bundle, pointer target, three-card row, both directions and fragment replacement';
}catch(e){document.getElementById('result').textContent='FAIL: '+e.message}},50);
}catch(e){document.getElementById('result').textContent='FAIL: '+e.message}},150);
</script></body></html>`;
const fixture = path.join(os.tmpdir(), `superwoo-cross-sell-${process.pid}.html`);
try {
    for (const [mode, source] of [['stale cart bundle', html], ['current cart bundle', html.replace('window.__superwooCartDrawerInitialized=true;', 'window.__testTouch=true;')]]) {
        fs.writeFileSync(fixture, source, 'utf8');
        const output = execFileSync(chrome, ['--headless=new', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--allow-file-access-from-files', '--no-first-run', '--virtual-time-budget=3000', '--dump-dom', pathToFileURL(fixture).href], { encoding:'utf8', windowsHide:true, timeout:20000, maxBuffer:1024*1024*10 });
        const result = output.match(/<pre id="result">([^<]*)<\/pre>/);
        if (!result || !result[1].startsWith('PASS:')) { console.error(mode + ': ' + (result ? result[1] : 'FAIL: browser result missing')); process.exitCode=1; }
        else { console.log(mode + ': ' + result[1]); }
    }
} finally { if (fs.existsSync(fixture)) fs.unlinkSync(fixture); }
