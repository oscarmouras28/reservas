// Calendario de reserva: elige día y hora de un psicólogo.
// Las fechas son "YYYY-MM-DD" en la zona horaria del profesional; las horas
// llegan ya formateadas desde el servidor (HH:MM, hora de Chile).
export default (config) => ({
    professional: config.professional,
    services: config.services,
    urls: config.urls,
    timezone: config.timezone,
    today: config.today,
    maxMonth: config.maxMonth,

    serviceId: config.services[0].id,
    month: config.today.slice(0, 7),
    availableDays: [],
    selectedDate: null,
    slots: [],
    selectedSlot: null,
    loadingDays: false,
    loadingSlots: false,
    error: null,

    async init() {
        await this.loadDays();
    },

    get service() {
        return this.services.find((s) => s.id === Number(this.serviceId));
    },

    get monthLabel() {
        const [y, m] = this.month.split('-').map(Number);
        const text = new Intl.DateTimeFormat('es-CL', { month: 'long', year: 'numeric', timeZone: 'UTC' })
            .format(new Date(Date.UTC(y, m - 1, 1)));
        return text.charAt(0).toUpperCase() + text.slice(1);
    },

    get canGoBack() {
        return this.month > this.today.slice(0, 7);
    },

    get canGoForward() {
        return this.month < this.maxMonth;
    },

    // Semana de lunes a domingo; null = casilla vacía antes del día 1.
    get cells() {
        const [y, m] = this.month.split('-').map(Number);
        const offset = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() + 6) % 7;
        const daysInMonth = new Date(Date.UTC(y, m, 0)).getUTCDate();
        const cells = Array(offset).fill(null);
        for (let d = 1; d <= daysInMonth; d++) {
            const iso = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            cells.push({ day: d, iso, available: this.availableDays.includes(iso) });
        }
        return cells;
    },

    get selectedDateLabel() {
        if (!this.selectedDate) return '';
        const [y, m, d] = this.selectedDate.split('-').map(Number);
        const text = new Intl.DateTimeFormat('es-CL', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' })
            .format(new Date(Date.UTC(y, m - 1, d)));
        return text.charAt(0).toUpperCase() + text.slice(1);
    },

    async request(url, params) {
        const query = new URLSearchParams(params).toString();
        const response = await fetch(`${url}?${query}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    },

    async loadDays() {
        this.loadingDays = true;
        this.error = null;
        try {
            const data = await this.request(this.urls.days, { service: this.serviceId, month: this.month });
            this.availableDays = data.days;
        } catch (e) {
            this.availableDays = [];
            this.error = 'No pudimos cargar la disponibilidad. Intenta de nuevo en un momento.';
        } finally {
            this.loadingDays = false;
        }
    },

    async changeMonth(delta) {
        const [y, m] = this.month.split('-').map(Number);
        const next = new Date(Date.UTC(y, m - 1 + delta, 1));
        this.month = `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = null;
        this.slots = [];
        this.selectedSlot = null;
        await this.loadDays();
    },

    async changeService() {
        this.selectedDate = null;
        this.slots = [];
        this.selectedSlot = null;
        await this.loadDays();
    },

    async pickDay(cell) {
        if (!cell || !cell.available) return;
        this.selectedDate = cell.iso;
        this.selectedSlot = null;
        this.loadingSlots = true;
        this.error = null;
        try {
            const data = await this.request(this.urls.slots, { service: this.serviceId, date: cell.iso });
            this.slots = data.slots;
        } catch (e) {
            this.slots = [];
            this.error = 'No pudimos cargar las horas de ese día. Intenta de nuevo.';
        } finally {
            this.loadingSlots = false;
        }
    },

    pickSlot(slot) {
        this.selectedSlot = slot;
    },
});
