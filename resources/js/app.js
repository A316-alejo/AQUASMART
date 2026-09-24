import './bootstrap';
import './charts';

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-nav-toggle]')) {
        document.body.classList.toggle('nav-open');
    } else if (event.target.closest('[data-nav-close]')) {
        document.body.classList.remove('nav-open');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.body.classList.remove('nav-open');
    }
});
