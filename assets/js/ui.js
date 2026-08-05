console.log("UI.js loaded v1.1");
const UI = {
	async confirm(message) {
		return new Promise((resolve) => {
			const overlay = document.createElement('div');
			overlay.className = 'modal-overlay';
			overlay.style.zIndex = '999999';
			overlay.style.display = 'flex';
			overlay.innerHTML = `
				<div class="modal" style="max-width: 450px; padding: 30px 25px; text-align: center; border-radius: 16px; background: #1a1e29; box-shadow: 0 20px 40px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05);">
                    <div style="margin-bottom: 25px;">
					    <div style="width: 64px; height: 64px; background: rgba(79, 157, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
					    <div style="font-size: 1.15rem; color: #e2e8f0; line-height: 1.6;">${message.replace(/\n/g, '<br>')}</div>
                    </div>
					<div style="display: flex; gap: 12px; justify-content: center;">
						<button class="btn btn-ghost" id="ui-confirm-cancel" style="min-width: 110px; font-weight: 500;">Hủy</button>
						<button class="btn btn-primary" id="ui-confirm-ok" style="min-width: 110px; font-weight: 500;">Đồng ý</button>
					</div>
				</div>
			`;
			document.body.appendChild(overlay);
			overlay.querySelector('#ui-confirm-ok').onclick = () => { overlay.remove(); resolve(true); };
			overlay.querySelector('#ui-confirm-cancel').onclick = () => { overlay.remove(); resolve(false); };
		});
	},

	async alert(message) {
		return new Promise((resolve) => {
			const overlay = document.createElement('div');
			overlay.className = 'modal-overlay';
			overlay.style.zIndex = '999999';
			overlay.style.display = 'flex';
			overlay.innerHTML = `
				<div class="modal" style="max-width: 450px; padding: 30px 25px; text-align: center; border-radius: 16px; background: #1a1e29; box-shadow: 0 20px 40px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05);">
                    <div style="margin-bottom: 25px;">
                        <div style="width: 64px; height: 64px; background: rgba(79, 157, 255, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>
					    <div style="font-size: 1.15rem; color: #e2e8f0; line-height: 1.6;">${message.replace(/\n/g, '<br>')}</div>
                    </div>
					<div style="display: flex; justify-content: center;">
						<button class="btn btn-primary" id="ui-alert-ok" style="min-width: 130px; font-weight: 500;">OK</button>
					</div>
				</div>
			`;
			document.body.appendChild(overlay);
			overlay.querySelector('#ui-alert-ok').onclick = () => { overlay.remove(); resolve(); };
		});
	},
	renderCategories(categories) {
		const sidebar = document.getElementById("category-sidebar");
		if (!sidebar) return;
		sidebar.innerHTML = "";

		if (categories.length === 0) {
			sidebar.innerHTML =
				'<div class="subtitle">Không có danh mục nào.</div>';
			return;
		}

		categories.forEach((cat) => {
			const item = document.createElement("div");
			item.className = "category-item";
			item.dataset.category = cat;
			if (App.currentCategory === cat) item.classList.add("active");

			item.onclick = () => {
				document
					.querySelectorAll(".category-item")
					.forEach((i) => i.classList.remove("active"));
				item.classList.add("active");
				App.loadProjects(cat);
			};

			const isStrict = /^\d{4}_\d{2}$/.test(cat);
			let displayTitle = "";
			let displaySubtitle = "";
			let displayIcon = "";

			if (isStrict) {
				const parts = cat.split("_");
				const year = parts[0];
				const month = parts[1];
				displayTitle = `Tháng ${month}`;
				displaySubtitle = year;
				displayIcon = month;
			} else {
				displayTitle = cat;
				displaySubtitle = "Thư mục";
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
		const list = document.getElementById("project-list");
		if (!list) return;
		list.innerHTML = "";

		document.getElementById("current-category").innerText =
			category || "Tất cả";
		document.getElementById("content-title").innerText =
			`Dự án: ${category || "Tất cả"}`;

		if (projects.length === 0) {
			list.innerHTML =
				'<div class="subtitle subtitle-padded">Không có dự án nào trong tháng này.</div>';
			return;
		}

		// Stats calculation
		let stats = { total: projects.length, configured: 0, demo: 0 };

		projects.forEach((p) => {
			const card = document.createElement("div");
			card.className = "item-card";

			const isConfigured =
				p.config &&
				(p.config.ftp_host ||
					(p.config.prod && p.config.prod.ftp_host));
			const isLockedDemo = !!(p.config && p.config.lock_demo);
			const isLockedProd = !!(p.config && p.config.lock_production);
			const hasDeployed = !!(p.config && p.config.deployed);

			card.dataset.lockDemo = isLockedDemo ? "1" : "0";
			card.dataset.lockProd = isLockedProd ? "1" : "0";
			card.dataset.configured = isConfigured ? "1" : "0";
			card.dataset.deployed = JSON.stringify(
				p.config && p.config.deployed ? p.config.deployed : {},
			);

			if (isConfigured) stats.configured++;
			if (isLockedDemo) stats.demo++;

			const safeName = p.name.replace(/'/g, "\\'").replace(/`/g, "\\`");
			const safeCat = category.replace(/'/g, "\\'").replace(/`/g, "\\`");

			const hasDemo = !!(
				p.config &&
				p.config.deployed &&
				p.config.deployed.demo &&
				p.config.deployed.demo.deploy_time
			);
			const hasProd = !!(
				p.config &&
				p.config.deployed &&
				p.config.deployed.production
			);

			let badgeClass = "badge-pending";
			let badgeText = "⏳ Đang tiến hành";
			let cardStatusClass = "card-status-pending";

			if (hasProd) {
				badgeClass = "badge-prod";
				badgeText = "🚀 Đã up Production";
				cardStatusClass = "card-status-prod";
			} else if (hasDemo) {
				badgeClass = "badge-ok";
				badgeText = "🌿 Đã up Demo";
				cardStatusClass = "card-status-demo";
			}

			card.className = `item-card ${cardStatusClass}`;

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
                    <button class="btn btn-primary ${isLockedDemo ? "btn-deploy-locked" : ""} btn-deploy-small" 
                        onclick="event.stopPropagation(); ${isLockedDemo ? "UI.notify('Dự án này đang bị KHÓA!', 'error')" : `App.deployDemo('${safeName}', '${safeCat}')`}">
                        🚀 Deploy
                    </button>
                </div>
            `;
			list.appendChild(card);
		});

		// Update stats in UI
		document.getElementById("stat-total-projects").innerText = stats.total;
		document.getElementById("stat-configured").innerText = stats.configured;
		document.getElementById("stat-demo").innerText = stats.demo;
	},

	// ===== PORTAL MENU (single element at body level) =====
	openMenu(event, projectName, category) {
		const trigger = event.currentTarget;
		const portal = document.getElementById("action-menu-portal");

		// Read state from parent attributes
		const card = trigger.closest(".item-card");
		const isLockedDemo = card.dataset.lockDemo === "1";
		const isLockedProd = card.dataset.lockProd === "1";
		const isConfigured = card.dataset.configured === "1";
		const deployedJson = card.dataset.deployed || "";

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
					: ""
			}
            <div class="menu-divider"></div>
            <button class="action-menu-item ${isLockedDemo ? "disabled" : ""}" onclick="UI.closePortalMenu(); App.deployDemo('${safeName}','${safeCat}')">
                <span class="menu-icon mi-green"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
                Deploy Demo ${isLockedDemo ? "🔒" : ""}
            </button>
            <button class="action-menu-item ${isLockedDemo ? "disabled" : ""}" onclick="UI.closePortalMenu(); App.deployDbDemo('${safeName}','${safeCat}')">
                <span class="menu-icon mi-green portal-db-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-2.239 10-5V7c0-2.761-4.477-5-10-5S2 4.239 2 7v10c0 2.761 4.477 5 10 5z"/><path d="M2 7c0 2.761 4.477 5 10 5s10-2.239 10-5"/><path d="M2 12c0 2.761 4.477 5 10 5s10-2.239 10-5"/></svg></span>
                Deploy DB Demo ${isLockedDemo ? "🔒" : ""}
            </button>
            <button class="action-menu-item" onclick="UI.closePortalMenu(); App.pushTools('${safeName}','${safeCat}')">
                <span class="menu-icon mi-cyan"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></span>
                Sync Tools
            </button>
            <button class="action-menu-item ${!isConfigured || isLockedProd ? "disabled" : ""}" onclick="UI.closePortalMenu(); App.publishToProduction('${safeName}','${safeCat}')">
                <span class="menu-icon mi-blue"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 8 16 12 12 16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
                Publish Production ${isLockedProd ? "🔒" : ""}
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
            <button class="action-menu-item ${isLockedDemo ? "portal-danger-item" : ""}" onclick="UI.closePortalMenu(); App.toggleActionLock('${safeName}','demo')">
                <span class="menu-icon ${isLockedDemo ? "mi-red" : ""}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="${isLockedDemo ? "M7 11V7a5 5 0 0 1 9.9-1" : "M7 11V7a5 5 0 0 1 10 0v4"}"/></svg></span>
                ${isLockedDemo ? "Mở khóa Demo" : "Khóa Demo"}
            </button>
            <button class="action-menu-item ${isLockedProd ? "portal-danger-item" : ""}" onclick="UI.closePortalMenu(); App.toggleActionLock('${safeName}','production')">
                <span class="menu-icon ${isLockedProd ? "mi-red" : ""}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="${isLockedProd ? "M7 11V7a5 5 0 0 1 9.9-1" : "M7 11V7a5 5 0 0 1 10 0v4"}"/></svg></span>
                ${isLockedProd ? "Mở khóa Production" : "Khóa Production"}
            </button>
        `;

		// Bind deployed detail button safely (avoids JSON-in-attribute issues)
		if (deployedJson) {
			const detailBtn = portal.querySelector("#portal-detail-btn");
			if (detailBtn) {
				const deployedObj = JSON.parse(deployedJson);
				detailBtn.addEventListener("click", () => {
					UI.closePortalMenu();
					UI.renderProjectDetail(safeName, deployedObj);
				});
			}
		}

		// Position: absolute relative to document body
		portal.style.visibility = "hidden";
		portal.style.display = "block";

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
		portal.style.visibility = "visible";

		// Close on outside click (defer so this click doesn't immediately close it)
		setTimeout(() => {
			const closeMenu = (e) => {
				if (!portal.contains(e.target)) {
					UI.closePortalMenu();
					document.removeEventListener("click", closeMenu);
				}
			};
			document.addEventListener("click", closeMenu);
		}, 10);
	},

	closePortalMenu() {
		const portal = document.getElementById("action-menu-portal");
		if (portal) portal.style.display = "none";
	},

	showChangeTypeModal(name, category) {
		document.getElementById("ct-project-name").value = name;
		document.getElementById("ct-project-category").value = category;
		document.getElementById("ct-old-type").value = "";
		document.getElementById("ct-new-type").value = "";
		this.showModal("change-type-modal");
	},

	notify(message, type = "info") {
		const container = document.getElementById("toast-container");
		if (!container) return;

		const toast = document.createElement("div");
		toast.className = `toast toast-${type}`;

		let icon = "ℹ️";
		if (type === "success") icon = "✔️";
		if (type === "error") icon = "❌";

		toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-msg">${message}</div>
        `;

		container.appendChild(toast);

		// Tự động xóa sau 3 giây
		setTimeout(() => {
			toast.classList.add("hide");
			setTimeout(() => toast.remove(), 300);
		}, 3000);

		// Cho phép nhấn để đóng ngay
		toast.onclick = () => {
			toast.classList.add("hide");
			setTimeout(() => toast.remove(), 300);
		};
	},

	// ===== MODAL =====
	showModal(id) {
		const modal = document.getElementById(id);
		if (modal) {
			modal.style.display = "flex";
			if (id === "deploy-modal") {
				const title = document.getElementById("deploy-modal-title");
				if (title) title.innerText = "Đang triển khai...";
				document.getElementById("progress-fill").style.width = "0%";
				document.getElementById("status-text").innerText =
					"Chuẩn bị...";
				document.getElementById("log-output").innerHTML = "";
				document.getElementById("deploy-footer").style.display = "none";
			}
		}
	},

	hideModal(id) {
		document.getElementById(id).style.display = "none";
	},

	fillConfigForm(name, config) {
		document.getElementById("current-project").value = name;
		if (!config) config = {};
		const prod = config.prod || {};
		const deployed = config.deployed || {};

		Object.keys(prod).forEach((key) => {
			const el = document.getElementById(key);
			if (el && el.id !== "current-project") {
				if (el.type === "checkbox") el.checked = !!prod[key];
				else el.value = prod[key];
			}
		});

		// Render Master Actions & Info (Modal version)
		this.renderMasterActions(name, App.currentCategory, config, "");
		this.renderMasterDeployedInfo(deployed, "");
		this.renderMasterHistoryInfo(config.history, "");
	},

	fillProjectDetailForm(name, config, category) {
		document.getElementById("d_current-project").value = name;
		if (!config) config = {};
		const prod = config.prod || {};
		const deployed = config.deployed || {};

		// Map fields with d_ prefix
		const fields = [
			"ftp_host",
			"web_domain",
			"ftp_user",
			"da_user",
			"ftp_pass",
			"ftp_root",
		];
		fields.forEach((f) => {
			const el = document.getElementById("d_" + f);
			if (el) el.value = prod[f] || "";
		});

		// Render Master Actions & Info (Page version)
		this.renderMasterActions(name, category, config, "d_");
		this.renderMasterDeployedInfo(deployed, "d_");
		this.renderMasterHistoryInfo(config.history, "d_");
	},

	renderMasterActions(name, category, config, prefix = "") {
		const container = document.getElementById(
			prefix + "master-action-buttons",
		);
		if (!container) return;
		container.innerHTML = "";

		const isLockedDemo = !!(config && config.lock_demo);
		const isLockedProd = !!(config && config.lock_production);
		const prod = config.prod || {};

		const project = App.projects.find((p) => p.name === name);
		const localUrl = project
			? `http://localhost/${project.relPath.replace(/\\/g, "/")}/`
			: "";

		const getUrl = (url, isSsl = false) => {
			if (!url) return "";
			if (url.startsWith("http")) return url;
			const protocol = isSsl ? "https" : "http";
			return `${protocol}://${url}`;
		};

		const hasProd = !!(config.deployed && config.deployed.production);
		const hasDemo = !!(config.deployed && config.deployed.demo && config.deployed.demo.deploy_time);

		let demoUrl =
			config.deployed && config.deployed.demo && config.deployed.demo.url
				? config.deployed.demo.url
				: config.demo_url || "";
		if (!demoUrl && hasDemo && project) {
			let demoDomain = "demo92.nasanivietnam.net";
			const demoId = config.deployed.demo.demo_server_id || "legacy";
			if (App.globalConfig && App.globalConfig.demo_list) {
				const demoServer = App.globalConfig.demo_list.find(
					(d) => d.id === demoId,
				);
				if (demoServer)
					demoDomain = demoServer.web_domain
						.replace(/https?:\/\//i, "")
						.replace(/\/$/, "");
			}
			demoUrl = `${demoDomain}/${project.relPath.replace(/\\/g, "/")}/`;
		}

		const prodUrl = prod.web_domain || "";
		const isDemoSsl = !!(config.demo && config.demo.ssl);
		const isProdSsl = !!(config.prod && config.prod.ssl);

		const safeName = name.replace(/'/g, "\\'").replace(/`/g, "\\`");
		const safeCat = (category || "")
			.replace(/'/g, "\\'")
			.replace(/`/g, "\\`");

		const isConfiguredProd = !!(
			prod &&
			(prod.ftp_host || prod.web_domain || prod.da_user || prod.ftp_user)
		);
		const isConfiguredLocal = !!(config && config.configured_local);

		container.innerHTML = `
			<div class="env-block">
				<div class="section-title"><span class="swatch" style="background:linear-gradient(90deg,var(--local-a),var(--local-b))"></span><h2>Môi trường Local (Dev)</h2></div>
				<p class="section-sub">Chạy và chỉnh sửa dự án trên máy của bạn.</p>
				<div class="hero-action local-hero" onclick="${localUrl ? `window.open('${localUrl}', '_blank')` : ""}">
					<div class="content">
						<div class="icon">⌂</div>
						<div class="txt"><div class="t">Mở Website (Local)</div><div class="s">${localUrl ? "Xem trực tiếp trên trình duyệt" : "Chưa xác định đường dẫn"}</div></div>
						<div class="arrow">→</div>
					</div>
				</div>
				<div class="chip-row">
					<div class="chip ${isConfiguredLocal ? "locked" : "util"}" onclick="${isConfiguredLocal ? "UI.notify('Dự án này đã được cấu hình Source Local!', 'info')" : `App.setupLocalSource('${safeName}', '${safeCat}')`}"><div class="ic" style="background:${isConfiguredLocal ? "rgba(255,255,255,0.05)" : "rgba(79,157,255,0.15)"};color:${isConfiguredLocal ? "#98A0B8" : "#4F9DFF"};">${isConfiguredLocal ? "✓" : "🔧"}</div><div class="lbl">${isConfiguredLocal ? "Đã cấu hình Source" : "Cấu hình Source Local"}</div></div>
					<div class="chip neutral" onclick="App.openAntigravity('${safeName}')"><div class="ic">▶</div><div class="lbl">Mở bằng Antigravity</div></div>
					<div class="chip neutral" onclick="SchemaBuilder.init('${safeName}')"><div class="ic">▤</div><div class="lbl">Visual Schema</div></div>
					<div class="chip neutral" onclick="App.pushTools('${safeName}', '${safeCat}')"><div class="ic">⚡</div><div class="lbl">Sync Tools</div></div>
					<div class="chip util" onclick="UI.showChangeTypeModal('${safeName}', '${safeCat}')"><div class="ic">🔄</div><div class="lbl">Đổi Type DB</div></div>
					<div class="chip util" onclick="App.integrateAMP('${safeName}','${safeCat}')"><div class="ic">⚡</div><div class="lbl">Tích hợp AMP</div></div>
				</div>
			</div>

			<div class="env-block">
				<div class="section-title"><span class="swatch" style="background:linear-gradient(90deg,var(--demo-a),var(--demo-b))"></span><h2>Môi trường Demo</h2></div>
				<p class="section-sub">Bản xem trước dành cho khách hàng ${isLockedDemo ? "— hiện đang bị khóa." : ""}</p>
				<div class="hero-action demo-hero" onclick="${hasDemo && demoUrl ? `window.open('${getUrl(demoUrl, isDemoSsl)}', '_blank')` : ""}">
					<div class="content">
						<div class="icon">⛓</div>
						<div class="txt"><div class="t">${hasDemo ? "Mở Website (Demo)" : "Chưa Deploy Demo"}</div><div class="s">${demoUrl || "Chưa triển khai Demo"}</div></div>
						<div class="arrow">→</div>
					</div>
				</div>
				<div class="chip-row">
					<div class="chip ${isLockedDemo ? "locked" : ""}" onclick="${isLockedDemo ? "UI.notify('Dự án đang bị KHÓA!', 'error')" : `App.deployDemo('${safeName}','${safeCat}')`}"><div class="ic" style="background:rgba(255,183,77,0.15);color:#FFCB7A;">▶</div><div class="lbl">Deploy Demo</div></div>
					<div class="chip ${isLockedDemo ? "locked" : ""}" onclick="${isLockedDemo ? "UI.notify('Dự án đang bị KHÓA!', 'error')" : `App.deployDbDemo('${safeName}','${safeCat}')`}"><div class="ic" style="background:rgba(255,183,77,0.15);color:#FFCB7A;">▤</div><div class="lbl">Deploy DB Demo</div></div>
					${hasDemo ? `<div class="chip util" onclick="App.downloadPackage('${safeName}','${safeCat}')"><div class="ic">⭳</div><div class="lbl">Download Package</div></div>` : ""}
					${hasDemo ? `<div class="chip danger" onclick="App.cleanupTools('${safeName}','${safeCat}', 'demo')"><div class="ic">✎</div><div class="lbl">Dọn dẹp Demo</div></div>` : ""}
					<div class="chip" onclick="App.toggleActionLock('${safeName}','demo', '${safeCat}')"><div class="ic" style="background:rgba(63,216,160,0.15);color:#5FF0BE;">⚿</div><div class="lbl">${isLockedDemo ? "Mở khóa Demo" : "Khóa Demo"}</div></div>
				</div>
			</div>

			<div class="env-block">
				<div class="section-title"><span class="swatch" style="background:linear-gradient(90deg,var(--prod-a),var(--prod-b))"></span><h2>Môi trường Production</h2></div>
				${
					!isConfiguredProd
						? `
				<p class="section-sub">Chưa có cấu hình Hosting — vui lòng cấu hình Hosting trước khi Publish Production.</p>
				<div class="hero-action prod-hero" style="background:linear-gradient(135deg, rgba(255, 94, 143, 0.18), rgba(182, 68, 255, 0.1)); border:1px dashed rgba(255, 94, 143, 0.4);" onclick="UI.showModal('${prefix ? "detail-config-modal" : "config-modal"}')">
					<div class="content">
						<div class="icon">⚙</div>
						<div class="txt"><div class="t">⚙️ Cấu hình Hosting</div><div class="s">Nhập IP, FTP User, DA User, Web Domain để sẵn sàng Deploy Production</div></div>
						<div class="arrow">→</div>
					</div>
				</div>
				`
						: `
				<p class="section-sub">Website chính thức — mọi thao tác tại đây ảnh hưởng người dùng thật.</p>
				<div class="hero-action prod-hero" onclick="${isLockedProd ? "UI.notify('Production đang bị KHÓA!', 'error')" : `App.publishToProduction('${safeName}','${safeCat}')`}">
					<div class="content">
						<div class="icon">◉</div>
						<div class="txt"><div class="t">Publish Production</div><div class="s">Đưa bản mới nhất lên chính thức</div></div>
						<div class="arrow">→</div>
					</div>
				</div>
				<div class="chip-row">
					<div class="chip util" onclick="UI.showModal('${prefix ? "detail-config-modal" : "config-modal"}')"><div class="ic">⚙</div><div class="lbl">Cấu hình Hosting</div></div>
					${hasProd && prodUrl ? `<div class="chip neutral" onclick="window.open('${getUrl(prodUrl, isProdSsl)}', '_blank')"><div class="ic">🌐</div><div class="lbl">Website (Prod)</div></div>` : ""}
					<div class="chip util" onclick="App.installSSL('${safeName}', '${safeCat}')"><div class="ic">🔒</div><div class="lbl">Install SSL</div></div>
					<div class="chip util" onclick="App.showChangePhpVersionModal('${safeName}', '${safeCat}')"><div class="ic">🌐</div><div class="lbl">Đổi PHP Version</div></div>
					<div class="chip danger" onclick="App.cleanupTools('${safeName}','${safeCat}', 'production')"><div class="ic">✎</div><div class="lbl">Dọn dẹp Production</div></div>
					<div class="chip danger" onclick="App.toggleActionLock('${safeName}','production', '${safeCat}')"><div class="ic">⚿</div><div class="lbl">${isLockedProd ? "Mở khóa Production" : "Khóa Production"}</div></div>
				</div>
				`
				}
			</div>
		`;
	},

	togglePasswordText(elId, pass, btn) {
		const el = document.getElementById(elId);
		if (!el) return;
		if (el.innerText === "••••••••••••") {
			el.innerText = pass;
			btn.innerText = "Ẩn";
		} else {
			el.innerText = "••••••••••••";
			btn.innerText = "Hiện";
		}
	},

	renderMasterDeployedInfo(deployed, prefix = "") {
		const container = document.getElementById(
			prefix + "master-deployed-info",
		);
		if (!container) return;

		const hasDemo = deployed && deployed.demo && deployed.demo.deploy_time;
		const hasProd = deployed && deployed.production;

		container.style.display = "block";
		if (!hasDemo && !hasProd) {
			container.innerHTML = `
				<div class="side-card">
					<h3>Thông tin triển khai</h3>
					<div class="deploy-box empty-box" style="background:linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)); border:1px dashed rgba(255,255,255,0.12); text-align:center; padding:24px 16px;">
						<div style="font-size: 28px; margin-bottom: 8px; opacity: 0.7;">🚀</div>
						<div style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">Chưa Triển Khai</div>
						<div style="font-size: 11.5px; color: var(--text-muted); line-height: 1.5;">Dự án này chưa được deploy lên môi trường Demo hoặc Production.</div>
					</div>
				</div>
			`;
			return;
		}

		container.innerHTML = `
			<div class="side-card">
				<h3>Thông tin triển khai</h3>
				${
					hasDemo
						? `
				<div class="deploy-box">
					<div class="tag">⚡ DEMO</div>
					<div class="kv"><span class="k">DB</span><span class="v">${deployed.demo.db_name}</span></div>
					<div class="kv">
						<span class="k">Pass</span>
						<span class="v pw-row">
							<span id="${prefix}demo_pw_text">••••••••••••</span>
							<button class="eye-btn" onclick="UI.togglePasswordText('${prefix}demo_pw_text', '${deployed.demo.db_pass}', this)">Hiện</button>
						</span>
					</div>
				</div>`
						: ""
				}

				${
					hasProd
						? `
				<div class="deploy-box prod-box">
					<div class="tag">🚀 PRODUCTION</div>
					<div class="kv"><span class="k">DB</span><span class="v">${deployed.production.db_name}</span></div>
					<div class="kv"><span class="k">Email</span><span class="v">${deployed.production.email_user || "—"}</span></div>
					<div class="kv">
						<span class="k">Pass</span>
						<span class="v pw-row">
							<span id="${prefix}prod_pw_text">••••••••••••</span>
							<button class="eye-btn" onclick="UI.togglePasswordText('${prefix}prod_pw_text', '${deployed.production.db_pass}', this)">Hiện</button>
						</span>
					</div>
				</div>`
						: ""
				}
			</div>
		`;
	},

	renderMasterHistoryInfo(history, prefix = "") {
		const container = document.getElementById(
			prefix + "master-history-info",
		);
		if (!container) return;

		container.style.display = "block";
		if (!history || history.length === 0) {
			container.innerHTML = `
				<div class="side-card">
					<h3>Lịch sử thao tác</h3>
					<div style="text-align:center; padding:18px 10px; color:var(--text-muted); font-size:12px; border:1px dashed rgba(255,255,255,0.08); border-radius:10px;">
						Chưa có lịch sử thao tác
					</div>
				</div>
			`;
			return;
		}

		container.style.display = "block";
		container.innerHTML = `
			<div class="side-card">
				<h3>Lịch sử thao tác</h3>
				<div class="history-scroll-container" style="max-height: 250px; overflow-y: auto;">
					${history
						.map(
							(item) => `
					<div class="history-item">
						<div class="history-top">
							<span class="history-name">
								<span class="${item.action.includes("Khóa") ? "lock-dot" : "unlock-dot"}"></span>
								${item.action}
							</span>
							<span class="history-time">${item.time}</span>
						</div>
						<div class="history-status">✓ ${item.message || "Thành công"}</div>
					</div>`,
						)
						.join("")}
				</div>
			</div>
		`;
	},

	_addSectionTitle(container, text, color) {
		const div = document.createElement("div");
		div.className = "master-section-title";
		div.style.color = color;
		div.style.marginTop = "15px";
		div.innerText = text;
		container.appendChild(div);
	},

	_createMasterBtn(container, act) {
		if (act.hidden) return;
		const btn = document.createElement("button");
		btn.className = `btn master-btn ${act.disabled ? "disabled" : ""}`;
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

	renderProjectDetail(name, deployed) {
		document.getElementById("detail-title").innerText =
			`Thông tin: ${name}`;
		const content = document.getElementById("detail-content");

		let html = "";
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
                        <div class="info-box-row"><span class="info-box-row-label-prod">Email Pass</span><code class="code-prod">${deployed.production.email_pass || "—"}</code></div>
                        <div class="info-box-meta">Triển khai: ${deployed.production.deploy_time}</div>
                    </div>
                </div>`;
		}

		content.innerHTML =
			html || '<p class="color-muted">Chưa có thông tin triển khai.</p>';
		this.showModal("project-detail-modal");
	},

	updateDeployStatus(status, progress, logText = null) {
		document.getElementById("progress-fill").style.width = `${progress}%`;
		document.getElementById("status-text").innerText = status;

		// Cập nhật tiêu đề modal nếu trạng thái là kết thúc
		const titleEl = document.getElementById("deploy-modal-title");
		if (titleEl && (status === "Thành công!" || status.includes("Lỗi"))) {
			titleEl.innerText = status;
		}

		if (logText) {
			const log = document.getElementById("log-output");
			log.innerHTML += `${logText}<br>`;
			log.scrollTop = log.scrollHeight;
		}
	},

	parseQuickConfig(mode = "") {
		const prefix = mode === "detail" ? "d_" : "";
		const text = document
			.getElementById(prefix + "quick_paste")
			.value.trim();
		if (!text) return;

		const config = { da_port: "1111" };
		const lines = text.split(/\r?\n/);

		const findHosts = (str) => {
			const matches =
				str.match(
					/(?:https?:\/\/|ftp\.)?([a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|(?:\d{1,3}\.){3}\d{1,3})/gi,
				) || [];
			return matches.map(
				(m) =>
					m
						.replace(/https?:\/\//i, "")
						.replace(/ftp\./i, "")
						.split(":")[0],
			);
		};

		let currentSection = "";
		lines.forEach((line) => {
			line = line.trim();
			if (!line) return;
			if (line.match(/Hosting/i) || line.match(/Control Panel/i))
				currentSection = "DA";
			else if (line.match(/FTP/i)) currentSection = "FTP";

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
					if (currentSection === "DA" || !config.ftp_host)
						config.ftp_host = host;
				}
			}
			if (line.match(/Username/i)) {
				const val = line
					.replace(/Username[:\s\t]+/i, "")
					.split(/[\s\t]+/)[0];
				if (val) {
					if (currentSection === "DA") config.da_user = val;
					else config.ftp_user = val;
				}
			}
			if (line.match(/Password/i)) {
				const val = line
					.replace(/Password[:\s\t]+/i, "")
					.split(/[\s\t]+/)[0];
				if (val) config.ftp_pass = val;
			}
		});

		// Đổ dữ liệu
		if (config.web_domain && document.getElementById(prefix + "web_domain"))
			document.getElementById(prefix + "web_domain").value =
				config.web_domain;
		if (config.ftp_host && document.getElementById(prefix + "ftp_host"))
			document.getElementById(prefix + "ftp_host").value =
				config.ftp_host;
		if (config.ftp_user && document.getElementById(prefix + "ftp_user"))
			document.getElementById(prefix + "ftp_user").value =
				config.ftp_user;
		if (config.da_user && document.getElementById(prefix + "da_user"))
			document.getElementById(prefix + "da_user").value = config.da_user;
		if (config.ftp_pass && document.getElementById(prefix + "ftp_pass"))
			document.getElementById(prefix + "ftp_pass").value =
				config.ftp_pass;

		document.getElementById(prefix + "quick_paste").value = "";
		if (mode === "detail") this.hideModal("quick-paste-modal");
		this.notify("Đã phân tích xong dữ liệu!", "success");
	},

	togglePassword(targetId, btn) {
		const input = document.getElementById(targetId);
		const isPassword = input.type === "password";
		input.type = isPassword ? "text" : "password";

		// Thay đổi icon
		if (isPassword) {
			btn.innerHTML =
				'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
		} else {
			btn.innerHTML =
				'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
		}
	},

	showToast(message, type = "info") {
		let container = document.getElementById("toast-container");
		if (!container) {
			container = document.createElement("div");
			container.id = "toast-container";
			container.style.cssText =
				"position:fixed; bottom:24px; right:24px; z-index:99999; display:flex; flex-direction:column; gap:10px; pointer-events:none;";
			document.body.appendChild(container);
		}

		const toast = document.createElement("div");
		let bg = "linear-gradient(135deg, #4F9DFF, #7C6FF5)";
		if (type === "success")
			bg = "linear-gradient(135deg, #3FD8A0, #37D9B8)";
		if (type === "error") bg = "linear-gradient(135deg, #FF5E8F, #FF7E5F)";
		if (type === "warning")
			bg = "linear-gradient(135deg, #FFB74D, #FF7E5F)";

		toast.style.cssText = `background:${bg}; color:#fff; padding:12px 18px; border-radius:12px; font-weight:600; font-size:13px; box-shadow:0 8px 24px rgba(0,0,0,0.35); pointer-events:auto; transition:all 0.3s ease; transform:translateY(10px); opacity:0;`;
		toast.innerText = message;
		container.appendChild(toast);

		requestAnimationFrame(() => {
			toast.style.transform = "translateY(0)";
			toast.style.opacity = "1";
		});

		setTimeout(() => {
			toast.style.transform = "translateY(-10px)";
			toast.style.opacity = "0";
			setTimeout(() => toast.remove(), 300);
		}, 3500);
	},

	notify(message, type = "info") {
		this.showToast(message, type);
	},

	switchProjectTab(btn, tabId) {
		// Toggle Buttons / Tabs
		const parent = btn.parentElement;
		parent
			.querySelectorAll(".tab, .btn")
			.forEach((b) => b.classList.remove("active"));
		btn.classList.add("active");

		// Toggle Content
		const layout = btn.closest(".view-section");
		layout
			.querySelectorAll(".project-tab-content")
			.forEach((t) => t.classList.add("d-none"));
		document.getElementById(tabId).classList.remove("d-none");

		// Hook for specific tabs
		if (tabId === "d_tab-fonts") {
			FontManager.loadCssPreview();
		} else if (tabId === "d_tab-webp") {
			WebpManager.loadImages();
		} else if (tabId === "d_tab-trim") {
			ImageTrimManager.init();
		} else if (tabId === "d_tab-auto-media") {
			const projectName =
				document.getElementById("d_current-project")?.value || "";
			AutoMediaManager.init(projectName);
		} else if (tabId === "d_tab-seed") {
			const projectName =
				document.getElementById("d_current-project")?.value || "";
			SeedManager.init(projectName);
		}
	},
};
