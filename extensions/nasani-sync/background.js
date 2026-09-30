// Background Service Worker for Nasani Projects Sync
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'OPEN_OR_FOCUS_NASANI') {
        const cleanTargetUrl = "https://projects.nasani.vn/ky-thuat/program/list/";
        const reportPayload = {
            reportCode: request.reportCode,
            adminUrl: request.adminUrl,
            adminUser: request.adminUser,
            adminPass: request.adminPass,
            createdAt: Date.now()
        };

        // Lưu vào chrome.storage.local để truyền dữ liệu nội bộ qua Extension mà KHÔNG đưa lên URL
        chrome.storage.local.set({ pending_auto_report: reportPayload }, () => {
            chrome.tabs.query({ url: "*://projects.nasani.vn/*" }, (tabs) => {
                if (tabs && tabs.length > 0) {
                    // Đã có tab projects.nasani.vn đang mở
                    const existingTab = tabs[0];
                    const windowId = existingTab.windowId;

                    if (windowId) {
                        chrome.windows.update(windowId, { focused: true });
                    }

                    chrome.tabs.update(existingTab.id, { active: true }, () => {
                        const currentUrl = existingTab.url || '';
                        if (currentUrl.includes('/program/list')) {
                            // Đang ở danh sách dự án, gửi message trực tiếp để thao tác ngay
                            chrome.tabs.sendMessage(existingTab.id, {
                                action: 'TRIGGER_AUTO_REPORT',
                                ...reportPayload
                            }, (resp) => {
                                if (!chrome.runtime.lastError && resp && resp.status === 'started') {
                                    // Đã nhận message trực tiếp -> Xóa ngay pending khỏi storage
                                    chrome.storage.local.remove(['pending_auto_report']);
                                } else {
                                    // Nếu content script chưa phản hồi, điều hướng tới clean URL
                                    chrome.tabs.update(existingTab.id, { url: cleanTargetUrl });
                                }
                            });
                        } else {
                            // Nếu đang ở trang khác trên projects.nasani.vn, chuyển tới clean URL
                            chrome.tabs.update(existingTab.id, { url: cleanTargetUrl });
                        }
                    });
                    sendResponse({ status: 'focused', tabId: existingTab.id });
                } else {
                    // Chưa có tab nào mở -> Tạo tab mới với URL sạch HOÀN TOÀN (KHÔNG chứa query params)
                    chrome.tabs.create({ url: cleanTargetUrl, active: true }, (newTab) => {
                        if (newTab.windowId) {
                            chrome.windows.update(newTab.windowId, { focused: true });
                        }
                        sendResponse({ status: 'created', tabId: newTab.id });
                    });
                }
            });
        });
        return true; // Giữ kênh kết nối async
    }
});
