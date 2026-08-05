const Api = {
    async fetch(action, options = {}) {
        const url = `api.php?action=${action}`;
        const res = await fetch(url, options);
        return await res.json();
    },

    async getCategories(strict = false) {
        return this.fetch(`listCategories${strict ? '&strict=true' : ''}`);
    },

    async getProjects(category) {
        return this.fetch(`listProjects&category=${category}`);
    },

    async saveConfig(name, config, category = '') {
        return this.fetch('saveConfig', {
            method: 'POST',
            body: JSON.stringify({ name, config, category })
        });
    },

    async deploy(name) {
        return this.fetch('deploy', {
            method: 'POST',
            body: JSON.stringify({ name })
        });
    },

    async getProjectConfig(name, category = '') {
        return this.fetch(`getProjectConfig&name=${name}&category=${category}`);
    },

    async getProjectSchemaList(name, category = App.currentCategory) {
        return this.fetch(`getProjectSchemaList&name=${name}&category=${category || ''}`);
    },

    async loadModuleSchema(name, file, category = App.currentCategory) {
        return this.fetch(`loadModuleSchema&name=${name}&file=${file}&category=${category || ''}`);
    },

    async getTypeImageSize(name, type) {
        return this.fetch(
            `getTypeImageSize&name=${encodeURIComponent(name)}&type=${encodeURIComponent(type)}`,
        );
    },

    async setupLocalSource(name, category, forceOverwriteDb = null) {
        return this.fetch('setupLocalSource', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, category, forceOverwriteDb })
        });
    },

    async reindexProjects() {
        return this.fetch('reindexProjects');
    }
};
