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
