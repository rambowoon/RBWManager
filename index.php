<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RamboWoon Manager | Admin Dashboard</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">
</head>

<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div class="logo-text">RamboWoon</div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-item" onclick="App.showDashboard(); App.loadProjects('')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Bảng điều khiển
                </div>
                <div class="nav-item" onclick="App.showDashboard(); App.showCategories()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    Dự án theo tháng
                </div>
                <div class="menu-divider"></div>
                <div class="nav-item" onclick="ConverterUI.show()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Công cụ ảnh
                </div>
                <div class="nav-item" onclick="App.showAIChecker()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Kiểm tra AI Models
                </div>
                <div class="nav-item" onclick="App.showCacheClearer()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/></svg>
                    Xóa cache trình duyệt
                </div>
                <div class="menu-divider"></div>
                <div class="nav-item" onclick="App.showGlobalConfig()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Setting
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="system-status-box">
                    <div class="system-status-title">SYSTEM STATUS</div>
                    <div class="system-status-content">
                        <span class="system-status-dot"></span>
                        Connected
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header>
                <div class="header-left">
                    <div id="nav-header" class="nav-header-flex">
                        <button class="btn btn-ghost" onclick="App.showCategories()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        </button>
                        <div class="category-breadcrumb">Dự án / <span id="current-category" class="category-current">Toàn bộ</span></div>
                    </div>
                    <div id="dashboard-breadcrumb" class="dashboard-breadcrumb-text">Bảng điều khiển</div>
                </div>

                <div class="header-right flex-center-gap">
                    <button class="btn btn-primary" onclick="App.showDeployProjectModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Triển khai dự án
                    </button>
                    <button class="btn btn-ghost" onclick="App.loadProjects('')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Làm mới
                    </button>
                </div>
            </header>

            <div class="content-body">
                <!-- VIEW: DASHBOARD -->
                <div id="view-dashboard" class="view-section">
                    <div class="stats-grid" id="stats-summary">
                        <div class="stat-card">
                            <div class="stat-label">Tổng dự án</div>
                            <div class="stat-value" id="stat-total-projects">--</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Đã cấu hình</div>
                            <div class="stat-value" id="stat-configured" style="color:var(--success)">--</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Dự án Demo</div>
                            <div class="stat-value" id="stat-demo" style="color:var(--purple)">--</div>
                        </div>
                    </div>

                    <!-- Content Area (Split View) -->
                    <div class="projects-split-view">
                        <!-- Left Sidebar: Months -->
                        <div id="category-sidebar" class="category-sidebar-nav">
                            <div class="subtitle">Đang tải...</div>
                        </div>

                        <!-- Right Content: Projects -->
                        <div class="projects-content-area">
                            <div class="section-header">
                                <h2 class="section-title" id="content-title">Danh sách dự án</h2>
                            </div>

                            <div id="project-list" class="grid-container">
                                <!-- Dynamic content here -->
                                <div class="subtitle">Chọn một tháng để xem dự án...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW: PROJECT DETAIL (FULL PAGE) -->
                <div id="view-project-detail" class="view-section" style="display:none;">
                    <div class="section-header detail-section-header">
                        <div class="flex-align-center-gap20">
                            <button class="btn btn-ghost btn-back" onclick="App.showDashboard()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Quay lại
                            </button>
                            <h2 class="section-title detail-title" id="detail-project-name">Tên dự án</h2>
                        </div>
                        <div id="detail-project-status"></div>
                    </div>

                    <div class="project-master-tabs">
                        <button class="btn btn-ghost project-tab-btn active" onclick="UI.switchProjectTab(this, 'd_tab-config')">⚙️ Cấu hình &amp; Deploy</button>
                        <button class="btn btn-ghost project-tab-btn" onclick="UI.switchProjectTab(this, 'd_tab-fonts')">🖋️ Quản lý Fonts</button>
                        <button class="btn btn-ghost project-tab-btn" onclick="UI.switchProjectTab(this, 'd_tab-webp')">🖼️ Convert Ảnh WebP</button>
                        <button class="btn btn-ghost project-tab-btn" onclick="UI.switchProjectTab(this, 'd_tab-trim')">✂️ Trim Ảnh</button>
                        <button class="btn btn-ghost project-tab-btn" onclick="UI.switchProjectTab(this, 'd_tab-auto-media')">🤖 Tự động Map Ảnh</button>
                        <button class="btn btn-ghost project-tab-btn" onclick="UI.switchProjectTab(this, 'd_tab-seed')">🌱 Tạo Dữ Liệu Mẫu</button>
                    </div>

                    <!-- TAB: CONFIG & DEPLOY -->
                    <div id="d_tab-config" class="project-tab-content project-master-layout">
                        <!-- Left: Info & Config -->
                        <div class="master-config-area">
                            <div id="d_master-deployed-info" class="deployed-info-container"></div>

                            <div class="hosting-config-header">
                                <label class="label-m0">⚙️ CẤU HÌNH HOSTING</label>
                                <button class="btn btn-ghost btn-sm btn-quick-paste" onclick="UI.showModal('quick-paste-modal')">
                                    ⚡ Nhập nhanh cấu hình
                                </button>
                            </div>

                            <div class="card-container card-padded">
                                <form id="detail-config-form">
                                    <input type="hidden" id="d_current-project">
                                    <div class="form-grid-2">
                                        <div class="form-group"><label>Host / IP (FTP)</label><input type="text" id="d_ftp_host"></div>
                                        <div class="form-group"><label>Web Domain</label><input type="text" id="d_web_domain"></div>
                                    </div>
                                    <div class="form-grid-2">
                                        <div class="form-group"><label>FTP User</label><input type="text" id="d_ftp_user"></div>
                                        <div class="form-group"><label>DA User</label><input type="text" id="d_da_user"></div>
                                    </div>
                                    <div class="form-group">
                                        <label>Password (FTP/DA)</label>
                                        <div class="password-wrapper">
                                            <input type="password" id="d_ftp_pass">
                                            <span class="toggle-password" onclick="UI.togglePassword('d_ftp_pass', this)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="form-group"><label>FTP Root Path</label><input type="text" id="d_ftp_root"></div>
                                    
                                    <div class="form-submit-row">
                                        <button type="submit" class="btn btn-primary btn-submit-large">Lưu cấu hình</button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Lịch sử thao tác (Detail View) -->
                            <div id="d_master-history-info"></div>
                        </div>
                        <div class="master-actions-sidebar">
                            <div id="d_master-action-buttons" class="master-btn-grid"></div>
                        </div>
                    </div>

                    <!-- TAB: FONTS -->
                    <div id="d_tab-fonts" class="project-tab-content d-none">
                        <div class="fonts-layout-grid">
                            <!-- Left side: Combined Font Search -->
                            <div class="flex-col-gap20">
                                <div class="card-container card-padded">
                                    <div class="flex-align-center-gap10-mb20">
                                        <div class="accent-bar-primary"></div>
                                        <h3 class="card-title-sm">TÌM KIẾM FONTS (LOCAL & GOOGLE)</h3>
                                    </div>
                                    <div class="font-search-row">
                                        <input type="text" id="project-font-search-input" placeholder="Nhập tên font (ví dụ: Roboto, Be Vietnam...)" class="flex-1" onkeyup="if(event.key==='Enter') FontManager.search(true)">
                                        <button class="btn btn-primary" onclick="FontManager.search(true)">Tìm kiếm</button>
                                    </div>

                                    <div id="project-font-results" class="grid-container font-results-grid">
                                        <p class="empty-results-text">Kết quả tìm kiếm sẽ hiển thị tại đây...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right side: fonts.css Preview -->
                            <div class="card-container card-padded card-padded-flex-col">
                                <div class="flex-between-center-mb20">
                                    <div class="flex-align-center-gap10">
                                        <div class="accent-bar-success"></div>
                                        <h3 class="card-title-sm">PREVIEW FONTS.CSS</h3>
                                    </div>
                                    <button class="btn btn-ghost btn-sm" onclick="FontManager.loadCssPreview()" title="Làm mới">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                                    </button>
                                </div>
                                <div id="fonts-css-preview" class="fonts-preview-box">
                                    /* Đang tải nội dung... */
                                </div>
                                <div id="installed-fonts-list" class="flex-col-gap8">
                                    <!-- List of fonts will be here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: WEBP CONVERTER -->
                    <div id="d_tab-webp" class="project-tab-content d-none">
                        <div class="card-container card-padded">
                            <div class="flex-between-center-mb20 flex-wrap-gap15">
                                <div class="flex-align-center-gap10">
                                    <div class="accent-bar-primary"></div>
                                    <h3 class="card-title-sm">CHUYỂN ĐỔI ẢNH TRONG THƯ MỤC ASSETS/IMAGES/IMAGES</h3>
                                </div>
                                <div class="flex-align-center-gap15">
                                    <label style="cursor: pointer; font-size: 0.8rem; color: #fff; user-select: none; display: inline-flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" id="project-webp-deep" style="accent-color: var(--primary); width: 15px; height: 15px; cursor: pointer;">
                                        <span>Nén sâu (TinyPNG)</span>
                                    </label>
                                    <div class="flex-align-center-gap8">
                                        <span style="font-size:0.8rem; color:var(--muted);">Chất lượng WebP:</span>
                                        <input type="number" id="project-webp-quality" value="100" min="10" max="100" class="input-quality" style="width: 60px; padding: 4px 8px; font-size: 0.8rem; border-radius: 6px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff;">
                                    </div>
                                    <button class="btn btn-primary" onclick="WebpManager.convertAll()" id="btn-project-webp-convert">🚀 Bắt đầu Convert</button>
                                    <button class="btn btn-ghost" onclick="WebpManager.undoAll()" id="btn-project-webp-undo-all" style="border: 1px solid var(--border); display: none; color: var(--warning);">↩️ Hoàn tác tất cả</button>
                                </div>
                            </div>

                            <div class="grid-1-gap15">
                                <div id="project-webp-status" class="d-none" style="padding:15px; border-radius:10px; font-size:0.85rem;"></div>
                                
                                <div style="overflow-x:auto;">
                                    <table class="table table-webp">
                                        <thead>
                                            <tr style="border-bottom:1px solid var(--border); text-align:left; color:var(--muted);">
                                                <th class="table-webp-th">Tên hình ảnh</th>
                                                <th class="table-webp-th">Định dạng</th>
                                                <th class="table-webp-th">Kích thước file</th>
                                                <th class="table-webp-th">Độ phân giải</th>
                                                <th class="table-webp-th">Trạng thái</th>
                                                <th class="table-webp-th" style="text-align:center; width:150px;">Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody id="project-webp-images-list">
                                            <tr>
                                                <td colspan="6" style="text-align:center; padding:30px; color:var(--muted);">Đang quét thư mục hình ảnh...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: IMAGE TRIM -->
                    <div id="d_tab-trim" class="project-tab-content d-none">
                        <div class="trim-shell">
                            <div class="trim-header">
                                <div class="trim-title-wrap">
                                    <div class="trim-title-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.12 15.88"/><path d="M14.47 14.48 20 20"/><path d="M8.12 8.12 12 12"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="trim-title">Trim ảnh trong thư mục assets/images/images</h3>
                                        <p class="trim-subtitle">Xóa pixel thừa theo màu góc trên-trái hoặc nền trong suốt, chỉ áp dụng ảnh đã chọn</p>
                                    </div>
                                </div>
                                <div class="trim-stats-group">
                                    <div class="trim-stat-chip">Đã chọn: <strong id="trim-selected-count">0</strong></div>
                                    <div class="trim-stat-chip">Tổng: <strong id="trim-total-count">0</strong> ảnh</div>
                                </div>
                            </div>

                            <div class="trim-toolbar">
                                <div class="trim-toolbar-left">
                                    <button type="button" class="am-toolbar-btn" id="btn-trim-toggle-all" data-mode="select">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Chọn tất cả</span>
                                    </button>
                                    <button type="button" class="am-toolbar-btn" onclick="ImageTrimManager.loadImages()">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                                        Quét lại
                                    </button>
                                </div>
                                <div class="trim-toolbar-right">
                                    <label class="trim-tolerance-label" for="trim-tolerance">Tolerance</label>
                                    <input type="range" id="trim-tolerance" min="0" max="80" value="12">
                                    <input type="number" id="trim-tolerance-number" min="0" max="80" value="12">
                                    <button class="btn btn-primary" id="btn-project-trim-run" onclick="ImageTrimManager.trimSelected()">Trim ảnh đã chọn</button>
                                    <button class="btn btn-ghost" id="btn-project-trim-undo-all" onclick="ImageTrimManager.undoAll()" style="display:none; color:var(--warning);">Hoàn tác tất cả</button>
                                </div>
                            </div>

                            <div id="project-trim-status" class="trim-status" style="display:none;"></div>

                            <div id="project-trim-images-grid" class="trim-grid">
                                <div class="trim-empty">Đang quét thư mục hình ảnh...</div>
                            </div>
                        </div>
                    </div>

                    <div id="d_tab-auto-media" class="project-tab-content d-none">
                        <div class="am-shell">
                            <!-- Header -->
                            <div class="am-header">
                                <div class="am-title-wrap">
                                    <div class="am-title-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="am-title">Tự động Map Ảnh</h3>
                                        <p class="am-subtitle">Quét &amp; gán ảnh theo sub-type tự động</p>
                                    </div>
                                </div>
                                <div class="am-stats-group">
                                    <div class="am-stat-chip">
                                        <span class="am-stat-dot"></span>
                                        Đã chọn: <strong id="auto-media-selected-count">0</strong>
                                    </div>
                                    <div class="am-stat-chip am-stat-total">
                                        Tổng: <strong id="auto-media-total">0</strong> ảnh
                                    </div>
                                </div>
                            </div>

                            <!-- Type Tabs -->
                            <div id="auto-media-main-tabs" class="am-main-tabs">
                                <button type="button" class="am-tab-btn active" data-main-key="type-photo">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    Photo
                                </button>
                                <button type="button" class="am-tab-btn" data-main-key="type-static">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                                    Static
                                </button>
                                <button type="button" class="am-tab-btn" data-main-key="type-news">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>
                                    News
                                </button>
                                <button type="button" class="am-tab-btn" data-main-key="type-products">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                    Products
                                </button>
                            </div>

                            <!-- Toolbar -->
                            <div class="am-toolbar">
                                <div class="am-toolbar-left">
                                    <button type="button" class="am-toolbar-btn" id="btn-am-toggle-all" data-mode="select">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Chọn tất cả</span>
                                    </button>
                                    <button type="button" class="am-toolbar-btn" id="btn-am-rescan">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                                        <span>Quét lại</span>
                                    </button>
                                </div>
                                <div class="am-warning-inline">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    File cũ sẽ bị xóa vật lý khi cập nhật
                                </div>
                            </div>

                            <!-- Groups Content -->
                            <div id="auto-media-groups" class="am-groups">
                                <div class="am-empty">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <p>Chọn tab loại ảnh để bắt đầu quét</p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="am-footer">
                                <div class="am-footer-info" id="am-run-summary" style="display:none;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span id="am-run-summary-text"></span>
                                </div>
                                <button id="btn-auto-media-run" class="am-run-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    Cập nhật &amp; Dọn rác
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: SEED DATA -->
                    <div id="d_tab-seed" class="project-tab-content d-none">
                        <div class="seed-shell">
                            <!-- Header -->
                            <div class="seed-header">
                                <div class="seed-title-wrap">
                                    <div class="seed-title-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V12M12 12C12 12 9 9 6 9s-6 3-6 3M12 12c0 0 3-3 6-3s6 3 6 3M6 15c0 0-3-3-6-3M18 15c0 0 3-3 6-3"/><circle cx="12" cy="5" r="3"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="seed-title">Tạo Dữ Liệu Mẫu</h3>
                                        <p class="seed-subtitle">Sinh dữ liệu random từ ảnh đã chọn theo từng sub-type</p>
                                    </div>
                                </div>
                                <!-- Controls -->
                                <div class="seed-controls">
                                    <div class="seed-control-group">
                                        <label>Số bản ghi / sub-type</label>
                                        <input type="number" id="seed-count-input" value="5" min="1" max="200" class="seed-count-input">
                                    </div>
                                    <div class="seed-control-group">
                                        <label>Số danh mục / cấp</label>
                                        <input type="number" id="seed-cat-count-input" value="3" min="1" max="50" class="seed-count-input">
                                    </div>
                                    <div class="seed-control-group">
                                        <label>Thư mục ảnh</label>
                                        <select id="seed-folder-select" class="seed-folder-select">
                                            <option value="project_images">assets/images/images (dự án)</option>
                                            <option value="custom_pool">Thư viện ảnh chung (Setting)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Type Tabs -->
                            <div id="seed-main-tabs" class="am-main-tabs" style="margin-bottom:16px;">
                                <button type="button" class="am-tab-btn active" data-main-key="type-photo">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Photo
                                </button>
                                <button type="button" class="am-tab-btn" data-main-key="type-static">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>Static
                                </button>
                                <button type="button" class="am-tab-btn" data-main-key="type-news">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/></svg>News
                                </button>
                                <button type="button" class="am-tab-btn" data-main-key="type-products">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Products
                                </button>
                            </div>

                            <!-- 2-column layout -->
                            <div class="seed-layout">
                                <!-- LEFT: sub-types -->
                                <div class="seed-left">
                                    <div class="seed-panel-header">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                                        Chọn sub-type cần tạo
                                    </div>
                                    <div id="seed-sub-types" class="seed-sub-types">
                                        <div class="seed-state">Đang tải cấu hình...</div>
                                    </div>
                                </div>

                                <!-- RIGHT: image grid -->
                                <div class="seed-right">
                                    <div class="seed-panel-header">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        Chọn ảnh (cho sub-type đang active)
                                        <span class="seed-img-total-chip">Tổng: <strong id="seed-img-total">0</strong> ảnh</span>
                                        <button type="button" class="am-toolbar-btn" id="btn-seed-scan" style="margin-left:auto;" onclick="SeedManager.scanFolder()">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            Quét
                                        </button>
                                    </div>
                                    <!-- Subfolder nav for custom_pool -->
                                    <div id="seed-subfolder-nav" class="seed-subfolder-nav" style="display:none;"></div>
                                    <!-- Image grid -->
                                    <div id="seed-images-grid" class="seed-images-grid">
                                        <div class="seed-img-empty">
                                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            <p>Nhấn Quét để tải danh sách ảnh</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="seed-footer">
                                <div class="seed-footer-hint">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                    Ảnh được copy vào thư mục public của dự án, DB được ghi bản ghi ngẫu nhiên
                                </div>
                                <button id="btn-seed-run-ai" class="seed-run-btn" style="background: var(--primary); margin-right: 10px;" onclick="UI.showModal('seed-ai-modal')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    <span>Tạo bằng AI (Gemini)</span>
                                </button>
                                <button id="btn-seed-run" class="seed-run-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    <span>Tạo dữ liệu mẫu</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW: IMAGE CONVERTER -->
                <div id="view-converter" class="view-section" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">Chuyển đổi hình ảnh (WebP/JPG)</h2>
                    </div>

                    <div class="item-card converter-dropzone" id="drop-zone">
                        <div class="item-card-icon converter-icon-box">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                        </div>
                        <h3 class="mb-8">Kéo thả ảnh vào đây</h3>
                        <p class="converter-dropzone-text">Hoặc nhấn để chọn file (Hỗ trợ PNG, JPG, WebP)</p>
                        <input type="file" id="file-input" multiple accept="image/*" style="display:none;">
                        <button class="btn btn-primary" onclick="document.getElementById('file-input').click()">Chọn ảnh</button>
                    </div>

                    <div id="converter-controls" class="converter-controls-box">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Định dạng đầu ra</label>
                                <select id="conv-format" class="custom-select">
                                    <option value="webp">WebP (Khuyên dùng)</option>
                                    <option value="jpg">JPG</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Chất lượng (1-100)</label>
                                <input type="number" id="conv-quality" value="100" min="1" max="100" style="width: 100%; padding: 8px 12px; font-size: 0.9rem; border-radius: 6px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff;">
                            </div>
                            <div class="form-group" style="grid-column: span 2; display: flex; align-items: center; margin-top: -5px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; font-size: 0.85rem; color: #fff;">
                                    <input type="checkbox" id="conv-deep" checked style="accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer;">
                                    <span>Nén sâu (Giảm bảng màu tối ưu dung lượng giống TinyPNG)</span>
                                </label>
                            </div>
                        </div>
                        <div id="selected-count" class="selected-count-text"> Đã chọn: 0 ảnh </div>
                        <div class="flex-gap12">
                            <button class="btn btn-primary" id="start-convert-btn" onclick="ConverterUI.process()">🚀 Bắt đầu chuyển đổi</button>
                            <button class="btn btn-ghost" onclick="ConverterUI.reset()">Hủy</button>
                        </div>
                    </div>

                    <div id="converter-results" class="converter-results-box">
                        <div class="badge badge-ok badge-full-block">
                            Hoàn tất! <a id="zip-download-link" href="#" class="zip-link">Tải xuống file ZIP (Tất cả ảnh)</a>
                        </div>
                    </div>
                </div>

                <!-- VIEW: AI MODELS CHECKER -->
                <div id="view-ai-checker" class="view-section" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">📊 Chi tiết AI Models & Chi phí</h2>
                    </div>
                    
                    <div class="card-container" style="background:var(--card); padding:24px; border-radius:20px; border:1px solid var(--border); margin-top:20px;">
                        <!-- Custom Tabs -->
                        <div style="display:flex; gap:10px; margin-bottom:24px; border-bottom:1px solid var(--border); padding-bottom:15px;">
                            <button id="tab-btn-gemini" class="btn btn-ghost active" onclick="AIChecker.switchTab('gemini')" style="border-radius:8px 8px 0 0; border:none; padding:10px 20px; display:flex; align-items:center;">
                                <svg width="18" height="18" viewBox="0 0 24 24" style="margin-right:8px;"><path fill="#4285F4" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/><circle fill="#4285F4" cx="12" cy="12" r="5"/></svg>
                                Gemini
                            </button>
                            <button id="tab-btn-claude" class="btn btn-ghost" onclick="AIChecker.switchTab('claude')" style="border-radius:8px 8px 0 0; border:none; padding:10px 20px; display:flex; align-items:center;">
                                <svg width="18" height="18" viewBox="0 0 24 24" style="margin-right:8px;"><path fill="#d97757" d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/></svg>
                                Claude
                            </button>
                        </div>

                        <!-- Hidden Inputs (Still used for Logic) -->
                        <input type="hidden" id="gemini-api-key">
                        <input type="hidden" id="claude-api-key">

                        <div id="ai-checker-content">
                            <div id="ai-loading" style="display:none; text-align:center; padding:40px;">
                                <div class="spinner" style="margin:0 auto 15px;"></div>
                                <div style="color:var(--muted);">Đang tải dữ liệu model...</div>
                            </div>
                            
                            <div id="ai-results-table-container" style="overflow-x:auto;">
                                <!-- Table will be rendered here -->
                                <p style="color:var(--muted); text-align:center; padding:40px;">Đang khởi tạo dữ liệu...</p>
                            </div>
                        </div>
                    </div>
                </div>
 
                <!-- VIEW: BROWSER CACHE CLEARER -->
                <div id="view-cache-clearer" class="view-section" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">🧹 Xóa cache trình duyệt cho URL</h2>
                    </div>
                    
                    <div class="card-container" style="background:var(--card); padding:24px; border-radius:20px; border:1px solid var(--border); margin-top:20px;">
                        <p style="color:var(--muted); font-size:0.9rem; margin-bottom:20px;">
                            Công cụ này gửi các yêu cầu ép tải lại từ mạng (<code>cache: 'reload'</code>) trực tiếp tới đường dẫn URL chỉ định (trên cả HTTP và HTTPS) nhằm xóa bỏ và cập nhật cache của riêng đường link đó (bao gồm cache chuyển hướng 301) mà không ảnh hưởng tới các dự án khác trên localhost.
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight:600; margin-bottom:8px; display:block;">Nhập đường dẫn URL cần xóa cache:</label>
                            <input type="text" id="cache-clear-url" placeholder="Ví dụ: http://localhost/2026_05/oneled_0056226w/ hoặc https://localhost/2026_05/oneled_0056226w/" style="width:100%; padding:12px; background:rgba(0,0,0,0.2); border:1px solid var(--border); border-radius:8px; color:#fff;">
                        </div>

                        <div style="display:flex; gap:12px; align-items:center;">
                            <button class="btn btn-primary" onclick="CacheClearer.run()" id="btn-cache-clear">🚀 Tiến hành xóa cache</button>
                        </div>
                        
                        <div id="cache-clear-status" style="margin-top:20px; display:none; padding:15px; border-radius:10px; font-size:0.85rem;"></div>
                    </div>
                </div>

                <!-- VIEW: GLOBAL CONFIG (SETTING) -->
                <div id="view-global-config" class="view-section" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">⚙️ Cấu hình chung (Setting)</h2>
                    </div>
                    
                    <div class="card-container card-padded" style="margin-top:20px;">
                        <form id="global-config-form">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Host / IP (FTP)</label>
                                    <input type="text" id="g_ftp_host" required>
                                </div>
                                <div class="form-group">
                                    <label>Tên miền (Mặc định)</label>
                                    <input type="text" id="g_web_domain" placeholder="demo.domain.com">
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>DA Port</label>
                                    <input type="text" id="g_da_port" value="1111">
                                </div>
                                <div class="form-group">
                                    <label>Username (FTP/DA)</label>
                                    <input type="text" id="g_ftp_user" required>
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Password (FTP/DA)</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="g_ftp_pass" required>
                                        <span class="toggle-password" onclick="UI.togglePassword('g_ftp_pass', this)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>FTP Root Path</label>
                                    <input type="text" id="g_ftp_root" placeholder="/public_html">
                                </div>
                            </div>

                            <div style="margin-top: 15px; border-top: 1px solid var(--border); padding-top: 15px;">
                                <label style="color: var(--primary); margin-bottom:12px; font-size:0.7rem;">☁️ CLOUDFLARE API (PRODUCTION)</label>
                                <div class="form-group">
                                    <label>Account ID</label>
                                    <input type="text" id="g_cf_account_id">
                                </div>
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Global API Key / Token</label>
                                        <div class="password-wrapper">
                                            <input type="password" id="g_cf_api_token">
                                            <span class="toggle-password" onclick="UI.togglePassword('g_cf_api_token', this)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Auth Email</label>
                                        <input type="text" id="g_cf_auth_email">
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 15px; border-top: 1px solid var(--border); padding-top: 15px;">
                                <label style="color: var(--success); margin-bottom:12px; font-size:0.7rem;">🤖 AI API KEYS (MODELS CHECKER)</label>
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Gemini API Key</label>
                                        <div class="password-wrapper">
                                            <input type="password" id="g_gemini_key" placeholder="AIzaSy...">
                                            <span class="toggle-password" onclick="UI.togglePassword('g_gemini_key', this)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Claude API Key</label>
                                        <div class="password-wrapper">
                                            <input type="password" id="g_claude_key" placeholder="sk-ant-api03...">
                                            <span class="toggle-password" onclick="UI.togglePassword('g_claude_key', this)">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 15px; border-top: 1px solid var(--border); padding-top: 15px;">
                                <label style="color: var(--purple); margin-bottom:12px; font-size:0.7rem;">🏗️ PROJECT SCAFFOLDING (ĐÚC DỰ ÁN)</label>
                                <div class="form-grid-2">
                                    <div class="form-group"><label>Source Path</label><input type="text" id="g_source_path" placeholder="D:/RBWStack/www/source_laravel"></div>
                                    <div class="form-group"><label>Source DB Name</label><input type="text" id="g_source_db_name" placeholder="source_nasani_2026"></div>
                                </div>
                                <div class="form-group" style="margin-top:10px;">
                                    <label>Editor Path (Mở dự án)</label>
                                    <input type="text" id="g_editor_path" placeholder="C:\Users\...\Antigravity.exe">
                                </div>
                                <div class="form-group" style="margin-top:10px;">
                                    <label>Font Source Path (Thư viện Font local)</label>
                                    <input type="text" id="g_font_source_path" placeholder="D:/RBWStack/www/font_library">
                                </div>
                                <div class="form-group" style="margin-top:10px;">
                                    <label>🌱 Thư viện Ảnh Mẫu (Tạo Dữ Liệu Mẫu)</label>
                                    <input type="text" id="g_images_pool_path" placeholder="D:/RBWStack/www/images">
                                </div>
                            </div>
                            <div class="form-submit-row" style="margin-top: 25px; border-top: 1px solid var(--border); padding-top: 20px;">
                                <button type="submit" class="btn btn-primary btn-submit-large">Lưu cấu hình</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- ======= MODALS (REUSED) ======= -->



    <!-- Project Config Modal -->
    <div id="config-modal" class="modal-overlay">
        <div class="modal" style="max-width: 850px; width: 95%;">
            <div class="modal-header-flex">
                <h2 id="modal-title">Bảng điều khiển dự án</h2>
                <button class="btn btn-close-circle" onclick="UI.hideModal('config-modal')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>

            <div class="project-master-layout">
                <!-- Left: Config Form -->
                <div class="master-config-area">
                    <div class="quick-paste-box">
                        <label>⚡ PASTE NHANH CẤU HÌNH</label>
                        <textarea id="quick_paste" placeholder="Dán thông tin hosting tại đây..."></textarea>
                        <button type="button" class="btn btn-primary btn-sm-full" onclick="UI.parseQuickConfig()">Phân tích & Đổ dữ liệu</button>
                    </div>

                    <form id="config-form">
                        <input type="hidden" id="current-project">
                        <div class="form-grid-2">
                            <div class="form-group"><label>Host / IP (FTP)</label><input type="text" id="ftp_host" placeholder="ftp.domain.com"></div>
                            <div class="form-group"><label>Web Domain</label><input type="text" id="web_domain" placeholder="domain.com"></div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group"><label>FTP User</label><input type="text" id="ftp_user"></div>
                            <div class="form-group"><label>DA User</label><input type="text" id="da_user"></div>
                        </div>
                        <div class="form-group">
                            <label>Password (FTP/DA)</label>
                            <div class="password-wrapper">
                                <input type="password" id="ftp_pass">
                                <span class="toggle-password" onclick="UI.togglePassword('ftp_pass', this)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </span>
                            </div>
                        </div>
                        <div class="form-group"><label>FTP Root Path</label><input type="text" id="ftp_root" placeholder="/public_html"></div>
                        
                        <div class="modal-footer-actions">
                            <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                        </div>
                    </form>
                    
                    <!-- Lịch sử thao tác -->
                    <div id="master-history-info"></div>
                </div>

                <!-- Right: Actions & Info -->
                <div class="master-actions-sidebar">
                    <div>
                        <div class="master-section-title" style="color:var(--purple)">⚡ Chức năng nhanh</div>
                        <div id="master-action-buttons" class="master-btn-grid"></div>
                    </div>

                    <div id="master-deployed-info"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pre-deploy Modal (Confirmation) -->
    <div id="pre-deploy-modal" class="modal-overlay">
        <div class="modal">
            <h2>Xác nhận Deploy Demo</h2>
            <p id="pre-deploy-project-desc" style="color:var(--muted); font-size:0.9rem; margin-bottom:20px;"></p>
            <div class="form-group">
                <label>Hậu tố DB (Tùy chọn: vd 'shop', 'v2')</label>
                <input type="text" id="manual_db_suffix" placeholder="Mặc định: Tên dự án">
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; cursor:pointer; color:#fff; font-weight:normal;">
                    <input type="checkbox" id="pre_deploy_ssl" style="width:18px; height:18px; accent-color:var(--primary);"> Sử dụng SSL (HTTPS)
                </label>
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; cursor:pointer; color:#fff; font-weight:normal;">
                    <input type="checkbox" id="pre_deploy_pack_upload" style="width:18px; height:18px; accent-color:var(--primary);" checked> Nén & Tải mã nguồn lên Host (dist.zip)
                </label>
            </div>
            <div class="form-group" style="margin-top: 5px; margin-left: 26px;">
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; cursor:pointer; color:#ccc; font-weight:normal; font-size:0.85rem;">
                    <input type="checkbox" id="pre_deploy_use_7zip" style="width:16px; height:16px; accent-color:var(--primary);" checked> Sử dụng nén bằng 7-Zip (Bỏ check sẽ nén bằng Tar/PHP)
                </label>
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; cursor:pointer; color:#fff; font-weight:normal;">
                    <input type="checkbox" id="pre_deploy_export_upload" style="width:18px; height:18px; accent-color:var(--primary);" checked> Xuất & Tải Database lên Host (dist.sql)
                </label>
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; cursor:pointer; color:#fff; font-weight:normal;">
                    <input type="checkbox" id="pre_deploy_create_db" style="width:18px; height:18px; accent-color:var(--primary);" checked> Tạo/Cập nhật Database trên DirectAdmin
                </label>
            </div>
            <div class="form-group" style="margin-top: 10px;">
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; cursor:pointer; color:#fff; font-weight:normal;">
                    <input type="checkbox" id="pre_deploy_extract_setup" style="width:18px; height:18px; accent-color:var(--primary);" checked> Giải nén source & Import database trên Host
                </label>
            </div>
            <div class="project-actions" style="margin-top: 20px;">
                <button class="btn btn-ghost" onclick="UI.hideModal('pre-deploy-modal')">Hủy</button>
                <button id="confirm-deploy-btn" class="btn btn-primary">🚀 Bắt đầu Deploy</button>
            </div>
        </div>
    </div>

    <!-- Deploy Progress Modal -->
    <div id="deploy-modal" class="modal-overlay">
        <div class="modal">
            <h2 id="deploy-modal-title">Đang triển khai...</h2>
            <div id="deploy-project-name" style="color:var(--muted); font-size:0.85rem; margin-bottom:15px;"></div>
            <div class="progress-container">
                <div style="height:6px; background:rgba(255,255,255,0.05); border-radius:10px; overflow:hidden;">
                    <div id="progress-fill" style="height:100%; width:0%; background:var(--primary); transition:width .4s;"></div>
                </div>
                <div id="status-text" style="font-size:0.8rem; color:var(--muted); margin-top:8px;">Chuẩn bị...</div>
                <div id="log-output"></div>
            </div>
            <div class="project-actions" id="deploy-footer" style="display:none;">
                <button class="btn btn-primary" style="width:100%; justify-content:center;" onclick="UI.hideModal('deploy-modal'); App.loadProjects(App.currentCategory);">Hoàn tất</button>
            </div>
        </div>
    </div>

    <!-- Shared Action Menu -->
    <!-- Project Detail Modal -->
    <div id="project-detail-modal" class="modal-overlay">
        <div class="modal">
            <h2 id="detail-title">Thông tin Dự án</h2>
            <div id="detail-content" style="margin: 20px 0;">
                <!-- Loaded by JS -->
            </div>
            <div class="project-actions">
                <button class="btn btn-primary" onclick="UI.hideModal('project-detail-modal')">Đóng</button>
            </div>
        </div>
    </div>

    <!-- Quick Paste Modal -->
    <div id="quick-paste-modal" class="modal-overlay">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header-flex">
                <h2>⚡ Nhập nhanh cấu hình</h2>
                <button class="btn btn-close-circle" onclick="UI.hideModal('quick-paste-modal')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="quick-paste-box" style="margin:0; border:none; background:transparent; padding:0;">
                <p style="font-size:0.75rem; color:var(--muted); margin-bottom:15px;">Dán toàn bộ thông tin Hosting/FTP bạn nhận được vào đây. Hệ thống sẽ tự động bóc tách các trường dữ liệu.</p>
                <textarea id="d_quick_paste" placeholder="Ví dụ:
