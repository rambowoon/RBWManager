const { spawn } = require('child_process');
const fs = require('fs');
const net = require('net');

function getFreePort() {
    return new Promise((resolve, reject) => {
        const srv = net.createServer();
        srv.listen(0, '127.0.0.1', () => {
            const port = srv.address().port;
            srv.close(() => resolve(port));
        });
        srv.on('error', reject);
    });
}

function findBrowser() {
    const candidates = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'
    ];
    for (const p of candidates) {
        if (fs.existsSync(p)) return p;
    }
    return null;
}

async function capture(url, outputPath) {
    const browserPath = findBrowser();
    if (!browserPath) {
        throw new Error('Không tìm thấy trình duyệt Chrome hoặc Edge.');
    }

    const port = await getFreePort();
    const chrome = spawn(browserPath, [
        '--headless=new',
        `--remote-debugging-port=${port}`,
        '--disable-gpu',
        '--no-sandbox',
        '--hide-scrollbars',
        '--mute-audio',
        '--window-size=1280,800',
        'about:blank'
    ]);

    const killChrome = () => {
        try { chrome.kill(); } catch (e) {}
    };

    const cleanup = () => {
        killChrome();
    };

    process.on('exit', cleanup);
    process.on('SIGINT', () => { cleanup(); process.exit(1); });

    try {
        // Wait for CDP endpoint
        let versionData = null;
        for (let i = 0; i < 30; i++) {
            await new Promise(r => setTimeout(r, 150));
            try {
                const res = await fetch(`http://127.0.0.1:${port}/json/version`);
                if (res.ok) {
                    versionData = await res.json();
                    if (versionData && versionData.webSocketDebuggerUrl) break;
                }
            } catch (e) {}
        }

        if (!versionData || !versionData.webSocketDebuggerUrl) {
            throw new Error('Không thể kết nối Chrome CDP');
        }

        // Create page
        const newPageRes = await fetch(`http://127.0.0.1:${port}/json/new?${encodeURIComponent(url)}`, { method: 'PUT' });
        const pageData = await newPageRes.json();
        const wsUrl = pageData.webSocketDebuggerUrl;

        const ws = new WebSocket(wsUrl);
        let msgId = 1;
        const callbacks = new Map();

        ws.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.id && callbacks.has(msg.id)) {
                    const cb = callbacks.get(msg.id);
                    callbacks.delete(msg.id);
                    if (msg.error) cb.reject(new Error(msg.error.message));
                    else cb.resolve(msg.result);
                }
            } catch (e) {}
        };

        const send = (method, params = {}) => {
            return new Promise((resolve, reject) => {
                const id = msgId++;
                callbacks.set(id, { resolve, reject });
                ws.send(JSON.stringify({ id, method, params }));
            });
        };

        await new Promise((resolve, reject) => {
            ws.onopen = resolve;
            ws.onerror = reject;
        });

        await send('Page.enable');
        await send('DOM.enable');

        // Allow up to 3 seconds for initial load
        await new Promise(r => setTimeout(r, 1500));

        // Auto-scroll to trigger lazy loaded images, then scroll back to top
        try {
            await send('Runtime.evaluate', {
                expression: `new Promise((resolve) => {
                    let totalHeight = 0;
                    let distance = 350;
                    let timer = setInterval(() => {
                        let scrollHeight = Math.min(document.body.scrollHeight || 0, 8000);
                        window.scrollBy(0, distance);
                        totalHeight += distance;
                        if (totalHeight >= scrollHeight) {
                            clearInterval(timer);
                            window.scrollTo(0, 0);
                            setTimeout(resolve, 300);
                        }
                    }, 60);
                })`,
                awaitPromise: true,
                returnByValue: true
            });
        } catch (e) {
            // Ignore if page blocks eval
        }

        // Wait a tiny bit for layout stabilization
        await new Promise(r => setTimeout(r, 400));

        // Full page screenshot
        const captureResult = await send('Page.captureScreenshot', {
            format: 'webp',
            quality: 82,
            captureBeyondViewport: true
        });

        if (!captureResult || !captureResult.data) {
            throw new Error('Dữ liệu screenshot rỗng');
        }

        const buffer = Buffer.from(captureResult.data, 'base64');
        fs.writeFileSync(outputPath, buffer);

        try { ws.close(); } catch (e) {}
        killChrome();

        console.log(JSON.stringify({
            status: 'success',
            file: outputPath,
            size: buffer.length
        }));
    } catch (err) {
        killChrome();
        console.error(JSON.stringify({
            status: 'error',
            message: err.message
        }));
        process.exit(1);
    }
}

const args = process.argv.slice(2);
if (args.length < 2) {
    console.error('Usage: node capture_fullpage.js <url> <outputPath>');
    process.exit(1);
}

capture(args[0], args[1]);
