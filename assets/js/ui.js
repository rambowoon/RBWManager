console.log('UI.js loaded v1.1');
const UI = {
	renderCategories(categories) {
		const sidebar = document.getElementById('category-sidebar');
		if (!sidebar) return;
		sidebar.innerHTML = '';

		if (categories.length === 0) {
			sidebar.innerHTML =
				'<div class="subtitle">Không có danh mục nào.</div>';
			return;
		}

		categories.forEach((cat) => {
			const item = document.createElement('div');
			item.className = 'category-item';
			item.dataset.category = cat;
			if (App.currentCategory === cat) item.classList.add('active');

			item.onclick = () => {
				document
					.querySelectorAll('.category-item')
					.forEach((i) => i.classList.remove('active'));
				item.classList.add('active');
				App.loadProjects(cat);
			};

			const isStrict = /^\d{4}_\d{2}$/.test(cat);
			let displayTitle = '';
			let displaySubtitle = '';
			let displayIcon = '';

			if (isStrict) {
				const parts = cat.split('_');
				const year = parts[0];
				const month = parts[1];
				displayTitle = `Tháng ${month}`;
				displaySubtitle = year;
				displayIcon = month;
			} else {
				displayTitle = cat;
				displaySubtitle = 'Thư mục';
				displayIcon = cat.substring(0, 2).toUpperCase();
			}

			item.innerHTML = `
                <div class="category-item-icon">${displayIcon}</div>
                <div class="flex-1-minw0">
                    <div class="category-item-title" title="${displayTitle}">${displayTitle}</div>
                    <div class="category-item-subtitle">${displaySubtitle}</div>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
            `;
			sidebar.appendChild(item);
		});
	},

	renderProjects(projects, category) {
		const list = document.getElementById('project-list');
		if (!list) return;
		list.innerHTML = '';

		document.getElementById('current-category').innerText =
			category || 'Tất cả';
		document.getElementById('content-title').innerText =
			`Dự án: ${category || 'Tất cả'}`;

		if (projects.length === 0) {
			list.innerHTML =
				'<div class="subtitle subtitle-padded">Không có dự án nào trong tháng này.</div>';
			return;
		}

		// Stats calculation
		let stats = { total: projects.length, configured: 0, demo: 0 };

		projects.forEach((p) => {
			const card = document.createElement('div');
			card.className = 'item-card';

			const isConfigured =
				p.config &&
				(p.config.ftp_host ||
					(p.config.prod && p.config.prod.ftp_host));
			const isLockedDemo = !!(p.config && p.config.lock_demo);
			const isLockedProd = !!(p.config && p.config.lock_production);
			const hasDeployed = !!(p.config && p.config.deployed);

			card.dataset.lockDemo = isLockedDemo ? '1' : '0';
			card.dataset.lockProd = isLockedProd ? '1' : '0';
			card.dataset.configured = isConfigured ? '1' : '0';
			card.dataset.deployed = JSON.stringify(
				p.config && p.config.deployed ? p.config.deployed : {},
			);

			if (isConfigured) stats.configured++;
			if (isLockedDemo) stats.demo++;

			const safeName = p.name.replace(/'/g, "\\'").replace(/`/g, '\\`');
			const safeCat = category.replace(/'/g, "\\'").replace(/`/g, '\\`');

			const hasDemo = !!(p.config && p.config.deployed && p.config.deployed.demo);
			const hasProd = !!(p.config && p.config.deployed && p.config.deployed.production);

			let badgeClass = 'badge-pending';
			let badgeText = '⏳ Đang tiến hành';

			if (hasProd) {
				badgeClass = 'badge-prod';
				badgeText = '🚀 Đã up Production';
			} else if (hasDemo) {
				badgeClass = 'badge-ok';
				badgeText = '🌿 Đã up Demo';
			}

			card.onclick = () => App.openConfig(p.name, category);

			card.innerHTML = `
                <div class="item-card-header">
                    <div class="item-card-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                    </div>
                    <div class="item-card-main flex-1-minw0">
                        <div class="item-card-title">${p.name}</div>
                        <div class="item-card-desc">${p.relPath}</div>
                    </div>
                    <button class="btn btn-ghost btn-action-trigger" onclick="event.stopPropagation(); UI.openMenu(event, '${safeName}', '${safeCat}')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                </div>
                <div class="item-card-footer">
                    <span class="badge ${badgeClass}">${badgeText}</span>
                    <button class="btn btn-primary ${isLockedDemo ? 'btn-deploy-locked' : ''} btn-deploy-small" 
                        onclick="event.stopPropagation(); ${isLockedDemo ? "UI.notify('Dự án này đang bị KHÓA!', 'error')" : `App.deployDemo('${safeName}', '${safeCat}')`}">
                        🚀 Deploy
                    </button>
                </div>
            `;
			list.appendChild(card);
		});

		// Update stats in UI
		document.getElementById('stat-total-projects').innerText = stats.total;
		document.getElementById('stat-configured').innerText = stats.configured;
		document.getElementById('stat-demo').innerText = stats.demo;
	},

	// ===== PORTAL MENU (single element at body level) =====
	openMenu(event, projectName, category) {
		const trigger = event.currentTarget;
		const portal = document.getElementById('action-menu-portal');

		// Read state from parent attributes
		const card = trigger.closest('.item-card');
		const isLockedDemo = card.dataset.lockDemo === '1';
		const isLockedProd = card.dataset.lockProd === '1';
		const isConfigured = card.dataset.configured === '1';
		const deployedJson = card.dataset.deployed || '';

		const safeName = projectName;
		const safeCat = category;

		// Build menu content
		portal.innerHTML = `
            <button class="action-menu-item portal-antigravity-btn" onclick="UI.closePortalMenu(); App.openAntigravity('${safeName}')">
                <span class="menu-icon mi-purple"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></span>
                <strong class="portal-antigravity-text">Mở bằng Antigravity</strong>
            </button>
            <div class="menu-divider"></div>
            <button class="action-menu-item" onclick="UI.closePortalMenu(); App.openConfig('${safeName}')">
                <span class="menu-icon mi-cyan"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-2.82-1.17l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                Cấu hình
            </button>
            ${
				deployedJson
					? `
            <button id="portal-detail-btn" class="action-menu-item">
                <span class="menu-icon mi-blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
                Xem Thông tin DB
            </button>`
					: ''
			}
            <div class="menu-divider"></div>
            <button class="action-menu-item ${isLockedDemo ? 'disabled' : ''}" onclick="UI.closePortalMenu(); App.deployDemo('${safeName}','${safeCat}')">
                <span class="menu-icon mi-green"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
                Deploy Demo ${isLockedDemo ? '🔒' : ''}
            </button>
            <button class="action-menu-item ${isLockedDemo ? 'disabled' : ''}" onclick="UI.closePortalMenu(); App.deployDbDemo('${safeName}','${safeCat}')">
                <span class="menu-icon mi-green portal-db-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-2.239 10-5V7c0-2.761-4.477-5-10-5S2 4.239 2 7v10c0 2.761 4.477 5 10 5z"/><path d="M2 7c0 2.761 4.477 5 10 5s10-2.239 10-5"/><path d="M2 12c0 2.761 4.477 5 10 5s10-2.239 10-5"/></svg></span>
                Deploy DB Demo ${isLockedDemo ? '🔒' : ''}
            </button>
            <button class="action-menu-item" onclick="UI.closePortalMenu(); App.pushTools('${safeName}','${safeCat}')">
                <span class="menu-icon mi-cyan"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></span>
                Sync Tools
            </button>
            <button class="action-menu-item ${!isConfigured || isLockedProd ? 'disabled' : ''}" onclick="UI.closePortalMenu(); App.publishToProduction('${safeName}','${safeCat}')">
                <span class="menu-icon mi-blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 8 16 12 12 16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
                Publish Production ${isLockedProd ? '🔒' : ''}
            </button>
            <div class="menu-divider"></div>
            <button class="action-menu-item" onclick="UI.closePortalMenu(); App.installSSL('${safeName}')">
                <span class="menu-icon mi-purple"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                Cài SSL
            </button>
            <button class="action-menu-item" onclick="UI.closePortalMenu(); App.showChangePhpVersionModal('${safeName}')">
                <span class="menu-icon mi-green"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></span>
                Đổi PHP Version
            </button>
            <button class="action-menu-item" onclick="UI.closePortalMenu(); App.downloadPackage('${safeName}','${safeCat}')">
                <span class="menu-icon mi-amber"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
                Download Package
            </button>
            <div class="menu-divider"></div>
            <button class="action-menu-item portal-danger-item" onclick="UI.closePortalMenu(); App.cleanupTools('${safeName}','${safeCat}', 'demo')">
                <span class="menu-icon mi-red"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/></svg></span>
                Dọn dẹp Demo
            </button>
            <button class="action-menu-item portal-danger-item" onclick="UI.closePortalMenu(); App.cleanupTools('${safeName}','${safeCat}', 'production')">
                <span class="menu-icon mi-red"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/></svg></span>
                Dọn dẹp Production
            </button>
            <div class="menu-divider"></div>
            <button class="action-menu-item ${isLockedDemo ? 'portal-danger-item' : ''}" onclick="UI.closePortalMenu(); App.toggleActionLock('${safeName}','demo')">
                <span class="menu-icon ${isLockedDemo ? 'mi-red' : ''}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="${isLockedDemo ? 'M7 11V7a5 5 0 0 1 9.9-1' : 'M7 11V7a5 5 0 0 1 10 0v4'}"/></svg></span>
                ${isLockedDemo ? 'Mở khóa Demo' : 'Khóa Demo'}
            </button>
            <button class="action-menu-item ${isLockedProd ? 'portal-danger-item' : ''}" onclick="UI.closePortalMenu(); App.toggleActionLock('${safeName}','production')">
                <span class="menu-icon ${isLockedProd ? 'mi-red' : ''}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="${isLockedProd ? 'M7 11V7a5 5 0 0 1 9.9-1' : 'M7 11V7a5 5 0 0 1 10 0v4'}"/></svg></span>
                ${isLockedProd ? 'Mở khóa Production' : 'Khóa Production'}
            </button>
        `;

		// Bind deployed detail button safely (avoids JSON-in-attribute issues)
		if (deployedJson) {
			const detailBtn = portal.querySelector('#portal-detail-btn');
			if (detailBtn) {
				const deployedObj = JSON.parse(deployedJson);
				detailBtn.addEventListener('click', () => {
					UI.closePortalMenu();
					UI.renderProjectDetail(safeName, deployedObj);
				});
			}
		}

		// Position: absolute relative to document body
		portal.style.visibility = 'hidden';
		portal.style.display = 'block';

		const rect = trigger.getBoundingClientRect();
		const menuW = 210;
		const menuH = portal.offsetHeight || 280;

		let top = rect.bottom + window.scrollY + 6;
		let left = rect.right + window.scrollX - menuW;

		// Flip upward if not enough space below in the viewport
		if (rect.bottom + menuH > window.innerHeight - 10) {
			top = rect.top + window.scrollY - menuH - 6;
		}

		// Chống tràn mép trên (theo viewport)
		if (top < window.scrollY + 10) top = window.scrollY + 10;
		// Chống tràn mép phải/trái (theo viewport)
		if (left < window.scrollX + 10) left = window.scrollX + 10;
		if (left + menuW > window.scrollX + window.innerWidth - 10) {
			left = window.scrollX + window.innerWidth - menuW - 10;
		}

		portal.style.top = `${top}px`;
		portal.style.left = `${left}px`;
		portal.style.visibility = 'visible';

		// Close on outside click (defer so this click doesn't immediately close it)
		setTimeout(() => {
			const closeMenu = (e) => {
				if (!portal.contains(e.target)) {
					UI.closePortalMenu();
					document.removeEventListener('click', closeMenu);
				}
			};
			document.addEventListener('click', closeMenu);
		}, 10);
	},

	closePortalMenu() {
		const portal = document.getElementById('action-menu-portal');
		if (portal) portal.style.display = 'none';
	},

	showChangeTypeModal(name) {
		document.getElementById('ct-project-name').value = name;
		document.getElementById('ct-old-type').value = '';
		document.getElementById('ct-new-type').value = '';
		this.showModal('change-type-modal');
	},

	notify(message, type = 'info') {
		const container = document.getElementById('toast-container');
		if (!container) return;

		const toast = document.createElement('div');
		toast.className = `toast toast-${type}`;

		let icon = 'ℹ️';
		if (type === 'success') icon = '✔️';
		if (type === 'error') icon = '❌';

		toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-msg">${message}</div>
        `;

		container.appendChild(toast);

		// Tự động xóa sau 3 giây
		setTimeout(() => {
			toast.classList.add('hide');
			setTimeout(() => toast.remove(), 300);
		}, 3000);

		// Cho phép nhấn để đóng ngay
		toast.onclick = () => {
			toast.classList.add('hide');
			setTimeout(() => toast.remove(), 300);
		};
	},

	// ===== MODAL =====
	showModal(id) {
		const modal = document.getElementById(id);
		if (modal) {
			modal.style.display = 'flex';
			if (id === 'deploy-modal') {
				const title = document.getElementById('deploy-modal-title');
				if (title) title.innerText = 'Đang triển khai...';
				document.getElementById('progress-fill').style.width = '0%';
				document.getElementById('status-text').innerText = 'Chuẩn bị...';
				document.getElementById('log-output').innerHTML = '';
				document.getElementById('deploy-footer').style.display = 'none';
			}
		}
	},

	hideModal(id) {
		document.getElementById(id).style.display = 'none';
	},

	fillConfigForm(name, config) {
		document.getElementById('current-project').value = name;
		if (!config) config = {};
		const prod = config.prod || {};
		const deployed = config.deployed || {};

		Object.keys(prod).forEach((key) => {
			const el = document.getElementById(key);
			if (el && el.id !== 'current-project') {
				if (el.type === 'checkbox') el.checked = !!prod[key];
				else el.value = prod[key];
			}
		});
		
		// Render Master Actions & Info (Modal version)
		this.renderMasterActions(name, App.currentCategory, config, '');
		this.renderMasterDeployedInfo(deployed, '');
		this.renderMasterHistoryInfo(config.history, '');
	},

	fillProjectDetailForm(name, config, category) {
		document.getElementById('d_current-project').value = name;
		if (!config) config = {};
		const prod = config.prod || {};
		const deployed = config.deployed || {};

		// Map fields with d_ prefix
		const fields = [
			'ftp_host',
			'web_domain',
			'ftp_user',
			'da_user',
			'ftp_pass',
			'ftp_root',
		];
		fields.forEach((f) => {
			const el = document.getElementById('d_' + f);
			if (el) el.value = prod[f] || '';
		});

		// Render Master Actions & Info (Page version)
		this.renderMasterActions(name, category, config, 'd_');
		this.renderMasterDeployedInfo(deployed, 'd_');
		this.renderMasterHistoryInfo(config.history, 'd_');
	},

	renderMasterActions(name, category, config, prefix = '') {
		const container = document.getElementById(
			prefix + 'master-action-buttons',
		);
		if (!container) return;
		container.innerHTML = '';

		const isLockedDemo = !!(config && config.lock_demo);
		const isLockedProd = !!(config && config.lock_production);
		const prod = config.prod || {};

		// Nhóm: TRUY CẬP & TRIỂN KHAI
		this._addSectionTitle(container, '⚡ TRIỂN KHAI', 'var(--purple)');

		// Tính toán URLs
		const project = App.projects.find((p) => p.name === name);
		const localUrl = project
			? `http://localhost/${project.relPath.replace(/\\/g, '/')}/`
			: '';

		const getUrl = (url, isSsl = false) => {
			if (!url) return '';
			if (url.startsWith('http')) return url;
			const protocol = isSsl ? 'https' : 'http';
			return `${protocol}://${url}`;
		};

		const hasProd = !!(config.deployed && config.deployed.production);
		const hasDemo = !!(config.deployed && config.deployed.demo);

		// Tự động dựng Demo URL nếu thiếu
		let demoUrl = config.demo_url || '';
		if (!demoUrl && hasDemo && project) {
			const demoDomain = (App.globalConfig && App.globalConfig.web_domain) 
				? App.globalConfig.web_domain.replace(/https?:\/\//i, '').replace(/\/$/, '')
				: 'demo92.nasanivietnam.info';
			demoUrl = `${demoDomain}/${project.relPath.replace(/\\/g, '/')}/`;
		}

		const prodUrl = prod.web_domain || '';

		const isDemoSsl = !!(config.demo && config.demo.ssl);
		const isProdSsl = !!(config.prod && config.prod.ssl);

		let smartUrl = localUrl;
		let smartLabel = 'Website (Local)';
		let smartIcon = '🏠';
		let smartColor = '#94a3b8';

		if (hasProd && prodUrl) {
			smartUrl = getUrl(prodUrl, isProdSsl);
			smartLabel = 'Website (Prod)';
			smartIcon = '🌐';
			smartColor = '#6366f1';
		} else if (hasDemo && demoUrl) {
			smartUrl = getUrl(demoUrl, isDemoSsl);
			smartLabel = 'Website (Demo)';
			smartIcon = '🔗';
			smartColor = '#10b981';
		}

		const mainActions = [
			{
				label: smartLabel,
				icon: smartIcon,
				color: smartColor,
				onclick: () => window.open(smartUrl, '_blank'),
				hidden: !smartUrl,
			},
			{
				label: 'Deploy Demo',
				icon: '🚀',
				color: 'var(--primary)',
				onclick: () => App.deployDemo(name, category),
				disabled: isLockedDemo,
			},
			{
				label: 'Deploy DB Demo',
				icon: '🗄️',
				color: '#0ea5e9',
				onclick: () => App.deployDbDemo(name, category),
				disabled: isLockedDemo,
			},
			{
				label: 'Sync Tools',
				icon: '⚡',
				color: '#3b82f6',
				onclick: () => App.pushTools(name, category),
			},
			{
				label: 'Publish Production',
				icon: '🌍',
				color: 'var(--success)',
				onclick: () => App.publishToProduction(name, category),
				disabled: isLockedProd,
			},
		];
		mainActions.forEach((act) => this._createMasterBtn(container, act));

		// Nhóm: CÔNG CỤ & HỆ THỐNG
		this._addSectionTitle(container, '🛠️ CÔNG CỤ', 'var(--warning)');
		const toolActions = [
			{
				label: 'Download Package',
				icon: '📦',
				color: '#f59e0b',
				onclick: () => App.downloadPackage(name, category),
			},
			{
				label: 'Install SSL',
				icon: '🛡️',
				color: '#8b5cf6',
				onclick: () => App.installSSL(name),
			},
			{
				label: 'Đổi PHP Version',
				icon: '🌐',
				color: '#10b981',
				onclick: () => App.showChangePhpVersionModal(name),
			},
			{
				label: 'Đổi Type DB',
				icon: '🔄',
				color: '#fbbf24',
				onclick: () => UI.showChangeTypeModal(name),
			},
			{
				label: 'Visual Schema (GUI)',
				icon: '📑',
				color: 'var(--primary)',
				onclick: () => SchemaBuilder.init(name),
			},
			{
				label: 'Tích hợp AMP',
				icon: '⚡',
				color: '#f97316',
				onclick: () => App.integrateAMP(name, category),
			},
			{
				label: 'Mở bằng Antigravity',
				icon: '🚀',
				color: '#a78bfa',
				onclick: () => App.openAntigravity(name),
			},
			{
				label: 'Dọn dẹp Demo',
				icon: '🧹',
				color: 'var(--danger)',
				onclick: () => App.cleanupTools(name, category, 'demo'),
			},
			{
				label: 'Dọn dẹp Production',
				icon: '🧹',
				color: 'var(--danger)',
				onclick: () => App.cleanupTools(name, category, 'production'),
			},
		];
		toolActions.forEach((act) => this._createMasterBtn(container, act));


		// Nhóm: BẢO MẬT (LOCK)
		this._addSectionTitle(container, '🔒 BẢO MẬT', '#ef4444');
		const lockActions = [
			{
				label: isLockedDemo ? 'Mở khóa Demo' : 'Khóa Demo',
				icon: isLockedDemo ? '🔓' : '🔒',
				color: isLockedDemo ? '#10b981' : '#ef4444',
				onclick: () => App.toggleActionLock(name, 'demo'),
			},
			{
				label: isLockedProd ? 'Mở khóa Production' : 'Khóa Production',
				icon: isLockedProd ? '🔓' : '🔒',
				color: isLockedProd ? '#10b981' : '#ef4444',
				onclick: () => App.toggleActionLock(name, 'production'),
			},
		];
		lockActions.forEach((act) => this._createMasterBtn(container, act));
	},

	_addSectionTitle(container, text, color) {
		const div = document.createElement('div');
		div.className = 'master-section-title';
		div.style.color = color;
		div.style.marginTop = '15px';
		div.innerText = text;
		container.appendChild(div);
	},

	_createMasterBtn(container, act) {
		if (act.hidden) return;
		const btn = document.createElement('button');
		btn.className = `btn master-btn ${act.disabled ? 'disabled' : ''}`;
		if (act.disabled) btn.disabled = true;

		if (!act.disabled) {
			btn.style.color = act.color;
			btn.style.background = `${act.color}12`;
			btn.style.borderColor = `${act.color}22`;
		}

		btn.onclick = (e) => {
			e.preventDefault();
			act.onclick();
		};
		btn.innerHTML = `<span>${act.icon}</span> ${act.label}`;
		container.appendChild(btn);
	},

	renderMasterDeployedInfo(deployed, prefix = '') {
		const container = document.getElementById(
			prefix + 'master-deployed-info',
		);
		if (!container) return;

		const hasDemo = deployed && deployed.demo;
		const hasProd = deployed && deployed.production;

		if (!hasDemo && !hasProd) {
			container.style.display = 'none';
			container.innerHTML = '';
			return;
		}

		container.style.display = 'block';
		container.innerHTML = '';

		let html =
			'<div class="master-section-title master-section-title-success">📋 Thông tin triển khai</div>';

		if (hasDemo) {
			html += `
                <div class="info-box info-box-demo">
                    <div class="info-box-header">🌿 DEMO</div>
                    <div class="info-box-content">
                        DB: <code>${deployed.demo.db_name}</code><br>
                        Pass: <code class="code-demo">${deployed.demo.db_pass}</code>
                    </div>
                </div>`;
		}

		if (hasProd) {
			html += `
                <div class="info-box info-box-prod">
                    <div class="info-box-header">🚀 PRODUCTION</div>
                    <div class="info-box-content">
                        DB: <code>${deployed.production.db_name}</code><br>
                        Email: <code class="code-prod">${deployed.production.email_user}</code><br>
                        Pass: <code class="code-prod">${deployed.production.db_pass}</code>
                    </div>
                </div>`;
		}

		container.innerHTML = html;
	},

	renderMasterHistoryInfo(history, prefix = '') {
		const container = document.getElementById(prefix + 'master-history-info');
		if (!container) return;

		if (!history || history.length === 0) {
			container.style.display = 'none';
			container.innerHTML = '';
			return;
		}

		container.style.display = 'block';
		
		let html = '<div class="master-section-title master-section-title-history">🕒 Lịch sử thao tác</div>';
		html += '<div class="history-scroll-container">';

		history.forEach(item => {
			html += `
				<div class="history-item-box">
					<div class="history-item-header">
						<strong class="history-item-action">${item.action}</strong>
						<span class="history-item-time">${item.time}</span>
					</div>
					<div class="history-item-message">${item.message}</div>
				</div>
			`;
		});

		html += '</div>';
		container.innerHTML = html;
	},

	renderProjectDetail(name, deployed) {
		document.getElementById('detail-title').innerText =
			`Thông tin: ${name}`;
		const content = document.getElementById('detail-content');

		let html = '';
		if (deployed.demo) {
			html += `
                <div class="info-box info-box-demo">
                    <div class="info-box-header">🌿 Demo</div>
                    <div class="info-box-grid">
                        <div class="info-box-row"><span class="info-box-row-label-demo">Database</span><code class="code-neutral">${deployed.demo.db_name}</code></div>
                        <div class="info-box-row"><span class="info-box-row-label-demo">Username</span><code class="code-neutral">${deployed.demo.db_user}</code></div>
                        <div class="info-box-row"><span class="info-box-row-label-demo">Password</span><code class="code-demo">${deployed.demo.db_pass}</code></div>
                    </div>
                </div>`;
		}
		if (deployed.production) {
			html += `
                <div class="info-box info-box-prod">
                    <div class="info-box-header">🚀 Production</div>
                    <div class="info-box-grid">
                        <div class="info-box-row"><span class="info-box-row-label-prod">Database</span><code class="code-neutral">${deployed.production.db_name}</code></div>
                        <div class="info-box-row"><span class="info-box-row-label-prod">Username</span><code class="code-neutral">${deployed.production.db_user}</code></div>
                        <div class="info-box-row"><span class="info-box-row-label-prod">DB Pass</span><code class="code-prod">${deployed.production.db_pass}</code></div>
                        <div class="info-box-row"><span class="info-box-row-label-prod">Email Pass</span><code class="code-prod">${deployed.production.email_pass || '—'}</code></div>
                        <div class="info-box-meta">Triển khai: ${deployed.production.deploy_time}</div>
                    </div>
                </div>`;
		}

		content.innerHTML =
			html ||
			'<p class="color-muted">Chưa có thông tin triển khai.</p>';
		this.showModal('project-detail-modal');
	},

	updateDeployStatus(status, progress, logText = null) {
		document.getElementById('progress-fill').style.width = `${progress}%`;
		document.getElementById('status-text').innerText = status;

		// Cập nhật tiêu đề modal nếu trạng thái là kết thúc
		const titleEl = document.getElementById('deploy-modal-title');
		if (titleEl && (status === 'Thành công!' || status.includes('Lỗi'))) {
			titleEl.innerText = status;
		}

		if (logText) {
			const log = document.getElementById('log-output');
			log.innerHTML += `${logText}<br>`;
			log.scrollTop = log.scrollHeight;
		}
	},

	parseQuickConfig(mode = '') {
		const prefix = mode === 'detail' ? 'd_' : '';
		const text = document
			.getElementById(prefix + 'quick_paste')
			.value.trim();
		if (!text) return;

		const config = { da_port: '1111' };
		const lines = text.split(/\r?\n/);

		const findHosts = (str) => {
			const matches =
				str.match(
					/(?:https?:\/\/|ftp\.)?([a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|(?:\d{1,3}\.){3}\d{1,3})/gi,
				) || [];
			return matches.map(
				(m) =>
					m
						.replace(/https?:\/\//i, '')
						.replace(/ftp\./i, '')
						.split(':')[0],
			);
		};

		let currentSection = '';
		lines.forEach((line) => {
			line = line.trim();
			if (!line) return;
			if (line.match(/Hosting/i) || line.match(/Control Panel/i))
				currentSection = 'DA';
			else if (line.match(/FTP/i)) currentSection = 'FTP';

			if (line.match(/tên miền/i)) {
				const hosts = findHosts(line);
				if (hosts.length > 0) config.web_domain = hosts[0];
			}
			if (line.match(/Control panel/i) || line.match(/Host name/i)) {
				const hosts = findHosts(line);
				if (hosts.length > 0) {
					const ip = hosts.find((h) =>
						/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(h),
					);
					const host = ip || hosts[0];
					if (currentSection === 'DA' || !config.ftp_host)
						config.ftp_host = host;
				}
			}
			if (line.match(/Username/i)) {
				const val = line
					.replace(/Username[:\s\t]+/i, '')
					.split(/[\s\t]+/)[0];
				if (val) {
					if (currentSection === 'DA') config.da_user = val;
					else config.ftp_user = val;
				}
			}
			if (line.match(/Password/i)) {
				const val = line
					.replace(/Password[:\s\t]+/i, '')
					.split(/[\s\t]+/)[0];
				if (val) config.ftp_pass = val;
			}
		});

		// Đổ dữ liệu
		if (config.web_domain && document.getElementById(prefix + 'web_domain'))
			document.getElementById(prefix + 'web_domain').value =
				config.web_domain;
		if (config.ftp_host && document.getElementById(prefix + 'ftp_host'))
			document.getElementById(prefix + 'ftp_host').value =
				config.ftp_host;
		if (config.ftp_user && document.getElementById(prefix + 'ftp_user'))
			document.getElementById(prefix + 'ftp_user').value =
				config.ftp_user;
		if (config.da_user && document.getElementById(prefix + 'da_user'))
			document.getElementById(prefix + 'da_user').value = config.da_user;
		if (config.ftp_pass && document.getElementById(prefix + 'ftp_pass'))
			document.getElementById(prefix + 'ftp_pass').value =
				config.ftp_pass;

		document.getElementById(prefix + 'quick_paste').value = '';
		if (mode === 'detail') this.hideModal('quick-paste-modal');
		this.notify('Đã phân tích xong dữ liệu!', 'success');
	},

	togglePassword(targetId, btn) {
		const input = document.getElementById(targetId);
		const isPassword = input.type === 'password';
		input.type = isPassword ? 'text' : 'password';

		// Thay đổi icon
		if (isPassword) {
			btn.innerHTML =
				'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
		} else {
			btn.innerHTML =
				'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
		}
	},

	switchProjectTab(btn, tabId) {
		// Toggle Buttons
		const parent = btn.parentElement;
		parent.querySelectorAll('.btn').forEach((b) => b.classList.remove('active'));
		btn.classList.add('active');

		// Toggle Content
		const layout = btn.closest('.view-section');
		layout
			.querySelectorAll('.project-tab-content')
			.forEach((t) => t.classList.add('d-none'));
		document.getElementById(tabId).classList.remove('d-none');

		// Hook for specific tabs
		if (tabId === 'd_tab-fonts') {
			FontManager.loadCssPreview();
		} else if (tabId === 'd_tab-webp') {
			WebpManager.loadImages();
		} else if (tabId === 'd_tab-trim') {
			ImageTrimManager.init();
		} else if (tabId === 'd_tab-auto-media') {
			const projectName = document.getElementById('d_current-project')?.value || '';
			AutoMediaManager.init(projectName);
		} else if (tabId === 'd_tab-seed') {
			const projectName = document.getElementById('d_current-project')?.value || '';
			SeedManager.init(projectName);
		}
	},
};
