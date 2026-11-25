import './bootstrap';
import Alpine from 'alpinejs';
import bookingCalendar from './booking';

window.Alpine = Alpine;
Alpine.data('bookingCalendar', bookingCalendar);
Alpine.start();
