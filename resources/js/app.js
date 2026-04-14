import './bootstrap';

let _editorPromise = null;
window.loadToastUIEditor = () => {
    if (!_editorPromise) {
        _editorPromise = import('@toast-ui/editor').then(m => {
            window.ToastUIEditor = m.default;
        });
    }
    return _editorPromise;
};

let _viewerPromise = null;
window.loadToastUIViewer = () => {
    if (!_viewerPromise) {
        _viewerPromise = import('@toast-ui/editor/viewer').then(m => {
            window.ToastUIViewer = m.default;
        });
    }
    return _viewerPromise;
};

window.getTheme = () =>
    document.documentElement.classList.contains('dark') ? 'dark' : 'light';

// Load the viewer module (once) and return a new ToastUIViewer instance.
async function createViewer(el, content) {
    if (!window.ToastUIViewer) await window.loadToastUIViewer();
    return new window.ToastUIViewer({ el, initialValue: content, theme: window.getTheme() });
}

// Alpine data factory for side-by-side rendered Markdown comparison.
// Initialises two Toast UI Viewer instances, syncs their scroll positions
// proportionally, and supports block-level diff highlighting.
// Usage: x-data="toastCompareViewers({{ Js::from($left) }}, {{ Js::from($right) }})"
window.toastCompareViewers = (leftContent, rightContent) => ({
    _leftViewer: null,
    _rightViewer: null,
    _syncing: false,
    _leftScrollHandler: null,
    _rightScrollHandler: null,
    _leftPane: null,
    _rightPane: null,
    highlightsEnabled: false,
    mounted: false,

    async init() {
        // Capture DOM elements before any await to avoid a race with Livewire
        // morphing the second wire:ignore sibling pane mid-microtask.
        const leftEl  = this.$el.querySelector('[data-toast-viewer-left]');
        const rightEl = this.$el.querySelector('[data-toast-viewer-right]');
        if (!leftEl || !rightEl) {
            console.error('[toastCompareViewers] mount elements not found', { leftEl, rightEl });
            return;
        }
        try {
            this._leftViewer  = await createViewer(leftEl,  leftContent);
            this._rightViewer = await createViewer(rightEl, rightContent);
            this.mounted = true;
            this._setupScrollSync();
        } catch (err) {
            console.error('[toastCompareViewers] init failed', err);
        }
    },

    _setupScrollSync() {
        this._leftPane = this.$el.querySelector('[data-compare-pane-left]');
        this._rightPane = this.$el.querySelector('[data-compare-pane-right]');
        if (!this._leftPane || !this._rightPane) return;

        const sync = (source, target) => {
            if (this._syncing) return;
            this._syncing = true;
            const ratio = source.scrollTop / Math.max(1, source.scrollHeight - source.clientHeight);
            target.scrollTop = ratio * Math.max(1, target.scrollHeight - target.clientHeight);
            requestAnimationFrame(() => { this._syncing = false; });
        };

        this._leftScrollHandler = () => sync(this._leftPane, this._rightPane);
        this._rightScrollHandler = () => sync(this._rightPane, this._leftPane);
        this._leftPane.addEventListener('scroll', this._leftScrollHandler, { passive: true });
        this._rightPane.addEventListener('scroll', this._rightScrollHandler, { passive: true });
    },

    toggleHighlights() {
        this.highlightsEnabled = !this.highlightsEnabled;
        if (this.highlightsEnabled) {
            this._applyHighlights();
        } else {
            this._clearHighlights();
        }
    },

    // Collect meaningful, non-nested block elements from a pane.
    _getBlocks(pane) {
        const selector = 'h1, h2, h3, h4, h5, h6, p, li, blockquote, pre, tr';
        return Array.from(pane.querySelectorAll(selector)).filter(el => {
            if (!el.textContent.trim()) return false;
            // Exclude elements whose nearest block ancestor is also a block
            // element — e.g. <p> inside <blockquote>, <p> inside <li>.
            return !el.parentElement?.closest('p, li, blockquote, pre');
        });
    },

    _buildFreqMap(blocks) {
        const map = new Map();
        for (const [, t] of blocks) map.set(t, (map.get(t) || 0) + 1);
        return map;
    },

    _highlightSurplus(blocks, freqMap, oppositeFreqMap, className) {
        const used = new Map();
        for (const [el, t] of blocks) {
            const surplus = Math.max(0, (freqMap.get(t) || 0) - (oppositeFreqMap.get(t) || 0));
            const n = used.get(t) || 0;
            if (n < surplus) {
                // border-left/padding/margin don't apply to <tr>; highlight cells instead
                const targets = el.tagName === 'TR'
                    ? Array.from(el.querySelectorAll('td, th'))
                    : [el];
                for (const target of targets) target.classList.add(className);
                used.set(t, n + 1);
            }
        }
    },

    _applyHighlights() {
        if (!this._leftPane || !this._rightPane) return;

        const normalize = el => el.textContent.trim().replace(/\s+/g, ' ');
        const tag       = blocks => blocks.map(el => [el, normalize(el)]);

        const leftBlocks  = tag(this._getBlocks(this._leftPane));
        const rightBlocks = tag(this._getBlocks(this._rightPane));
        const leftFreq    = this._buildFreqMap(leftBlocks);
        const rightFreq   = this._buildFreqMap(rightBlocks);

        this._highlightSurplus(leftBlocks,  leftFreq,  rightFreq, 'ares-diff-deleted');
        this._highlightSurplus(rightBlocks, rightFreq, leftFreq,  'ares-diff-added');
    },

    _clearHighlights() {
        for (const pane of [this._leftPane, this._rightPane]) {
            if (!pane) continue;
            for (const el of pane.querySelectorAll('.ares-diff-deleted, .ares-diff-added')) {
                el.classList.remove('ares-diff-deleted', 'ares-diff-added');
            }
        }
    },

    destroy() {
        if (this._leftPane  && this._leftScrollHandler)  this._leftPane.removeEventListener('scroll',  this._leftScrollHandler);
        if (this._rightPane && this._rightScrollHandler) this._rightPane.removeEventListener('scroll', this._rightScrollHandler);
        this._leftScrollHandler  = null;
        this._rightScrollHandler = null;
        if (this._leftViewer)  { this._leftViewer.destroy();  this._leftViewer  = null; }
        if (this._rightViewer) { this._rightViewer.destroy(); this._rightViewer = null; }
    },
});

