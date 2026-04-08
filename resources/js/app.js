import './bootstrap';

window.loadToastUIEditor = () =>
    import('@toast-ui/editor').then(m => {
        window.ToastUIEditor = m.default;
    });
