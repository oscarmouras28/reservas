// Cuenta regresiva de la hora retenida. Al llegar a cero recarga la página,
// y el servidor muestra que la retención venció.
export default (seconds) => ({
    left: seconds,

    init() {
        const end = Date.now() + this.left * 1000;
        const timer = setInterval(() => {
            this.left = Math.max(0, Math.round((end - Date.now()) / 1000));
            if (this.left === 0) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
    },

    get clock() {
        return `${Math.floor(this.left / 60)}:${String(this.left % 60).padStart(2, '0')}`;
    },
});
