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
        '--disable-setuid-sandbox',
        '--ignore-certificate-errors',
        '--allow-running-insecure-content',
        '--disable-web-security',
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

        let inFlightRequests = 0;
        let lastRequestTime = Date.now();

        ws.onmessage = (event) => {
            try {
                const msg = JSON.parse(event.data);
                if (msg.method === 'Network.requestWillBeSent') {
                    inFlightRequests++;
                    lastRequestTime = Date.now();
                } else if (msg.method === 'Network.loadingFinished' || msg.method === 'Network.loadingFailed') {
                    inFlightRequests = Math.max(0, inFlightRequests - 1);
                    lastRequestTime = Date.now();
                }

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

        // Chờ document.readyState === 'complete' hoặc timeout 6 giây
        const waitReadyState = async () => {
            const start = Date.now();
            while (Date.now() - start < 6000) {
                try {
                    const res = await send('Runtime.evaluate', {
                        expression: 'document.readyState',
                        returnByValue: true
                    });
                    if (res && res.result && res.result.value === 'complete') {
                        break;
                    }
                } catch (e) {}
                await new Promise(r => setTimeout(r, 200));
            }
        };
        await waitReadyState();

        // Chờ 1.5s để jQuery, apps.js và các slider (Splide, Swiper) khởi tạo hoàn tất
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

        // 3. Cuộn từ từ toàn trang để Splide, Swiper và các JS component kích hoạt tính toán kích thước
        try {
            await send('Runtime.evaluate', {
                expression: `new Promise((resolve) => {
                    let totalHeight = 0;
                    const distance = 400;
                    const scrollHeightLimit = Math.min(document.body.scrollHeight || 0, 15000);
                    const timer = setInterval(() => {
                        window.scrollBy(0, distance);
                        totalHeight += distance;
                        if (totalHeight >= scrollHeightLimit) {
                            clearInterval(timer);
                            window.scrollTo(0, 0);
                            window.dispatchEvent(new Event('resize'));
                            setTimeout(resolve, 500);
                        }
                    }, 50);
                })`,
                awaitPromise: true
            });
        } catch (e) {}

        // 4. Ép hiển thị 100% tất cả animation (AOS, data-sa, wow), tắt delay và transition
        try {
            await send('Runtime.evaluate', {
                expression: `(() => {
                    const style = document.createElement('style');
                    style.id = 'rbw-screenshot-override';
                    style.textContent = \`
                        *, *::before, *::after {
                            -webkit-transition: none !important;
                            -moz-transition: none !important;
                            -o-transition: none !important;
                            transition: none !important;
                            -webkit-animation: none !important;
                            -moz-animation: none !important;
                            -o-animation: none !important;
                            animation: none !important;
                        }
                        [data-sa], [data-aos], .wow, [class*="animate__"] {
                            opacity: 1 !important;
                            transform: none !important;
                            visibility: visible !important;
                        }
                        .sa-animate, .aos-animate, .animated {
                            opacity: 1 !important;
                            transform: none !important;
                            visibility: visible !important;
                        }
                    \`;
                    document.head.appendChild(style);

                    document.querySelectorAll('[data-sa]').forEach(el => el.classList.add('sa-animate'));
                    document.querySelectorAll('[data-aos]').forEach(el => el.classList.add('aos-animate'));
                    document.querySelectorAll('.wow').forEach(el => el.classList.add('animated'));
                })()`
            });
        } catch (e) {}

        // 5. Ép tải toàn bộ ảnh (data-src, lazy-load, loading="eager" và fonts ready)
        try {
            await send('Runtime.evaluate', {
                expression: `(async () => {
                    const imgs = document.querySelectorAll('img');
                    imgs.forEach(img => {
                        if (img.dataset.src && (!img.src || img.src.includes('data:image'))) img.src = img.dataset.src;
                        if (img.dataset.original && (!img.src || img.src.includes('data:image'))) img.src = img.dataset.original;
                        if (img.dataset.lazy && (!img.src || img.src.includes('data:image'))) img.src = img.dataset.lazy;
                        if (img.dataset.srcset && !img.srcset) img.srcset = img.dataset.srcset;

                        if (img.loading === 'lazy') {
                            img.loading = 'eager';
                            // Chỉ ép re-fetch nếu ảnh chưa nạp xong
                            if (!img.complete || img.naturalWidth === 0) {
                                const cur = img.src;
                                img.src = '';
                                img.src = cur;
                            }
                        }
                    });

                    const lazyBgs = document.querySelectorAll('[data-bg], [data-background], [data-bg-image]');
                    lazyBgs.forEach(el => {
                        const bg = el.dataset.bg || el.dataset.background || el.dataset.bgImage;
                        if (bg && !el.style.backgroundImage) {
                            el.style.backgroundImage = 'url("' + bg + '")';
                        }
                    });

                    // Chờ toàn bộ hình ảnh nạp hoàn tất
                    await Promise.all(Array.from(document.images).map(img => {
                        if (img.complete && img.naturalWidth !== 0) return Promise.resolve();
                        return new Promise(res => {
                            img.onload = res;
                            img.onerror = res;
                            setTimeout(res, 3500);
                        });
                    }));

                    if (document.fonts && document.fonts.ready) {
                        try { await document.fonts.ready; } catch(e) {}
                    }
                })()`,
                awaitPromise: true
            });
        } catch (e) {}

        // 6. Chờ Network Idle (không còn request nào trong vòng 400ms hoặc tối đa 3s)
        const waitNetworkIdle = async () => {
            const maxWait = 3000;
            const startWait = Date.now();
            while (Date.now() - startWait < maxWait) {
                if (inFlightRequests === 0 && (Date.now() - lastRequestTime > 400)) {
                    break;
                }
                await new Promise(r => setTimeout(r, 150));
            }
        };
        await waitNetworkIdle();

        // 7. Đợi layout và các slider animation ổn định lần cuối
        await new Promise(r => setTimeout(r, 500));

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
