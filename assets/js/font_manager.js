const FontManager = {
    async reindex() {
        UI.notify('Đang quét và đồng bộ lại chỉ mục tree.md...', 'info');
        try {
            const res = await (await fetch('api.php?action=reindexFonts')).json();
            if (res.status === 'success') {
                UI.notify(res.message, 'success');
                this.search(true);
            } else {
                UI.notify('Lỗi đồng bộ: ' + (res.message || 'Thao tác thất bại'), 'error');
            }
        } catch (err) {
            UI.notify('Lỗi kết nối API đồng bộ', 'error');
        }
    },

    async search(isProjectView = false) {
        const inputId = isProjectView ? 'project-font-search-input' : 'font-search-input';
        const resultsId = isProjectView ? 'project-font-results' : 'font-results';
        
        const query = document.getElementById(inputId).value.trim();
        // Allow empty query to show all
        
        const resultsContainer = document.getElementById(resultsId);
        resultsContainer.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px;"><div class="spinner" style="margin:0 auto 10px;"></div>Đang tìm kiếm...</div>';

        try {
            const res = await (await fetch(`api.php?action=searchFonts&query=${encodeURIComponent(query)}`)).json();
            if (res.status === 'success') {
                this.renderResults(res.data, resultsId);
                
                if (query !== '' && !res.google_search) {
                    UI.notify('Không thể kết nối với Google Fonts. Chỉ hiển thị kết quả từ Local Library.', 'info');
                }
            } else {
                UI.notify('Lỗi tìm kiếm: ' + res.message, 'error');
            }
        } catch (err) {
            UI.notify('Lỗi kết nối API', 'error');
        }
    },

    renderResults(fonts, resultsId) {
        const container = document.getElementById(resultsId);
        container.innerHTML = '';

        if (fonts.length === 0) {
            container.innerHTML = '<p style="color:var(--muted); text-align:center; padding:40px; grid-column: 1/-1;">Không tìm thấy font nào phù hợp.</p>';
            return;
        }

        const woffFonts = [];
        const convertFonts = [];

        fonts.forEach(font => {
            if (font.source === 'google' || (font.variants && font.variants.length > 0)) {
                woffFonts.push(font);
            }
            if (font.source === 'local' && font.convert_variants && font.convert_variants.length > 0) {
                convertFonts.push(font);
            }
        });

        // 1. Render WOFF Fonts
        if (woffFonts.length > 0) {
            const header = document.createElement('div');
            header.style.gridColumn = '1/-1';
            header.style.margin = '20px 0 10px 0';
            header.innerHTML = `
                <h4 style="margin:0; color:var(--primary); font-size:0.95rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:8px;">
                    <span style="display:inline-block; width:6px; height:14px; background:var(--primary); border-radius:2px;"></span>
                    Font WOFF / WOFF2 (Khuyên Dùng - Tương Thích Web Tốt Nhất)
                </h4>
            `;
            container.appendChild(header);

            woffFonts.forEach(font => {
                const card = document.createElement('div');
                const isGoogle = font.source === 'google';
                card.className = isGoogle ? 'font-card-premium google' : 'font-card-premium';
                
                const displayFamily = isGoogle ? font.family : font.family.split(' > ').pop();

                card.innerHTML = `
                    <div class="font-card-header">
                        <div class="font-card-header-left">
                            <div class="font-family-title">${displayFamily}</div>
                            <div class="font-path-badge">${isGoogle ? '☁️ Google Web Fonts' : '📁 ' + font.family}</div>
                        </div>
                        <div class="font-source-tag ${isGoogle ? 'google' : 'local'}">
                            ${isGoogle ? '☁️ Google' : '⚡ Local WOFF'}
                        </div>
                    </div>
                    
                    ${!isGoogle ? `
                    <div class="font-variants-wrap">
                        <div class="font-variants-header">
                            <span class="font-variants-title">BIẾN THỂ WEIGHT & STYLE (${(font.variants || []).length})</span>
                            <button class="btn btn-ghost" style="font-size:0.68rem; padding:2px 8px; height:auto; color:#22d3ee;" onclick="FontManager.toggleAll('${font.id}')">Chọn tất cả</button>
                        </div>
                        <div class="font-variants-box" id="vars-${font.id.replace(/[^a-z0-9]/gi, '')}">
                            ${(font.variants || []).map(v => `
                                <label class="font-variant-chip">
                                    <input type="checkbox" class="font-variant-checkbox" data-font="${font.id}" data-variant="${v}">
                                    <span>${String(v).replace('i', ' Italic')}</span>
                                </label>
                            `).join('')}
                        </div>
                    </div>
                    ` : `
                    <div class="font-variants-wrap">
                        <div class="font-variants-title">BIẾN THỂ (${(font.variants || []).length})</div>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            ${(font.variants || []).slice(0, 12).map(v => `<span style="font-size:0.68rem; color:#cbd5e1; background:rgba(255,255,255,0.06); padding:3px 9px; border-radius:6px; border:1px solid rgba(255,255,255,0.08);">${v}</span>`).join('')}
                            ${font.variants.length > 12 ? `<span style="font-size:0.68rem; color:var(--muted); padding:3px 6px;">+${font.variants.length - 12}</span>` : ''}
                        </div>
                    </div>
                    `}

                    <div class="font-preview-box-premium" style="font-family: '${displayFamily}', sans-serif;">
                        The quick brown fox jumps over the lazy dog.
                    </div>

                    <div>
                        ${isGoogle ? 
                            `<button class="font-action-btn-google" onclick="FontManager.quickAddGoogle('${font.family}', ${JSON.stringify(font.variants).replace(/"/g, '&quot;')})">⚡ Add via Google @import</button>` :
                            `<button class="font-action-btn-local" onclick="FontManager.install('${font.id}', '${font.family}')">💾 Install Local Font</button>`
                        }
                    </div>
                `;
                
                if (isGoogle) {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = `https://fonts.googleapis.com/css2?family=${displayFamily.replace(/ /g, '+')}&display=swap`;
                    document.head.appendChild(link);
                }
                
                container.appendChild(card);
            });
        }

        // 2. Render OTF/TTF Fonts
        if (convertFonts.length > 0) {
            const header = document.createElement('div');
            header.style.gridColumn = '1/-1';
            header.style.margin = '40px 0 10px 0';
            header.innerHTML = `
                <h4 style="margin:0; color:var(--warning); font-size:0.95rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:8px;">
                    <span style="display:inline-block; width:6px; height:14px; background:var(--warning); border-radius:2px;"></span>
                    Font Gốc OTF / TTF (Cần Convert Sang WOFF)
                </h4>
            `;
            container.appendChild(header);

            convertFonts.forEach(font => {
                const card = document.createElement('div');
                card.className = 'font-card-premium convertible';
                const displayFamily = font.family.split(' > ').pop();

                card.innerHTML = `
                    <div class="font-card-header">
                        <div class="font-card-header-left">
                            <div class="font-family-title">${displayFamily}</div>
                            <div class="font-path-badge">📁 ${font.family}</div>
                        </div>
                        <div class="font-source-tag convertible">
                            ⚙️ Convertible
                        </div>
                    </div>
                    
                    <div class="font-variants-wrap">
                        <div class="font-variants-header">
                            <span class="font-variants-title">BIẾN THỂ CẦN CONVERT (${(font.convert_variants || []).length})</span>
                            <button class="btn btn-ghost" style="font-size:0.68rem; padding:2px 8px; height:auto; color:#fbbf24;" onclick="FontManager.toggleAllConvert('${font.id}')">Chọn tất cả</button>
                        </div>
                        <div class="font-variants-box" id="conv-vars-${font.id.replace(/[^a-z0-9]/gi, '')}">
                            ${(font.convert_variants || []).map(cv => `
                                <label class="font-variant-chip">
                                    <input type="checkbox" class="font-convert-checkbox" data-font="${font.id}" data-variant="${cv.variant}" data-ext="${cv.ext}" data-filename="${cv.filename}">
                                    <span>${String(cv.variant).replace('i', ' Italic')} (${cv.ext.toUpperCase()})</span>
                                </label>
                            `).join('')}
                        </div>
                    </div>

                    <div class="font-preview-box-premium">
                        The quick brown fox jumps over the lazy dog.
                    </div>

                    <div>
                        <button class="font-action-btn-convert" onclick="FontManager.convertAndInstall('${font.id}', '${font.family}')">⚡ Convert & Install Font</button>
                    </div>
                `;
                
                container.appendChild(card);
            });
        }
    },

    quickAddGoogle(family, variants = []) {
        if (!confirm(`Bạn có chắc chắn muốn thêm Google Font "${family}" qua @import vào dự án không?`)) return;
        // Construct a precise Google Fonts v2 URL
        // Format: ital,wght@0,100;0,400;1,100;1,400
        
        let urlParams = "";
        if (variants && variants.length > 0) {
            const normalWeights = [];
            const italicWeights = [];
            
            variants.forEach(v => {
                const variantStr = String(v);
                if (variantStr.endsWith('i')) {
                    italicWeights.push(variantStr.replace('i', ''));
                } else {
                    normalWeights.push(variantStr || '400');
                }
            });

            // Sort weights numerically
            normalWeights.sort((a, b) => parseInt(a) - parseInt(b));
            italicWeights.sort((a, b) => parseInt(a) - parseInt(b));

            if (italicWeights.length > 0) {
                // If we have italics, we use the ital,wght syntax
                const pairs = [];
                normalWeights.forEach(w => pairs.push(`0,${w}`));
                italicWeights.forEach(w => pairs.push(`1,${w}`));
                urlParams = `:ital,wght@${pairs.join(';')}`;
            } else {
                // Only normal weights
                urlParams = `:wght@${normalWeights.join(';')}`;
            }
        }

        const importUrl = `@import url('https://fonts.googleapis.com/css2?family=${family.replace(/ /g, '+')}${urlParams}&display=swap');`;
        this.executeAddGoogle(family, importUrl);
    },

    async executeAddGoogle(family, importUrl) {
        const projectName = document.getElementById('d_current-project')?.value;
        if (!projectName) return UI.notify('Vui lòng mở một dự án trước!', 'error');

        try {
            const res = await (await fetch(`api.php?action=addGoogleFont`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: projectName, importUrl })
            })).json();

            if (res.status === 'success') {
                UI.notify(`Đã thêm Google Font ${family} vào fonts.css!`, 'success');
                this.loadCssPreview();
            } else {
                UI.notify(res.message, 'error');
            }
        } catch (err) {
            UI.notify('Lỗi kết nối API', 'error');
        }
    },

    async install(fontId, fontFamily) {
        const projectName = document.getElementById('d_current-project')?.value || document.getElementById('current-project')?.value;
        if (!projectName) return UI.notify('Vui lòng mở một dự án trước khi cài đặt font!', 'error');

        // Get selected variants
        const checkedBoxes = document.querySelectorAll(`.font-variant-checkbox[data-font="${fontId}"]:checked`);
        if (checkedBoxes.length === 0) return UI.notify('Vui lòng chọn ít nhất một biến thể font!', 'error');
        
        const selectedVariants = Array.from(checkedBoxes).map(cb => cb.dataset.variant);

        if (!confirm(`Bạn có chắc muốn cài đặt ${selectedVariants.length} biến thể của font "${fontFamily}" vào dự án "${projectName}"?`)) return;

        UI.showModal('deploy-modal');
        UI.updateDeployStatus('Đang chuẩn bị...', 10, `Bắt đầu copy font ${fontFamily}...`);

        try {
            const res = await (await fetch(`api.php?action=installFont`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: projectName, fontId, fontFamily, variants: selectedVariants })
            })).json();

            if (res.status === 'success') {
                UI.updateDeployStatus('Thành công!', 100, `Đã cài đặt font ${fontFamily} vào thư mục assets/fonts/ của dự án.`);
                UI.notify('Cài đặt font thành công!', 'success');
                confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                this.loadCssPreview();
            } else {
                UI.updateDeployStatus('Lỗi!', 50, `<span style="color:var(--danger)">${res.message}</span>`);
            }
            document.getElementById('deploy-footer').style.display = 'flex';
        } catch (err) {
            UI.updateDeployStatus('Lỗi kết nối!', 0, 'Không thể kết nối API.');
            document.getElementById('deploy-footer').style.display = 'flex';
        }
    },

    toggleAll(fontId, status) {
        const checkboxes = document.querySelectorAll(`.font-variant-checkbox[data-font="${fontId}"]`);
        const btn = event.target;
        
        // If status is specifically provided (true/false), use it. 
        // Otherwise, determine based on current state (toggle).
        let targetState = status;
        if (targetState === undefined) {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            targetState = !allChecked;
        }

        checkboxes.forEach(cb => cb.checked = targetState);
        
        // Update button text
        if (btn && btn.tagName === 'BUTTON') {
            btn.innerText = targetState ? 'Bỏ chọn' : 'Chọn hết';
        }
    },

    async addGoogleFont() {
        // Redirection to unify logic
        const importUrl = document.getElementById('google-font-url')?.value.trim();
        if (!importUrl) return UI.notify('Vui lòng nhập link @import!', 'error');
        this.executeAddGoogle('Custom Font', importUrl);
    },

    async loadCssPreview() {
        const projectName = document.getElementById('d_current-project')?.value;
        if (!projectName) return;

        const previewContainer = document.getElementById('fonts-css-preview');
        if (!previewContainer) return;

        try {
            const res = await (await fetch(`api.php?action=getFontsCss&name=${encodeURIComponent(projectName)}`)).json();
            if (res.status === 'success') {
                const content = res.data || '/* Chưa có font nào được thêm */';
                previewContainer.innerText = content;
                this.renderInstalledFonts(content);
            }
        } catch (err) {
            previewContainer.innerText = '/* Lỗi tải file fonts.css */';
        }
    },

    renderInstalledFonts(cssContent) {
        const listContainer = document.getElementById('installed-fonts-list');
        if (!listContainer) return;
        listContainer.innerHTML = '';

        // Extract font names from @font-face or @import
        const fonts = [];
        
        // Match @font-face font-family
        const fontFaceRegex = /font-family:\s*['"]([^'"]+)['"]/g;
        let match;
        while ((match = fontFaceRegex.exec(cssContent)) !== null) {
            if (!fonts.includes(match[1])) fonts.push(match[1]);
        }

        // Match @import Google Fonts
        const importRegex = /family=([^&:]+)/g;
        while ((match = importRegex.exec(cssContent)) !== null) {
            const family = decodeURIComponent(match[1]).replace(/\+/g, ' ');
            if (!fonts.includes(family)) fonts.push(family);
        }

        if (fonts.length === 0) {
            listContainer.innerHTML = '<p style="font-size:0.8rem; color:var(--muted);">Chưa có font nào được cài đặt.</p>';
            return;
        }

        fonts.forEach(font => {
            const item = document.createElement('div');
            item.className = 'badge badge-ok';
            item.style.display = 'flex';
            item.style.justifyContent = 'space-between';
            item.style.alignItems = 'center';
            item.style.padding = '8px 12px';
            item.style.background = 'rgba(255,255,255,0.05)';
            item.style.border = '1px solid var(--border)';
            item.style.textTransform = 'none';
            item.innerHTML = `
                <span style="color:#fff; font-weight:600; margin-right:10px;">${font}</span>
                <span style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:0.6rem; color:var(--muted);">Installed</span>
                    <button class="btn btn-ghost" style="padding:2px; height:auto; color:var(--danger); font-size:0.75rem; border:none; background:none; cursor:pointer;" onclick="FontManager.remove('${font.replace(/'/g, "\\'")}')" title="Gỡ bỏ font">❌</button>
                </span>
            `;
            listContainer.appendChild(item);

            // Dynamically load font for preview in the list if it's a google font
            if (cssContent.includes('fonts.googleapis.com')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = `https://fonts.googleapis.com/css2?family=${font.replace(/ /g, '+')}&display=swap`;
                document.head.appendChild(link);
            }
        });
    },

    async remove(fontName) {
        const projectName = document.getElementById('d_current-project')?.value || document.getElementById('current-project')?.value;
        if (!projectName) return UI.notify('Vui lòng mở một dự án trước!', 'error');

        if (!confirm(`Bạn có chắc chắn muốn gỡ bỏ font "${fontName}" ra khỏi dự án "${projectName}" không?`)) return;

        try {
            const res = await (await fetch(`api.php?action=removeFont`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: projectName, fontName })
            })).json();

            if (res.status === 'success') {
                UI.notify(`Đã gỡ bỏ font ${fontName} thành công!`, 'success');
                this.loadCssPreview();
            } else {
                UI.notify(res.message, 'error');
            }
        } catch (err) {
            UI.notify('Lỗi kết nối API', 'error');
        }
    },

    toggleAllConvert(fontId, status) {
        const checkboxes = document.querySelectorAll(`.font-convert-checkbox[data-font="${fontId}"]`);
        const btn = event.target;
        
        let targetState = status;
        if (targetState === undefined) {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            targetState = !allChecked;
        }

        checkboxes.forEach(cb => cb.checked = targetState);
        
        if (btn && btn.tagName === 'BUTTON') {
            btn.innerText = targetState ? 'Bỏ chọn' : 'Chọn hết';
        }
    },

    async convertAndInstall(fontId, fontFamily) {
        const projectName = document.getElementById('d_current-project')?.value || document.getElementById('current-project')?.value;
        if (!projectName) return UI.notify('Vui lòng mở một dự án trước khi cài đặt font!', 'error');

        const checkedBoxes = document.querySelectorAll(`.font-convert-checkbox[data-font="${fontId}"]:checked`);
        if (checkedBoxes.length === 0) return UI.notify('Vui lòng chọn ít nhất một biến thể font để convert!', 'error');
        
        if (!confirm(`Bạn có chắc muốn convert và cài đặt ${checkedBoxes.length} biến thể của font "${fontFamily}" vào dự án "${projectName}"?`)) return;

        UI.showModal('deploy-modal');
        UI.updateDeployStatus('Đang chuẩn bị...', 10, `Bắt đầu tải các tệp tin font từ thư viện local...`);

        try {
            const filesToUpload = [];

            for (let i = 0; i < checkedBoxes.length; i++) {
                const cb = checkedBoxes[i];
                const variant = cb.dataset.variant;
                const ext = cb.dataset.ext;
                const filename = cb.dataset.filename;
                
                UI.updateDeployStatus('Đang tải...', Math.round(10 + (i / checkedBoxes.length) * 40), `Đang tải biến thể ${variant} (${ext.toUpperCase()})...`);
                
                const fileUrl = `api.php?action=getFontFile&fontId=${encodeURIComponent(fontId)}&variant=${variant}&ext=${ext}`;
                const resp = await fetch(fileUrl);
                if (!resp.ok) throw new Error(`Không thể tải biến thể ${variant} từ server.`);
                
                const arrayBuffer = await resp.arrayBuffer();

                UI.updateDeployStatus('Đang convert...', Math.round(50 + (i / checkedBoxes.length) * 30), `Đang convert biến thể ${variant} sang WOFF & WOFF2...`);
                
                const Font = window.fonteditor.Font;
                const fontObj = Font.create(arrayBuffer, {
                    type: ext.toLowerCase(),
                    hinting: true
                });

                // Write WOFF2
                const woff2Buffer = fontObj.write({
                    type: 'woff2',
                    hinting: true
                });
                let binaryWoff2 = '';
                const bytesWoff2 = new Uint8Array(woff2Buffer);
                const lenWoff2 = bytesWoff2.byteLength;
                for (let j = 0; j < lenWoff2; j++) {
                    binaryWoff2 += String.fromCharCode(bytesWoff2[j]);
                }
                const base64Woff2 = btoa(binaryWoff2);

                // Write WOFF
                const woffBuffer = fontObj.write({
                    type: 'woff',
                    hinting: true
                });
                let binaryWoff = '';
                const bytesWoff = new Uint8Array(woffBuffer);
                const lenWoff = bytesWoff.byteLength;
                for (let j = 0; j < lenWoff; j++) {
                    binaryWoff += String.fromCharCode(bytesWoff[j]);
                }
                const base64Woff = btoa(binaryWoff);

                const weight = variant.replace('i', '');
                const style = variant.endsWith('i') ? 'italic' : 'normal';

                // Add WOFF2 (recommended order)
                filesToUpload.push({
                    fileName: `${filename}.woff2`,
                    data: base64Woff2,
                    weight: weight,
                    style: style,
                    ext: 'woff2'
                });

                // Add WOFF
                filesToUpload.push({
                    fileName: `${filename}.woff`,
                    data: base64Woff,
                    weight: weight,
                    style: style,
                    ext: 'woff'
                });
            }

            UI.updateDeployStatus('Đang cài đặt...', 90, `Đang gửi các tệp tin đã convert lên server...`);
            
            const res = await (await fetch(`api.php?action=installConvertedFonts`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: projectName, fontFamily, files: filesToUpload })
            })).json();

            if (res.status === 'success') {
                UI.updateDeployStatus('Thành công!', 100, `Đã convert và cài đặt font ${fontFamily} vào thư mục assets/fonts/ của dự án.`);
                UI.notify('Convert và cài đặt font thành công!', 'success');
                confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                this.loadCssPreview();
            } else {
                UI.updateDeployStatus('Lỗi!', 50, `<span style="color:var(--danger)">${res.message}</span>`);
            }
            document.getElementById('deploy-footer').style.display = 'flex';
        } catch (err) {
            UI.updateDeployStatus('Lỗi xử lý!', 0, `<span style="color:var(--danger)">Lỗi: ${err.message}</span>`);
            document.getElementById('deploy-footer').style.display = 'flex';
        }
    }
};
