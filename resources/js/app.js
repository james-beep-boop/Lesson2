import './bootstrap';

window.loadToastUIEditor = () =>
    import('@toast-ui/editor').then(m => {
        window.ToastUIEditor = m.default;
    });

// Alpine data factory shared by all read-only Toast UI Viewer instances.
// Usage: x-data="toastViewer({{ Js::from($content) }})"
window.toastViewer = (content) => ({
    _viewer: null,
    async init() {
        if (!window.ToastUIEditor) {
            await window.loadToastUIEditor();
        }
        this._viewer = window.ToastUIEditor.factory({
            el: this.$el.querySelector('[data-toast-viewer]'),
            initialValue: content,
            viewer: true,
            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        });
    },
    destroy() {
        if (this._viewer) {
            this._viewer.destroy();
            this._viewer = null;
        }
    },
});
