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

// Alpine data factory for side-by-side rendered Markdown comparison.
// Initialises two Toast UI Viewer instances and syncs their scroll positions
// proportionally so both panes track together as the user scrolls.
// Usage: x-data="toastCompareViewers({{ Js::from($left) }}, {{ Js::from($right) }})"
window.toastCompareViewers = (leftContent, rightContent) => ({
    _leftViewer: null,
    _rightViewer: null,
    _syncing: false,
    _leftScrollHandler: null,
    _rightScrollHandler: null,
    _leftPane: null,
    _rightPane: null,

    async init() {
        if (!window.ToastUIViewer) {
            await window.loadToastUIViewer();
        }
        this._leftViewer = new window.ToastUIViewer({
            el: this.$el.querySelector('[data-toast-viewer-left]'),
            initialValue: leftContent,
            theme: window.getTheme(),
        });
        this._rightViewer = new window.ToastUIViewer({
            el: this.$el.querySelector('[data-toast-viewer-right]'),
            initialValue: rightContent,
            theme: window.getTheme(),
        });
        this._setupScrollSync();
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

    destroy() {
        if (this._leftPane && this._leftScrollHandler) this._leftPane.removeEventListener('scroll', this._leftScrollHandler);
        if (this._rightPane && this._rightScrollHandler) this._rightPane.removeEventListener('scroll', this._rightScrollHandler);
        if (this._leftViewer) { this._leftViewer.destroy(); this._leftViewer = null; }
        if (this._rightViewer) { this._rightViewer.destroy(); this._rightViewer = null; }
    },
});

// Alpine data factory shared by all read-only Toast UI Viewer instances.
// Usage: x-data="toastViewer({{ Js::from($content) }})"
window.toastViewer = (content) => ({
    async init() {
        if (!window.ToastUIViewer) {
            await window.loadToastUIViewer();
        }
        this._viewer = new window.ToastUIViewer({
            el: this.$el.querySelector('[data-toast-viewer]'),
            initialValue: content,
            theme: window.getTheme(),
        });
    },
    destroy() {
        if (this._viewer) {
            this._viewer.destroy();
            this._viewer = null;
        }
    },
});
