import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('chatRoom', (config) => ({
    ...config,
    budget: config.auto_budget,
    busy: false,
    actionError: null,
    pollTimer: null,
    fastPolls: 0,
    unread: 0,
    list: null,

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
        const delay = this.fastPolls > 0 ? 2000 : 10000;
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

        this.status = data.status;
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

    async action(path) {
        if (this.busy) return;
        this.busy = true;
        this.actionError = null;
        try {
            const res = await fetch(path, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) {
                this.actionError = data.error || 'Request failed.';
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

    start() { return this.action(`/chats/${this.id}/start`); },
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
