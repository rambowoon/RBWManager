var FileManager = {
    currentPath: '/',
    lastProject: null,
    baseUrl: '',
    rawFiles: [],
    lastFindIndex: -1,
    treeCache: {},
    
    init() {
        if (!document.getElementById('detail-project-name')?.innerText || !App.currentCategory) return;
        const currentProject = document.getElementById('detail-project-name')?.innerText;
        if (this.lastProject !== currentProject) {
            this.currentPath = '/';
            this.lastProject = currentProject;
            this.treeCache = {};
            this.loadTree();
        } else if (!document.getElementById('fm-tree-children-root')) {
            this.loadTree();
        }
        this.loadCurrentPath();
    },

    loadTree() {
        const container = document.getElementById('fm-tree-container');
        if (!container) return;
        
        container.innerHTML = `
            <style>
                .fm-tree-node {
                    display: flex; align-items: center; gap: 6px; padding: 5px 8px; border-radius: 6px;
                    cursor: pointer; transition: all 0.15s ease; color: var(--text-secondary, #94a3b8);
                    font-family: var(--mono, monospace); font-size: 12px; user-select: none;
                }
                .fm-tree-node:hover { background: rgba(255,255,255,0.06); color: #fff; }
                .fm-tree-node.active {
                    background: rgba(0, 213, 146, 0.12); color: var(--primary, #00d2d3);
                    font-weight: 600; border-left: 2px solid var(--primary, #00d2d3);
                }
                .fm-tree-toggle {
                    width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center;
                    font-size: 9px; color: #64748b; cursor: pointer; transition: transform 0.15s ease;
                }
                .fm-tree-toggle:hover { color: #fff; }
                .fm-tree-icon { font-size: 13px; line-height: 1; }
                .fm-tree-label {
                    flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
                }
            </style>
            <div class="fm-tree-item" id="tree-item-root">
                <div class="fm-tree-node ${this.currentPath === '/' ? 'active' : ''}" data-path="/" onclick="FileManager.selectTreeNode('/', this)">
                    <span class="fm-tree-toggle" onclick="FileManager.toggleTreeNode('/', this, event)">▼</span>
                    <span class="fm-tree-icon">📂</span>
                    <span class="fm-tree-label">root (/)</span>
                </div>
                <div id="fm-tree-children-root" class="fm-tree-children" style="padding-left: 12px;">
                    <div style="padding: 6px 8px; color: var(--text-muted, #888); font-size: 11px;">Đang tải...</div>
                </div>
            </div>
        `;
        
        this.fetchTreeDirs('/', (dirs) => {
            this.renderTreeChildren('root', '/', dirs);
        });
    },

    fetchTreeDirs(path, callback) {
        if (this.treeCache[path]) {
            callback(this.treeCache[path]);
            return;
        }
        
        const projectName = document.getElementById('detail-project-name')?.innerText;
        if (!projectName || !App.currentCategory) return;
        
        fetch('api.php?action=fmList', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: projectName,
                category: App.currentCategory,
                path: path
            })
        }).then(r => r.json()).then(res => {
            if (res.status === 'success') {
                const dirs = (res.data || []).filter(f => f.is_dir).map(f => f.name);
                this.treeCache[path] = dirs;
                callback(dirs);
            } else {
                callback([]);
            }
        }).catch(() => callback([]));
    },

    renderTreeChildren(containerSuffix, parentPath, dirs) {
        const wrap = document.getElementById(`fm-tree-children-${containerSuffix}`);
        if (!wrap) return;
        
        if (dirs.length === 0) {
            wrap.innerHTML = `<div style="padding: 3px 8px; color: var(--text-muted, #64748b); font-size: 11px; font-style: italic;">(Trống)</div>`;
            return;
        }
        
        dirs.sort((a, b) => a.localeCompare(b));
        
        let html = '';
        dirs.forEach(dirName => {
            const fullPath = (parentPath === '/' ? '' : parentPath) + '/' + dirName;
            const safeId = 'tree-' + fullPath.replace(/[^a-zA-Z0-9_-]/g, '_');
            const isActive = this.currentPath === fullPath;
            
            html += `
                <div class="fm-tree-item" id="${safeId}">
                    <div class="fm-tree-node ${isActive ? 'active' : ''}" data-path="${this.escapeHtml(fullPath)}" onclick="FileManager.selectTreeNode('${this.escapeHtml(fullPath)}', this)">
                        <span class="fm-tree-toggle" onclick="FileManager.toggleTreeNode('${this.escapeHtml(fullPath)}', this, event)">▶</span>
                        <span class="fm-tree-icon">📁</span>
                        <span class="fm-tree-label" title="${this.escapeHtml(dirName)}">${this.escapeHtml(dirName)}</span>
                    </div>
                    <div id="fm-tree-children-${safeId}" class="fm-tree-children" style="padding-left: 12px; display: none;"></div>
                </div>
            `;
        });
        
        wrap.innerHTML = html;
        this.highlightActiveTreeNode();
    },

    toggleTreeNode(path, el, event) {
        if (event) event.stopPropagation();
        const safeId = 'tree-' + path.replace(/[^a-zA-Z0-9_-]/g, '_');
        const childrenWrap = document.getElementById(`fm-tree-children-${safeId}`);
        if (!childrenWrap) return;
        
        const isHidden = childrenWrap.style.display === 'none';
        if (isHidden) {
            childrenWrap.style.display = 'block';
            el.innerText = '▼';
            const icon = el.nextElementSibling;
            if (icon && icon.classList.contains('fm-tree-icon')) icon.innerText = '📂';
            
            if (!childrenWrap.dataset.loaded) {
                childrenWrap.innerHTML = `<div style="padding: 4px 8px; color: var(--text-muted, #888); font-size: 11px;">Đang tải...</div>`;
                this.fetchTreeDirs(path, (dirs) => {
                    childrenWrap.dataset.loaded = 'true';
                    this.renderTreeChildren(safeId, path, dirs);
                });
            }
        } else {
            childrenWrap.style.display = 'none';
            el.innerText = '▶';
            const icon = el.nextElementSibling;
            if (icon && icon.classList.contains('fm-tree-icon')) icon.innerText = '📁';
        }
    },

    selectTreeNode(path, nodeEl) {
        this.navigateTo(path);
    },

    highlightActiveTreeNode() {
        document.querySelectorAll('.fm-tree-node').forEach(n => {
            if (n.dataset.path === this.currentPath) {
                n.classList.add('active');
            } else {
                n.classList.remove('active');
            }
        });
    },

    loadCurrentPath() {
        const tbody = document.getElementById('fm-file-list');
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--muted);">Đang tải danh sách file...</td></tr>`;
        this.updateBreadcrumb();
        this.highlightActiveTreeNode();
        
        fetch('api.php?action=fmList', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('detail-project-name')?.innerText,
                category: App.currentCategory,
                path: this.currentPath
            })
        }).then(res => res.json()).then(res => {
            if (res.status === 'success') {
                this.baseUrl = res.baseUrl || '';
                this.rawFiles = res.data || [];
                const searchInput = document.getElementById('fm-search-input');
                if (searchInput && searchInput.value) {
                    this.filterList(searchInput.value);
                } else {
                    this.renderList(this.rawFiles);
                }
            } else {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--danger);">${res.message || 'Lỗi tải danh sách file'}</td></tr>`;
            }
        }).catch(err => {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--danger);">Lỗi kết nối</td></tr>`;
        });
    },

    updateBreadcrumb() {
        const wrap = document.getElementById('fm-breadcrumb');
        let html = `<span style="cursor:pointer; color:var(--primary);" onclick="FileManager.navigateTo('/')">/ root</span>`;
        
        let parts = this.currentPath.split('/').filter(p => p.trim() !== '');
        let accumPath = '';
        
        parts.forEach(part => {
            accumPath += '/' + part;
            html += ` <span style="color:var(--muted);">/</span> <span style="cursor:pointer; color:var(--primary);" onclick="FileManager.navigateTo('${accumPath}')">${part}</span>`;
        });
        
        wrap.innerHTML = html;
    },

    navigateTo(path) {
        this.currentPath = path;
        if (!this.currentPath.startsWith('/')) this.currentPath = '/' + this.currentPath;
        const searchInput = document.getElementById('fm-search-input');
        if (searchInput) searchInput.value = '';
        this.highlightActiveTreeNode();
        this.loadCurrentPath();
    },

    filterList(query) {
        if (!query || !query.trim()) {
            this.renderList(this.rawFiles);
            return;
        }
        const q = query.toLowerCase().trim();
        const filtered = (this.rawFiles || []).filter(f => f.name.toLowerCase().includes(q));
        this.renderList(filtered);
    },

    formatBytes(bytes, decimals = 2) {
        if (!+bytes) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    },

    isEditable(filename) {
        const lower = filename.toLowerCase();
        if (lower.startsWith('.') || lower.includes('htaccess') || lower.includes('env')) return true;
        const ext = lower.split('.').pop();
        const textExts = [
            'php', 'html', 'htm', 'css', 'scss', 'less', 'js', 'json', 'txt', 'md', 
            'env', 'htaccess', 'sql', 'xml', 'yml', 'yaml', 'ini', 'conf', 'config',
            'svg', 'sh', 'bat', 'log', 'lock', 'blade', 'phtml'
        ];
        return textExts.includes(ext);
    },

    isImage(filename) {
        const ext = filename.toLowerCase().split('.').pop();
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif', 'bmp'].includes(ext);
    },

    getFileMeta(filename, isDir) {
        if (isDir) {
            return {
                badge: '<span style="font-size: 1.25rem;">📁</span>',
                nameColor: 'var(--primary, #00d2d3)',
                isDir: true
            };
        }
        
        const lower = filename.toLowerCase();
        const ext = lower.split('.').pop();
        
        const makeBadge = (label, bg, color, border) => `
            <span style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:22px; border-radius:5px; background:${bg}; color:${color}; border:1px solid ${border}; font-size:10px; font-weight:800; font-family:var(--mono, monospace); letter-spacing:0.5px;">${label}</span>
        `;
        
        if (lower.endsWith('.blade.php') || ext === 'php') {
            return {
                badge: makeBadge('PHP', 'rgba(99,102,241,0.15)', '#818cf8', 'rgba(129,140,248,0.3)'),
                nameColor: '#c7d2fe'
            };
        }
        if (['js', 'ts', 'jsx', 'tsx', 'mjs', 'cjs'].includes(ext)) {
            return {
                badge: makeBadge('JS', 'rgba(245,158,11,0.15)', '#fbbf24', 'rgba(251,191,36,0.3)'),
                nameColor: '#fef08a'
            };
        }
        if (['css', 'scss', 'sass', 'less'].includes(ext)) {
            return {
                badge: makeBadge('CSS', 'rgba(14,165,233,0.15)', '#38bdf8', 'rgba(56,189,248,0.3)'),
                nameColor: '#bae6fd'
            };
        }
        if (['html', 'htm', 'phtml'].includes(ext)) {
            return {
                badge: makeBadge('HTML', 'rgba(249,115,22,0.15)', '#fb923c', 'rgba(251,146,60,0.3)'),
                nameColor: '#fed7aa'
            };
        }
        if (['json', 'lock', 'env', 'config', 'ini', 'yml', 'yaml'].includes(ext) || lower.startsWith('.env') || lower.includes('htaccess')) {
            const label = lower.includes('htaccess') ? 'HTA' : (lower.startsWith('.env') ? 'ENV' : ext.toUpperCase().slice(0, 4));
            return {
                badge: makeBadge(label, 'rgba(16,185,129,0.15)', '#34d399', 'rgba(52,211,153,0.3)'),
                nameColor: '#a7f3d0'
            };
        }
        if (['sql', 'db', 'sqlite', 'dat'].includes(ext)) {
            return {
                badge: makeBadge('SQL', 'rgba(234,179,8,0.15)', '#fde047', 'rgba(253,224,71,0.3)'),
                nameColor: '#fef9c3'
            };
        }
        if (['md', 'txt', 'log', 'pdf', 'doc', 'docx'].includes(ext)) {
            return {
                badge: makeBadge(ext.toUpperCase().slice(0, 3), 'rgba(168,85,247,0.15)', '#c084fc', 'rgba(192,132,252,0.3)'),
                nameColor: '#e9d5ff'
            };
        }
        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) {
            return {
                badge: makeBadge('ZIP', 'rgba(239,68,68,0.15)', '#f87171', 'rgba(248,113,113,0.3)'),
                nameColor: '#fecaca'
            };
        }
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif'].includes(ext)) {
            return {
                badge: makeBadge('IMG', 'rgba(236,72,153,0.15)', '#f472b6', 'rgba(244,114,182,0.3)'),
                nameColor: '#fbcfe8'
            };
        }
        
        return {
            badge: makeBadge('FILE', 'rgba(255,255,255,0.06)', '#94a3b8', 'rgba(255,255,255,0.12)'),
            nameColor: '#dfe6e9'
        };
    },

    formatDate(dateStr) {
        if (!dateStr || dateStr === '-') return '-';
        if (dateStr.includes('/')) return dateStr;
        
        const months = {
            'jan': '01', 'feb': '02', 'mar': '03', 'apr': '04', 'may': '05', 'jun': '06',
            'jul': '07', 'aug': '08', 'sep': '09', 'oct': '10', 'nov': '11', 'dec': '12'
        };
        
        const parts = dateStr.trim().split(/\s+/);
        if (parts.length === 3) {
            const m = months[parts[0].toLowerCase()];
            if (m) {
                const day = parts[1].padStart(2, '0');
                if (parts[2].includes(':')) {
                    const currentYear = new Date().getFullYear();
                    return `${day}/${m}/${currentYear} ${parts[2]}`;
                } else {
                    return `${day}/${m}/${parts[2]}`;
                }
            }
        }
        return dateStr;
    },

    renderList(files) {
        const tbody = document.getElementById('fm-file-list');
        if (!files || files.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--muted);">Thư mục trống</td></tr>`;
            return;
        }

        // Sort: folders first, then files by name
        files.sort((a, b) => {
            if (a.is_dir && !b.is_dir) return -1;
            if (!a.is_dir && b.is_dir) return 1;
            return a.name.localeCompare(b.name);
        });

        let html = '';
        files.forEach(f => {
            const meta = this.getFileMeta(f.name, f.is_dir);
            let icon = meta.badge;
            const size = f.is_dir ? '-' : this.formatBytes(f.size);
            const path = (this.currentPath === '/' ? '' : this.currentPath) + '/' + f.name;
            const fileUrl = this.baseUrl ? (this.baseUrl + (this.currentPath === '/' ? '' : this.currentPath) + '/' + encodeURIComponent(f.name)) : '';
            const displayDate = this.formatDate(f.date);
            
            let actions = '';
            const btnStyle = "padding: 4px 10px; font-size: 11.5px; border-radius: 6px; font-weight: 500; transition: all 0.2s ease; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.03);";
            
            if (f.is_dir) {
                actions = `
                    <button class="btn btn-ghost btn-sm" onclick="FileManager.navigateTo('${path}')" title="Mở" style="${btnStyle} color: #74b9ff;" onmouseover="this.style.background='rgba(116,185,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">📂 Mở</button>
                    <button class="btn btn-ghost btn-sm" onclick="FileManager.deleteItem('${path}', true)" title="Xóa" style="${btnStyle} color: #ff7675;" onmouseover="this.style.background='rgba(255,118,117,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">🗑️ Xóa</button>
                `;
            } else {
                if (this.isImage(f.name) && fileUrl) {
                    icon = `<img src="${fileUrl}" style="width:32px; height:32px; object-fit:cover; border-radius:5px; border:1px solid rgba(255,255,255,0.15); cursor:pointer; vertical-align:middle; display:inline-block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='none'" onclick="FileManager.previewImage('${fileUrl}', '${this.escapeHtml(f.name)}')" onerror="this.outerHTML='${meta.badge}'">`;
                    actions += `<button class="btn btn-ghost btn-sm" onclick="FileManager.previewImage('${fileUrl}', '${this.escapeHtml(f.name)}')" title="Xem ảnh" style="${btnStyle} color: #00cec9;" onmouseover="this.style.background='rgba(0,206,201,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">👁️ Xem</button>`;
                }
                if (this.isEditable(f.name)) {
                    actions += `<button class="btn btn-ghost btn-sm" onclick="FileManager.editFile('${path}')" title="Sửa nhanh" style="${btnStyle} color: #a29bfe;" onmouseover="this.style.background='rgba(162,155,254,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">📝 Sửa</button>`;
                    actions += `<button class="btn btn-ghost btn-sm" onclick="FileManager.openInEditor('${path}')" title="Mở trong Antigravity IDE" style="${btnStyle} color: #ffeaa7;" onmouseover="this.style.background='rgba(255,234,167,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">⚡ IDE</button>`;
                }
                actions += `<button class="btn btn-ghost btn-sm" onclick="FileManager.deleteItem('${path}', false)" title="Xóa" style="${btnStyle} color: #ff7675;" onmouseover="this.style.background='rgba(255,118,117,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">🗑️ Xóa</button>`;
            }

            html += `
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); transition: all 0.15s ease; cursor: default;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.03)';" onmouseout="this.style.backgroundColor='transparent';">
                    <td style="padding: 10px 12px; width: 46px; text-align: center; vertical-align: middle;">${icon}</td>
                    <td style="padding: 10px 14px; font-weight: 500; vertical-align: middle; font-family: var(--mono, monospace); font-size: 13px;">
                        ${f.is_dir ? `<a href="javascript:void(0)" onclick="FileManager.navigateTo('${path}')" style="color:${meta.nameColor}; text-decoration:none; font-weight:600; transition: opacity 0.15s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">${f.name}</a>` : `<span style="color:${meta.nameColor};">${f.name}</span>`}
                    </td>
                    <td style="padding: 10px 14px; color:#94a3b8; font-size: 0.8rem; vertical-align: middle; font-family: var(--mono, monospace);">${size}</td>
                    <td style="padding: 10px 14px; color:#94a3b8; font-size: 0.8rem; vertical-align: middle; font-family: var(--mono, monospace);">${displayDate}</td>
                    <td style="padding: 10px 14px; text-align: right; white-space: nowrap; vertical-align: middle;">
                        <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                            ${actions}
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    },

    async deleteItem(path, isDir) {
        const itemName = path.split('/').pop();
        const msg = `Bạn có chắc chắn muốn xóa ${isDir ? 'thư mục' : 'file'} <b style="color:var(--primary); word-break:break-all;">${itemName}</b> không?<br><span style="color:var(--danger); font-size:0.85em;">⚠️ Hành động này sẽ xóa trực tiếp trên Demo Server và không thể hoàn tác!</span>`;
        const confirmed = (typeof UI !== 'undefined' && UI.confirm) ? await UI.confirm(msg) : confirm(`Bạn có chắc chắn muốn xóa không?`);
        if (!confirmed) return;
        
        fetch('api.php?action=fmDelete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('detail-project-name')?.innerText,
                category: App.currentCategory,
                path: path,
                isDir: isDir
            })
        }).then(res => res.json()).then(res => {
            if (res.status === 'success') {
                UI.showToast('Xóa thành công', 'success');
                this.loadCurrentPath();
            } else {
                UI.showToast('Lỗi: ' + res.message, 'error');
            }
        });
    },

    showCreateDirModal() {
        const name = prompt('Nhập tên thư mục mới:');
        if (!name) return;
        
        const path = (this.currentPath === '/' ? '' : this.currentPath) + '/' + name;
        fetch('api.php?action=fmCreateDir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('detail-project-name')?.innerText,
                category: App.currentCategory,
                path: path
            })
        }).then(res => res.json()).then(res => {
            if (res.status === 'success') {
                UI.showToast('Tạo thư mục thành công', 'success');
                this.loadCurrentPath();
            } else {
                UI.showToast('Lỗi: ' + (res.message || 'Không thể tạo thư mục'), 'error');
            }
        });
    },

    showUploadModal() {
        let input = document.createElement('input');
        input.type = 'file';
        input.onchange = e => {
            const file = e.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('name', document.getElementById('detail-project-name')?.innerText);
            formData.append('category', App.currentCategory);
            formData.append('path', (this.currentPath === '/' ? '' : this.currentPath) + '/' + file.name);
            formData.append('file', file);
            
            UI.showToast('Đang tải lên...', 'info');
            fetch('api.php?action=fmUpload', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    UI.showToast('Tải lên thành công', 'success');
                    this.loadCurrentPath();
                } else {
                    UI.showToast('Lỗi: ' + res.message, 'error');
                }
            });
        };
        input.click();
    },

    editFile(path) {
        UI.showToast('Đang tải nội dung file...', 'info');
        fetch('api.php?action=fmGet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('detail-project-name')?.innerText,
                category: App.currentCategory,
                path: path
            })
        }).then(res => res.json()).then(res => {
            if (res.status === 'success') {
                this.showEditorModal(path, res.content);
            } else {
                UI.showToast('Lỗi: ' + res.message, 'error');
            }
        });
    },

    showEditorModal(path, content) {
        // Remove existing if any
        let existing = document.getElementById('fm-editor-modal');
        if (existing) existing.remove();
        this.lastFindIndex = -1;
        
        const modalHtml = `
            <div class="modal-overlay" id="fm-editor-modal" style="display:flex; z-index:9999;" onclick="if(event.target===this) this.remove()">
                <div class="modal-content" style="max-width: 980px; width: 95%; height: 88vh; display: flex; flex-direction: column; background: #18181c; border-radius: 14px; overflow: hidden; border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 16px 60px rgba(0,0,0,0.9);">
                    
                    <!-- Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; background: #1f1f26; border-bottom: 1px solid rgba(255,255,255,0.08);">
                        <div style="display:flex; align-items:center; gap:10px; overflow:hidden;">
                            <div style="width:28px; height:28px; border-radius:6px; background:rgba(0,210,211,0.12); display:flex; align-items:center; justify-content:center; color:#00d2d3; font-size:14px;">📝</div>
                            <span style="font-size:13px; font-weight:600; color:#aaa;">Sửa file:</span>
                            <span style="color:#00d2d3; font-family:monospace; font-size:13px; background:rgba(0,210,211,0.08); padding:3px 10px; border-radius:6px; border:1px solid rgba(0,210,211,0.2); text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">${this.escapeHtml(path)}</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" class="btn btn-ghost btn-sm" onclick="FileManager.openInEditor('${this.escapeHtml(path)}')" title="Mở file này bằng Antigravity IDE" style="padding:4px 10px; font-size:12px; border:1px solid rgba(162,155,254,0.3); border-radius:6px; color:#a29bfe; background:rgba(162,155,254,0.08);">
                                ⚡ Mở Antigravity
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="FileManager.toggleFindWidget()" title="Tìm kiếm (Ctrl + F)" style="padding:4px 10px; font-size:12px; border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#ccc;">
                                🔍 Tìm kiếm
                            </button>
                            <button type="button" onclick="document.getElementById('fm-editor-modal').remove()" style="background:none; border:none; color:#71717a; font-size:22px; cursor:pointer; line-height:1; padding:2px 8px; border-radius:6px; transition:all 0.15s;" onmouseover="this.style.color='#fff'; this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.color='#71717a'; this.style.background='none'">×</button>
                        </div>
                    </div>

                    <!-- Body / Textarea with Floating VSCode-style Find Widget -->
                    <div class="modal-body" style="flex:1; display:flex; flex-direction:column; padding: 0; position:relative; overflow:hidden;">
                        
                        <!-- Floating Find Widget -->
                        <div id="fm-editor-find-widget" style="position: absolute; top: 12px; right: 24px; z-index: 100; background: rgba(24, 24, 30, 0.95); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.65); padding: 5px 8px; display: flex; align-items: center; gap: 6px; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
                            <div style="position:relative; display:flex; align-items:center;">
                                <input type="text" id="fm-editor-find-input" placeholder="Tìm kiếm..." style="width: 200px; padding: 5px 28px 5px 10px; font-size: 12.5px; border-radius: 5px; border: 1px solid rgba(255,255,255,0.15); background: #121216; color: #fff; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#00d2d3'" onblur="this.style.borderColor='rgba(255,255,255,0.15)'" oninput="FileManager.findInEditor(false)" onkeydown="if(event.key==='Enter') FileManager.findInEditor(event.shiftKey ? 'prev' : 'next')">
                                <span id="fm-editor-find-status" style="position:absolute; right:8px; font-size:11px; color:#71717a; pointer-events:none; white-space:nowrap;"></span>
                            </div>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="FileManager.findInEditor('prev')" title="Trước đó (Shift + Enter)" style="padding: 4px 7px; font-size: 12px; border-radius: 4px; line-height: 1; border: 1px solid rgba(255,255,255,0.1); color:#ccc;">↑</button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="FileManager.findInEditor('next')" title="Tiếp theo (Enter)" style="padding: 4px 7px; font-size: 12px; border-radius: 4px; line-height: 1; border: 1px solid rgba(255,255,255,0.1); color:#ccc;">↓</button>
                            <button type="button" onclick="FileManager.toggleFindWidget(false)" title="Đóng tìm kiếm (Escape)" style="background:none; border:none; color:#71717a; font-size:16px; cursor:pointer; line-height:1; padding:2px 5px; margin-left:2px; border-radius:4px;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#71717a'">×</button>
                        </div>

                        <!-- Editor Textarea -->
                        <textarea id="fm-editor-textarea" spellcheck="false" style="flex:1; width:100%; height:100%; resize:none; padding:18px 24px; font-family:'Fira Code', Consolas, Menlo, Monaco, monospace; background:#121215; color:#f4f4f5; border:none; outline:none; font-size:13.5px; line-height:1.65; white-space:pre; overflow:auto; tab-size:4;">${this.escapeHtml(content)}</textarea>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer" style="padding: 12px 20px; border-top: 1px solid rgba(255,255,255,0.08); background: #1f1f26; display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:16px; font-size: 12px; color: #71717a;">
                            <span>⌨️ <kbd style="background:rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px; font-family:monospace; color:#ccc;">Ctrl + S</kbd> Lưu nhanh</span>
                            <span>🔍 <kbd style="background:rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px; font-family:monospace; color:#ccc;">Ctrl + F</kbd> Tìm kiếm</span>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('fm-editor-modal').remove()" style="border: 1px solid rgba(255,255,255,0.1); border-radius:6px; font-size:13px; padding:6px 16px;">Hủy</button>
                            <button type="button" class="btn btn-primary" onclick="FileManager.saveFile('${this.escapeHtml(path)}')" style="border-radius:6px; font-size:13px; font-weight:600; padding:6px 18px;">💾 Lưu Thay Đổi</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Bind hotkeys Ctrl+S, Ctrl+F, Escape in textarea
        const textarea = document.getElementById('fm-editor-textarea');
        const findInput = document.getElementById('fm-editor-find-input');
        
        textarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                FileManager.saveFile(path);
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                FileManager.toggleFindWidget(true);
                if (findInput) {
                    findInput.focus();
                    findInput.select();
                }
            }
            if (e.key === 'Escape') {
                FileManager.toggleFindWidget(false);
            }
        });
    },

    toggleFindWidget(show) {
        const widget = document.getElementById('fm-editor-find-widget');
        if (!widget) return;
        if (show === undefined) {
            widget.style.display = widget.style.display === 'none' ? 'flex' : 'none';
        } else {
            widget.style.display = show ? 'flex' : 'none';
        }
        if (widget.style.display !== 'none') {
            const input = document.getElementById('fm-editor-find-input');
            if (input) { input.focus(); input.select(); }
        }
    },

    findInEditor(direction = 'next') {
        const input = document.getElementById('fm-editor-find-input');
        const textarea = document.getElementById('fm-editor-textarea');
        const status = document.getElementById('fm-editor-find-status');
        if (!input || !textarea) return;
        
        const term = input.value;
        if (!term) {
            if (status) status.innerText = '';
            return;
        }
        
        const text = textarea.value;
        const lowerText = text.toLowerCase();
        const lowerTerm = term.toLowerCase();
        
        // Count total matches
        let pos = lowerText.indexOf(lowerTerm);
        const matches = [];
        while (pos !== -1) {
            matches.push(pos);
            pos = lowerText.indexOf(lowerTerm, pos + 1);
        }
        
        const count = matches.length;
        if (count === 0) {
            if (status) status.innerText = '0/0';
            return;
        }
        
        let targetPos = matches[0];
        const cursor = textarea.selectionStart || 0;
        
        if (direction === 'prev') {
            // Find previous match before current cursor
            const prevMatches = matches.filter(p => p < cursor);
            targetPos = prevMatches.length > 0 ? prevMatches[prevMatches.length - 1] : matches[matches.length - 1];
        } else if (direction === 'next') {
            // Find next match after current cursor
            const nextMatches = matches.filter(p => p > (textarea.selectionStart || 0));
            targetPos = nextMatches.length > 0 ? nextMatches[0] : matches[0];
        } else {
            // Live typing, find match at or after cursor
            targetPos = matches.find(p => p >= cursor) ?? matches[0];
        }
        
        textarea.focus();
        textarea.setSelectionRange(targetPos, targetPos + term.length);
        
        // Smooth scroll to selection
        const linesBefore = text.substring(0, targetPos).split('\n').length;
        const lineHeight = 22.2; // approx 13.5px * 1.65
        textarea.scrollTop = Math.max(0, (linesBefore - 5) * lineHeight);
        
        const matchIdx = matches.indexOf(targetPos) + 1;
        if (status) status.innerText = `${matchIdx}/${count}`;
    },
    
    saveFile(path) {
        const content = document.getElementById('fm-editor-textarea').value;
        const btn = document.querySelector('#fm-editor-modal .btn-primary');
        const oldText = btn.innerText;
        btn.innerText = 'Đang lưu...';
        btn.disabled = true;
        
        fetch('api.php?action=fmSave', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('detail-project-name')?.innerText,
                category: App.currentCategory,
                path: path,
                content: content
            })
        }).then(res => res.json()).then(res => {
            btn.innerText = oldText;
            btn.disabled = false;
            if (res.status === 'success') {
                UI.showToast('Lưu file thành công!', 'success');
                document.getElementById('fm-editor-modal').remove();
                this.loadCurrentPath();
            } else {
                UI.showToast('Lỗi: ' + res.message, 'error');
            }
        });
    },

    openInEditor(path) {
        UI.showToast('Đang tải file và khởi động Antigravity...', 'info');
        fetch('api.php?action=fmOpenInEditor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('detail-project-name')?.innerText,
                category: App.currentCategory,
                path: path
            })
        }).then(res => res.json()).then(res => {
            if (res.status === 'success') {
                UI.showToast('🚀 Đang mở file trong Antigravity IDE!', 'success');
                if (res.ideUrl) {
                    window.location.href = res.ideUrl;
                    
                    // Start checking for background syncs
                    window.fmSyncIntervals = window.fmSyncIntervals || {};
                    if (window.fmSyncIntervals[path]) clearInterval(window.fmSyncIntervals[path]);
                    
                    let lastSyncTime = 0;
                    let isFirstCheck = true;
                    let lastToastTime = 0;
                    
                    const checkSync = () => {
                        fetch('api.php?action=fmCheckSyncStatus', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                name: document.getElementById('detail-project-name')?.innerText,
                                category: App.currentCategory,
                                path: path
                            })
                        }).then(r => r.json()).then(syncRes => {
                            if (syncRes.status === 'success') {
                                const newTime = parseFloat(syncRes.time);
                                if (isFirstCheck) {
                                    lastSyncTime = newTime;
                                    isFirstCheck = false;
                                } else if (newTime > lastSyncTime) {
                                    lastSyncTime = newTime;
                                    const now = Date.now();
                                    if (now - lastToastTime > 3000) {
                                        lastToastTime = now;
                                        const fileName = path.split('/').pop();
                                        UI.showToast(`✅ IDE: Đã lưu tự động ${fileName} lên Server!`, 'success');
                                    }
                                }
                            }
                        }).catch(() => {});
                    };
                    
                    checkSync(); // Initial check immediately
                    window.fmSyncIntervals[path] = setInterval(checkSync, 2000);
                }
            } else {
                UI.showToast('Lỗi mở IDE: ' + res.message, 'error');
            }
        }).catch(err => {
            UI.showToast('Lỗi kết nối khi mở IDE', 'error');
        });
    },

    syncLocalFile(path) {
        UI.showToast('Đang đồng bộ file lên Demo Host...', 'info');
        fetch('api.php?action=fmSyncLocalFile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('detail-project-name')?.innerText,
                category: App.currentCategory,
                path: path
            })
        }).then(res => res.json()).then(res => {
            if (res.status === 'success') {
                UI.showToast('✅ Đã đồng bộ file lên Demo Host!', 'success');
                this.loadCurrentPath();
            } else {
                UI.showToast('Lỗi đồng bộ: ' + res.message, 'error');
            }
        });
    },

    previewImage(url, title) {
        let existing = document.getElementById('fm-image-modal');
        if (existing) existing.remove();
        
        const modalHtml = `
            <div class="modal-overlay" id="fm-image-modal" style="display:flex; z-index:9999;" onclick="if(event.target===this) this.remove()">
                <div class="modal-content" style="max-width: 800px; width: 90%; max-height: 90vh; display: flex; flex-direction: column; background: #1e1e24; border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 10px 40px rgba(0,0,0,0.8);">
                    <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h4 style="margin:0; font-size:14px; color:#fff; word-break:break-all;">🖼️ ${this.escapeHtml(title)}</h4>
                        <button class="modal-close" onclick="document.getElementById('fm-image-modal').remove()" style="background:none; border:none; color:#aaa; font-size:22px; cursor:pointer; line-height:1;">×</button>
                    </div>
                    <div class="modal-body" style="padding: 20px; display:flex; justify-content:center; align-items:center; background: repeating-conic-gradient(#18181b 0% 25%, #27272a 0% 50%) 50% / 20px 20px; min-height: 250px; overflow:auto;">
                        <img src="${url}" alt="${this.escapeHtml(title)}" style="max-width: 100%; max-height: 60vh; object-fit: contain; box-shadow: 0 4px 20px rgba(0,0,0,0.6); border-radius:4px;">
                    </div>
                    <div class="modal-footer" style="padding: 10px 18px; border-top: 1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; align-items:center; background: #18181b;">
                        <span style="font-size:11px; color:#888; word-break:break-all; max-width:60%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${url}</span>
                        <div style="display:flex; gap:8px;">
                            <a href="${url}" target="_blank" class="btn btn-ghost btn-sm" style="text-decoration:none; padding:4px 10px; font-size:12px;">🔗 Mở tab mới</a>
                            <button class="btn btn-primary btn-sm" onclick="document.getElementById('fm-image-modal').remove()" style="padding:4px 12px; font-size:12px;">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    },

    escapeHtml(unsafe) {
        return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
};

const SyncCenter = {
    lastData: null,
    currentFilter: 'all',
    searchQuery: '',
    
    init() {
        // Init state
    },
    
    scan() {
        const projectName = document.getElementById('detail-project-name')?.innerText;
        if (!projectName) return;
        
        document.getElementById('sync-center-content').innerHTML = `
            <div style="padding: 60px 20px; text-align: center; background: rgba(255,255,255,0.015); border: 1px dashed rgba(255,255,255,0.1); border-radius: 16px;">
                <div class="loader" style="margin: 0 auto 18px; border: 3px solid rgba(0, 210, 211, 0.15); border-top-color: var(--primary, #00d2d3); border-radius: 50%; width: 36px; height: 36px; animation: sc-spin 0.8s linear infinite;"></div>
                <h4 style="color: #fff; font-size: 15px; margin-bottom: 6px; font-weight: 600;">Đang quét & đối soát toàn bộ cây thư mục</h4>
                <p style="color: var(--text-muted, #94a3b8); font-size: 13px;">Hệ thống đang so sánh thời gian & dung lượng giữa Local và Demo...</p>
            </div>
            <style>@keyframes sc-spin { 100% { transform:rotate(360deg); } }</style>
        `;
        
        fetch('api.php?action=fmSyncCenterCompare', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: projectName,
                category: App.currentCategory
            })
        }).then(r => r.json()).then(res => {
            if (res.status === 'success') {
                this.lastData = res.comparison;
                this.currentFilter = 'all';
                this.searchQuery = '';
                this.render();
            } else {
                document.getElementById('sync-center-content').innerHTML = `
                    <div style="padding: 24px; border-radius: 12px; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; display: flex; align-items: center; gap: 12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <div style="font-weight:600; font-size:14px; margin-bottom:2px;">Quét thất bại</div>
                            <div style="font-size:13px; opacity:0.9;">${this.escapeHtml(res.message)}</div>
                        </div>
                    </div>
                `;
            }
        }).catch(err => {
            document.getElementById('sync-center-content').innerHTML = `
                <div style="padding: 24px; border-radius: 12px; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5;">
                    Lỗi kết nối khi quét. Vui lòng kiểm tra lại mạng hoặc cấu hình Demo.
                </div>
            `;
        });
    },

    setFilter(filter) {
        this.currentFilter = filter;
        
        // Update active class on filter buttons
        document.querySelectorAll('.sc-pill-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        if (filter === 'all') {
            document.querySelector('.sc-pill-btn.pill-all')?.classList.add('active');
        } else if (filter === 'upload') {
            document.querySelector('.sc-pill-btn.pill-up')?.classList.add('active');
        } else if (filter === 'download') {
            document.querySelector('.sc-pill-btn.pill-down')?.classList.add('active');
        } else if (filter === 'conflict') {
            document.querySelector('.sc-pill-btn.pill-conflict')?.classList.add('active');
        }
        
        this.renderTableOnly();
    },

    setSearch(query) {
        this.searchQuery = (query || '').toLowerCase().trim();
        this.renderTableOnly();
    },

    getAllItems() {
        if (!this.lastData) return [];
        const items = [];
        this.lastData.local_newer.forEach(f => items.push({ path: f.path, type: 'local_newer', reason: 'Local mới hơn', color: 'emerald', defaultAction: 'upload', local_mtime: f.local_mtime, remote_mtime: f.remote_mtime }));
        this.lastData.local_only.forEach(f => items.push({ path: f.path, type: 'local_only', reason: 'Chưa có trên Demo', color: 'cyan', defaultAction: 'upload', local_mtime: f.local_mtime, remote_mtime: f.remote_mtime }));
        this.lastData.remote_newer.forEach(f => items.push({ path: f.path, type: 'remote_newer', reason: 'Demo mới hơn', color: 'purple', defaultAction: 'download', local_mtime: f.local_mtime, remote_mtime: f.remote_mtime }));
        this.lastData.remote_only.forEach(f => items.push({ path: f.path, type: 'remote_only', reason: 'Chỉ có trên Demo', color: 'indigo', defaultAction: 'download', local_mtime: f.local_mtime, remote_mtime: f.remote_mtime }));
        this.lastData.conflict.forEach(f => items.push({ path: f.path, type: 'conflict', reason: 'Lệch dung lượng (Conflict)', color: 'rose', defaultAction: 'upload', local_mtime: f.local_mtime, remote_mtime: f.remote_mtime }));
        return items;
    },

    formatTime(ts) {
        if (!ts) return '<span style="color:#64748b; font-size:11.5px; font-style:italic;">Chưa có</span>';
        const d = new Date(ts * 1000);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const seconds = String(d.getSeconds()).padStart(2, '0');
        return `${day}/${month}/${year} <span style="color:#94a3b8; font-size:11px;">${hours}:${minutes}:${seconds}</span>`;
    },

    getFileIcon(path) {
        const ext = path.split('.').pop().toLowerCase();
        if (['php', 'blade.php'].includes(ext) || path.endsWith('.blade.php')) {
            return `<span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:5px; background:rgba(99,102,241,0.15); color:#818cf8; font-size:10px; font-weight:700; font-family:monospace;">PHP</span>`;
        }
        if (['js', 'ts', 'jsx', 'tsx'].includes(ext)) {
            return `<span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:5px; background:rgba(245,158,11,0.15); color:#fbbf24; font-size:10px; font-weight:700; font-family:monospace;">JS</span>`;
        }
        if (['css', 'scss', 'sass', 'less'].includes(ext)) {
            return `<span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:5px; background:rgba(14,165,233,0.15); color:#38bdf8; font-size:10px; font-weight:700; font-family:monospace;">CSS</span>`;
        }
        if (['json', 'lock', 'env', 'config'].includes(ext) || path.includes('.env')) {
            return `<span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:5px; background:rgba(16,185,129,0.15); color:#34d399; font-size:10px; font-weight:700; font-family:monospace;">CFG</span>`;
        }
        if (['md', 'txt', 'sql'].includes(ext)) {
            return `<span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:5px; background:rgba(168,85,247,0.15); color:#c084fc; font-size:10px; font-weight:700; font-family:monospace;">DOC</span>`;
        }
        return `<span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:5px; background:rgba(255,255,255,0.08); color:#94a3b8; font-size:10px; font-weight:700;">FILE</span>`;
    },

    render() {
        if (!this.lastData) return;
        const allItems = this.getAllItems();
        
        const countUpload = this.lastData.local_newer.length + this.lastData.local_only.length;
        const countDownload = this.lastData.remote_newer.length + this.lastData.remote_only.length;
        const countConflict = this.lastData.conflict.length;
        const total = allItems.length;

        if (total === 0) {
            document.getElementById('sync-center-content').innerHTML = `
                <div style="padding: 60px 20px; text-align: center; background: rgba(16,185,129,0.03); border: 1px solid rgba(16,185,129,0.15); border-radius: 16px;">
                    <div style="display:inline-flex; width:64px; height:64px; border-radius:50%; background:rgba(16,185,129,0.1); color:#10b981; align-items:center; justify-content:center; margin-bottom:16px;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <h3 style="color:#fff; font-size:18px; font-weight:700; margin-bottom:6px;">Tuyệt vời! Mọi thứ đã đồng bộ 100%</h3>
                    <p style="color:var(--text-muted, #94a3b8); font-size:13px; max-width:400px; margin:0 auto;">Không tìm thấy file nào bị lệch giữa máy Local và Demo Server.</p>
                </div>
            `;
            return;
        }

        let html = `
            <style>
                .sc-pill-btn {
                    height: 38px; padding: 0 16px; box-sizing: border-box; border-radius: 9px; font-size: 13px; font-weight: 700;
                    cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 8px;
                    border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.03); color: #94a3b8;
                }
                .sc-pill-btn:hover { background: rgba(255,255,255,0.08); color: #fff; transform: translateY(-1px); }
                
                /* Base Active Fallback */
                .sc-pill-btn.active {
                    background: #00d2d3 !important; color: #0f172a !important; font-weight: 700 !important;
                    border-color: #00f2fe !important; box-shadow: 0 4px 18px rgba(0, 210, 211, 0.45) !important;
                    transform: translateY(-1px);
                }
                .sc-pill-btn.active .sc-badge-count {
                    background: rgba(0, 0, 0, 0.3) !important; color: #fff !important;
                }
                
                /* Pill: Tất cả (Cyan Theme) */
                .sc-pill-btn.pill-all { border-color: rgba(0, 210, 211, 0.25); background: rgba(0, 210, 211, 0.05); color: #cbd5e1; }
                .sc-pill-btn.pill-all .sc-badge-count { background: rgba(0, 210, 211, 0.2); color: #00d2d3; }
                .sc-pill-btn.pill-all.active {
                    background: linear-gradient(135deg, #00d2d3, #0097e6) !important;
                    color: #04141e !important; border-color: #00f2fe !important;
                    box-shadow: 0 4px 18px rgba(0, 210, 211, 0.45) !important; font-weight: 700 !important;
                }
                .sc-pill-btn.pill-all.active .sc-badge-count { background: rgba(0, 0, 0, 0.35) !important; color: #ffffff !important; }
                
                /* Pill: Đẩy lên Demo (Emerald Theme) */
                .sc-pill-btn.pill-up { border-color: rgba(16, 185, 129, 0.25); background: rgba(16, 185, 129, 0.05); color: #cbd5e1; }
                .sc-pill-btn.pill-up svg { color: #34d399; }
                .sc-pill-btn.pill-up .sc-badge-count { background: rgba(16, 185, 129, 0.2); color: #34d399; }
                .sc-pill-btn.pill-up.active {
                    background: linear-gradient(135deg, #10b981, #059669) !important;
                    color: #ffffff !important; border-color: #34d399 !important;
                    box-shadow: 0 4px 18px rgba(16, 185, 129, 0.45) !important; font-weight: 700 !important;
                }
                .sc-pill-btn.pill-up.active svg { color: #ffffff !important; }
                .sc-pill-btn.pill-up.active .sc-badge-count { background: rgba(0, 0, 0, 0.35) !important; color: #ffffff !important; }
                
                /* Pill: Kéo về Local (Purple Theme) */
                .sc-pill-btn.pill-down { border-color: rgba(168, 85, 247, 0.25); background: rgba(168, 85, 247, 0.05); color: #cbd5e1; }
                .sc-pill-btn.pill-down svg { color: #c084fc; }
                .sc-pill-btn.pill-down .sc-badge-count { background: rgba(168, 85, 247, 0.2); color: #c084fc; }
                .sc-pill-btn.pill-down.active {
                    background: linear-gradient(135deg, #a855f7, #7c3aed) !important;
                    color: #ffffff !important; border-color: #c084fc !important;
                    box-shadow: 0 4px 18px rgba(168, 85, 247, 0.45) !important; font-weight: 700 !important;
                }
                .sc-pill-btn.pill-down.active svg { color: #ffffff !important; }
                .sc-pill-btn.pill-down.active .sc-badge-count { background: rgba(0, 0, 0, 0.35) !important; color: #ffffff !important; }
                
                /* Pill: Xung đột (Rose Theme) */
                .sc-pill-btn.pill-conflict { border-color: rgba(244, 63, 94, 0.25); background: rgba(244, 63, 94, 0.05); color: #cbd5e1; }
                .sc-pill-btn.pill-conflict svg { color: #fb7185; }
                .sc-pill-btn.pill-conflict .sc-badge-count { background: rgba(244, 63, 94, 0.2); color: #fb7185; }
                .sc-pill-btn.pill-conflict.active {
                    background: linear-gradient(135deg, #f43f5e, #e11d48) !important;
                    color: #ffffff !important; border-color: #fb7185 !important;
                    box-shadow: 0 4px 18px rgba(244, 63, 94, 0.45) !important; font-weight: 700 !important;
                }
                .sc-pill-btn.pill-conflict.active svg { color: #ffffff !important; }
                .sc-pill-btn.pill-conflict.active .sc-badge-count { background: rgba(0, 0, 0, 0.35) !important; color: #ffffff !important; }
                
                .sc-badge-count {
                    padding: 2px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700;
                    transition: all 0.2s ease;
                }
                
                .sc-table { width: 100%; border-collapse: separate; border-spacing: 0 4px; }
                .sc-table thead th {
                    padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase;
                    letter-spacing: 0.05em; color: #64748b; border: none; text-align: left !important;
                }
                .sc-table tbody tr {
                    background: rgba(18, 24, 34, 0.6); backdrop-filter: blur(8px);
                    transition: all 0.15s ease; border-radius: 8px;
                }
                .sc-table tbody tr:hover {
                    background: rgba(26, 34, 48, 0.85); transform: translateY(-1px);
                    box-shadow: 0 4px 14px rgba(0,0,0,0.15);
                }
                .sc-table tbody td {
                    padding: 10px 14px; vertical-align: middle; border: none; text-align: left;
                }
                .sc-table tbody tr td:first-child { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
                .sc-table tbody tr td:last-child { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
                
                .sc-select-action {
                    appearance: none; -webkit-appearance: none;
                    background: rgba(13, 18, 28, 0.9) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center;
                    border: 1px solid rgba(255, 255, 255, 0.12);
                    border-radius: 7px; color: #f1f5f9; padding: 6px 28px 6px 10px;
                    font-size: 12.5px; font-weight: 500; cursor: pointer; transition: all 0.2s ease;
                }
                .sc-select-action:focus, .sc-select-action:hover {
                    border-color: rgba(255,255,255,0.3); background-color: rgba(20, 28, 42, 0.95);
                }
                
                .sc-btn-sync-single {
                    width: 30px; height: 30px; border-radius: 7px; display: inline-flex;
                    align-items: center; justify-content: center; background: rgba(255,255,255,0.04);
                    border: 1px solid rgba(255,255,255,0.08); color: #94a3b8; cursor: pointer;
                    transition: all 0.2s ease;
                }
                .sc-btn-sync-single:hover {
                    background: var(--primary, #00d2d3); color: #000; border-color: transparent;
                    transform: scale(1.08); box-shadow: 0 0 12px var(--primary-glow, rgba(0,210,211,0.4));
                }
                
                .sc-btn-diff {
                    height: 30px; padding: 0 10px; border-radius: 7px; display: inline-flex;
                    align-items: center; gap: 5px; background: rgba(0, 210, 211, 0.08);
                    border: 1px solid rgba(0, 210, 211, 0.2); color: var(--primary, #00d2d3);
                    font-size: 11.5px; font-weight: 700; cursor: pointer; transition: all 0.2s ease;
                }
                .sc-btn-diff:hover {
                    background: rgba(0, 210, 211, 0.22); color: #00f2fe; border-color: rgba(0, 210, 211, 0.5);
                    transform: translateY(-1px); box-shadow: 0 0 12px rgba(0, 210, 211, 0.3);
                }
                
                .sc-search-box {
                    height: 38px; padding: 0 14px; box-sizing: border-box;
                    background: rgba(13, 17, 24, 0.7); border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 8px; color: #fff; font-size: 13px; width: 240px;
                    outline: none; transition: border-color 0.2s;
                }
                .sc-search-box:focus { border-color: var(--primary, #00d2d3); box-shadow: 0 0 0 2px var(--primary-glow, rgba(0,210,211,0.15)); }

                .sc-custom-cb {
                    width: 16px; height: 16px; accent-color: var(--primary, #00d2d3); cursor: pointer;
                }
                
                .sc-tag {
                    display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px;
                    border-radius: 6px; font-size: 11.5px; font-weight: 600;
                }
                .sc-tag-emerald { background: rgba(16, 185, 129, 0.12); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.2); }
                .sc-tag-cyan { background: rgba(6, 182, 212, 0.12); color: #22d3ee; border: 1px solid rgba(34, 211, 238, 0.2); }
                .sc-tag-purple { background: rgba(168, 85, 247, 0.12); color: #c084fc; border: 1px solid rgba(192, 132, 252, 0.2); }
                .sc-tag-indigo { background: rgba(99, 102, 241, 0.12); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.2); }
                .sc-tag-rose { background: rgba(244, 63, 94, 0.12); color: #fb7185; border: 1px solid rgba(251, 113, 133, 0.2); }

                /* Diff Modal Styles */
                .sc-diff-overlay {
                    position: fixed; inset: 0; z-index: 10050;
                    background: rgba(6, 9, 15, 0.88); backdrop-filter: blur(12px);
                    display: flex; align-items: center; justify-content: center;
                    padding: 24px; animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
                }
                .sc-diff-dialog {
                    width: 95vw; max-width: 1400px; height: 90vh; max-height: 900px;
                    background: #0b0f17; border: 1px solid rgba(255, 255, 255, 0.12);
                    border-radius: 16px; display: flex; flex-direction: column;
                    overflow: hidden; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.7);
                }
                .sc-diff-header {
                    padding: 14px 20px; background: rgba(255, 255, 255, 0.03);
                    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
                }
                .sc-diff-body {
                    flex: 1; overflow: auto; background: #070a10; font-family: var(--mono, monospace);
                    font-size: 12px; line-height: 1.5; color: #cbd5e1;
                }
                .sc-diff-footer {
                    padding: 12px 20px; background: rgba(255, 255, 255, 0.03);
                    border-top: 1px solid rgba(255, 255, 255, 0.08);
                    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
                }
                .diff-line {
                    display: flex; width: 100%; border-bottom: 1px solid rgba(255, 255, 255, 0.02); box-sizing: border-box;
                }
                .diff-line:hover { background: rgba(255, 255, 255, 0.04); }
                .diff-cell-inner {
                    display: flex; width: 100%; min-height: 22px; box-sizing: border-box; align-items: stretch;
                }
                .diff-cell-inner:hover { background: rgba(255, 255, 255, 0.04); }
                .diff-num {
                    width: 44px; text-align: right; padding: 2px 8px; color: #475569;
                    user-select: none; border-right: 1px solid rgba(255, 255, 255, 0.06); flex-shrink: 0; box-sizing: border-box;
                }
                .diff-text {
                    padding: 2px 10px; white-space: pre; flex: 1; min-width: 0; overflow-x: auto;
                }
                .diff-add { background: rgba(16, 185, 129, 0.16) !important; color: #34d399 !important; }
                .diff-add .diff-num { color: #10b981; background: rgba(16, 185, 129, 0.08); }
                .diff-del { background: rgba(244, 63, 94, 0.16) !important; color: #fb7185 !important; }
                .diff-del .diff-num { color: #f43f5e; background: rgba(244, 63, 94, 0.08); }
                .diff-gutter-sym { width: 20px; text-align: center; font-weight: 700; user-select: none; flex-shrink: 0; padding-top: 2px; }
                .diff-split-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                .diff-split-table tr { border-bottom: 1px solid rgba(255, 255, 255, 0.02); }
                .diff-split-table td { padding: 0; vertical-align: top; width: 50%; border-right: 1px solid rgba(255,255,255,0.08); }
                .diff-split-table td:last-child { border-right: none; }
            </style>

            <div style="display: flex; flex-direction: column; gap: 16px;">
                <!-- Header Toolbar & Filters -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: rgba(255,255,255,0.02); padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <button class="sc-pill-btn pill-all ${this.currentFilter === 'all' ? 'active' : ''}" onclick="SyncCenter.setFilter('all')">
                            Tất cả <span class="sc-badge-count">${total}</span>
                        </button>
                        <button class="sc-pill-btn pill-up ${this.currentFilter === 'upload' ? 'active' : ''}" onclick="SyncCenter.setFilter('upload')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                            Đẩy lên Demo <span class="sc-badge-count">${countUpload}</span>
                        </button>
                        <button class="sc-pill-btn pill-down ${this.currentFilter === 'download' ? 'active' : ''}" onclick="SyncCenter.setFilter('download')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                            Kéo về Local <span class="sc-badge-count">${countDownload}</span>
                        </button>
                        ${countConflict > 0 ? `
                            <button class="sc-pill-btn pill-conflict ${this.currentFilter === 'conflict' ? 'active' : ''}" onclick="SyncCenter.setFilter('conflict')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                Xung đột <span class="sc-badge-count">${countConflict}</span>
                            </button>
                        ` : ''}
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" class="sc-search-box" placeholder="🔍 Lọc theo tên file/đường dẫn..." oninput="SyncCenter.setSearch(this.value)" value="${this.escapeHtml(this.searchQuery)}">
                        <button class="btn btn-primary" onclick="SyncCenter.executeAll()" style="height: 38px; box-sizing: border-box; display: flex; align-items: center; gap: 8px; font-weight: 700; padding: 0 18px; border-radius: 8px; box-shadow: 0 4px 14px var(--primary-glow, rgba(0,210,211,0.25));">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            <span id="sc-btn-execute-text">THỰC THI ĐÃ CHỌN</span>
                        </button>
                    </div>
                </div>

                <!-- Table Content Container -->
                <div id="sc-table-container"></div>
            </div>
        `;

        document.getElementById('sync-center-content').innerHTML = html;
        this.renderTableOnly();
    },

    renderTableOnly() {
        const container = document.getElementById('sc-table-container');
        if (!container) return;

        let items = this.getAllItems();

        // Apply filter
        if (this.currentFilter === 'upload') {
            items = items.filter(i => i.type === 'local_newer' || i.type === 'local_only');
        } else if (this.currentFilter === 'download') {
            items = items.filter(i => i.type === 'remote_newer' || i.type === 'remote_only');
        } else if (this.currentFilter === 'conflict') {
            items = items.filter(i => i.type === 'conflict');
        }

        // Apply search
        if (this.searchQuery) {
            items = items.filter(i => i.path.toLowerCase().includes(this.searchQuery));
        }

        if (items.length === 0) {
            container.innerHTML = `
                <div style="padding: 40px; text-align: center; color: var(--text-muted, #94a3b8); font-size: 13px;">
                    Không tìm thấy file nào khớp với bộ lọc hiện tại.
                </div>
            `;
            return;
        }

        let tableHtml = `
            <table class="sc-table">
                <thead>
                    <tr>
                        <th style="width: 44px; text-align: center;">
                            <input type="checkbox" class="sc-custom-cb" id="sync-check-all" checked onchange="document.querySelectorAll('.sync-cb').forEach(cb => cb.checked = this.checked); SyncCenter.updateExecuteBtnCount();">
                        </th>
                        <th>Đường dẫn File</th>
                        <th style="width: 170px; white-space: nowrap;">Trạng thái / Lý do</th>
                        <th style="width: 155px; white-space: nowrap;">Ngày Local</th>
                        <th style="width: 155px; white-space: nowrap;">Ngày Demo</th>
                        <th style="width: 160px; white-space: nowrap;">Hành động</th>
                        <th style="width: 110px; text-align: left; white-space: nowrap;">Xử lý</th>
                    </tr>
                </thead>
                <tbody>
        `;

        items.forEach(item => {
            const path = this.escapeHtml(item.path);
            const isUpload = item.defaultAction === 'upload';
            const icon = this.getFileIcon(item.path);
            
            // Format nice path: split dir & filename
            const parts = item.path.split('/');
            const fileName = parts.pop();
            const dirName = parts.length > 0 ? parts.join('/') + '/' : '';

            tableHtml += `
                <tr>
                    <td style="text-align: center;">
                        <input type="checkbox" class="sc-custom-cb sync-cb" value="${path}" checked onchange="SyncCenter.updateExecuteBtnCount();">
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="SyncCenter.openDiff('${path}')" title="Nhấn để xem Diff so sánh nội dung">
                            ${icon}
                            <div style="font-family: var(--mono, monospace); font-size: 12.5px; line-height: 1.4; word-break: break-all;">
                                <span style="color: #64748b; font-size: 11.5px;">${this.escapeHtml(dirName)}</span><span style="color: #f8fafc; font-weight: 600; text-decoration: underline; text-decoration-color: rgba(255,255,255,0.2);">${this.escapeHtml(fileName)}</span>
                            </div>
                        </div>
                    </td>
                    <td style="white-space: nowrap;">
                        <span class="sc-tag sc-tag-${item.color}">
                            ${item.type === 'local_newer' || item.type === 'local_only' ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>' : ''}
                            ${item.type === 'remote_newer' || item.type === 'remote_only' ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>' : ''}
                            ${item.type === 'conflict' ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>' : ''}
                            ${item.reason}
                        </span>
                    </td>
                    <td style="white-space: nowrap; font-family: var(--mono, monospace); font-size: 12px; color: ${item.local_mtime ? '#34d399' : '#64748b'};">
                        ${this.formatTime(item.local_mtime)}
                    </td>
                    <td style="white-space: nowrap; font-family: var(--mono, monospace); font-size: 12px; color: ${item.remote_mtime ? '#c084fc' : '#64748b'};">
                        ${this.formatTime(item.remote_mtime)}
                    </td>
                    <td style="white-space: nowrap;">
                        <select class="sc-select-action sync-action" data-path="${path}">
                            <option value="upload" ${isUpload ? 'selected' : ''}>↑ Đẩy lên Demo</option>
                            <option value="download" ${!isUpload ? 'selected' : ''}>↓ Kéo về Local</option>
                        </select>
                    </td>
                    <td style="text-align: left; white-space: nowrap;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <button class="sc-btn-diff" onclick="SyncCenter.openDiff('${path}')" title="So sánh sự thay đổi code (Diff)">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span>Diff</span>
                            </button>
                            <button class="sc-btn-sync-single" onclick="SyncCenter.executeSingle(this, '${path}')" title="Đồng bộ ngay file này (Tự động Backup)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tableHtml += `</tbody></table>`;
        container.innerHTML = tableHtml;
        this.updateExecuteBtnCount();
    },

    updateExecuteBtnCount() {
        const checkedCount = document.querySelectorAll('.sync-cb:checked').length;
        const btnText = document.getElementById('sc-btn-execute-text');
        if (btnText) {
            btnText.innerText = `THỰC THI (${checkedCount} FILE)`;
        }
    },

    executeSingle(btn, path) {
        const row = btn.closest('tr');
        const action = row.querySelector('.sync-action').value;
        const actions = { upload: [], download: [] };
        actions[action].push(path);
        
        btn.innerHTML = '<span class="loader" style="width:12px; height:12px; border-width:2px; display:inline-block;"></span>';
        btn.disabled = true;

        this.runExecuteApi(actions).then(res => {
            if (res.status === 'success') {
                UI.showToast(`Đã ${action === 'upload' ? 'đẩy' : 'kéo'} file ${path.split('/').pop()} thành công (Đã tự động backup)!`, 'success');
                row.style.opacity = '0.35';
                const cb = row.querySelector('.sync-cb');
                if (cb) cb.checked = false;
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
                btn.style.borderColor = 'rgba(16,185,129,0.4)';
                this.updateExecuteBtnCount();
            } else {
                UI.showToast('Lỗi: ' + res.message, 'error');
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
                btn.disabled = false;
            }
        }).catch(() => {
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>';
            btn.disabled = false;
        });
    },

    async executeAll() {
        let actions = { upload: [], download: [] };
        
        document.querySelectorAll('.sc-table tbody tr').forEach(row => {
            const cb = row.querySelector('.sync-cb');
            const select = row.querySelector('.sync-action');
            if (cb && cb.checked && select) {
                const action = select.value;
                const path = cb.value;
                if (actions[action]) {
                    actions[action].push(path);
                }
            }
        });
        
        const totalSelected = actions.upload.length + actions.download.length;
        if (totalSelected === 0) {
            UI.showToast('Chưa chọn file nào để đồng bộ', 'warning');
            return;
        }
        
        if (!await UI.confirm(`Xác nhận đồng bộ?\n- Đẩy lên Demo: ${actions.upload.length} files\n- Kéo về Local: ${actions.download.length} files\n\n🛡️ Hệ thống sẽ TỰ ĐỘNG BACKUP file cũ trước khi ghi đè.`)) return;
        
        UI.showToast('Đang thực thi đồng bộ & tạo backup an toàn...', 'info');
        
        const btnText = document.getElementById('sc-btn-execute-text');
        const oldText = btnText ? btnText.innerText : 'THỰC THI';
        if (btnText) btnText.innerHTML = '<span class="loader" style="width:12px; height:12px; border-width:2px; display:inline-block; margin-right:6px;"></span> ĐANG ĐỒNG BỘ...';
        
        this.runExecuteApi(actions).then(res => {
            if (res.status === 'success') {
                const results = res.results || [];
                const errors = results.filter(r => r.status === 'error');
                if (errors.length > 0) {
                    UI.showToast(`Đã đồng bộ ${results.length - errors.length}/${results.length} files. Có ${errors.length} file bị lỗi!`, 'warning');
                } else {
                    UI.showToast(`Đồng bộ thành công ${results.length} files (Tất cả đã được backup)!`, 'success');
                }
                this.scan(); // Rescan to refresh differences
            } else {
                UI.showToast('Lỗi đồng bộ: ' + res.message, 'error');
                if (btnText) btnText.innerText = oldText;
            }
        }).catch(err => {
            UI.showToast('Lỗi kết nối khi đồng bộ', 'error');
            if (btnText) btnText.innerText = oldText;
        });
    },

    runExecuteApi(actions) {
        const projectName = document.getElementById('detail-project-name')?.innerText;
        return fetch('api.php?action=fmSyncCenterExecute', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: projectName,
                category: App.currentCategory,
                actions: actions
            })
        }).then(r => r.json());
    },

    // ==========================================
    // DIFF VIEWER & COMPARISON ENGINE
    // ==========================================
    diffMode: 'sideBySide', // 'sideBySide' or 'unified'
    currentDiffData: null,

    openDiff(path) {
        const projectName = document.getElementById('detail-project-name')?.innerText;
        if (!projectName) return;

        UI.showToast('Đang nạp dữ liệu so sánh file...', 'info');

        fetch(`api.php?action=fmGetDiff&name=${encodeURIComponent(projectName)}&category=${encodeURIComponent(App.currentCategory)}&path=${encodeURIComponent(path)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') {
                    UI.showToast('Không thể đọc file để so sánh: ' + (res.message || 'Lỗi server'), 'error');
                    return;
                }
                this.currentDiffData = res;
                this.renderDiffModal(res);
            })
            .catch(err => {
                UI.showToast('Lỗi kết nối khi tải diff', 'error');
            });
    },

    closeDiffModal() {
        const modal = document.getElementById('sc-diff-modal-container');
        if (modal) modal.remove();
        this.currentDiffData = null;
    },

    setDiffViewMode(mode) {
        this.diffMode = mode;
        if (this.currentDiffData) {
            this.renderDiffModal(this.currentDiffData);
        }
    },

    computeLineDiff(oldStr, newStr) {
        const oldLines = (oldStr || '').split('\n');
        const newLines = (newStr || '').split('\n');

        // Simple LCS Diff Algorithm
        const n = oldLines.length;
        const m = newLines.length;
        
        // Matrix for LCS length
        const dp = Array.from({ length: n + 1 }, () => new Uint16Array(m + 1));
        for (let i = 0; i < n; i++) {
            for (let j = 0; j < m; j++) {
                if (oldLines[i] === newLines[j]) {
                    dp[i + 1][j + 1] = dp[i][j] + 1;
                } else {
                    dp[i + 1][j + 1] = Math.max(dp[i + 1][j], dp[i][j + 1]);
                }
            }
        }

        // Backtrack to get diff operations
        let i = n, j = m;
        const diff = [];
        while (i > 0 || j > 0) {
            if (i > 0 && j > 0 && oldLines[i - 1] === newLines[j - 1]) {
                diff.push({ type: 'equal', oldLine: oldLines[i - 1], newLine: newLines[j - 1], oldNum: i, newNum: j });
                i--; j--;
            } else if (j > 0 && (i === 0 || dp[i][j - 1] >= dp[i - 1][j])) {
                diff.push({ type: 'insert', oldLine: '', newLine: newLines[j - 1], oldNum: null, newNum: j });
                j--;
            } else if (i > 0 && (j === 0 || dp[i][j - 1] < dp[i - 1][j])) {
                diff.push({ type: 'delete', oldLine: oldLines[i - 1], newLine: '', oldNum: i, newNum: null });
                i--;
            }
        }

        return diff.reverse();
    },

    renderDiffModal(data) {
        let existing = document.getElementById('sc-diff-modal-container');
        if (existing) existing.remove();

        const path = data.path;
        const local = data.local;
        const remote = data.remote;

        const isLocalExists = local && local.exists;
        const isRemoteExists = remote && remote.exists;

        const localContent = isLocalExists ? local.content : '';
        const remoteContent = isRemoteExists ? remote.content : '';

        // Calculate diff: Local as old (left), Demo as new (right)
        const diffLines = this.computeLineDiff(localContent, remoteContent);
        const countAdded = diffLines.filter(l => l.type === 'insert').length;
        const countDeleted = diffLines.filter(l => l.type === 'delete').length;

        const modal = document.createElement('div');
        modal.id = 'sc-diff-modal-container';
        modal.className = 'sc-diff-overlay';
        modal.onclick = (e) => {
            if (e.target === modal) SyncCenter.closeDiffModal();
        };

        let diffBodyHtml = '';

        if (!isLocalExists && !isRemoteExists) {
            diffBodyHtml = '<div style="padding:40px; text-align:center; color:#f87171;">File không tồn tại ở cả 2 môi trường.</div>';
        } else if (this.diffMode === 'sideBySide') {
            // Side by side rendering
            let rowsHtml = '';
            diffLines.forEach(item => {
                if (item.type === 'equal') {
                    rowsHtml += `
                        <tr>
                            <td>
                                <div class="diff-cell-inner">
                                    <span class="diff-num">${item.oldNum}</span>
                                    <span class="diff-gutter-sym" style="color:#475569;"> </span>
                                    <span class="diff-text">${this.escapeHtml(item.oldLine)}</span>
                                </div>
                            </td>
                            <td>
                                <div class="diff-cell-inner">
                                    <span class="diff-num">${item.newNum}</span>
                                    <span class="diff-gutter-sym" style="color:#475569;"> </span>
                                    <span class="diff-text">${this.escapeHtml(item.newLine)}</span>
                                </div>
                            </td>
                        </tr>
                    `;
                } else if (item.type === 'delete') {
                    rowsHtml += `
                        <tr>
                            <td>
                                <div class="diff-cell-inner diff-del">
                                    <span class="diff-num">${item.oldNum}</span>
                                    <span class="diff-gutter-sym">-</span>
                                    <span class="diff-text">${this.escapeHtml(item.oldLine)}</span>
                                </div>
                            </td>
                            <td style="background:rgba(255,255,255,0.01);">
                                <div class="diff-cell-inner">
                                    <span class="diff-num" style="opacity:0.3;"></span>
                                    <span class="diff-gutter-sym"> </span>
                                    <span class="diff-text"></span>
                                </div>
                            </td>
                        </tr>
                    `;
                } else if (item.type === 'insert') {
                    rowsHtml += `
                        <tr>
                            <td style="background:rgba(255,255,255,0.01);">
                                <div class="diff-cell-inner">
                                    <span class="diff-num" style="opacity:0.3;"></span>
                                    <span class="diff-gutter-sym"> </span>
                                    <span class="diff-text"></span>
                                </div>
                            </td>
                            <td>
                                <div class="diff-cell-inner diff-add">
                                    <span class="diff-num">${item.newNum}</span>
                                    <span class="diff-gutter-sym">+</span>
                                    <span class="diff-text">${this.escapeHtml(item.newLine)}</span>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            });

            diffBodyHtml = `
                <table class="diff-split-table">
                    <thead style="background:#0d121d; position:sticky; top:0; z-index:10; border-bottom:1px solid rgba(255,255,255,0.08);">
                        <tr>
                            <th style="padding:8px 14px; text-align:left; color:#34d399; font-size:12px; font-weight:700; width:50%; border-right:1px solid rgba(255,255,255,0.08);">
                                💻 BẢN LOCAL (DEV) ${isLocalExists ? `(${this.formatBytes(local.size)})` : '<span style="color:#f87171;">(Chưa có)</span>'}
                            </th>
                            <th style="padding:8px 14px; text-align:left; color:#c084fc; font-size:12px; font-weight:700; width:50%;">
                                ☁️ BẢN DEMO (HOSTING) ${isRemoteExists ? `(${this.formatBytes(remote.size)})` : '<span style="color:#f87171;">(Chưa có)</span>'}
                            </th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            `;
        } else {
            // Unified rendering
            let linesHtml = '';
            diffLines.forEach(item => {
                if (item.type === 'equal') {
                    linesHtml += `
                        <div class="diff-line">
                            <span class="diff-num">${item.oldNum || ''}</span>
                            <span class="diff-num">${item.newNum || ''}</span>
                            <span class="diff-gutter-sym" style="color:#475569;"> </span>
                            <span class="diff-text">${this.escapeHtml(item.oldLine || item.newLine)}</span>
                        </div>
                    `;
                } else if (item.type === 'delete') {
                    linesHtml += `
                        <div class="diff-line diff-del">
                            <span class="diff-num">${item.oldNum}</span>
                            <span class="diff-num" style="opacity:0.3;"> </span>
                            <span class="diff-gutter-sym">-</span>
                            <span class="diff-text">${this.escapeHtml(item.oldLine)}</span>
                        </div>
                    `;
                } else if (item.type === 'insert') {
                    linesHtml += `
                        <div class="diff-line diff-add">
                            <span class="diff-num" style="opacity:0.3;"> </span>
                            <span class="diff-num">${item.newNum}</span>
                            <span class="diff-gutter-sym">+</span>
                            <span class="diff-text">${this.escapeHtml(item.newLine)}</span>
                        </div>
                    `;
                }
            });

            diffBodyHtml = `
                <div style="background:#0d121d; position:sticky; top:0; z-index:10; border-bottom:1px solid rgba(255,255,255,0.08); padding:8px 14px; font-size:12px; font-weight:700; display:flex; gap:20px;">
                    <span style="color:#34d399;">💻 Local: ${isLocalExists ? this.formatBytes(local.size) : 'Chưa có'}</span>
                    <span style="color:#c084fc;">☁️ Demo: ${isRemoteExists ? this.formatBytes(remote.size) : 'Chưa có'}</span>
                </div>
                <div>${linesHtml}</div>
            `;
        }

        modal.innerHTML = `
            <div class="sc-diff-dialog">
                <!-- Header -->
                <div class="sc-diff-header">
                    <div style="display:flex; align-items:center; gap:10px; min-width:0;">
                        <span style="font-size:18px;">🔍</span>
                        <div>
                            <div style="font-size:14px; font-weight:700; color:#fff; font-family:var(--mono, monospace); word-break:break-all;">
                                ${this.escapeHtml(path)}
                            </div>
                            <div style="font-size:11.5px; color:#94a3b8; display:flex; align-items:center; gap:12px; margin-top:2px;">
                                <span><b style="color:#34d399;">+${countAdded}</b> thêm mới</span>
                                <span><b style="color:#fb7185;">-${countDeleted}</b> xóa/sửa</span>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:#38bdf8;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Tự động Backup trước khi đè</span>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <!-- Mode Toggle -->
                        <div style="display:flex; background:rgba(255,255,255,0.06); padding:3px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                            <button style="padding:4px 10px; border-radius:6px; font-size:11.5px; font-weight:700; border:none; cursor:pointer; background:${this.diffMode === 'sideBySide' ? 'var(--primary, #00d2d3)' : 'transparent'}; color:${this.diffMode === 'sideBySide' ? '#000' : '#94a3b8'}; transition:all 0.15s;" onclick="SyncCenter.setDiffViewMode('sideBySide')">
                                Song song (Split)
                            </button>
                            <button style="padding:4px 10px; border-radius:6px; font-size:11.5px; font-weight:700; border:none; cursor:pointer; background:${this.diffMode === 'unified' ? 'var(--primary, #00d2d3)' : 'transparent'}; color:${this.diffMode === 'unified' ? '#000' : '#94a3b8'}; transition:all 0.15s;" onclick="SyncCenter.setDiffViewMode('unified')">
                                Gộp dòng (Unified)
                            </button>
                        </div>
                        <button class="btn btn-ghost" onclick="SyncCenter.closeDiffModal()" style="height:32px; width:32px; padding:0; border-radius:8px; font-size:16px;">✕</button>
                    </div>
                </div>

                <!-- Body (Diff Container) -->
                <div class="sc-diff-body">
                    ${diffBodyHtml}
                </div>

                <!-- Footer Actions -->
                <div class="sc-diff-footer">
                    <div style="font-size:12px; color:#94a3b8; display:flex; align-items:center; gap:8px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>Bản gốc trước khi ghi đè sẽ được tự động lưu trữ trong thư mục <code>backups/sync_snapshots/</code></span>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px;">
                        <button class="btn btn-ghost" onclick="SyncCenter.closeDiffModal()" style="height:36px; padding:0 14px;">Đóng</button>
                        <button class="btn" style="height:36px; padding:0 16px; font-weight:700; background:linear-gradient(135deg, #a855f7, #7c3aed); color:#fff; border:none;" onclick="SyncCenter.executeSingleDiff('${this.escapeHtml(path)}', 'download')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                            Kéo Demo về Local (Backup Local)
                        </button>
                        <button class="btn btn-primary" style="height:36px; padding:0 16px; font-weight:700;" onclick="SyncCenter.executeSingleDiff('${this.escapeHtml(path)}', 'upload')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                            Đẩy Local lên Demo (Backup Demo)
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    },

    async executeSingleDiff(path, action) {
        if (!await UI.confirm(`Xác nhận ${action === 'upload' ? 'đẩy bản Local lên ghi đè Demo' : 'kéo bản Demo về ghi đè Local'}?\n\n🛡️ Bản cũ sẽ được tự động backup an toàn.`)) return;

        const actions = { upload: [], download: [] };
        actions[action].push(path);

        UI.showToast('Đang thực thi đồng bộ...', 'info');

        this.runExecuteApi(actions).then(res => {
            if (res.status === 'success') {
                UI.showToast(`Đã ${action === 'upload' ? 'đẩy' : 'kéo'} file thành công & đã tự động backup!`, 'success');
                this.closeDiffModal();
                this.scan(); // Refresh differences
            } else {
                UI.showToast('Lỗi đồng bộ: ' + res.message, 'error');
            }
        }).catch(err => {
            UI.showToast('Lỗi kết nối khi đồng bộ', 'error');
        });
    },

    // ==========================================
    // BACKUP & RESTORE HISTORY MANAGEMENT
    // ==========================================
    cachedBackups: [],
    backupFilter: 'all',
    backupSearch: '',

    openBackupHistory() {
        const projectName = document.getElementById('detail-project-name')?.innerText?.trim() || (App.currentProject ? App.currentProject.name : '');
        if (!projectName) {
            UI.showToast('Vui lòng chọn dự án trước khi xem lịch sử backup!', 'warning');
            return;
        }

        UI.showToast('Đang tải danh sách bản sao lưu...', 'info');

        fetch(`api.php?action=fmListBackups&name=${encodeURIComponent(projectName)}&category=${encodeURIComponent(App.currentCategory || 'projects')}`)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') {
                    UI.showToast('Không thể tải lịch sử backup: ' + (res.message || 'Lỗi server'), 'error');
                    return;
                }
                this.cachedBackups = res.backups || [];
                this.renderBackupHistoryModal();
            })
            .catch(() => {
                UI.showToast('Lỗi kết nối khi lấy lịch sử backup', 'error');
            });
    },

    closeBackupHistoryModal() {
        const modal = document.getElementById('sc-backup-modal-container');
        if (modal) modal.remove();
    },

    setBackupFilter(type) {
        this.backupFilter = type;
        document.querySelectorAll('#sc-backup-modal-container .sc-pill-btn').forEach(btn => btn.classList.remove('active'));
        const targetBtn = document.getElementById(`sc-bk-pill-${type}`);
        if (targetBtn) {
            targetBtn.classList.add('active');
        }
        this.renderBackupList();
    },

    setBackupSearch(query) {
        this.backupSearch = (query || '').toLowerCase().trim();
        this.renderBackupList();
    },

    getFilteredBackups() {
        let list = this.cachedBackups;
        if (this.backupFilter !== 'all') {
            list = list.filter(b => b.type === this.backupFilter);
        }
        if (this.backupSearch) {
            list = list.filter(b => (b.original_rel_path || '').toLowerCase().includes(this.backupSearch) || (b.date || '').includes(this.backupSearch));
        }
        return list;
    },

    renderBackupHistoryModal() {
        let existing = document.getElementById('sc-backup-modal-container');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'sc-backup-modal-container';
        modal.className = 'sc-diff-overlay';
        modal.onclick = (e) => {
            if (e.target === modal) SyncCenter.closeBackupHistoryModal();
        };

        const total = this.cachedBackups.length;
        const countUpload = this.cachedBackups.filter(b => b.type === 'remote_before_upload').length;
        const countDownload = this.cachedBackups.filter(b => b.type === 'local_before_download').length;
        const countEdit = this.cachedBackups.filter(b => b.type === 'remote_before_edit').length;

        modal.innerHTML = `
            <div class="sc-diff-dialog" style="max-width: 1200px; height: 85vh;">
                <!-- Header -->
                <div class="sc-diff-header">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:22px;">📦</span>
                        <div>
                            <div style="font-size:15px; font-weight:700; color:#fff;">
                                Lịch sử Sao lưu &amp; Khôi phục (Backup Snapshots)
                            </div>
                            <div style="font-size:12px; color:#94a3b8; margin-top:2px;">
                                Tự động lưu trữ bản gốc trước mọi thao tác ghi đè — Khôi phục 1-click về Local hoặc Demo
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-ghost" onclick="SyncCenter.closeBackupHistoryModal()" style="height:32px; width:32px; padding:0; border-radius:8px; font-size:16px;">✕</button>
                </div>

                <!-- Toolbar & Filter -->
                <div style="padding: 12px 20px; background: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="sc-pill-btn pill-all ${this.backupFilter === 'all' ? 'active' : ''}" id="sc-bk-pill-all" style="height:34px; padding:0 12px; font-size:12px;" onclick="SyncCenter.setBackupFilter('all')">
                            Tất cả <span class="sc-badge-count">${total}</span>
                        </button>
                        <button class="sc-pill-btn pill-up ${this.backupFilter === 'remote_before_upload' ? 'active' : ''}" id="sc-bk-pill-remote_before_upload" style="height:34px; padding:0 12px; font-size:12px;" onclick="SyncCenter.setBackupFilter('remote_before_upload')">
                            🛡️ Demo trước khi đè <span class="sc-badge-count">${countUpload}</span>
                        </button>
                        <button class="sc-pill-btn pill-down ${this.backupFilter === 'local_before_download' ? 'active' : ''}" id="sc-bk-pill-local_before_download" style="height:34px; padding:0 12px; font-size:12px;" onclick="SyncCenter.setBackupFilter('local_before_download')">
                            💻 Local trước khi kéo <span class="sc-badge-count">${countDownload}</span>
                        </button>
                        <button class="sc-pill-btn pill-conflict ${this.backupFilter === 'remote_before_edit' ? 'active' : ''}" id="sc-bk-pill-remote_before_edit" style="height:34px; padding:0 12px; font-size:12px;" onclick="SyncCenter.setBackupFilter('remote_before_edit')">
                            ✎ Sửa trên Web <span class="sc-badge-count">${countEdit}</span>
                        </button>
                    </div>

                    <div style="min-width: 260px;">
                        <input type="text" placeholder="🔍 Tìm theo đường dẫn file hoặc ngày..." style="width:100%; height:34px; padding:0 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(0,0,0,0.25); color:#fff; font-size:12.5px; outline:none; box-sizing:border-box;" oninput="SyncCenter.setBackupSearch(this.value)">
                    </div>
                </div>

                <!-- Body List Container -->
                <div class="sc-diff-body" id="sc-backup-list-body" style="padding: 16px;"></div>

                <!-- Footer -->
                <div class="sc-diff-footer">
                    <div style="font-size:12px; color:#64748b;">
                        📁 Thư mục lưu trữ: <code>backups/sync_snapshots/</code>
                    </div>
                    <button class="btn btn-ghost" onclick="SyncCenter.closeBackupHistoryModal()" style="height:36px; padding:0 16px;">Đóng</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        this.renderBackupList();
    },

    renderBackupList() {
        const container = document.getElementById('sc-backup-list-body');
        if (!container) return;

        const list = this.getFilteredBackups();

        if (list.length === 0) {
            container.innerHTML = `
                <div style="padding: 60px 20px; text-align: center; color: #64748b;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:10px; opacity:0.5;"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                    <div style="font-size: 14px; font-weight: 600; color: #cbd5e1;">Chưa có bản sao lưu nào khớp</div>
                    <p style="font-size: 12px; margin: 4px 0 0;">Bản sao lưu sẽ tự động được tạo mỗi khi bạn thực hiện Đồng bộ (Sync) hoặc Sửa file.</p>
                </div>
            `;
            return;
        }

        let html = `
            <table class="sc-table">
                <thead>
                    <tr>
                        <th style="width: 175px; white-space: nowrap;">Thời gian Sao lưu</th>
                        <th>Đường dẫn File gốc</th>
                        <th style="width: 190px; white-space: nowrap;">Loại Snapshot</th>
                        <th style="width: 100px; white-space: nowrap;">Dung lượng</th>
                        <th style="width: 250px; text-align: right; white-space: nowrap;">Khôi phục (Rollback)</th>
                    </tr>
                </thead>
                <tbody>
        `;

        list.forEach(item => {
            const path = item.original_rel_path || '';
            const icon = this.getFileIcon(path);
            const backupFile = item.backup_file || '';
            
            let typeLabel = '';
            let typeTagClass = '';
            if (item.type === 'remote_before_upload') {
                typeLabel = '🛡️ Demo (Trước khi Upload)';
                typeTagClass = 'sc-tag-emerald';
            } else if (item.type === 'local_before_download') {
                typeLabel = '💻 Local (Trước khi Download)';
                typeTagClass = 'sc-tag-purple';
            } else if (item.type === 'remote_before_edit') {
                typeLabel = '✎ Demo (Trước khi Sửa Web)';
                typeTagClass = 'sc-tag-cyan';
            } else {
                typeLabel = '⏪ Bản lưu trước Restore';
                typeTagClass = 'sc-tag-indigo';
            }

            html += `
                <tr>
                    <td style="white-space: nowrap; font-family: var(--mono, monospace); font-size: 12px; color: #94a3b8;">
                        <span style="color: #f8fafc; font-weight: 600;">${this.escapeHtml(item.time || '')}</span> 
                        <span style="color: #64748b; font-size: 11px;">${this.escapeHtml(item.date || '')}</span>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            ${icon}
                            <span style="font-family: var(--mono, monospace); font-size: 12.5px; color: #f8fafc; font-weight: 600; word-break: break-all;">
                                ${this.escapeHtml(path)}
                            </span>
                        </div>
                    </td>
                    <td style="white-space: nowrap;">
                        <span class="sc-tag ${typeTagClass}">${typeLabel}</span>
                    </td>
                    <td style="white-space: nowrap; font-family: var(--mono, monospace); font-size: 12px; color: #94a3b8;">
                        ${this.formatBytes(item.size)}
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                        <div style="display: inline-flex; align-items: center; gap: 6px;">
                            <button class="sc-btn-diff" onclick="SyncCenter.previewBackup('${this.escapeHtml(backupFile)}', '${this.escapeHtml(path)}')" title="Xem nội dung code bản snapshot này">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span>Xem</span>
                            </button>
                            <button class="btn btn-ghost btn-sm" style="height:30px; padding:0 10px; font-size:11.5px; font-weight:700; color:#c084fc; border-color:rgba(168,85,247,0.3);" onclick="SyncCenter.restoreBackup('${this.escapeHtml(backupFile)}', '${this.escapeHtml(path)}', 'local')" title="Khôi phục ghi đè lại file Local">
                                ⏪ Về Local
                            </button>
                            <button class="btn btn-ghost btn-sm" style="height:30px; padding:0 10px; font-size:11.5px; font-weight:700; color:#34d399; border-color:rgba(16,185,129,0.3);" onclick="SyncCenter.restoreBackup('${this.escapeHtml(backupFile)}', '${this.escapeHtml(path)}', 'remote')" title="Khôi phục ghi đè lại lên Demo Hosting">
                                ⏫ Lên Demo
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `</tbody></table>`;
        container.innerHTML = html;
    },

    async restoreBackup(backupFile, path, target) {
        const isRemote = target === 'remote';
        const targetName = isRemote ? 'Demo Hosting' : 'Local Workspace';
        const actionVerb = isRemote ? 'LÊN' : 'VỀ';
        const actionVerbLower = isRemote ? 'lên' : 'về';
        
        if (!await UI.confirm(`Xác nhận KHÔI PHỤC ${actionVerb} ${targetName} cho file:\n${path}\n\n🛡️ Bản file hiện tại sẽ được tự động sao lưu an toàn trước khi khôi phục.`)) {
            return;
        }

        const projectName = document.getElementById('detail-project-name')?.innerText;
        UI.showToast(`Đang khôi phục file ${actionVerbLower} ${targetName}...`, 'info');

        fetch('api.php?action=fmRestoreBackup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: projectName,
                category: App.currentCategory,
                backup_file: backupFile,
                path: path,
                target: target
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                UI.showToast(res.message || `Đã khôi phục thành công ${actionVerbLower} ${targetName}!`, 'success');
                this.openBackupHistory(); // Reload list to show new restore backup
                this.scan(); // Rescan Sync Center
            } else {
                UI.showToast('Lỗi khôi phục: ' + res.message, 'error');
            }
        })
        .catch(() => {
            UI.showToast('Lỗi kết nối khi khôi phục', 'error');
        });
    },

    previewBackup(backupFile, path) {
        const projectName = document.getElementById('detail-project-name')?.innerText;
        UI.showToast('Đang tải nội dung bản sao lưu...', 'info');

        fetch(`api.php?action=fmGetBackupContent&name=${encodeURIComponent(projectName)}&category=${encodeURIComponent(App.currentCategory)}&backup_file=${encodeURIComponent(backupFile)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status !== 'success') {
                    UI.showToast('Không thể đọc file: ' + res.message, 'error');
                    return;
                }

                let previewModal = document.getElementById('sc-backup-preview-modal');
                if (previewModal) previewModal.remove();

                previewModal = document.createElement('div');
                previewModal.id = 'sc-backup-preview-modal';
                previewModal.className = 'sc-diff-overlay';
                previewModal.style.zIndex = '10060';
                previewModal.onclick = (e) => {
                    if (e.target === previewModal) previewModal.remove();
                };

                const lines = (res.content || '').split('\n');
                let codeHtml = '';
                lines.forEach((line, idx) => {
                    codeHtml += `
                        <div class="diff-line">
                            <span class="diff-num">${idx + 1}</span>
                            <span class="diff-text">${this.escapeHtml(line)}</span>
                        </div>
                    `;
                });

                previewModal.innerHTML = `
                    <div class="sc-diff-dialog" style="max-width: 1100px; height: 80vh;">
                        <div class="sc-diff-header">
                            <div style="font-size: 14px; font-weight: 700; color: #fff; font-family: var(--mono, monospace);">
                                📄 Xem Snapshot: ${this.escapeHtml(path)}
                            </div>
                            <button class="btn btn-ghost" onclick="document.getElementById('sc-backup-preview-modal').remove()" style="height:30px; width:30px; padding:0; border-radius:8px;">✕</button>
                        </div>
                        <div class="sc-diff-body" style="padding: 10px 0;">
                            ${codeHtml}
                        </div>
                        <div class="sc-diff-footer">
                            <span style="font-size: 12px; color: #94a3b8;">${lines.length} dòng | ${this.formatBytes(res.content.length)}</span>
                            <div style="display:flex; gap:10px;">
                                <button class="btn btn-ghost" onclick="document.getElementById('sc-backup-preview-modal').remove()">Đóng</button>
                                <button class="btn btn-primary" onclick="SyncCenter.restoreBackup('${this.escapeHtml(backupFile)}', '${this.escapeHtml(path)}', 'local'); document.getElementById('sc-backup-preview-modal').remove();">
                                    ⏪ Khôi phục về Local
                                </button>
                                <button class="btn" style="background:linear-gradient(135deg, #10b981, #059669); color:#fff; font-weight:700;" onclick="SyncCenter.restoreBackup('${this.escapeHtml(backupFile)}', '${this.escapeHtml(path)}', 'remote'); document.getElementById('sc-backup-preview-modal').remove();">
                                    ⏫ Khôi phục lên Demo
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(previewModal);
            })
            .catch(() => {
                UI.showToast('Lỗi kết nối khi tải nội dung snapshot', 'error');
            });
    },

    formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    escapeHtml(unsafe) {
        return (unsafe || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
};
