const SeedManager = {
	currentProject: '',
	currentMainKey: 'type-photo',
	mainKeys: ['type-photo', 'type-static', 'type-news', 'type-products'],
	subTypes: {},        // { subKey: {...config} }
	selectedImages: {},  // { subKey: [file, ...] }
	allImages: [],       // danh sách ảnh đã scan
	currentFolder: 'project_images',

	async init(projectName) {
		if (!projectName) return;
		this.currentProject = projectName;
		this.selectedImages = {};
		this.subTypes = {};
		this.allImages = [];
		this.bindEvents();
		await this.loadTypes(this.currentMainKey);
	},

	bindEvents() {
		const wrap = document.getElementById('seed-main-tabs');
		if (!wrap || wrap.dataset.bound === '1') return;
		wrap.dataset.bound = '1';

		wrap.addEventListener('click', async (e) => {
			const btn = e.target.closest('[data-main-key]');
			if (!btn) return;
			await this.loadTypes(btn.dataset.mainKey);
		});

		// Folder selector
		document.getElementById('seed-folder-select')?.addEventListener('change', async (e) => {
			this.currentFolder = e.target.value;
			await this.scanFolder();
		});

		// Seed count
		// Seed run button
		document.getElementById('btn-seed-run')?.addEventListener('click', () => this.runSeed());
	},

	// ─── Load types config ───
	async loadTypes(mainKey) {
		if (!this.mainKeys.includes(mainKey)) return;
		this.currentMainKey = mainKey;
		this.selectedImages = {};

		document.querySelectorAll('#seed-main-tabs [data-main-key]').forEach((b) =>
			b.classList.toggle('active', b.dataset.mainKey === mainKey)
		);

		this._renderSubTypesLoading();

		try {
			const res = await fetch(`seed_images.php?action=load_types&project_name=${encodeURIComponent(this.currentProject)}&main_key=${encodeURIComponent(mainKey)}`).then((r) => r.json());
			if (res.status !== 'success') throw new Error(res.message);
			this.subTypes = res.sub_types || {};
			this._renderSubTypes();
			// Auto-load ảnh mặc định
			await this.scanFolder();
		} catch (e) {
			document.getElementById('seed-sub-types').innerHTML = `<div class="seed-state error">${e.message}</div>`;
		}
	},

	// ─── Scan folder ảnh ───
	async scanFolder(subdir = '') {
		const box = document.getElementById('seed-images-grid');
		if (box) box.innerHTML = `<div class="seed-skeleton-row"></div><div class="seed-skeleton-row" style="opacity:.6"></div>`;

		const totalEl = document.getElementById('seed-img-total');
		if (totalEl) totalEl.textContent = '...';

		try {
			const url = `seed_images.php?action=scan_folder&project_name=${encodeURIComponent(this.currentProject)}&folder=${encodeURIComponent(this.currentFolder)}&subdir=${encodeURIComponent(subdir)}`;
			const res = await fetch(url).then((r) => r.json());
			if (res.status !== 'success') throw new Error(res.message);

			this.allImages = res.images || [];
			if (totalEl) totalEl.textContent = this.allImages.length;

			// Render subfolder nav nếu custom_pool
			this._renderSubfolderNav(res.subfolders || []);
			this._renderImageGrid();
		} catch (e) {
			if (box) box.innerHTML = `<div class="seed-state error">${e.message}</div>`;
		}
	},

	// ─── Render sub-types bên trái ───
	_renderSubTypes() {
		const box = document.getElementById('seed-sub-types');
		if (!box) return;

		const keys = Object.keys(this.subTypes);
		if (keys.length === 0) {
			box.innerHTML = '<div class="seed-state">Không có sub-type nào</div>';
			return;
		}

		box.innerHTML = keys.map((k) => {
			const st = this.subTypes[k];
			const ratio = st.ratio ? `${st.ratio.width}×${st.ratio.height}` : '—';
			const kindBadge = st.kind === 'static'
				? '<span class="seed-badge static">static</span>'
				: '<span class="seed-badge album">album</span>';
			const count = st.number !== null ? st.number : '∞';
			const selectedCount = (this.selectedImages[k] || []).length;

			return `
				<div class="seed-subtype-card ${selectedCount > 0 ? 'has-images' : ''}" data-sub-key="${k}">
					<div class="seed-subtype-head">
						<div class="seed-subtype-dot"></div>
						<div>
							<div class="seed-subtype-name">${st.title}</div>
							<div class="seed-subtype-meta">${ratio} · ${count} bản ghi</div>
						</div>
						${kindBadge}
					</div>
					<div class="seed-subtype-stats">
						<span class="seed-subtype-table">${st.table}</span>
						<span class="seed-img-count-badge" id="seed-img-count-${k}">${selectedCount > 0 ? selectedCount + ' ảnh' : 'Chưa chọn'}</span>
					</div>
				</div>`;
		}).join('');

		// Bind click → highlight + filter grid
		box.querySelectorAll('.seed-subtype-card').forEach((card) => {
			card.addEventListener('click', () => {
				box.querySelectorAll('.seed-subtype-card').forEach((c) => c.classList.remove('active'));
				card.classList.add('active');
				this._renderImageGrid(card.dataset.subKey);
			});
		});

		// Auto-chọn card đầu tiên
		box.querySelector('.seed-subtype-card')?.click();
	},

	_renderSubTypesLoading() {
		const box = document.getElementById('seed-sub-types');
		if (box) box.innerHTML = `
			<div class="seed-skeleton-row"></div>
			<div class="seed-skeleton-row" style="opacity:.7"></div>
			<div class="seed-skeleton-row" style="opacity:.4"></div>`;
	},

	// ─── Render grid ảnh bên phải ───
	_renderImageGrid(activeSubKey = null) {
		const grid = document.getElementById('seed-images-grid');
		if (!grid) return;

		// Xác định sub-key đang active
		const activeCard = document.querySelector('.seed-subtype-card.active');
		const subKey = activeSubKey || (activeCard ? activeCard.dataset.subKey : null);

		if (this.allImages.length === 0) {
			grid.innerHTML = `
				<div class="seed-img-empty">
					<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
					<p>Chọn thư mục ảnh và nhấn Quét</p>
				</div>`;
			return;
		}

		const selected = this.selectedImages[subKey] || [];
		grid.innerHTML = this.allImages.map((img) => {
			const isChecked = selected.includes(img.file);
			const dim = img.width && img.height ? `${img.width}×${img.height}` : '—';
			return `
				<label class="seed-img-item ${isChecked ? 'checked' : ''}" data-file="${img.file}" data-sub="${subKey}">
					<input type="checkbox" class="seed-img-check" ${isChecked ? 'checked' : ''}>
					<div class="seed-img-thumb">
						<img src="${img.preview}" alt="${img.name}" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\\'seed-img-ph\\'><svg width=\\'16\\' height=\\'16\\' viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'currentColor\\' stroke-width=\\'2\\'><rect x=\\'3\\' y=\\'3\\' width=\\'18\\' height=\\'18\\' rx=\\'2\\'/><circle cx=\\'8.5\\' cy=\\'8.5\\' r=\\'1.5\\'/></svg></div>'">
					</div>
					<div class="seed-img-meta">
						<div class="seed-img-name" title="${img.file}">${img.name}</div>
						<div class="seed-img-info">${dim} · ${this._formatSize(img.size)}</div>
					</div>
				</label>`;
		}).join('');

		// Bind checkboxes
		grid.querySelectorAll('.seed-img-check').forEach((chk) => {
			chk.addEventListener('change', () => {
				const label = chk.closest('.seed-img-item');
				const file = label.dataset.file;
				const sub = label.dataset.sub;
				if (!this.selectedImages[sub]) this.selectedImages[sub] = [];
				if (chk.checked) {
					if (!this.selectedImages[sub].includes(file)) this.selectedImages[sub].push(file);
					label.classList.add('checked');
				} else {
					this.selectedImages[sub] = this.selectedImages[sub].filter((f) => f !== file);
					label.classList.remove('checked');
				}
				// Update count badge
				const badge = document.getElementById(`seed-img-count-${sub}`);
				const cnt = (this.selectedImages[sub] || []).length;
				if (badge) {
					badge.textContent = cnt > 0 ? cnt + ' ảnh' : 'Chưa chọn';
					badge.className = `seed-img-count-badge ${cnt > 0 ? 'has' : ''}`;
				}
				// Update subtype card
				const card = document.querySelector(`.seed-subtype-card[data-sub-key="${sub}"]`);
				if (card) card.classList.toggle('has-images', cnt > 0);
				// Update footer
				this._updateRunButton();
			});
		});
	},

	_renderSubfolderNav(subfolders) {
		const nav = document.getElementById('seed-subfolder-nav');
		if (!nav) return;
		if (subfolders.length === 0) {
			nav.style.display = 'none';
			return;
		}
		nav.style.display = 'flex';
		nav.innerHTML = `
			<button class="seed-subfolder-btn active" data-subdir="">Tất cả</button>
			${subfolders.map((f) => `<button class="seed-subfolder-btn" data-subdir="${f}">${f}</button>`).join('')}`;
		nav.querySelectorAll('.seed-subfolder-btn').forEach((btn) => {
			btn.addEventListener('click', async () => {
				nav.querySelectorAll('.seed-subfolder-btn').forEach((b) => b.classList.remove('active'));
				btn.classList.add('active');
				await this.scanFolder(btn.dataset.subdir);
			});
		});
	},

	_updateRunButton() {
		const btn = document.getElementById('btn-seed-run');
		const totalSelected = Object.values(this.selectedImages).reduce((a, arr) => a + arr.length, 0);
		const subCount = Object.values(this.selectedImages).filter((a) => a.length > 0).length;
		if (btn) {
			const span = btn.querySelector('span');
			if (span) span.textContent = subCount > 0 ? `Tạo dữ liệu mẫu (${subCount} type)` : 'Tạo dữ liệu mẫu';
		}
	},

	// ─── Run seed ───
	async runSeed(useAi = false) {
		const subKeys = Object.keys(this.selectedImages).filter((k) => this.selectedImages[k].length > 0);
		if (subKeys.length === 0) {
			UI.notify('Vui lòng chọn ít nhất 1 sub-type và ảnh', 'error');
			return;
		}

		const seedCount = parseInt(document.getElementById('seed-count-input')?.value || '5', 10);
		const seedCatCount = parseInt(document.getElementById('seed-cat-count-input')?.value || '3', 10);
		const aiModel = document.getElementById('seed-ai-model')?.value || 'gemini-1.5-flash';
		const aiPrompt = document.getElementById('seed-ai-prompt')?.value || '';

		let btn = document.getElementById('btn-seed-run');
		if (useAi) {
			btn = document.getElementById('seed-ai-confirm-btn');
		}
		const origHTML = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Đang tạo...`;

		if (!document.getElementById('seed-spin-style')) {
			const s = document.createElement('style');
			s.id = 'seed-spin-style';
			s.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
			document.head.appendChild(s);
		}

		const payload = {
			project_name: this.currentProject,
			main_key: this.currentMainKey,
			folder: this.currentFolder,
			seed_count: seedCount,
			seed_cat_count: seedCatCount,
			sub_types: Object.fromEntries(subKeys.map((k) => [k, this.selectedImages[k]])),
			use_ai: useAi,
			ai_model: aiModel,
			ai_prompt: aiPrompt,
		};

		try {
			const res = await fetch('seed_images.php?action=seed', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload),
			}).then((r) => r.json());

			if (res.status !== 'success') throw new Error(res.message || 'Lỗi không xác định');

			const totalSeeded = Object.values(res.details || {}).reduce((a, d) => a + (d.seeded || 0), 0);
			UI.notify(`✅ Đã tạo ${totalSeeded} bản ghi dữ liệu mẫu thành công!`, 'success');

			if (res.errors?.length) {
				res.errors.forEach((e) => UI.notify('⚠️ ' + e, 'error'));
			}

			if (useAi) {
				UI.hideModal('seed-ai-modal');
			}

			// Reload type counts
			await this.loadTypes(this.currentMainKey);
		} catch (e) {
			UI.notify('Lỗi: ' + e.message, 'error');
		} finally {
			btn.disabled = false;
			btn.innerHTML = origHTML;
		}
	},

	_formatSize(bytes) {
		const n = Number(bytes) || 0;
		if (n < 1024) return `${n} B`;
		if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
		return `${(n / (1024 * 1024)).toFixed(2)} MB`;
	},
};
