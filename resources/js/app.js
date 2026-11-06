import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('agentForm', (config) => ({
    providerId: config.providerId ?? '',
    customBaseUrl: config.customBaseUrl,
    customApiKey: config.customApiKey,
    model: config.model ?? '',
    advancedOpen: !!config.advancedOpen,

    open: false,
    highlighted: -1,
    groups: [],

    init() {
        this.groups = config.providers.map((p) => ({
            id: String(p.id),
            name: p.name,
            baseUrl: p.base_url,
            count: p.models_count ?? 0,
            isActive: !!p.is_active,
            expanded: false,
            loading: false,
            error: null,
            loaded: false,
            models: [],
        }));

        // Eagerly load + expand the initially selected provider's group.
        const selected = this.selectedGroup;
        if (selected) {
            selected.expanded = true;
            this.loadGroup(selected);
        }
    },

    get isCustom() {
        return this.providerId === '';
    },

    get selectedGroup() {
        return this.groups.find((g) => g.id === String(this.providerId)) ?? null;
    },

    get searching() {
        return (this.model ?? '').trim() !== '';
    },

    async loadGroup(g, force = false) {
        if (g.loading || (g.loaded && !force)) return;
        g.loading = true;
        g.error = null;
        try {
            const res = await fetch(`/api/providers/${g.id}/models${force ? '?refresh=1' : ''}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json().catch(() => ({ ok: false }));
            if (data.ok) {
                g.models = data.models ?? [];
                g.count = g.models.length;
                g.loaded = true;
            } else {
                g.error = data.error || 'request failed.';
            }
        } catch (e) {
            g.error = 'request failed.';
        } finally {
            g.loading = false;
        }
    },

    toggleGroup(g) {
        if (this.searching) return; // while searching every group stays auto-expanded
        g.expanded = !g.expanded;
        if (g.expanded) this.loadGroup(g);
    },

    /**
     * Groups prepared for rendering. While typing, filter across ALL groups and
     * auto-expand the ones with matches; otherwise respect collapse state.
     * Empty groups stay visible as headers so any provider can be picked.
     */
    get viewGroups() {
        const q = (this.model ?? '').trim().toLowerCase();
        const out = [];
        for (const g of this.groups) {
            let models = g.models;
            let nameHit = false;
            if (q !== '') {
                models = g.models.filter((m) => m.id.toLowerCase().includes(q) || m.name.toLowerCase().includes(q));
                nameHit = g.name.toLowerCase().includes(q);
                if (models.length === 0 && !nameHit && !g.error) continue;
            }
            out.push({
                g,
                models,
                showModels: q !== '' ? models.length > 0 : g.expanded,
                nameHit,
            });
        }
        return out;
    },

    /** Flattened dropdown rows for keyboard navigation. */
    flatItems() {
        const items = [];
        for (const v of this.viewGroups) {
            if (v.showModels) {
                for (const m of v.models) items.push({ type: 'model', g: v.g, m });
            }
        }
        items.push({ type: 'custom' });
        return items;
    },

    onPickerKeydown(e) {
        const items = this.flatItems();
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!this.open) {
                this.open = true;
                return;
            }
            if (items.length) this.highlighted = Math.min(this.highlighted + 1, items.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.highlighted = Math.max(this.highlighted - 1, 0);
        } else if (e.key === 'Enter') {
            if (this.open) {
                e.preventDefault();
                const item = items[Math.max(0, this.highlighted)];
                if (item) this.choose(item);
            }
        } else if (e.key === 'Tab') {
            this.open = false;
        }

        if (this.open) {
            this.$nextTick(() => {
                const el = document.querySelector('[data-picker-active="true"]');
                el?.scrollIntoView({ block: 'nearest' });
            });
        }
    },

    choose(item) {
        if (item.type === 'custom') {
            this.switchToCustom();
            return;
        }
        this.providerId = item.g.id;
        this.model = item.m.id;
        this.setPrice('price_per_1m_input', item.m.price_in_1m);
        this.setPrice('price_per_1m_output', item.m.price_out_1m);
        this.open = false;
        this.highlighted = -1;
    },

    switchToCustom() {
        this.providerId = '';
        this.open = false;
        this.highlighted = -1;
    },

    setPrice(name, v) {
        const el = this.$root.querySelector(`input[name="${name}"]`);
        if (el && !el.disabled && v !== null && v !== undefined) el.value = v;
    },

    isHighlighted(g, m) {
        const it = this.flatItems()[this.highlighted];
        return !!it && it.type === 'model' && it.g === g && it.m === m;
    },

    isCustomHighlighted() {
        const it = this.flatItems()[this.highlighted];
        return !!it && it.type === 'custom';
    },

    metaLine(m) {
        const parts = [];
        if (m.context_length) parts.push(Math.round(m.context_length / 1000).toLocaleString() + 'k ctx');
        const fmt = (p) => (p === null ? '?' : p === 0 ? 'free' : '$' + p);
        if (m.price_in_1m !== null || m.price_out_1m !== null) {
            parts.push(`${fmt(m.price_in_1m)} / ${fmt(m.price_out_1m)} per 1M`);
        }
        return parts.join(' · ');
    },
}));

Alpine.data('chatRoom', (config) => ({
    ...config,
    budget: config.auto_budget,
    busy: false,
    actionError: null,
    pollTimer: null,
    fastPolls: 0,
    unread: 0,
    list: null,

    get budgetDirty() {
        return Number(this.budget) !== Number(this.auto_budget);
    },

    get budgetValid() {
        const value = Number(this.budget);
        return Number.isInteger(value) && value >= 1 && value <= 500;
    },

    // Composer (user-composed messages)
    composerOpen: false,
    composerKind: '',
    composerTarget: '',
    draft: '',

    // Inline message editing
    editingId: null,
    draftEdit: '',

    init() {
        this.list = this.$refs.list;
        this.scrollToBottom(false);
        this.schedulePoll();
    },

    get lastId() {
        return this.messages.length ? this.messages[this.messages.length - 1].id : 0;
    },

    schedulePoll() {
        const delay = this.fastPolls > 0 ? 1000 : 2000;
        if (this.fastPolls > 0) this.fastPolls--;
        this.pollTimer = setTimeout(() => this.tick(), delay);
    },

    async tick() {
        if (!document.hidden) {
            await this.poll();
        }
        this.schedulePoll();
    },

    async poll() {
        try {
            const res = await fetch(`/api/chats/${this.id}/messages?after=${this.lastId}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            this.applyState(data);
        } catch (e) {
            /* transient network error — next tick retries */
        }
    },

    applyState(data) {
        const stickToBottom = this.isNearBottom();

        const known = new Set(this.messages.map((m) => m.id));
        for (const m of data.messages) {
            if (!known.has(m.id)) this.messages.push(m);
        }

        const budgetWasDirty = this.budgetDirty;

        this.status = data.status;
        this.auto_budget = data.auto_budget;
        if (!budgetWasDirty) this.budget = data.auto_budget;
        this.message_limit = data.message_limit;
        this.message_count = data.message_count;
        this.last_error = data.last_error;
        this.error_agent = data.error_agent;
        this.stats = data.stats;

        if (data.messages.length > 0) {
            if (stickToBottom) {
                this.scrollToBottom();
            } else {
                this.unread += data.messages.length;
            }
        }
    },

    async action(path, body = null) {
        if (this.busy) return;
        this.busy = true;
        this.actionError = null;
        try {
            const headers = {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            };
            if (body !== null) headers['Content-Type'] = 'application/json';

            const res = await fetch(path, {
                method: 'POST',
                headers,
                body: body === null ? undefined : JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) {
                this.actionError = data.error || data.message || 'Request failed.';
            } else {
                this.fastPolls = 8; // poll briskly for ~16s after a user action
                await this.poll();
            }
        } catch (e) {
            this.actionError = 'Request failed.';
        } finally {
            this.busy = false;
        }
    },

    start() {
        if (!this.budgetValid) {
            this.actionError = 'Budget must be a whole number between 1 and 500.';
            return;
        }

        return this.action(`/chats/${this.id}/start`, { auto_budget: Number(this.budget) });
    },
    stop() { return this.action(`/chats/${this.id}/stop`); },
    next() { return this.action(`/chats/${this.id}/next`); },
    reset() { return this.action(`/chats/${this.id}/reset`); },

    get csrfToken() {
        return document.querySelector('meta[name=csrf-token]').content;
    },

    async sendCustom() {
        if (this.busy || !this.draft.trim() || !this.composerKind) return;
        this.busy = true;
        this.actionError = null;

        const isNote = this.composerKind === 'note';
        const body = {
            content: this.draft,
            kind: isNote ? 'note' : 'agent',
        };

        if (isNote) {
            body.target = this.composerTarget === '' ? null : this.composerTarget;
        } else {
            body.chat_agent = this.composerKind; // the selected pivot id
        }

        try {
            const res = await fetch(`/chats/${this.id}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) {
                this.actionError = data.error || 'Request failed.';
            } else {
                this.draft = '';
                this.composerOpen = false;
                this.composerKind = '';
                this.composerTarget = '';
                this.fastPolls = 8;
                await this.poll();
            }
        } catch (e) {
            this.actionError = 'Request failed.';
        } finally {
            this.busy = false;
        }
    },

    editMsg(m) {
        this.editingId = m.id;
        this.draftEdit = m.content;
    },

    async saveEdit() {
        if (this.busy || !this.draftEdit.trim()) return;
        this.busy = true;
        try {
            const res = await fetch(`/messages/${this.editingId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({ content: this.draftEdit }),
            });
            if (res.ok) {
                const m = this.messages.find((x) => x.id === this.editingId);
                if (m) m.content = this.draftEdit;
                this.editingId = null;
            }
        } finally {
            this.busy = false;
        }
    },

    async removeMsg(id) {
        if (this.busy || !confirm('Delete this message? It will disappear from the chat and from future AI context.')) return;
        this.busy = true;
        try {
            const res = await fetch(`/messages/${id}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
            });
            if (res.ok) {
                this.messages = this.messages.filter((m) => m.id !== id);
                if (this.editingId === id) this.editingId = null;
            }
        } finally {
            this.busy = false;
        }
    },

    isNearBottom() {
        if (!this.list) return true;
        return this.list.scrollHeight - this.list.scrollTop - this.list.clientHeight < 120;
    },

    onScroll() {
        if (this.isNearBottom()) this.unread = 0;
    },

    scrollToBottom(smooth = true) {
        this.unread = 0;
        if (!this.list) return;
        this.list.scrollTo({ top: this.list.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    },

    fmtTokens(n) {
        if (n === null || n === undefined) return null;
        return n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : String(n);
    },

    fmtCost(c) {
        if (c === null || c === undefined) return null;
        const v = parseFloat(c);
        if (v === 0) return '$0';
        if (v < 0.01) return '$' + v.toFixed(4);
        return '$' + v.toFixed(2);
    },

    fmtMs(n) {
        if (n === null || n === undefined) return null;
        return n >= 1000 ? (n / 1000).toFixed(1) + 's' : n + 'ms';
    },

    fmtTime(iso) {
        if (!iso) return '';
        return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
}));

Alpine.start();
