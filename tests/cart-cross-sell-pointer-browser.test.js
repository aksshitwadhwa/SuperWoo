/** Exercise carousel arrows with actual Chrome pointer events. */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');
const { pathToFileURL } = require('url');

const chrome = process.env.SUPERWOO_TEST_CHROME || (fs.existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome') ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' : 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe');
if (!fs.existsSync(chrome)) {
    console.error('UNVERIFIED: Chrome is unavailable');
    process.exit(2);
}

const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'superwoo-cross-sell-pointer-'));
const profile = path.join(temp, 'chrome');
const fixture = path.join(temp, 'fixture.html');
const css = pathToFileURL(path.resolve(__dirname, '../public/css/cart-drawer.css')).href;
const js = pathToFileURL(path.resolve(__dirname, '../public/js/cart-cross-sell.js')).href;
fs.writeFileSync(fixture, `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="${css}"><style>*,*::before,*::after{box-sizing:border-box}body{margin:0}.superwoo-cart-shell,.superwoo-cart-drawer{width:440px!important;max-width:none!important;position:static!important;display:block!important;transform:none!important}.superwoo-cart-drawer__inner{display:block!important}</style></head><body><div data-superwoo-cart-shell class="superwoo-cart-shell is-open"><div class="superwoo-cart-drawer"><div class="superwoo-cart-drawer__inner"><div class="superwoo-cross-sells"><div class="superwoo-cross-sells__header"><h3>You may also like</h3><div class="superwoo-cross-sells__controls"><button type="button" class="superwoo-cross-sells__arrow" data-superwoo-cross-sell-nav="previous" aria-label="Previous"><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button><button type="button" class="superwoo-cross-sells__arrow" data-superwoo-cross-sell-nav="next" aria-label="Next"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button></div></div><div class="superwoo-cross-sells__row"><div class="superwoo-cross-sell">One</div><div class="superwoo-cross-sell">Two</div><div class="superwoo-cross-sell">Three</div><div class="superwoo-cross-sell">Four</div></div></div></div></div></div><script>window.__superwooCartDrawerInitialized=true;</script><script src="${js}"></script></body></html>`, 'utf8');

const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--disable-background-networking', '--remote-allow-origins=*', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { stdio: 'ignore', windowsHide: true });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
let nextId = 0;
const pending = new Map();

async function send(method, params = {}) {
    const id = ++nextId;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => { pending.delete(id); reject(new Error('CDP timed out: ' + method)); }, 8000);
        pending.set(id, { resolve, reject, timer });
        socket.send(JSON.stringify({ id, method, params }));
    });
}

async function evaluate(expression) {
    const response = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (response.exceptionDetails) {
        throw new Error(response.exceptionDetails.text);
    }
    return response.result.value;
}

async function pointerClick(direction) {
    const point = await evaluate(`(() => { const button=document.querySelector('[data-superwoo-cross-sell-nav="${direction}"]');const r=button.getBoundingClientRect();const x=r.left+r.width/2,y=r.top+r.height/2;return {x,y,hit:document.elementFromPoint(x,y).closest('[data-superwoo-cross-sell-nav]')===button,disabled:button.disabled};})()`);
    if (!point.hit || point.disabled) {
        throw new Error(direction + ' arrow cannot receive a pointer click: ' + JSON.stringify(point));
    }
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: point.x, y: point.y });
    await send('Input.dispatchMouseEvent', { type: 'mousePressed', x: point.x, y: point.y, button: 'left', clickCount: 1 });
    await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: point.x, y: point.y, button: 'left', clickCount: 1 });
}

async function scenario(width) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 700, deviceScaleFactor: 1, mobile: width < 500 });
    await evaluate(`document.querySelector('.superwoo-cart-shell').style.setProperty('width','${Math.min(width, 440)}px','important');document.querySelector('.superwoo-cart-drawer').style.setProperty('width','${Math.min(width, 440)}px','important');document.querySelector('.superwoo-cross-sells__row').scrollLeft=0`);
    const step = await evaluate(`(() => { const row=document.querySelector('.superwoo-cross-sells__row');return row.children[1].offsetLeft-row.children[0].offsetLeft;})()`);
    await pointerClick('next');
    const first = await evaluate(`document.querySelector('.superwoo-cross-sells__row').scrollLeft`);
    if (Math.abs(first - step) > 2) throw new Error(width + 'px: next moved ' + first + ', expected one card ' + step);
    await pointerClick('next');
    const second = await evaluate(`document.querySelector('.superwoo-cross-sells__row').scrollLeft`);
    if (Math.abs(second - 2 * step) > 2) throw new Error(width + 'px: second next moved ' + second + ', expected ' + 2 * step);
    await pointerClick('previous');
    const back = await evaluate(`document.querySelector('.superwoo-cross-sells__row').scrollLeft`);
    if (Math.abs(back - step) > 2) throw new Error(width + 'px: previous moved ' + back + ', expected ' + step);
}

(async () => {
    let port;
    for (let attempt = 0; attempt < 100; attempt++) {
        if (fs.existsSync(path.join(profile, 'DevToolsActivePort'))) {
            port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split(/\r?\n/)[0];
            break;
        }
        await delay(100);
    }
    if (!port) throw new Error('Chrome DevTools did not start');
    const targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
    const page = targets.find(target => target.type === 'page');
    if (!page) throw new Error('Chrome did not open a page');
    socket = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    socket.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        const request = pending.get(message.id);
        if (!request) return;
        pending.delete(message.id);
        clearTimeout(request.timer);
        if (message.error) request.reject(new Error(message.error.message));
        else request.resolve(message.result);
    });
    await send('Page.navigate', { url: pathToFileURL(fixture).href });
    for (let attempt = 0; attempt < 50; attempt++) {
        if (await evaluate(`document.readyState==='complete' && !!document.querySelector('.superwoo-cross-sells__row')`)) break;
        await delay(100);
    }
    await scenario(900);
    await scenario(375);
    console.log('PASS: real Chrome pointer clicks move exactly one card forward/back at desktop and mobile widths');
})().catch(error => { console.error('FAIL: ' + error.message); process.exitCode = 1; }).finally(async () => {
    if (socket && socket.readyState === WebSocket.OPEN) {
        try { await Promise.race([send('Browser.close'), delay(500)]); } catch (error) { /* Chrome may close before replying. */ }
        socket.close();
    }
    browser.kill();
    await delay(500);
    if (path.resolve(temp).startsWith(path.resolve(os.tmpdir()) + path.sep)) {
        try { fs.rmSync(temp, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 }); }
        catch (error) { console.error('Chrome kept its temporary profile open: ' + temp); }
    }
});
