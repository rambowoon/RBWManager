const App = {
	projects: [],
	currentCategory: null,
	globalConfig: null,

	async init() {
		this.bindEvents();
		
		// Load global config
		try {
			const gRes = await fetch('api.php?action=getGlobalConfig');
			const gData = await gRes.json();
			if (gData.status === 'success') {
				this.globalConfig = gData.data;
			}
		} catch (e) {
			console.error("Error loading global config:", e);
		}
		
		// 1. Tải danh mục vào sidebar (mặc định ban đầu lọc theo tháng)
		const data = await Api.getCategories(true);
		if (data.status === 'success' && data.data.length > 0) {
			// Mặc định chọn tháng mới nhất
			this.currentCategory = data.data[0];
			UI.renderCategories(data.data);
			
			// 2. Mở thẳng dự án của tháng mới nhất
			await this.loadProjects(this.currentCategory);
		} else {
			// Nếu không có tháng nào, mặc định về dashboard (hiển thị tất cả)
			await this.loadProjects('');
		}
		
		this.showDashboard();
	},

	async showCategories() {
		this.setActiveNav(1); // Active mục "Dự án theo tháng"
		const data = await Api.getCategories(true);
		if (data.status === 'success' && data.data.length > 0) {
			UI.renderCategories(data.data);
			// Auto select first month when entering projects by month view
			if (!this.currentCategory || !data.data.includes(this.currentCategory)) {
				this.currentCategory = data.data[0];
			}
			await this.loadProjects(this.currentCategory);
		}
	},

	setActiveNav(index) {
		const items = document.querySelectorAll('.sidebar-nav .nav-item');
		items.forEach((item, i) => {
			if (i === index) item.classList.add('active');
			else item.classList.remove('active');
		});
	},

	getActiveNav() {
		const items = document.querySelectorAll('.sidebar-nav .nav-item');
		for (let i = 0; i < items.length; i++) {
			if (items[i].classList.contains('active')) {
				return i;
			}
		}
		return -1;
	},

	hideAllViews() {
		const views = ['view-dashboard', 'view-converter', 'view-project-detail', 'view-ai-checker', 'view-cache-clearer', 'view-global-config'];
		views.forEach(id => {
			const el = document.getElementById(id);
			if (el) el.style.display = 'none';
		});
	},

	showDashboard() {
		this.hideAllViews();
		document.getElementById('view-dashboard').style.display = 'block';
	},

	async showProjectDetail(name, category) {
		this.hideAllViews();
		document.getElementById('view-project-detail').style.display = 'block';
		
		document.getElementById('detail-project-name').innerText = name;
		this.currentCategory = category; // Ensure category is updated
		
		const data = await Api.getProjectConfig(name);
		if (data.status === 'success') {
			UI.fillProjectDetailForm(name, data.data, category);
			
			// Reset Tabs to first one
			const firstTabBtn = document.querySelector('.project-master-tabs .btn');
			if (firstTabBtn) UI.switchProjectTab(firstTabBtn, 'd_tab-config');
		}
	},

	showCacheClearer() {
		this.setActiveNav(4);
		this.hideAllViews();
		document.getElementById('view-cache-clearer').style.display = 'block';
	},

	async showAIChecker() {
		this.setActiveNav(3);
		this.hideAllViews();
		document.getElementById('view-ai-checker').style.display = 'block';

		// Reset data để reload mới
		AIChecker.data = { gemini: [], claude: [] };

		try {
			const res = await (await fetch('api.php?action=getGlobalConfig')).json();
			if (res.status === 'success') {
				const config = res.data;
				document.getElementById('gemini-api-key').value = config.gemini_key || '';
				document.getElementById('claude-api-key').value = config.claude_key || '';
				
				// Mặc định mở tab Gemini
				await AIChecker.switchTab('gemini');
			}
		} catch (e) {}
	},

	async loadProjects(category) {
		this.currentCategory = category;
		
		// Xử lý Active Menu chính
		let currentActive = this.getActiveNav();
		if (category === '') {
			this.setActiveNav(0); // Bảng điều khiển
			currentActive = 0;
		} else {
			if (currentActive === -1) {
				currentActive = 1; // Mặc định khi tải trang ban đầu
			}
			this.setActiveNav(currentActive);
		}

		try {
			// Load categories dynamically based on view (strict month vs all folders with numbers)
			const strict = (currentActive === 1);
			const catData = await Api.getCategories(strict);
			if (catData.status === 'success') {
				UI.renderCategories(catData.data);
			}

			const data = await Api.getProjects(category);
			if (data.status === 'success') {
				this.projects = data.data;
				UI.renderProjects(this.projects, category);
				this.updateStats();
				
				if (category !== '') {
					document.querySelectorAll('.category-item').forEach(item => {
						if (item.dataset.category === category) item.classList.add('active');
						else item.classList.remove('active');
					});
				} else {
					// Xóa active ở cột tháng
					document.querySelectorAll('.category-item').forEach(i => i.classList.remove('active'));
				}
			}
		} catch (err) {
			alert('Lỗi tải danh sách dự án');
		}
	},

	updateStats() {
		let total = this.projects.length;
		let configured = 0;
		let demo = 0;

		this.projects.forEach((p) => {
			const isConfigured =
				p.config &&
				(p.config.ftp_host || (p.config.prod && p.config.prod.ftp_host));
			const isLockedDemo = !!(p.config && p.config.lock_demo);

			if (isConfigured) configured++;
			if (isLockedDemo) demo++;
		});

		document.getElementById('stat-total-projects').innerText = total;
		document.getElementById('stat-configured').innerText = configured;
		document.getElementById('stat-demo').innerText = demo;
	},

	bindEvents() {
		const configForm = document.getElementById('config-form');
		configForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			await this.saveConfig();
		});

		const globalForm = document.getElementById('global-config-form');
		globalForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			await this.saveGlobalConfig();
		});
		const detailForm = document.getElementById('detail-config-form');
		if (detailForm) {
			detailForm.addEventListener('submit', async (e) => {
				e.preventDefault();
				await this.saveConfig(true);
			});
		}

		const packUploadCheck = document.getElementById('pre_deploy_pack_upload');
		if (packUploadCheck) {
			packUploadCheck.addEventListener('change', (e) => {
				const use7zipCheck = document.getElementById('pre_deploy_use_7zip');
				if (use7zipCheck) {
					use7zipCheck.disabled = !e.target.checked;
					if (!e.target.checked) use7zipCheck.checked = false;
					else use7zipCheck.checked = true;
				}
			});
		}
	},

	async showGlobalConfig() {
		this.setActiveNav(5);
		this.hideAllViews();
		document.getElementById('view-global-config').style.display = 'block';

		try {
			const res = await (
				await fetch('api.php?action=getGlobalConfig')
			).json();
			if (res.status === 'success') {
				const config = res.data;
				document.getElementById('g_ftp_host').value =
					config.ftp_host || '';
				document.getElementById('g_web_domain').value =
					config.web_domain || '';
				document.getElementById('g_da_port').value =
					config.da_port || '1111';
				document.getElementById('g_ftp_user').value =
					config.ftp_user || '';
				document.getElementById('g_ftp_pass').value =
					config.ftp_pass || '';
				document.getElementById('g_ftp_root').value =
					config.ftp_root || '';
				document.getElementById('g_cf_account_id').value =
					config.cf_account_id || '';
				document.getElementById('g_cf_api_token').value =
					config.cf_api_token || '';
				document.getElementById('g_cf_auth_email').value =
					config.cf_auth_email || '';
				document.getElementById('g_gemini_key').value =
					config.gemini_key || '';
				document.getElementById('g_claude_key').value =
					config.claude_key || '';
				document.getElementById('g_source_path').value =
					config.source_path || '';
				document.getElementById('g_source_db_name').value =
					config.source_db_name || '';
				document.getElementById('g_editor_path').value =
					config.editor_path || '';
				document.getElementById('g_font_source_path').value =
					config.font_source_path || '';
				document.getElementById('g_images_pool_path').value =
					config.images_pool_path || '';
			}
		} catch (err) {
			alert('Lỗi tải cấu hình chung');
		}
	},

	async saveGlobalConfig() {
		const config = {
			ftp_host: document.getElementById('g_ftp_host').value,
			web_domain: document.getElementById('g_web_domain').value,
			da_port: document.getElementById('g_da_port').value,
			ftp_user: document.getElementById('g_ftp_user').value,
			ftp_pass: document.getElementById('g_ftp_pass').value,
			ftp_root: document.getElementById('g_ftp_root').value,
			cf_account_id: document.getElementById('g_cf_account_id').value,
			cf_api_token: document.getElementById('g_cf_api_token').value,
			cf_auth_email: document.getElementById('g_cf_auth_email').value,
			gemini_key: document.getElementById('g_gemini_key').value,
			claude_key: document.getElementById('g_claude_key').value,
			source_path: document.getElementById('g_source_path').value,
			source_db_name: document.getElementById('g_source_db_name').value,
			editor_path: document.getElementById('g_editor_path').value,
			font_source_path: document.getElementById('g_font_source_path').value,
			images_pool_path: document.getElementById('g_images_pool_path').value,
		};

		const res = await (
			await fetch('api.php?action=saveGlobalConfig', {
				method: 'POST',
				body: JSON.stringify(config),
			})
		).json();

		if (res.status === 'success') {
			UI.notify('Đã lưu cấu hình Setting thành công!', 'success');
		}
	},

	async openConfig(name, category) {
		this.showProjectDetail(name, category);
	},

	async deleteConfig(name) {
		if (!confirm(`Bạn có chắc muốn xóa cấu hình của dự án ${name}?`))
			return;
		try {
			const res = await (
				await fetch('api.php?action=deleteConfig', {
					method: 'POST',
					body: JSON.stringify({ name }),
				})
			).json();
			if (res.status === 'success') {
				this.loadProjects(this.currentCategory);
			}
		} catch (err) {
			alert('Lỗi khi xóa cấu hình');
		}
	},

	async saveConfig(isDetail = false) {
		const prefix = isDetail ? 'd_' : '';
		const name = document.getElementById(prefix + 'current-project').value;
		
		// Lấy cấu hình hiện tại để không ghi đè mất các thông tin khác (lock_demo, ssl, deployed...)
		const pIdx = this.projects.findIndex(p => p.name === name);
		const existingConfig = (pIdx !== -1 && this.projects[pIdx].config) ? this.projects[pIdx].config : {};
		
		// Clone cấu hình cũ
		const config = JSON.parse(JSON.stringify(existingConfig));
		if (!config.prod) config.prod = {};

		// Cập nhật các trường từ form
		config.prod.ftp_host = document.getElementById(prefix + 'ftp_host').value;
		config.prod.web_domain = document.getElementById(prefix + 'web_domain').value;
		config.prod.ftp_user = document.getElementById(prefix + 'ftp_user').value;
		config.prod.da_user = document.getElementById(prefix + 'da_user').value;
		config.prod.ftp_pass = document.getElementById(prefix + 'ftp_pass').value;
		config.prod.ftp_root = document.getElementById(prefix + 'ftp_root').value;

		const res = await (
			await fetch('api.php?action=saveConfig', {
				method: 'POST',
				body: JSON.stringify({ name, config }),
			})
		).json();

		if (res.status === 'success') {
			if (!isDetail) UI.hideModal('config-modal');
			else UI.notify('Đã lưu cấu hình dự án!', 'success');
			await this.loadProjects(this.currentCategory);
		} else {
			UI.notify('Lưu thất bại: ' + res.message, 'error');
		}
	},

	async deployProject(name) {
		await this._deployFlow(name, 'deploy', this.currentCategory);
	},

	async handleDeployDemo(name, category, options) {
		UI.showModal('deploy-modal');
		UI.updateDeployStatus('Đang kiểm tra...', 10, 'Đang xác minh Database trên DirectAdmin...');
		
		try {
			const checkRes = await (await fetch('api.php?action=preCheckDeployDemo', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ name, category, manual_db_suffix: options.manual_db_suffix })
			})).json();
			
			if (checkRes.status === 'error') {
				UI.updateDeployStatus('Lỗi!', 10, `<span style="color:var(--danger)">❌ ${checkRes.message}</span>`);
				document.getElementById('deploy-footer').style.display = 'flex';
				return;
			}
			
			const dbPass = checkRes.db_pass;
			const passwordUpdated = !!checkRes.password_updated;
			
			if (checkRes.action === 'prompt_confirm') {
				UI.hideModal('deploy-modal');
				
				document.getElementById('db-confirm-message').innerText = `Database '${name}' đã tồn tại trên demo và đang chứa dữ liệu.`;
				UI.showModal('deploy-db-confirm-modal');
				
				document.getElementById('btn-db-overwrite').onclick = async () => {
					UI.hideModal('deploy-db-confirm-modal');
					await this._deployFlow(name, 'deployDemo', category, {
						...options,
						clear_db: 1,
						db_pass: dbPass,
						password_updated: passwordUpdated
					});
				};
				
				document.getElementById('btn-db-skip').onclick = async () => {
					UI.hideModal('deploy-db-confirm-modal');
					await this._deployFlow(name, 'deployDemo', category, {
						...options,
						export_upload: false, // Bỏ qua import database
						create_db: false, // Không cần tạo lại
						clear_db: 0,
						db_pass: dbPass,
						password_updated: passwordUpdated
					});
				};
			} else {
				UI.hideModal('deploy-modal');
				await this._deployFlow(name, 'deployDemo', category, {
					...options,
					clear_db: 0,
					db_pass: dbPass,
					password_updated: passwordUpdated
				});
			}
		} catch (err) {
			UI.updateDeployStatus('Lỗi kết nối!', 10, `<span style="color:var(--danger)">❌ Không thể kết nối tới API kiểm tra.</span>`);
			document.getElementById('deploy-footer').style.display = 'flex';
		}
	},

	async deployDemo(name, category) {
		document.getElementById('pre-deploy-project-desc').innerText =
			`Dự án: ${name} (${category})`;
		document.getElementById('manual_db_suffix').value = '';
		document.getElementById('pre_deploy_ssl').checked = false;
		document.getElementById('pre_deploy_pack_upload').checked = true;
		if (document.getElementById('pre_deploy_use_7zip')) {
			document.getElementById('pre_deploy_use_7zip').checked = true;
		}
		document.getElementById('pre_deploy_export_upload').checked = true;
		document.getElementById('pre_deploy_create_db').checked = true;
		document.getElementById('pre_deploy_extract_setup').checked = true;
		UI.showModal('pre-deploy-modal');

		document.getElementById('confirm-deploy-btn').onclick = async () => {
			const manualSuffix = document.getElementById('manual_db_suffix').value;
			const useSSL = document.getElementById('pre_deploy_ssl').checked;
			const packUpload = document.getElementById('pre_deploy_pack_upload').checked;
			const use7zip = document.getElementById('pre_deploy_use_7zip') ? document.getElementById('pre_deploy_use_7zip').checked : true;
			const exportUpload = document.getElementById('pre_deploy_export_upload').checked;
			const createDb = document.getElementById('pre_deploy_create_db').checked;
			const extractSetup = document.getElementById('pre_deploy_extract_setup').checked;
			
			UI.hideModal('pre-deploy-modal');
			await this.handleDeployDemo(name, category, {
				manual_db_suffix: manualSuffix,
				use_ssl: useSSL,
				use_7zip: use7zip,
				pack_upload: packUpload,
				export_upload: exportUpload,
				create_db: createDb,
				extract_setup: extractSetup
			});
		};
	},

	async deployDbDemo(name, category) {
		document.getElementById('pre-deploy-project-desc').innerText =
			`Dự án: ${name} (${category}) - Chỉ Deploy Database`;
		document.getElementById('manual_db_suffix').value = '';
		document.getElementById('pre_deploy_ssl').checked = false;
		document.getElementById('pre_deploy_pack_upload').checked = false;
		if (document.getElementById('pre_deploy_use_7zip')) {
			document.getElementById('pre_deploy_use_7zip').checked = false;
		}
		document.getElementById('pre_deploy_export_upload').checked = true;
		document.getElementById('pre_deploy_create_db').checked = true;
		document.getElementById('pre_deploy_extract_setup').checked = true;
		UI.showModal('pre-deploy-modal');

		document.getElementById('confirm-deploy-btn').onclick = async () => {
			const manualSuffix = document.getElementById('manual_db_suffix').value;
			const useSSL = document.getElementById('pre_deploy_ssl').checked;
			const packUpload = document.getElementById('pre_deploy_pack_upload').checked;
			const use7zip = document.getElementById('pre_deploy_use_7zip') ? document.getElementById('pre_deploy_use_7zip').checked : false;
			const exportUpload = document.getElementById('pre_deploy_export_upload').checked;
			const createDb = document.getElementById('pre_deploy_create_db').checked;
			const extractSetup = document.getElementById('pre_deploy_extract_setup').checked;
			
			UI.hideModal('pre-deploy-modal');
			await this.handleDeployDemo(name, category, {
				manual_db_suffix: manualSuffix,
				use_ssl: useSSL,
				use_7zip: use7zip,
				pack_upload: packUpload,
				export_upload: exportUpload,
				create_db: createDb,
				extract_setup: extractSetup
			});
		};
	},

	async pushTools(name, category) {
		await this._deployFlow(
			name,
			'pushTools',
			category,
			{},
			'Đang cập nhật Bridge (Tools)...',
		);
	},

	async integrateAMP(name, category) {
		const confirmed = confirm(
			`⚡ Tích hợp AMP NASANI vào dự án "${name}"\n\nThao tác này sẽ:\n• Copy views, assets, helpers AMP vào dự án\n• Cập nhật config/app.php, config/view.php\n• Inject AMP routes vào src/Routes/web.php\n• Merge AMP methods vào Func.php\n• Cập nhật ApiController.php\n• Thêm <link rel="amphtml"> vào head.blade.php\n\nBạn có chắc chắn muốn tiếp tục?`
		);
		if (!confirmed) return;

		UI.showModal('deploy-modal');
		document.getElementById('deploy-project-name').innerText = name + ' (AMP Integration)';
		document.getElementById('log-output').innerHTML = '';
		document.getElementById('deploy-footer').style.display = 'none';
		UI.updateDeployStatus('Đang tích hợp AMP...', 20, 'Đang chạy integrate script...');

		try {
			const res = await (await fetch('api.php?action=integrateAMP', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ name, category }),
			})).json();

			if (res.status === 'success') {
				const logLines = (res.logs || []).map(l => `> ${l}`).join('<br>');
				UI.updateDeployStatus('Tích hợp AMP thành công!', 100,
					logLines + '<br><br><span style="color:var(--success); font-weight:bold;">✅ ' + res.message + '</span>');
				confetti({ particleCount: 120, spread: 65, origin: { y: 0.6 } });
			} else {
				UI.updateDeployStatus('Lỗi!', 50,
					`<span style="color:var(--danger)">❌ ${res.message}</span>`);
			}
		} catch (err) {
			UI.updateDeployStatus('Lỗi kết nối!', 0, 'Không thể kết nối API.');
		}
		document.getElementById('deploy-footer').style.display = 'flex';
		await this.loadProjects(this.currentCategory);
	},

	async publishToProduction(name, category) {
		await this._deployFlow(
			name,
			'publishToProduction',
			category,
			{},
			'Cloud Transfer: Demo -> Production...',
		);
	},

	async downloadPackage(name, category) {
		document.getElementById('deploy-project-name').innerText =
			name + ' (Packaging...)';
		document.getElementById('log-output').innerHTML = '';
		UI.showModal('deploy-modal');
		UI.updateDeployStatus(
			'Đang chuẩn bị...',
			10,
			'Yêu cầu đóng gói bản sạch từ Demo host...',
		);

		try {
			const res = await (
				await fetch(`api.php?action=downloadPackage`, {
					method: 'POST',
					body: JSON.stringify({ name, category }),
				})
			).json();

			if (res.status === 'success') {
				UI.updateDeployStatus(
					'Đóng gói thành công!',
					100,
					(res.logs || []).join('<br>') + '<br><br><span style="color:var(--success); font-weight:bold;">' + res.message + '</span>',
				);

				confetti({
					particleCount: 150,
					spread: 70,
					origin: { y: 0.6 },
				});
				document.getElementById('deploy-footer').style.display = 'flex';
			} else {
				UI.updateDeployStatus(
					'Lỗi!',
					50,
					`<span style="color:var(--error)">${res.message}</span>`,
				);
				document.getElementById('deploy-footer').style.display = 'flex';
			}
		} catch (err) {
			UI.updateDeployStatus('Lỗi hệ thống!', 0, 'Kết nối API thất bại.');
			document.getElementById('deploy-footer').style.display = 'flex';
		}
	},

	async installSSL(name) {
		document.getElementById('ssl-project-name').innerText = name;
		document.getElementById('ssl-log-output').innerHTML = '';
		document.getElementById('ssl-footer').style.display = 'none';
		document.getElementById('ssl-confirm-footer').style.display = 'flex';
		document.getElementById('ssl-status-icon').style.display = 'none';
		document.getElementById('ssl-status-text').innerText =
			"Xác nhận cài đặt SSL Let's Encrypt cho tên miền dự án.";

		UI.showModal('ssl-modal');

		const startBtn = document.getElementById('ssl-start-btn');
		startBtn.onclick = async () => {
			document.getElementById('ssl-confirm-footer').style.display =
				'none';
			document.getElementById('ssl-status-icon').style.display = 'block';
			document.getElementById('ssl-status-text').innerText =
				'Đang gửi yêu cầu cài đặt (2048-bit)...';

			try {
				const res = await (
					await fetch(`api.php?action=installSSL`, {
						method: 'POST',
						body: JSON.stringify({ name }),
					})
				).json();

				const logOutput = document.getElementById('ssl-log-output');
				if (res.status === 'success') {
					document.getElementById('ssl-status-text').innerText =
						'Cài đặt thành công!';
					logOutput.innerHTML = `<span style="color:#34d399">${res.message}</span>`;
					confetti({
						particleCount: 100,
						spread: 70,
						origin: { y: 0.6 },
					});
				} else {
					document.getElementById('ssl-status-text').innerText =
						'Cài đặt thất bại';
					logOutput.innerHTML = `<span style="color:#f87171">${res.message}</span>`;
				}
				document.getElementById('ssl-status-icon').style.display =
					'none';
				document.getElementById('ssl-footer').style.display = 'flex';
			} catch (err) {
				document.getElementById('ssl-status-text').innerText =
					'Lỗi kết nối';
				document.getElementById('ssl-footer').style.display = 'flex';
			}
		};
	},

	async toggleLock(name, btn) {
		try {
			const res = await (
				await fetch(`api.php?action=toggleLock`, {
					method: 'POST',
					body: JSON.stringify({ name }),
				})
			).json();

			if (res.status === 'success') {
				const isLocked = res.locked;
				if (isLocked) {
					btn.classList.add('btn-danger');
					btn.title = 'Mở khóa website';
					btn.innerHTML =
						'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
				} else {
					btn.classList.remove('btn-danger');
					btn.title = 'Khóa website';
					btn.innerHTML =
						'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>';
				}
			}
		} catch (err) {
			console.error('Lỗi toggle lock');
		}
	},

	async toggleActionLock(name, type) {
		try {
			const res = await (
				await fetch(`api.php?action=toggleActionLock`, {
					method: 'POST',
					body: JSON.stringify({ name, type }),
				})
			).json();

			if (res.status === 'success') {
				// Cập nhật dữ liệu local để UI thay đổi liền
				const configRes = await Api.getProjectConfig(name);
				if (configRes.status === 'success') {
					// Cập nhật trong danh sách projects
					const pIdx = this.projects.findIndex(p => p.name === name);
					if (pIdx !== -1) this.projects[pIdx].config = configRes.data;

					// Render lại các nút bấm ở trang chi tiết (nếu đang mở)
					UI.renderMasterActions(name, this.currentCategory, configRes.data, 'd_');

					// Render lại Dashboard để cập nhật màu nút Deploy ngoài kia
					UI.renderProjects(this.projects, this.currentCategory);
				}
				
				UI.notify(`Đã ${res.locked ? 'Khóa' : 'Mở khóa'} ${type === 'demo' ? 'Demo' : 'Production'} thành công!`, 'success');
			}
		} catch (err) {
			UI.notify('Lỗi khi thay đổi trạng thái khóa: ' + err.message, 'error');
		}
	},

	async _deployFlow(
		name,
		action,
		category = null,
		extraData = {},
		customInitiator = 'Khởi tạo quy trình...',
	) {
		const jobId = 'job_' + Date.now();
		document.getElementById('deploy-project-name').innerText =
			name + (category ? ` (${category})` : '');
		document.getElementById('log-output').innerHTML = '';
		document.getElementById('deploy-footer').style.display = 'none';
		UI.showModal('deploy-modal');
		UI.updateDeployStatus('Đang chuẩn bị...', 10, customInitiator);

		let progress = 15;
		let processedLogsCount = 0;
		let isFinished = false;

		const cleanUpJob = async () => {
			if (pollInterval) clearInterval(pollInterval);
			try {
				await fetch(`api.php?action=deleteLog&jobId=${jobId}`);
			} catch (e) { console.warn("Lỗi xóa log:", e); }
			document.getElementById('deploy-footer').style.display = 'flex';
			
			// Làm mới danh sách dự án
			await this.loadProjects(this.currentCategory);
			
			// Nếu đang ở trang chi tiết dự án, làm mới để cập nhật nút mở website và thông tin triển khai
			if (document.getElementById('view-project-detail').style.display === 'block') {
				await this.showProjectDetail(name, this.currentCategory);
			}
		};

		// Khởi động vòng lặp lấy log (Polling)
		const pollInterval = setInterval(async () => {
			if (isFinished) return;
			try {
				const res = await (await fetch(`api.php?action=getLogs&jobId=${jobId}`)).json();
				if (res.status === 'success' && res.logs.length > processedLogsCount) {
					// Chỉ lấy các dòng log mới
					const newLogs = res.logs.slice(processedLogsCount);
					newLogs.forEach(item => {
						if (item.status === 'info') {
							UI.updateDeployStatus('Đang xử lý...', progress, `> ${item.log}`);
							progress = Math.min(progress + 12, 95);
						} else if (item.status === 'success') {
							isFinished = true;
							UI.updateDeployStatus('Thành công!', 100, `✅ ${item.message || item.log}`);
							if (item.logs) item.logs.forEach(l => UI.updateDeployStatus('Thành công!', 100, `> ${l}`));
							confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
							cleanUpJob();
						} else if (item.status === 'error') {
							isFinished = true;
							UI.updateDeployStatus('Lỗi!', progress, `<span style="color:var(--danger)">❌ ${item.message || item.log || 'Có lỗi xảy ra'}</span>`);
							cleanUpJob();
						}
					});
					processedLogsCount = res.logs.length;
				}
			} catch (e) { console.error("Polling error:", e); }
		}, 700);

		try {
			const response = await fetch(`api.php?action=${action}`, {
				method: 'POST',
				body: JSON.stringify({ name, category, jobId, ...extraData }),
				headers: { 'Content-Type': 'application/json' }
			});

			let result = null;
			if (response.ok) {
				try {
					result = await response.json();
				} catch (e) {
					console.error("Lỗi parse JSON phản hồi:", e);
				}
			}

			const isBackgroundAction = (action === 'deploy' || action === 'deployDemo' || action === 'downloadPackage');

			if (!isBackgroundAction || (result && result.status === 'error')) {
				// Khi request chính hoàn tất, đợi thêm 1 chút để polling lấy nốt log cuối
				setTimeout(async () => {
					if (isFinished) return;
					isFinished = true;
					
					await cleanUpJob();
					
					if (result && result.status === 'error') {
						UI.updateDeployStatus('Lỗi!', progress, `<span style="color:var(--danger)">❌ ${result.message || 'Thực thi thất bại'}</span>`);
					} else if (!response.ok) {
						UI.updateDeployStatus('Lỗi hệ thống!', 0, `API trả về HTTP code: ${response.status}`);
					} else {
						confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
					}
				}, 1500);
			}

		} catch (err) {
			const isBackgroundAction = (action === 'deploy' || action === 'deployDemo' || action === 'downloadPackage');
			if (!isBackgroundAction) {
				setTimeout(async () => {
					if (isFinished) return;
					isFinished = true;
					await cleanUpJob();
					UI.updateDeployStatus('Lỗi kết nối!', 0, 'Kết nối API thất bại.');
				}, 2000);
			} else {
				console.warn("Fetch failed/timed out for background action, continuing polling...", err);
				UI.updateDeployStatus('Đang xử lý...', progress, '> ⚠️ Mất kết nối HTTP tạm thời với server. Đang tiếp tục theo dõi tiến trình chạy ngầm...');
			}
		}
	},
	async cleanupTools(name, category, type = 'demo') {
		if (
			!confirm(
				`Bạn có chắc muốn xóa file Bridge (Tool) trên ${type === 'demo' ? 'Demo' : 'Production'}?`,
			)
		)
			return;

		UI.showModal('deploy-modal');
		UI.updateDeployStatus(
			'Đang dọn dẹp...',
			50,
			`Gửi lệnh tự hủy tới Bridge (${type})...`,
		);

		try {
			const res = await (
				await fetch(`api.php?action=cleanupTools`, {
					method: 'POST',
					body: JSON.stringify({ name, category, type }),
				})
			).json();

			if (res.status === 'success') {
				UI.updateDeployStatus('Đã xóa Bridge!', 100, res.message);
				document.getElementById('deploy-footer').style.display = 'flex';
			} else {
				UI.updateDeployStatus(
					'Lỗi dọn dẹp',
					50,
					`<span style="color:var(--error)">${res.message}</span>`,
				);
				document.getElementById('deploy-footer').style.display = 'flex';
			}
		} catch (err) {
			console.error('Cleanup API error:', err);
			UI.updateDeployStatus(
				'Lỗi kết nối!',
				0,
				'Không thể kết nối API hoặc phản hồi từ server không hợp lệ.',
			);
			document.getElementById('deploy-footer').style.display = 'flex';
		}
	},

	async executeChangeType() {
		const name = document.getElementById('ct-project-name').value;
		const module = document.getElementById('ct-module').value;
		const oldType = document.getElementById('ct-old-type').value.trim();
		const newType = document.getElementById('ct-new-type').value.trim();

		if (!oldType || !newType) {
			UI.notify('Vui lòng nhập đầy đủ Type cũ và mới!', 'error');
			return;
		}

		if (!confirm(`Bạn có chắc muốn đổi tất cả Type từ "${oldType}" sang "${newType}" cho module ${module}?\n\nHành động này không thể hoàn tác!`)) {
			return;
		}

		UI.hideModal('change-type-modal');
		UI.showModal('deploy-modal');
		UI.updateDeployStatus('Đang thực thi...', 30, 'Đang kết nối Database Local...');

		try {
			const res = await (await fetch(`api.php?action=changeDatabaseType`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ name, module, old_type: oldType, new_type: newType }),
			})).json();

			if (res.status === 'success') {
				UI.updateDeployStatus('Thành công!', 100, `Đã cập nhật xong Type database cho dự án ${name}.<br>Chi tiết: ${res.message}`);
				UI.notify('Cập nhật database thành công!', 'success');
			} else {
				UI.updateDeployStatus('Lỗi thực thi', 50, `<span style="color:var(--danger)">${res.message}</span>`);
				UI.notify('Lỗi: ' + res.message, 'error');
			}
			document.getElementById('deploy-footer').style.display = 'flex';
		} catch (err) {
			UI.updateDeployStatus('Lỗi kết nối!', 0, 'Không thể kết nối API.');
			document.getElementById('deploy-footer').style.display = 'flex';
		}
	},

	showDeployProjectModal() {
		// Default to current month if not selected
		if (!this.currentCategory) {
			const now = new Date();
			const year = now.getFullYear();
			const month = String(now.getMonth() + 1).padStart(2, '0');
			this.currentCategory = `${year}_${month}`;
		}
		
		const displayMonth = document.getElementById('dp-current-month');
		if (displayMonth) displayMonth.innerText = this.currentCategory;
		
		const projectNameInput = document.getElementById('dp-project-name');
		if (projectNameInput) projectNameInput.value = '';
		
		UI.showModal('deploy-project-modal');
	},

	async executeDeployProject() {
		const projectName = document.getElementById('dp-project-name').value.trim();
		const sourceKey = document.getElementById('dp-source-key').value;
		const category = this.currentCategory;

		if (!projectName) {
			UI.notify('Vui lòng nhập tên dự án!', 'error');
			return;
		}

		UI.hideModal('deploy-project-modal');

		await this._deployFlow(
			projectName,
			'deployNewProject',
			category,
			{ projectName, sourceKey },
			'Khởi tạo đúc dự án từ source gốc...',
		);
	},

	async showChangePhpVersionModal(name) {
		document.getElementById('cpp-project-name').value = name;
		
		const selectEl = document.getElementById('cpp-php-index');
		selectEl.innerHTML = '<option value="">⏳ Đang tải phiên bản PHP từ host...</option>';
		selectEl.disabled = true;
		
		UI.showModal('change-php-modal');
		
		try {
			const res = await (await fetch('api.php?action=getAvailablePhpVersions', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ name }),
			})).json();
			
			if (res.status === 'success' && res.data && res.data.length > 0) {
				selectEl.innerHTML = '';
				res.data.forEach(item => {
					const opt = document.createElement('option');
					opt.value = item.index;
					opt.innerText = `${item.version}${item.active ? ' (Đang hoạt động)' : ''}`;
					if (item.active) {
						opt.selected = true;
					}
					selectEl.appendChild(opt);
				});
				selectEl.disabled = false;
			} else {
				selectEl.innerHTML = `<option value="">❌ Không thể lấy thông tin PHP: ${res.message || 'Lỗi không xác định'}</option>`;
				UI.notify('Lỗi: ' + (res.message || ''), 'error');
			}
		} catch (err) {
			selectEl.innerHTML = '<option value="">❌ Lỗi kết nối API</option>';
			UI.notify('Lỗi kết nối API DirectAdmin!', 'error');
		}
	},

	async executeChangePhpVersion() {
		const name = document.getElementById('cpp-project-name').value;
		const phpIndex = document.getElementById('cpp-php-index').value;

		UI.hideModal('change-php-modal');
		UI.showModal('deploy-modal');
		UI.updateDeployStatus('Đang thực thi...', 30, 'Đang gửi yêu cầu thay đổi phiên bản PHP tới DirectAdmin...');

		try {
			const res = await (await fetch(`api.php?action=changePhpVersion`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ name, php_version_index: phpIndex }),
			})).json();

			if (res.status === 'success') {
				UI.updateDeployStatus('Thành công!', 100, `Đã thay đổi phiên bản PHP thành công cho dự án ${name}.<br>DirectAdmin phản hồi: <span style="color:#34d399">${res.message}</span>`);
				UI.notify('Thay đổi phiên bản PHP thành công!', 'success');
				
				// Cập nhật lại lịch sử dự án
				const configRes = await Api.getProjectConfig(name);
				if (configRes.status === 'success') {
					UI.renderMasterHistoryInfo(configRes.data.history, 'd_');
				}
			} else {
				UI.updateDeployStatus('Lỗi thực thi', 50, `<span style="color:var(--danger)">${res.message}</span>`);
				UI.notify('Lỗi: ' + res.message, 'error');
			}
			document.getElementById('deploy-footer').style.display = 'flex';
		} catch (err) {
			UI.updateDeployStatus('Lỗi kết nối!', 0, 'Không thể kết nối API.');
			document.getElementById('deploy-footer').style.display = 'flex';
		}
	},

	async openAntigravity(name) {
		try {
			const res = await (await fetch(`api.php?action=openProject&name=${encodeURIComponent(name)}`)).json();
			if (res.status === 'success') {
				UI.notify('Đã yêu cầu mở dự án: ' + name, 'success');
			} else {
				UI.notify('Lỗi: ' + res.message, 'error');
			}
		} catch (err) {
			UI.notify('Lỗi kết nối API', 'error');
		}
	},
};

document.addEventListener('DOMContentLoaded', () => App.init());
