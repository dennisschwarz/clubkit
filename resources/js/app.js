/**
 * ClubKit – Global JS Entry Point
 * Modul-spezifisches JS liegt unter public/js/modules/
 */

// Flash-Messages nach 4 Sekunden ausblenden
document.addEventListener('DOMContentLoaded', () => {
    const flashes = document.querySelectorAll('[data-flash]');
    flashes.forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 4000);
    });
});