Host: 123.123.123.123
User: u123456
Pass: password123..." style="height:200px;"></textarea>
                <button type="button" class="btn btn-primary btn-sm-full" style="padding:12px;" onclick="UI.parseQuickConfig('detail')">Phân tích & Đổ dữ liệu</button>
            </div>
        </div>
    </div>

    <div id="action-menu-portal" class="action-menu"></div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="assets/js/api.js?v=<?= time(); ?>"></script>
    <script src="assets/js/ui.js?v=<?= time(); ?>"></script>
    <script src="assets/js/app.js?v=<?= time(); ?>"></script>
    <script src="assets/js/converter.js?v=<?= time(); ?>"></script>
    <script src="assets/js/ai_checker.js?v=<?= time(); ?>"></script>
    <script src="assets/js/schema_components.js?v=<?= time(); ?>"></script>
    <script src="assets/js/schema_builder.js?v=<?= time(); ?>"></script>
    <script type="module">
        import { Font, woff2 } from 'https://cdn.jsdelivr.net/npm/fonteditor-core/+esm';
        window.fonteditor = { Font, woff2 };
        woff2.init('https://cdn.jsdelivr.net/npm/fonteditor-core/woff2/woff2.wasm').then(() => {
            console.log('WOFF2 initialized');
        }).catch(err => {
            console.error('WOFF2 init failed', err);
        });
    </script>
    <script src="assets/js/font_manager.js?v=<?= time(); ?>"></script>
    <script src="assets/js/webp_manager.js?v=<?= time(); ?>"></script>
    <script src="assets/js/image_trim_manager.js?v=<?= time(); ?>"></script>
    <script src="assets/js/auto_media_manager.js?v=<?= time(); ?>"></script>
    <script src="assets/js/seed_manager.js?v=<?= time(); ?>"></script>
    <script src="assets/js/cache_clearer.js?v=<?= time(); ?>"></script>
    <!-- Change Type Database Modal -->
    <div id="change-type-modal" class="modal-overlay">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header-flex">
                <h2>Thay đổi Type Database</h2>
                <button class="btn btn-close-circle" onclick="UI.hideModal('change-type-modal')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <form id="change-type-form" onsubmit="event.preventDefault(); App.executeChangeType();">
                <input type="hidden" id="ct-project-name">
                <div class="form-group">
                    <label>Module chính</label>
                    <select id="ct-module" class="form-control">
                        <option value="product">Sản phẩm</option>
                        <option value="news">Tin tức</option>
                    </select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Type cũ (Old)</label>
                        <input type="text" id="ct-old-type" placeholder="san-pham" required>
                    </div>
                    <div class="form-group">
                        <label>Type mới (New)</label>
                        <input type="text" id="ct-new-type" placeholder="thuc-don" required>
                    </div>
                </div>
                <div style="background:rgba(245, 158, 11, 0.1); padding:15px; border-radius:10px; border:1px solid rgba(245, 158, 11, 0.2); margin-bottom:20px;">
                    <p style="color:var(--warning); font-size:0.75rem; margin:0;">
                        ⚠️ <strong>Lưu ý:</strong> Hành động này sẽ UPDATE trực tiếp database của dự án (các bảng list, cat, item, sub, gallery, seo, slug). Vui lòng kiểm tra kỹ trước khi thực hiện.
                    </p>
                </div>
                <div class="modal-footer-actions">
                    <button type="button" class="btn btn-ghost" onclick="UI.hideModal('change-type-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary">🚀 Thực thi Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change PHP Version Modal -->
    <div id="change-php-modal" class="modal-overlay">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header-flex">
                <h2>Đổi phiên bản PHP (Production)</h2>
                <button class="btn btn-close-circle" onclick="UI.hideModal('change-php-modal')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <form id="change-php-form" onsubmit="event.preventDefault(); App.executeChangePhpVersion();">
                <input type="hidden" id="cpp-project-name">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Chọn phiên bản PHP</label>
                    <select id="cpp-php-index" class="form-control" style="width: 100%; height: 45px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); color: #fff; border-radius: 8px; padding: 0 12px;">
                        <option value="1">Slot 1: PHP 8.4 / PHP 8.3 (Phiên bản chính - php1)</option>
                        <option value="2">Slot 2: PHP 8.3 / PHP 8.2 (Phiên bản phụ 1 - php2)</option>
                        <option value="3">Slot 3: PHP 8.2 / PHP 8.1 (Phiên bản phụ 2 - php3)</option>
                        <option value="4">Slot 4: PHP 8.1 / PHP 8.0 (Phiên bản phụ 3 - php4)</option>
                    </select>
                </div>
                <div style="background:rgba(139, 92, 246, 0.1); padding:15px; border-radius:10px; border:1px solid rgba(139, 92, 246, 0.2); margin-bottom:20px;">
                    <p style="color:#c084fc; font-size:0.75rem; margin:0;">
                        ℹ️ <strong>Lưu ý:</strong> DirectAdmin sẽ ánh xạ các chỉ mục 1, 2, 3, 4 tương ứng với cấu hình PHP được cài trên máy chủ của bạn. Thời gian áp dụng thay đổi từ 1-2 phút.
                    </p>
                </div>
                <div class="modal-footer-actions">
                    <button type="button" class="btn btn-ghost" onclick="UI.hideModal('change-php-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="cpp-submit-btn">🚀 Xác nhận thay đổi</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Schema Builder Modal -->
    <div id="schema-builder-modal" class="modal-overlay">
        <div class="modal" style="max-width: 1400px; width: 98%; height: 95vh; display: flex; flex-direction: column; padding: 20px;">
            <div class="modal-header-flex">
                <div>
                    <h2 id="sb-title">Visual Schema Builder</h2>
                    <p id="sb-project-name" style="color:var(--muted); font-size:0.8rem;"></p>
                </div>
                <div style="display:flex; gap:15px; align-items:center;">
                    <div style="display:flex; align-items:center; gap:8px; background:rgba(255,255,255,0.05); padding:5px 12px; border-radius:20px; border:1px solid rgba(255,255,255,0.1);">
                        <span style="font-size:0.75rem; color:var(--muted); font-weight:bold;">ĐA NGÔN NGỮ</span>
                        <label class="sb-switch">
                            <input type="checkbox" id="sb-global-lang" onchange="SchemaBuilder.toggleGlobalLang(this.checked)">
                            <span class="sb-slider"></span>
                        </label>
                    </div>
                    <select id="sb-file-select" class="form-control" style="width:200px;" onchange="SchemaBuilder.loadSelectedFile()"></select>
                    <button class="btn btn-close-circle" onclick="UI.hideModal('schema-builder-modal')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
                </div>
            </div>
            
            <div id="sb-content" style="flex:1; display:grid; grid-template-columns: 1fr 1fr; gap:20px; overflow:hidden; margin:15px 0;">
                <div id="sb-form-wrapper" style="overflow-y:auto; padding:20px; background:rgba(0,0,0,0.2); border-radius:12px;">
                    <div id="sb-form-container"></div>
                </div>
                <div id="sb-preview-wrapper" style="display:flex; flex-direction:column; background:#000; border-radius:12px; overflow:hidden; border:1px solid var(--border);">
                    <div style="display:flex; background:rgba(255,255,255,0.05); border-bottom:1px solid var(--border);">
                        <button id="sb-tab-preview" class="btn btn-ghost active" onclick="SchemaBuilder.switchTab('preview')" style="border-radius:0; border:none; border-bottom:2px solid var(--primary); font-size:0.7rem; padding:10px 20px;">📄 DỮ LIỆU JSON</button>
                        <button id="sb-tab-structure" class="btn btn-ghost" onclick="SchemaBuilder.switchTab('structure')" style="border-radius:0; border:none; font-size:0.7rem; padding:10px 20px;">🌿 CẤU TRÚC TYPE</button>
                    </div>
                    <div id="sb-preview-content" style="flex:1; overflow-y:auto;">
                        <pre id="sb-live-preview" style="padding:15px; color:#10b981; font-family:monospace; font-size:0.75rem; margin:0; white-space:pre-wrap;"></pre>
                        <div id="sb-structure-list" style="display:none; padding:20px;"></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer-actions">
                <div id="sb-status" style="font-size:0.85rem; color:var(--muted);"></div>
                <div style="display:flex; gap:12px;">
                    <button type="button" class="btn btn-ghost" onclick="UI.hideModal('schema-builder-modal')">Hủy</button>
                    <button type="button" class="btn btn-primary" onclick="SchemaBuilder.save()">💾 Lưu cấu hình</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Option Popup -->
    <div id="sb-add-opt-modal" class="modal-overlay" style="z-index:10001; display:none; background:rgba(0,0,0,0.8); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div class="modal" style="max-width:400px; padding:25px; border:1px solid var(--primary); background:#1a1d21; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
            <h3 style="margin-top:0; font-size:1.1rem; color:var(--primary); display:flex; align-items:center; gap:10px;">
                <span>✨ Thêm Option Mới</span>
            </h3>
            <p style="font-size:0.8rem; color:var(--muted); margin-bottom:20px;">Nhập tên key mới cho cấu hình của bạn.</p>
            <input type="text" id="sb-new-opt-key" class="form-control" style="width:100%; height:45px; margin-bottom:20px; font-weight:bold; font-size:1.1rem; border-color:rgba(255,255,255,0.1); text-align:center;" placeholder="ví dụ: is_hot, title_sub...">
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn btn-ghost" onclick="document.getElementById('sb-add-opt-modal').style.display='none'">Hủy</button>
                <button class="btn btn-primary" id="sb-btn-confirm-add" style="padding:0 25px; height:40px;">Thêm ngay</button>
            </div>
        </div>
    </div>

    <!-- Add Album Popup -->
    <div id="sb-add-album-modal" class="modal-overlay" style="z-index:10002; display:none; background:rgba(0,0,0,0.8); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div class="modal" style="max-width:400px; padding:25px; border:1px solid var(--success); background:#1a1d21; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
            <h3 style="margin-top:0; font-size:1.1rem; color:var(--success); display:flex; align-items:center; gap:10px;">
                <span>📸 Thêm Album Gallery Mới</span>
            </h3>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="font-size:0.7rem; color:var(--muted);">TÊN ALBUM (HIỂN THỊ)</label>
                <input type="text" id="sb-album-name" class="form-control" style="width:100%; height:40px;" placeholder="Ví dụ: Hình ảnh sản phẩm">
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:0.7rem; color:var(--muted);">KEY / TYPE (SLUG)</label>
                <input type="text" id="sb-album-key" class="form-control" style="width:100%; height:40px; font-family:monospace;" placeholder="ví dụ: san-pham">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn btn-ghost" onclick="document.getElementById('sb-add-album-modal').style.display='none'">Hủy</button>
                <button class="btn btn-primary" id="sb-btn-confirm-album" style="padding:0 25px; height:40px; background:var(--success); border-color:var(--success);">Thêm ngay</button>
            </div>
        </div>
    </div>

    <!-- Add Image Popup -->
    <div id="sb-add-image-modal" class="modal-overlay" style="z-index:10003; display:none; background:rgba(0,0,0,0.8); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div class="modal" style="max-width:400px; padding:25px; border:1px solid var(--primary); background:#1a1d21; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
            <h3 style="margin-top:0; font-size:1.1rem; color:var(--primary); display:flex; align-items:center; gap:10px;">
                <span>🖼️ Thêm Loại Ảnh Mới</span>
            </h3>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="font-size:0.7rem; color:var(--muted);">TÊN ẢNH (HIỂN THỊ)</label>
                <input type="text" id="sb-image-name" class="form-control" style="width:100%; height:40px;" placeholder="Ví dụ: Ảnh nền, Icon...">
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-size:0.7rem; color:var(--muted);">KEY / TYPE (TÊN BIẾN)</label>
                <input type="text" id="sb-image-key" class="form-control" style="width:100%; height:40px; font-family:monospace;" placeholder="ví dụ: background, icon">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn btn-ghost" onclick="document.getElementById('sb-add-image-modal').style.display='none'">Hủy</button>
                <button class="btn btn-primary" id="sb-btn-confirm-image" style="padding:0 25px; height:40px;">Thêm ngay</button>
            </div>
        </div>
    </div>

    <!-- SSL Modal -->
    <div id="ssl-modal" class="modal-overlay">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header-flex">
                <h2>Cài đặt SSL Let's Encrypt</h2>
                <button class="btn-close-circle" onclick="UI.hideModal('ssl-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 20px 0;">
                <div id="ssl-project-name" style="font-weight: 700; color: var(--primary); margin-bottom: 10px; font-size: 1.1rem;"></div>
                <div id="ssl-status-text" style="font-size: 0.9rem; color: var(--muted); margin-bottom: 20px; line-height: 1.5;"></div>
                
                <div id="ssl-status-icon" style="display:none; text-align:center; margin-bottom: 20px;">
                    <div class="spinner" style="margin: 0 auto;"></div>
                </div>

                <div id="ssl-log-output" style="max-height: 150px; overflow-y: auto; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; font-family: monospace; font-size: 0.75rem; margin-bottom: 10px;"></div>
            </div>
            
            <div id="ssl-confirm-footer" class="modal-footer-actions">
                <button class="btn btn-ghost" onclick="UI.hideModal('ssl-modal')">Hủy</button>
                <button id="ssl-start-btn" class="btn btn-primary">🛡️ Xác nhận cài đặt</button>
            </div>
            
            <div id="ssl-footer" class="modal-footer-actions" style="display:none;">
                <button class="btn btn-primary" style="width:100%; justify-content:center;" onclick="UI.hideModal('ssl-modal')">Đóng</button>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>
    <!-- MODAL: PROJECT DEPLOYMENT (SCAFFOLDING) -->
    <div id="deploy-project-modal" class="modal-overlay">
        <div class="modal" style="max-width:500px;">
            <div class="modal-header-flex">
                <h3 class="modal-title">Triển khai dự án mới</h3>
                <button class="btn-close-circle" onclick="UI.hideModal('deploy-project-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <p style="font-size:0.9rem; color:var(--muted); margin-bottom:20px;">
                    Dự án sẽ được copy từ source gốc và khởi tạo Database tự động cho tháng <strong id="dp-current-month">--</strong>.
                </p>
                <div class="form-group">
                    <label>Tên dự án (Folder name)</label>
                    <input type="text" id="dp-project-name" placeholder="vd: huynhhungcopiers_0604626w">
                </div>
                <div class="form-group">
                    <label>Source mẫu</label>
                    <select id="dp-source-key" class="custom-select">
                        <option value="default">Source Nasani 2026 (Laravel)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer-actions" style="margin-top:20px;">
                <button class="btn btn-ghost" onclick="UI.hideModal('deploy-project-modal')">Hủy</button>
                <button class="btn btn-primary" id="dp-confirm-btn" onclick="App.executeDeployProject()">🚀 Bắt đầu đúc dự án</button>
            </div>
        </div>
    </div>


    <!-- MODAL: AI SEED PROMPT -->
    <div id="seed-ai-modal" class="modal-overlay">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header-flex">
                <h2>Tạo bằng AI (Gemini)</h2>
                <button class="btn-close-circle" onclick="UI.hideModal('seed-ai-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 20px 0;">
                <p style="font-size:0.85rem; color:var(--muted); margin-bottom:15px; line-height:1.4;">
                    Nhập mô tả thông tin ngành nghề/lĩnh vực (ví dụ: Dịch vụ mai táng, Sản phẩm đồ gia dụng...). AI sẽ tự động tạo tên tiêu đề và mô tả phù hợp cho các bản ghi dữ liệu mẫu.
                </p>
                <div class="form-group">
                    <label>Mô tả ngành nghề/lĩnh vực</label>
                    <input type="text" id="seed-ai-prompt" placeholder="Ví dụ: Thiết bị nhà bếp thông minh" class="form-control" style="width:100%; height:40px; margin-top:5px;">
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>Chọn model AI (Gemini)</label>
                    <select id="seed-ai-model" class="form-control" style="width:100%; height:40px; margin-top:5px; background:rgba(0,0,0,0.2); border:1px solid var(--border); color:#fff; border-radius:8px; padding:0 12px;">
                        <option value="gemini-3.5-flash">Gemini 3.5 Flash</option>
                        <option value="gemini-3.1-flash-lite">Gemini 3.1 Flash Lite</option>
                        <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                        <option value="gemini-2.5-flash-lite">Gemini 2.5 Flash Lite</option>
                        <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                        <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer-actions">
                <button class="btn btn-ghost" onclick="UI.hideModal('seed-ai-modal')">Hủy</button>
                <button id="seed-ai-confirm-btn" class="btn btn-primary" onclick="SeedManager.runSeed(true)">🚀 Bắt đầu tạo bằng AI</button>
            </div>
        </div>
    </div>

    <!-- MODAL: DEPLOY DB CONFIRM -->
    <div id="deploy-db-confirm-modal" class="modal-overlay" style="z-index: 10005;">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header-flex">
                <h2>⚠️ Database đã có dữ liệu</h2>
                <button class="btn-close-circle" onclick="UI.hideModal('deploy-db-confirm-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 20px 0;">
                <p id="db-confirm-message" style="font-size:0.9rem; color:#fff; line-height:1.5; margin-bottom:15px;"></p>
                <p style="font-size:0.8rem; color:var(--muted); line-height:1.4;">
                    Vui lòng chọn một trong hai hành động dưới đây để tiếp tục tiến trình triển khai.
                </p>
            </div>
            <div class="modal-footer-actions" style="display:flex; flex-direction:column; gap:10px;">
                <button id="btn-db-overwrite" class="btn btn-primary" style="width:100%; justify-content:center; background:var(--danger); border-color:var(--danger);">💥 Xoá hết dữ liệu cũ &amp; Import mới</button>
                <button id="btn-db-skip" class="btn btn-ghost" style="width:100%; justify-content:center; color:#fff; background:rgba(255,255,255,0.05);">⏭️ Giữ lại dữ liệu cũ &amp; Bỏ qua import</button>
                <button class="btn btn-ghost" onclick="UI.hideModal('deploy-db-confirm-modal')" style="width:100%; justify-content:center;">Hủy</button>
            </div>
        </div>
    </div>

</body>
</html>
