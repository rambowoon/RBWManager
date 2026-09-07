<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RBWManager</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <script>
        (function() {
            var savedTheme = localStorage.getItem('rbw_theme') || 'mint';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
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
                <div class="logo-text">RBWManager</div>
            </div>

            <nav class="sidebar-nav">
                <!-- GLOBAL SIDEBAR NAV -->
                <div id="sidebar-nav-global">
                    <div class="nav-item active" onclick="App.showDashboard(); App.loadProjects('')">
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
                    <div class="nav-item" onclick="App.showCacheClearer()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/></svg>
                        Xóa cache trình duyệt
                    </div>
                    <div class="menu-divider"></div>
                    <div class="nav-item" onclick="App.showDemoServers()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        Server Demo
                    </div>
                    <div class="nav-item" onclick="App.showGlobalConfig()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Setting
                    </div>
                </div>

                <!-- PROJECT-SPECIFIC SIDEBAR NAV (ACTIVE IN PROJECT DETAIL) -->
                <div id="sidebar-nav-project" style="display:none;">
                    <div class="nav-item" onclick="App.showDashboard()" style="color:var(--primary); font-weight:700; margin-bottom:12px; background:rgba(0,210,211,0.06); border:1px solid rgba(0,210,211,0.2);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        Quay lại danh sách
                    </div>
                    <div style="font-size: 10px; font-weight: 750; text-transform: uppercase; color: #64748b; padding: 4px 10px 8px; letter-spacing: 0.8px;">
                        TÍNH NĂNG DỰ ÁN
                    </div>
                    <div class="nav-item project-side-tab active" data-tab="d_tab-config" onclick="UI.switchProjectTab(this, 'd_tab-config')">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">⚙️</span>
                        Cấu hình &amp; Deploy
                    </div>
                    <div class="nav-item project-side-tab" data-tab="d_tab-schema" onclick="UI.switchProjectTab(this, 'd_tab-schema')">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">▤</span>
                        Visual Schema
                    </div>
                    <div class="nav-item project-side-tab" data-tab="d_tab-fonts" onclick="UI.switchProjectTab(this, 'd_tab-fonts')">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">🖋️</span>
                        Quản lý Fonts
                    </div>
                    <div class="nav-item project-side-tab" data-tab="d_tab-webp" onclick="UI.switchProjectTab(this, 'd_tab-webp')">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">🖼️</span>
                        Convert Ảnh WebP
                    </div>
                    <div class="nav-item project-side-tab" data-tab="d_tab-trim" onclick="UI.switchProjectTab(this, 'd_tab-trim')">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">✂️</span>
                        Trim Ảnh
                    </div>
                    <div class="nav-item project-side-tab" data-tab="d_tab-auto-media" onclick="UI.switchProjectTab(this, 'd_tab-auto-media')">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">🤖</span>
                        Tự động Map Ảnh
                    </div>
                    <div class="nav-item project-side-tab" data-tab="d_tab-seed" onclick="UI.switchProjectTab(this, 'd_tab-seed')">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">🌱</span>
                        Tạo Dữ Liệu Mẫu
                    </div>
                    <div id="side-tab-filemanager" class="nav-item project-side-tab" style="display:none;" data-tab="d_tab-filemanager" onclick="UI.switchProjectTab(this, 'd_tab-filemanager'); typeof FileManager !== 'undefined' && FileManager.init()">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">📁</span>
                        Quản lý File (Host)
                    </div>
                    <div id="side-tab-synccenter" class="nav-item project-side-tab" style="display:none;" data-tab="d_tab-synccenter" onclick="UI.switchProjectTab(this, 'd_tab-synccenter'); typeof SyncCenter !== 'undefined' && SyncCenter.init()">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">🔄</span>
                        Sync Center
                    </div>
                    <div id="side-tab-backups" class="nav-item project-side-tab" style="display:none;" data-tab="d_tab-backups" onclick="UI.switchProjectTab(this, 'd_tab-backups'); typeof SyncCenter !== 'undefined' && SyncCenter.openBackupHistoryTab()">
                        <span style="font-size: 1rem; width: 22px; display: inline-flex; align-items: center; justify-content: center;">📦</span>
                        Lịch sử Backup
                    </div>
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
                    
                    <!-- Integrated Project Header (Moved up to top navbar) -->
                    <div id="project-detail-header" class="flex-align-center-gap15" style="display:none;">
                        <button class="btn btn-ghost btn-back back-btn" onclick="App.showDashboard()" style="padding: 6px 14px; font-size: 13px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg> Quay lại
                        </button>
                        <h2 class="section-title detail-title" id="detail-project-name" style="margin:0; font-size:1.15rem; font-weight:800; color:#fff; letter-spacing: 0.3px;">Tên dự án</h2>
                    </div>
                </div>

                <div class="header-right flex-center-gap">
                    <span class="badge-env" id="detail-project-status" style="display:none; margin-right: 6px;">● DEMO ĐANG CHẠY</span>
                    <!-- THEME DROPDOWN -->
                    <div class="theme-dropdown-wrapper">
                        <button class="theme-toggle-btn" id="theme-menu-btn" onclick="UI.toggleThemeMenu(event)" title="Đổi màu giao diện (Theme)">
                            <span class="theme-current-dot" id="theme-current-dot"></span>
                            <span id="theme-current-label">Xanh Mint</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div class="theme-menu" id="theme-menu-dropdown">
                            <div class="theme-menu-item" data-theme-val="mint" onclick="UI.setTheme('mint')">
                                <span class="theme-dot dot-mint"></span>
                                <span>Xanh Mint</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="emerald" onclick="UI.setTheme('emerald')">
                                <span class="theme-dot dot-emerald"></span>
                                <span>Lục Bảo (Emerald)</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="cyan" onclick="UI.setTheme('cyan')">
                                <span class="theme-dot dot-cyan"></span>
                                <span>Băng Tuyết (Cyan)</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="ocean" onclick="UI.setTheme('ocean')">
                                <span class="theme-dot dot-ocean"></span>
                                <span>Xanh Biển (Ocean)</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="indigo" onclick="UI.setTheme('indigo')">
                                <span class="theme-dot dot-indigo"></span>
                                <span>Chàm (Indigo)</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="purple" onclick="UI.setTheme('purple')">
                                <span class="theme-dot dot-purple"></span>
                                <span>Tím Neon</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="rose" onclick="UI.setTheme('rose')">
                                <span class="theme-dot dot-rose"></span>
                                <span>Hồng Ruby (Rose)</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="orange" onclick="UI.setTheme('orange')">
                                <span class="theme-dot dot-orange"></span>
                                <span>Cam Cyber</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="amber" onclick="UI.setTheme('amber')">
                                <span class="theme-dot dot-amber"></span>
                                <span>Hoàng Kim (Amber)</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                            <div class="theme-menu-item" data-theme-val="red" onclick="UI.setTheme('red')">
                                <span class="theme-dot dot-red"></span>
                                <span>Đỏ Crimson</span>
                                <span class="theme-check-icon">✓</span>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary" onclick="App.showDeployProjectModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Triển khai dự án
                    </button>
                    <button class="btn btn-ghost" onclick="App.refreshSystemCache()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 11-9-9c2.52 0 4.8 1.04 6.44 2.73L21 8M21 3v5h-5"/></svg>
                        Làm mới
                    </button>
                </div>
            </header>

            <div class="content-body">
                <!-- VIEW: DASHBOARD -->
                <div id="view-dashboard" class="view-section">
                    <div class="stats-grid" id="stats-summary">
                        <div class="stat-card stat-card-total">
                            <div class="stat-icon-box">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-label">Tổng dự án</div>
                                <div class="stat-value" id="stat-total-projects">--</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card-configured">
                            <div class="stat-icon-box">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-label">Đã cấu hình</div>
                                <div class="stat-value" id="stat-configured">--</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card-demo">
                            <div class="stat-icon-box">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-label">Dự án Demo</div>
                                <div class="stat-value" id="stat-demo">--</div>
                            </div>
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
                            <div class="section-header project-section-header">
                                <div class="section-header-left">
                                    <h2 class="section-title" id="content-title">Danh sách dự án</h2>
                                </div>
                                <div class="project-toolbar-actions">
                                    <!-- Search Box -->
                                    <div class="project-search-wrap">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                                        <input type="text" id="project-search-input" placeholder="Tìm dự án..." oninput="App.onProjectSearch(this.value)">
                                    </div>

                                    <!-- Sort Custom Dropdown -->
                                    <div class="project-sort-wrap" id="sort-dropdown-wrap">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
                                        <button class="sort-dropdown-btn" id="sort-dropdown-btn" onclick="App.toggleSortDropdown(event)">
                                            <span id="sort-label-text">Mới nhất trước</span>
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                        </button>
                                        <div class="sort-dropdown-menu" id="sort-dropdown-menu">
                                            <div class="sort-opt" data-val="date_desc" onclick="App.onProjectSortChange('date_desc')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                Mới nhất trước
                                            </div>
                                            <div class="sort-opt" data-val="date_asc" onclick="App.onProjectSortChange('date_asc')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 8 14"/></svg>
                                                Cũ nhất trước
                                            </div>
                                            <div class="sort-opt" data-val="name_asc" onclick="App.onProjectSortChange('name_asc')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="9" y2="18"/></svg>
                                                Tên: A → Z
                                            </div>
                                            <div class="sort-opt" data-val="name_desc" onclick="App.onProjectSortChange('name_desc')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="9" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                                                Tên: Z → A
                                            </div>
                                        </div>
                                    </div>

                                    <!-- View Toggle -->
                                    <div class="view-toggle-wrap">
                                        <button id="view-btn-card" class="view-toggle-btn active" onclick="App.onViewModeChange('card')" title="Dạng Card">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg>
                                        </button>
                                        <button id="view-btn-list" class="view-toggle-btn" onclick="App.onViewModeChange('list')" title="Dạng List">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="3" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="3" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>
                                        </button>
                                    </div>

                                    <div class="toolbar-sep"></div>

                                    <button class="btn btn-ghost" onclick="App.createCustomMonthFolder()" style="font-size:0.8rem; padding:6px 12px; height:34px;">
                                        ➕ Tạo thư mục
                                    </button>
                                </div>
                            </div>


                            <div id="project-list" class="grid-container">
                                <!-- Dynamic content here -->
                                <div class="subtitle">Chọn một tháng để xem dự án...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW: PROJECT DETAIL (FULL PAGE) -->
                <div id="view-project-detail" class="view-section" style="display:none; padding-top: 10px;">
                    <div class="project-master-tabs tabs" style="display:none; margin-top: 0; margin-bottom: 20px;">
                        <button class="btn btn-ghost project-tab-btn tab active" onclick="UI.switchProjectTab(this, 'd_tab-config')">⚙️ Cấu hình &amp; Deploy</button>
                        <button class="btn btn-ghost project-tab-btn tab" onclick="UI.switchProjectTab(this, 'd_tab-fonts')">🖋️ Quản lý Fonts</button>
                        <button class="btn btn-ghost project-tab-btn tab" onclick="UI.switchProjectTab(this, 'd_tab-webp')">🖼️ Convert Ảnh WebP</button>
                        <button class="btn btn-ghost project-tab-btn tab" onclick="UI.switchProjectTab(this, 'd_tab-trim')">✂️ Trim Ảnh</button>
                        <button class="btn btn-ghost project-tab-btn tab" onclick="UI.switchProjectTab(this, 'd_tab-auto-media')">🤖 Tự động Map Ảnh</button>
                        <button class="btn btn-ghost project-tab-btn tab" onclick="UI.switchProjectTab(this, 'd_tab-seed')">🌱 Tạo Dữ Liệu Mẫu</button>
                        <button id="tab-btn-filemanager" class="btn btn-ghost project-tab-btn tab" style="display:none;" onclick="UI.switchProjectTab(this, 'd_tab-filemanager'); typeof FileManager !== 'undefined' && FileManager.init()">📁 Quản lý File (Host)</button>
                        <button id="tab-btn-synccenter" class="btn btn-ghost project-tab-btn tab" style="display:none;" onclick="UI.switchProjectTab(this, 'd_tab-synccenter'); typeof SyncCenter !== 'undefined' && SyncCenter.init()">🔄 Sync Center</button>
                    </div>

                    <!-- TAB: CONFIG & DEPLOY -->
                    <div id="d_tab-config" class="project-tab-content project-master-layout content-grid">
                        <div class="master-actions-sidebar" id="d_master-action-buttons">
                            <!-- Dynamically populated sections matching sample -->
                        </div>

                        <div class="master-config-area">
                            <div id="d_master-deployed-info" class="deployed-info-container"></div>
                            <div id="d_master-history-info"></div>
                        </div>
                    </div>

                    <!-- TAB: VISUAL SCHEMA BUILDER -->
                    <div id="d_tab-schema" class="project-tab-content d-none">
                        <div class="card-container card-padded" style="background: rgba(13, 17, 25, 0.7); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; display: flex; flex-direction: column; min-height: calc(100vh - 140px);">
                            <div class="flex-between-center-mb20" style="padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); gap: 16px;">
                                <div class="flex-align-center-gap10">
                                    <div class="accent-bar-primary" style="width: 4px; height: 18px; border-radius: 2px; background: var(--accent-gradient, #00d2d3);"></div>
                                    <div>
                                        <h3 class="card-title-sm" style="font-size: 14px; font-weight: 800; letter-spacing: 0.03em; color: #fff; margin: 0;">VISUAL SCHEMA BUILDER</h3>
                                        <p id="sb-project-name" style="margin: 0; font-size: 12px; color: var(--text-muted, #64748b);">Tùy biến trực quan cấu hình Type, Module và Form nhập liệu</p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: nowrap; flex-shrink: 0;">
                                    <div style="display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); height: 38px; padding: 0 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); user-select: none;">
                                        <span style="font-size: 0.76rem; color: var(--text-secondary, #94a3b8); font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">Đa ngôn ngữ</span>
                                        <label class="sb-switch">
                                            <input type="checkbox" id="sb-global-lang" onchange="SchemaBuilder.toggleGlobalLang(this.checked)">
                                            <span class="sb-slider"></span>
                                        </label>
                                    </div>
                                    <select id="sb-file-select" onchange="SchemaBuilder.loadSelectedFile()"></select>
                                    <button type="button" class="btn btn-primary" id="sb-btn-save-top" onclick="SchemaBuilder.save()" style="height: 38px; padding: 0 18px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                        💾 Lưu cấu hình
                                    </button>
                                </div>
                            </div>

                            <div id="sb-content" style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; min-height: 650px; margin-bottom: 12px;">
                                <div id="sb-form-wrapper" style="overflow-y: auto; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); max-height: calc(100vh - 230px);">
                                    <div id="sb-form-container"></div>
                                </div>
                                <div id="sb-preview-wrapper" style="display: flex; flex-direction: column; background: #000; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); max-height: calc(100vh - 230px);">
                                    <div style="display: flex; background: rgba(255,255,255,0.05); border-bottom: 1px solid var(--border);">
                                        <button id="sb-tab-preview" class="btn btn-ghost active" onclick="SchemaBuilder.switchTab('preview')" style="border-radius: 0; border: none; border-bottom: 2px solid var(--primary); font-size: 0.75rem; padding: 10px 20px; font-weight: 600;">📄 DỮ LIỆU JSON</button>
                                        <button id="sb-tab-structure" class="btn btn-ghost" onclick="SchemaBuilder.switchTab('structure')" style="border-radius: 0; border: none; font-size: 0.75rem; padding: 10px 20px; font-weight: 600;">🌿 CẤU TRÚC TYPE</button>
                                    </div>
                                    <div id="sb-preview-content" style="flex: 1; overflow-y: auto;">
                                        <pre id="sb-live-preview" style="padding: 15px; color: #10b981; font-family: monospace; font-size: 0.75rem; margin: 0; white-space: pre-wrap;"></pre>
                                        <div id="sb-structure-list" style="display: none; padding: 20px;"></div>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.06);">
                                <div id="sb-status" style="font-size: 0.85rem; color: var(--muted);"></div>
                                <button type="button" class="btn btn-primary" id="sb-btn-save" onclick="SchemaBuilder.save()" style="padding: 8px 22px; font-weight: 700;">
                                    💾 Lưu cấu hình
                                </button>
                            </div>
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
                                        <button class="btn btn-secondary" onclick="FontManager.reindex()" title="Đồng bộ lại chỉ mục font từ tree.md">🔄 Đồng bộ</button>
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

                    <!-- TAB: SYNCCENTER -->
                    <div id="d_tab-synccenter" class="project-tab-content d-none">
                        <div class="card-container card-padded fm-shell" style="background: rgba(13, 17, 25, 0.7); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px;">
                            <div class="flex-between-center-mb20" style="padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                                <div class="flex-align-center-gap10">
                                    <div class="accent-bar-primary" style="width: 4px; height: 18px; border-radius: 2px; background: var(--accent-gradient, #00d2d3);"></div>
                                    <div>
                                        <h3 class="card-title-sm" style="font-size: 14px; font-weight: 800; letter-spacing: 0.03em; color: #fff; margin: 0;">TRUNG TÂM ĐỒNG BỘ 2 CHIỀU (SYNC CENTER)</h3>
                                        <p style="margin: 0; font-size: 12px; color: var(--text-muted, #64748b);">Tự động đối soát và đồng bộ mã nguồn giữa Local Workspace và Demo Hosting</p>
                                    </div>
                                </div>
                                <div class="flex-align-center-gap10" style="display: flex; align-items: center; gap: 10px;">
                                    <label class="sc-cleardata-toggle" style="height: 38px; box-sizing: border-box; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; padding: 0 14px; border-radius: 8px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.15); font-size: 12.5px; font-weight: 600; color: #cbd5e1; user-select: none; transition: all 0.2s; text-transform: none; letter-spacing: normal; margin: 0;" title="Mặc định tắt để bảo vệ dữ liệu Demo. Bật nếu muốn quét và đồng bộ các file chức năng xoá dữ liệu (ClearDataController, router...)">
                                        <input type="checkbox" id="sc-check-cleardata" onchange="SyncCenter.toggleClearData(this.checked)" style="accent-color: var(--primary, #00d2d3); width: 15px; height: 15px; cursor: pointer; margin: 0;">
                                        <span style="text-transform: none; letter-spacing: normal;">Đồng bộ ClearData</span>
                                    </label>
                                    <button class="btn btn-ghost btn-sm" onclick="SyncCenter.openBackupHistory()" style="height: 38px; box-sizing: border-box; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 0 14px; border-radius: 8px; border-color: rgba(255,255,255,0.15); color: #cbd5e1; margin: 0; text-transform: none;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> 📦 Lịch sử Backup &amp; Khôi phục
                                    </button>
                                    <button class="btn btn-outline btn-sm" onclick="SyncCenter.scan()" style="height: 38px; box-sizing: border-box; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 0 14px; border-radius: 8px; border-color: rgba(255,255,255,0.15); margin: 0; text-transform: none;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg> Quét &amp; So sánh
                                    </button>
                                </div>
                            </div>
                            <div id="sync-center-content">
                                <div style="padding: 50px 20px; text-align: center; color: var(--text-muted, #888); background: rgba(255,255,255,0.01); border-radius: 12px; border: 1px dashed rgba(255,255,255,0.06);">
                                    <div style="display: inline-flex; width: 56px; height: 56px; border-radius: 50%; background: rgba(255,255,255,0.03); align-items: center; justify-content: center; margin-bottom: 14px;">
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="opacity: 0.5;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                                    </div>
                                    <h4 style="color: #cbd5e1; font-size: 15px; margin-bottom: 4px; font-weight: 600;">Sẵn sàng đối soát mã nguồn</h4>
                                    <p style="font-size: 13px; max-width: 440px; margin: 0 auto; color: #64748b;">Nhấn <b>"Quét & So sánh"</b> để kiểm tra các file đã được AI hoặc thành viên khác chỉnh sửa.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: BACKUP & RESTORE HISTORY -->
                    <div id="d_tab-backups" class="project-tab-content d-none">
                        <div class="card-container card-padded fm-shell" style="background: rgba(13, 17, 25, 0.7); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px;">
                            <div class="flex-between-center-mb20" style="padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                                <div class="flex-align-center-gap10">
                                    <div class="accent-bar-primary" style="width: 4px; height: 18px; border-radius: 2px; background: var(--accent-gradient, #00d2d3);"></div>
                                    <div>
                                        <h3 class="card-title-sm" style="font-size: 14px; font-weight: 800; letter-spacing: 0.03em; color: #fff; margin: 0;">LỊCH SỬ SAO LƯU &amp; KHÔI PHỤC (BACKUP SNAPSHOTS)</h3>
                                        <p style="margin: 0; font-size: 12px; color: var(--text-muted, #64748b);">Tự động lưu trữ bản gốc trước mọi thao tác ghi đè — Khôi phục 1-click về Local hoặc Demo</p>
                                    </div>
                                </div>
                                <div class="flex-align-center-gap10">
                                    <button class="btn btn-ghost btn-sm" onclick="SyncCenter.openBackupHistoryTab()" style="display: flex; align-items: center; gap: 6px; font-weight: 600; padding: 7px 14px; border-radius: 8px; border-color: rgba(255,255,255,0.15); color: #cbd5e1;">
                                        🔄 Tải lại
                                    </button>
                                </div>
                            </div>
                            <div id="sc-backup-tab-main">
                                <!-- Dynamic content rendered by SyncCenter.renderBackupHistoryTab() -->
                            </div>
                        </div>
                    </div>

                    <!-- TAB: FILE MANAGER -->
                    <div id="d_tab-filemanager" class="project-tab-content d-none">
                        <div class="card-container card-padded fm-shell" style="background: rgba(13, 17, 25, 0.7); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px;">
                            <div class="flex-between-center-mb20" style="padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                                <div class="flex-align-center-gap10">
                                    <div class="accent-bar-primary" style="width: 4px; height: 18px; border-radius: 2px; background: var(--accent-gradient, #00d2d3);"></div>
                                    <div>
                                        <h3 class="card-title-sm" style="font-size: 14px; font-weight: 800; letter-spacing: 0.03em; color: #fff; margin: 0;">QUẢN LÝ FILE TRÊN HOSTING (FTP)</h3>
                                        <p style="margin: 0; font-size: 12px; color: var(--text-muted, #64748b);">Duyệt, chỉnh sửa và quản lý file trực tiếp trên Server</p>
                                    </div>
                                </div>
                                <div class="flex-align-center-gap10">
                                    <button class="btn btn-primary" onclick="FileManager.showUploadModal()">☁️ Tải lên</button>
                                    <button class="btn btn-secondary" onclick="FileManager.showCreateDirModal()">📁 Tạo thư mục</button>
                                    <button class="btn btn-ghost" onclick="FileManager.loadCurrentPath(); FileManager.loadTree();">🔄 Làm mới</button>
                                </div>
                            </div>
                            
                            <!-- 2-Column Split: Tree View & File List -->
                            <div class="fm-layout-split" style="display: flex; gap: 16px; min-height: 520px;">
                                <!-- Left Sidebar: Directory Tree -->
                                <div class="fm-tree-sidebar" style="width: 250px; min-width: 210px; max-width: 300px; background: rgba(0,0,0,0.25); border: 1px solid var(--border); border-radius: 10px; display: flex; flex-direction: column; overflow: hidden;">
                                    <div style="padding: 10px 14px; background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8); display: flex; align-items: center; gap: 6px;">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> CÂY THƯ MỤC
                                        </span>
                                        <button class="btn btn-ghost btn-sm" onclick="FileManager.loadTree(true); FileManager.loadCurrentPath();" style="padding: 2px 6px; font-size: 11px;" title="Tải lại cây thư mục">🔄</button>
                                    </div>
                                    <div id="fm-tree-container" style="flex: 1; overflow-y: auto; padding: 10px 8px; font-size: 13px;">
                                        <div style="color: var(--text-muted, #888); text-align: center; padding: 20px 0; font-size: 12px;">Đang tải cây thư mục...</div>
                                    </div>
                                </div>

                                <!-- Right Main Pane: Breadcrumb, Search & Table -->
                                <div class="fm-main-pane" style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 12px;">
                                    <!-- Breadcrumb & Search -->
                                    <div style="display: flex; justify-content: space-between; align-items: stretch; gap: 12px; height: 38px;">
                                        <div class="fm-breadcrumb" id="fm-breadcrumb" style="flex: 1; padding: 0 14px; background: rgba(0,0,0,0.2); border-radius: 8px; font-family: monospace; font-size: 0.88rem; border: 1px solid var(--border); overflow-x: auto; white-space: nowrap; display: flex; align-items: center; height: 100%; box-sizing: border-box;">
                                            <span class="fm-path-segment" onclick="FileManager.navigateTo('/')">/</span>
                                        </div>
                                        <div style="min-width: 250px; height: 100%;">
                                            <input type="text" id="fm-search-input" placeholder="🔍 Tìm kiếm file..." style="width: 100%; height: 100%; padding: 0 14px; border-radius: 8px; border: 1px solid var(--border); background: rgba(0,0,0,0.25); color: #fff; font-size: 0.85rem; outline: none; box-sizing: border-box;" oninput="FileManager.filterList(this.value)">
                                        </div>
                                    </div>
                                    
                                    <div class="fm-list-container" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; flex: 1;">
                                        <table class="table table-webp" style="width: 100%; border-collapse: collapse;">
                                            <thead>
                                                <tr style="border-bottom:1px solid var(--border); text-align:left; color:var(--muted); background: rgba(0,0,0,0.1);">
                                                    <th style="padding: 10px 14px; width: 40px;"></th>
                                                    <th style="padding: 10px 14px;">Tên File / Thư mục</th>
                                                    <th style="padding: 10px 14px; width: 120px;">Kích thước</th>
                                                    <th style="padding: 10px 14px; width: 180px;">Ngày sửa</th>
                                                    <th style="padding: 10px 14px; width: 100px; text-align: right;">Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody id="fm-file-list">
                                                <tr>
                                                    <td colspan="5" style="text-align:center; padding:30px; color:var(--muted);">Đang kết nối FTP...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
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

                <!-- VIEW: DEMO SERVERS MANAGER -->
                <div id="view-demo-servers" class="view-section" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">☁️ Quản lý Server Demo</h2>
                        <button class="btn btn-primary" onclick="App.addDemoServer()">➕ Thêm Server</button>
                    </div>
                    <div id="demo-servers-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px;">
                        <!-- JS Render -->
                    </div>
                </div>

                <!-- VIEW: GLOBAL CONFIG (SETTING) -->
                <div id="view-global-config" class="view-section" style="display:none;">
                    <div class="section-header">
                        <h2 class="section-title">⚙️ Cấu hình chung (Setting)</h2>
                    </div>
                    
                    <div class="card-container card-padded" style="margin-top:20px;">
                        <form id="global-config-form">

                            <div style="padding-top: 5px; margin-bottom: 15px;">
                                <label style="color: var(--primary); margin-bottom:12px; font-size:0.7rem;">🎨 GIAO DIỆN &amp; MÀU SẮC (THEME)</label>
                                <div class="form-group">
                                    <label>Chọn Theme màu hệ thống</label>
                                    <select id="g_theme_selector" onchange="UI.setTheme(this.value)">
                                        <option value="mint">🌿 Xanh Mint (Mint Green Glow - Mặc định)</option>
                                        <option value="emerald">🌲 Lục Bảo (Emerald Green)</option>
                                        <option value="cyan">❄️ Băng Tuyết (Cyber Cyan)</option>
                                        <option value="ocean">🌊 Xanh Biển (Ocean Blue)</option>
                                        <option value="indigo">🌌 Chàm (Electric Indigo)</option>
                                        <option value="purple">🔮 Tím Neon (Cyber Purple)</option>
                                        <option value="rose">🌹 Hồng Ruby (Neon Rose)</option>
                                        <option value="orange">🟠 Cam Cyber (Neon Orange)</option>
                                        <option value="amber">👑 Hoàng Kim (Sunset Amber)</option>
                                        <option value="red">🔥 Đỏ Rực (Crimson Red)</option>
                                    </select>
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
                                    <div class="form-group"><label>Source Folder Name</label><input type="text" id="g_source_folder_name" placeholder="source_laravel"></div>
                                </div>
                                <div class="form-grid-2">
                                    <div class="form-group"><label>Source DB Name</label><input type="text" id="g_source_db_name" placeholder="source_nasani_2026"></div>
                                    <div class="form-group">
                                        <label>Editor Path (Mở dự án)</label>
                                        <input type="text" id="g_editor_path" placeholder="C:\Users\...\Antigravity.exe">
                                    </div>
                                </div>
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Font Source Path (Thư viện Font local)</label>
                                        <input type="text" id="g_font_source_path" placeholder="D:/RBWStack/www/font_library">
                                    </div>
                                    <div class="form-group">
                                        <label>🌱 Thư viện Ảnh Mẫu (Tạo Dữ Liệu Mẫu)</label>
                                        <input type="text" id="g_images_pool_path" placeholder="D:/RBWStack/www/images">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 10px;">
                                    <label>📁 Cấu hình Định dạng Thư mục Tháng (Mặc định)</label>
                                    <select id="g_month_folder_format" class="custom-select">
                                        <option value="YYYY_MM">YYYY_MM (Ví dụ: 2026_08 - Chuẩn mặc định)</option>
                                        <option value="YYYY/thangMM">YYYY/thangMM (Ví dụ: 2026/thang08)</option>
                                        <option value="thangMM">thangMM (Ví dụ: thang08)</option>
                                        <option value="YYYY/YYtMM">YYYY/YYtMM (Ví dụ: 2026/26t08)</option>
                                    </select>
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
                <label>Chọn Server Demo</label>
                <select id="pre_deploy_demo_server_id" class="form-control" style="background:#1a1c23; border:1px solid #333; color:#fff; padding:8px; border-radius:4px; width:100%; outline:none;">
                    <!-- Populated by JS -->
                </select>
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

    <!-- Detail Hosting Config Modal -->
    <div id="detail-config-modal" class="modal-overlay">
        <div class="modal" style="max-width: 650px; width: 95%;">
            <div class="modal-header-flex">
                <h2>⚙️ Cấu hình Hosting</h2>
                <button class="btn btn-close-circle" onclick="UI.hideModal('detail-config-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="quick-paste-box">
                <div class="flex-between-center" style="margin-bottom: 6px;">
                    <label class="label-m0" style="color:var(--primary);">⚡ PASTE NHANH CẤU HÌNH</label>
                </div>
                <textarea id="d_quick_paste" placeholder="Dán thông tin hosting tại đây (DA / FTP info)..." style="height: 60px;"></textarea>
                <button type="button" class="btn btn-primary btn-sm-full" onclick="UI.parseQuickConfig('detail')">Phân tích &amp; Đổ dữ liệu</button>
            </div>

            <form id="detail-config-form">
                <input type="hidden" id="d_current-project">
                <div class="form-grid-2">
                    <div class="form-group"><label>Host / IP (FTP)</label><input type="text" id="d_ftp_host" placeholder="ftp.domain.com"></div>
                    <div class="form-group"><label>Web Domain</label><input type="text" id="d_web_domain" placeholder="domain.com"></div>
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
                <div class="form-group"><label>FTP Root Path</label><input type="text" id="d_ftp_root" placeholder="/public_html"></div>
                
                <div class="modal-footer-actions">
                    <button type="button" class="btn btn-ghost" onclick="UI.hideModal('detail-config-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                </div>
            </form>
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

    <!-- Demo Server Quick Paste Modal -->
    <div id="demo-quick-paste-modal" class="modal-overlay">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header-flex">
                <h2>⚡ Dán nhanh cấu hình Demo</h2>
                <button class="btn btn-close-circle" onclick="UI.hideModal('demo-quick-paste-modal')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="quick-paste-box" style="margin:0; border:none; background:transparent; padding:0;">
                <textarea id="demo_quick_paste_text" placeholder="Dán văn bản cấu hình vào đây..." style="height:200px;"></textarea>
                <input type="hidden" id="demo_quick_paste_target_id">
                <button type="button" class="btn btn-primary btn-sm-full" style="padding:12px;" onclick="App.processDemoQuickPaste()">Phân tích & Đổ dữ liệu</button>
            </div>
        </div>
    </div>

    <!-- Local DB Confirm Modal -->
    <div id="local-db-confirm-modal" class="modal-overlay" style="z-index: 10006;">
        <div class="modal" style="max-width: 520px;">
            <div class="modal-header-flex">
                <h2>⚠️ Database Đã Tồn Tại</h2>
                <button class="btn btn-close-circle" onclick="UI.hideModal('local-db-confirm-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div style="margin: 15px 0;">
                <p id="local-db-confirm-message" style="font-size:0.9rem; color:var(--text-primary); line-height:1.6;"></p>
                <div style="margin-top:12px; font-size:0.8rem; color:var(--text-muted); background:rgba(255,255,255,0.03); border:1px solid var(--border); padding:12px; border-radius:8px;">
                    💡 <b>Lựa chọn của bạn:</b><br>
                    • <b>Đồng ý (Ghi đè DB):</b> Xóa DB cũ và nạp lại từ file .sql.<br>
                    • <b>Bỏ qua (Giữ DB cũ):</b> Giữ DB hiện tại và tự động cập nhật file .env.
                </div>
            </div>
            <div class="project-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button id="btn-local-db-skip" class="btn btn-ghost">Bỏ qua (Giữ DB cũ)</button>
                <button id="btn-local-db-overwrite" class="btn btn-primary" style="background:linear-gradient(135deg,#FF5E8F,#FF7E5F);">Ghi đè Database</button>
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
    <script src="assets/js/file_manager.js?v=<?= time(); ?>"></script>
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
                <input type="hidden" id="ct-project-category">
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
                <input type="hidden" id="cpp-project-category">
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


    <!-- MODAL: AI SEED PROMPT & DESIGN REFERENCE -->
    <div id="seed-ai-modal" class="modal-overlay">
        <div class="modal" style="max-width: 520px;">
            <div class="modal-header-flex">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:1.2rem;">✨</span>
                    <h2>Tạo Dữ Liệu bằng AI (Gemini Vision)</h2>
                </div>
                <button class="btn-close-circle" onclick="UI.hideModal('seed-ai-modal')">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 15px 0;">
                <!-- DESIGN IMAGE UPLOAD / PASTE SECTION -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display:flex; justify-content:space-between; align-items:center;">
                        <span>🎨 Ảnh mẫu Design (Figma / Screenshot UI)</span>
                        <span style="font-size:0.7rem; color:var(--primary); font-weight:600; text-transform:none;">✨ Hỗ trợ Ctrl + V</span>
                    </label>
                    <div id="seed-design-dropzone" class="seed-design-dropzone" onclick="document.getElementById('seed-design-file-input').click()">
                        <input type="file" id="seed-design-file-input" accept="image/*" style="display:none;" onchange="SeedManager.handleDesignFileSelect(this)">
                        
                        <div id="seed-design-empty" class="seed-design-placeholder">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--primary); opacity:0.8;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <p style="font-size:0.85rem; font-weight:600; color:#fff; margin:6px 0 2px;">Kéo thả ảnh hoặc bấm để tải lên</p>
                            <span style="font-size:0.75rem; color:var(--muted);">Hoặc nhấn <strong>Ctrl + V</strong> để dán ảnh chụp màn hình Figma</span>
                        </div>

                        <div id="seed-design-preview-wrap" class="seed-design-preview-wrap" style="display:none;">
                            <img id="seed-design-preview-img" src="" alt="Design Preview" class="seed-design-preview-img">
                            <div class="seed-design-preview-info">
                                <span id="seed-design-filename" class="seed-design-name">design_screenshot.png</span>
                                <button type="button" class="btn btn-ghost btn-sm btn-danger-text" onclick="event.stopPropagation(); SeedManager.removeDesignImage()" title="Xóa ảnh này">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg> Xóa
                                </button>
                            </div>
                        </div>
                    </div>
                    <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:6px; line-height:1.4;">
                        💡 AI sẽ đọc tiêu đề, trích xuất các bài mẫu có trong ảnh design, và tự động viết thêm các bài còn lại cho đủ số lượng bạn đã chọn.
                    </p>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Mô tả bổ sung / Tên ngành nghề (Tùy chọn)</label>
                    <input type="text" id="seed-ai-prompt" placeholder="Ví dụ: Salon làm tóc nam Quốc Kỳ, Thiết bị bếp thông minh..." class="form-control" style="width:100%; height:38px;">
                </div>

                <div class="form-group">
                    <label>Chọn model AI</label>
                    <select id="seed-ai-model" class="form-control" style="width:100%; height:38px; background:var(--surface-2); border:1px solid var(--border); color:#fff; border-radius:8px; padding:0 12px;">
                        <option value="gemini-3.5-flash">Gemini 3.5 Flash (Tốc độ tối đa & Thông minh nhất)</option>
                        <option value="gemini-3.1-flash-lite">Gemini 3.1 Flash Lite (Siêu nhẹ)</option>
                        <option value="gemini-2.5-flash">Gemini 2.5 Flash (Ổn định & Vision chuẩn)</option>
                        <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                        <option value="gemini-1.5-pro">Gemini 1.5 Pro (Độ chính xác cao)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer-actions">
                <button class="btn btn-ghost" onclick="UI.hideModal('seed-ai-modal')">Hủy</button>
                <button id="seed-ai-confirm-btn" class="btn btn-primary" onclick="SeedManager.runSeed(true)">✨ Phân tích Design &amp; Tạo dữ liệu</button>
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
