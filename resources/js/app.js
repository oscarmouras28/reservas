import './bootstrap';
import Alpine from 'alpinejs';
import bookingCalendar from './booking';
import holdTimer from './hold';

window.Alpine = Alpine;
Alpine.data('bookingCalendar', bookingCalendar);
Alpine.data('holdTimer', holdTimer);
Alpine.start();
