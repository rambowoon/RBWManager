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

        let mainDocStatus = 200;

        ws.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.method === 'Network.responseReceived' && msg.params && msg.params.type === 'Document') {
                    if (msg.params.response && msg.params.response.status) {
                        mainDocStatus = msg.params.response.status;
                    }
                }
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

        await send('Network.enable');
        await send('Page.enable');
        await send('DOM.enable');

        // Allow up to 2 seconds for initial load
        await new Promise(r => setTimeout(r, 1500));

        // 1. Kiểm tra HTTP Status của trang chính
        if (mainDocStatus >= 400) {
            throw new Error(`Website trả về mã lỗi HTTP ${mainDocStatus} (trang không tồn tại hoặc lỗi máy chủ).`);
        }

        // 2. Kiểm tra nội dung text trang xem có phải trang lỗi / placeholder không
        try {
            const evalCheck = await send('Runtime.evaluate', {
                expression: `(() => {
                    const title = document.title || '';
                    const bodyText = (document.body ? document.body.innerText : '').substring(0, 3000);
                    const htmlSample = (document.documentElement ? document.documentElement.innerHTML : '').substring(0, 5000);
                    const combined = title + ' ' + bodyText + ' ' + htmlSample;

                    // Laravel & PHP Errors
                    if (/ErrorException/i.test(combined)) return 'Lỗi Laravel ErrorException';
                    if (/FatalErrorException/i.test(combined)) return 'Lỗi Laravel FatalErrorException';
                    if (/FatalThrowableError/i.test(combined)) return 'Lỗi Laravel FatalThrowableError';
                    if (/Undefined (property|variable|index|offset)/i.test(combined)) return 'Lỗi PHP: Undefined property/variable';
                    if (/Attempt to read property .* on (null|bool|string|array)/i.test(combined)) return 'Lỗi PHP: Attempt to read property on null';
                    if (/Trying to get property .* of non-object/i.test(combined)) return 'Lỗi PHP: Trying to get property of non-object';
                    if (/Call to undefined (function|method)/i.test(combined)) return 'Lỗi PHP: Call to undefined function/method';
                    if (/Whoops!/i.test(combined)) return 'Lỗi giao diện Whoops/Laravel';
                    if (/Whoops, looks like something went wrong/i.test(combined)) return 'Lỗi mặc định Laravel Whoops';
                    if (/View \\[.*?\\] not found/i.test(combined)) return 'Lỗi View Blade template không tìm thấy';
                    if (/Uncaught (Exception|Error)/i.test(combined)) return 'Lỗi Uncaught Exception/Error';
                    if (/Fatal error:/i.test(combined)) return 'Lỗi PHP Fatal error';
                    if (/Parse error:/i.test(combined)) return 'Lỗi PHP Parse error';

                    // Database Errors
                    if (/Error establishing a database connection/i.test(combined)) return 'Lỗi kết nối cơ sở dữ liệu (Database Error)';
                    if (/Database Error/i.test(combined)) return 'Lỗi cơ sở dữ liệu (Database Error)';
                    if (/SQLSTATE\\[/i.test(combined)) return 'Lỗi SQLSTATE Database';

                    // Server Defaults & Not Configured
                    if (/Apache is functioning normally/i.test(combined)) return 'Trang mặc định của Apache (chưa cấu hình source code)';
                    if (/Welcome to nginx!/i.test(combined)) return 'Trang mặc định của Nginx (chưa deploy code)';
                    if (/Default Web Site Page/i.test(combined)) return 'Trang mặc định của máy chủ';

                    // Generic HTTP Errors
                    if (/500 Internal Server Error/i.test(combined)) return 'Lỗi 500 Internal Server Error';
                    if (/404 Not Found/i.test(combined)) return 'Lỗi 404 Not Found';

                    return null;
                })()`,
                returnByValue: true
            });

            if (evalCheck && evalCheck.result && evalCheck.result.value) {
                throw new Error(`Website đang gặp sự cố: ${evalCheck.result.value}. Bỏ qua chụp ảnh.`);
            }
        } catch (errEval) {
            if (errEval.message && errEval.message.includes('Website đang gặp sự cố')) {
                throw errEval;
            }
        }

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