// Shared base for single-pane read-only Toast UI Viewer Alpine components.
// trackMounted: true causes `mounted` to flip after the viewer is ready —
// used by callers whose template hides a server-rendered fallback until then.
// _unwatchContent: blade callers may set this to the handle returned by
// $wire.watch(...) so destroy() can unsubscribe and prevent stale callbacks.
function makeSingleViewerBase(initialContent, { trackMounted = false } = {}) {
    return {
        _viewer: null,
        _last: initialContent || '',
        _pending: null,       // latest content buffered before the viewer finished mounting
        _unwatchContent: null,
        mounted: false,

        async init() {
            const mount = this.$el.querySelector('[data-toast-viewer]');
            if (!mount) { console.error('[toastViewer] mount element not found'); return; }
            try {
                this._viewer = await createViewer(mount, initialContent || '');
                if (trackMounted) this.mounted = true;
                // Flush any content that arrived while the viewer was still loading
                // (e.g. $wire.watch firing immediately after a file-upload conversion).
                if (this._pending !== null) {
                    const queued = this._pending;
                    this._pending = null;
                    this.update(queued);
                }
            } catch (err) {
                console.error('[toastViewer] init failed', err);
            }
        },

        // Guards against no-op re-renders: both the markdown-input event and the
        // $wire.watch roundtrip fire for the same keystroke; skip if unchanged.
        // If the viewer isn't ready yet, buffer the latest value for init() to flush.
        update(markdown) {
            const val = markdown ?? '';
            if (!this._viewer) {
                if (val !== this._last) this._pending = val;
                return;
            }
            if (val === this._last) return;
            this._last = val;
            this._viewer.setMarkdown(val);
        },

        destroy() {
            if (this._unwatchContent) { this._unwatchContent(); this._unwatchContent = null; }
            if (this._viewer) { this._viewer.destroy(); this._viewer = null; }
        },
    };
}

// Alpine data factory for a live-updating read-only Toast UI Viewer.
// Exposes update(markdown) to push new content without re-mounting the viewer.
// Usage: x-data="toastLiveViewer({{ Js::from($initial) }})"
window.toastLiveViewer = (initialContent) => makeSingleViewerBase(initialContent);

// Alpine data factory shared by all read-only Toast UI Viewer instances.
// Usage: x-data="toastViewer({{ Js::from($content) }})"
window.toastViewer = (content) => makeSingleViewerBase(content, { trackMounted: true });
