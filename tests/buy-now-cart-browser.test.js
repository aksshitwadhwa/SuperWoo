/** Ensure Razorpay's product button cannot run before the original cart is saved. */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { pathToFileURL } = require('url');

const chrome = process.env.SUPERWOO_TEST_CHROME || (fs.existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome') ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe');
if (!fs.existsSync(chrome)) {
    console.error('UNVERIFIED: Chrome is unavailable');
    process.exit(2);
}

const script = pathToFileURL(path.resolve(__dirname, '../public/js/buy-now-cart.js')).href;
const html = `<!doctype html><html><head><meta charset="utf-8"></head><body>
<button id="btn-1cc-pdp" type="button">Buy Now</button><pre id="result">PENDING</pre>
<script>
window.SuperWooBuyNowCart={ajaxUrl:'mock-admin-ajax',nonce:'old',error:'Cart could not be saved'};
window.browserTest={mode:'ok',gatewayClicks:0,requests:[]};
window.fetch=function(url,options){
 var data=new URLSearchParams(options.body),action=data.get('action');
 browserTest.requests.push({action:action,nonce:data.get('nonce')});
 return new Promise(function(resolve){setTimeout(function(){
  var status=200,payload={success:true,data:{saved:true,count:2}};
  if(action==='superwoo_refresh_buy_now_nonce')payload={success:true,data:{nonce:'fresh'}};
  else if(browserTest.mode==='fail') {status=500;payload={success:false,data:{code:'cart_unavailable'}};}
  else if(browserTest.mode==='nonce'&&data.get('nonce')!=='fresh') {status=403;payload={success:false,data:{code:'superwoo_buy_now_invalid_nonce'}};}
  resolve({status:status,json:function(){return Promise.resolve(payload)}});
 },35)});
};
document.getElementById('btn-1cc-pdp').addEventListener('click',function(){browserTest.gatewayClicks+=1});
</script>
<script src="${script}"></script>
<script>
function wait(ms){return new Promise(function(resolve){setTimeout(resolve,ms)})}
function assert(ok,message){if(!ok)throw Error(message)}
async function run(){
 var button=document.getElementById('btn-1cc-pdp');
 button.click();button.click();
 assert(browserTest.gatewayClicks===0,'Razorpay ran before snapshot confirmation');
 await wait(110);
 assert(browserTest.gatewayClicks===1,'confirmed snapshot did not start Razorpay exactly once');
 assert(browserTest.requests.length===1,'duplicate click sent duplicate snapshots');
 assert(sessionStorage.getItem('superwoo_buy_now_pending')==='1','cart restoration marker missing');
 browserTest.mode='fail';button.click();await wait(100);
 assert(browserTest.gatewayClicks===1,'Razorpay ran despite failed snapshot');
 assert(document.querySelector('[data-superwoo-buy-now-error]'),'failed snapshot had no visible error');
 browserTest.mode='nonce';button.click();await wait(150);
 assert(browserTest.gatewayClicks===2,'nonce refresh did not allow protected Buy Now');
 assert(browserTest.requests.slice(-3).map(function(item){return item.action}).join(',')==='superwoo_snapshot_buy_now_cart,superwoo_refresh_buy_now_nonce,superwoo_snapshot_buy_now_cart','nonce retry sequence incorrect');
 document.getElementById('result').textContent='PASS: Buy Now waits for snapshot, blocks failure, coalesces clicks and refreshes nonce';
}
run().catch(function(error){document.getElementById('result').textContent='FAIL: '+error.message});
</script></body></html>`;
const fixture = path.join(os.tmpdir(), `superwoo-buy-now-${process.pid}.html`);
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'superwoo-buy-now-profile-'));
try {
    fs.writeFileSync(fixture, html, 'utf8');
    const output = execFileSync(chrome, ['--headless=new', '--disable-gpu', '--disable-background-networking', '--allow-file-access-from-files', '--no-first-run', '--user-data-dir=' + profile, '--virtual-time-budget=3000', '--dump-dom', pathToFileURL(fixture).href], { encoding:'utf8', windowsHide:true, timeout:20000, maxBuffer:1024*1024*10 });
    const result = output.match(/<pre id="result">([^<]*)<\/pre>/);
    if (!result || !result[1].startsWith('PASS:')) { console.error(result ? result[1] : 'FAIL: browser result missing'); process.exitCode=1; }
    else { console.log(result[1]); }
} finally { if (fs.existsSync(fixture)) fs.unlinkSync(fixture); fs.rmSync(profile, { recursive:true, force:true, maxRetries:10, retryDelay:200 }); }
