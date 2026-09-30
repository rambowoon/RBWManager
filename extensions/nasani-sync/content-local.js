// Content Script injected into localhost / RBWManager
document.documentElement.setAttribute('data-rbw-nasani-ext', '1');
window._rbwNasaniExtLoaded = true;

window.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'RBW_OPEN_OR_FOCUS_NASANI') {
        try {
            chrome.runtime.sendMessage({
                action: 'OPEN_OR_FOCUS_NASANI',
                url: event.data.url,
                reportCode: event.data.reportCode,
                adminUrl: event.data.adminUrl,
                adminUser: event.data.adminUser,
                adminPass: event.data.adminPass
            }, (res) => {
                // Background đã xử lý
            });
        } catch (e) {
            console.warn('[NasaniSync Bridge] Background message error:', e);
        }
    }
});
