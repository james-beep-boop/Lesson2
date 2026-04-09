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

window.getTheme = () =>
    document.documentElement.classList.contains('dark') ? 'dark' : 'light';

// Alpine data factory shared by all read-only Toast UI Viewer instances.
// Usage: x-data="toastViewer({{ Js::from($content) }})"
window.toastViewer = (content) => ({
    async init() {
        if (!window.ToastUIEditor) {
            await window.loadToastUIEditor();
        }
        this._viewer = window.ToastUIEditor.factory({
            el: this.$el.querySelector('[data-toast-viewer]'),
            initialValue: content,
            viewer: true,
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
