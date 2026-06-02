// mb Kommunikation + Events – Main JavaScript

document.addEventListener('DOMContentLoaded', () => {
    initNavScroll();
    initScrollAnimations();
    initCounters();
});

// Sticky header with backdrop blur on scroll
function initNavScroll() {
    const nav = document.getElementById('main-nav');
    if (!nav) return;
    let lastScroll = 0;
    window.addEventListener('scroll', () => {
        const currentScroll = window.scrollY;
        if (currentScroll > 50) {
            nav.classList.add('scrolled', 'shadow-md');
            nav.classList.remove('transparent');
        } else {
            nav.classList.remove('scrolled', 'shadow-md');
            nav.classList.add('transparent');
        }
        lastScroll = currentScroll;
    }, { passive: true });
}

// Intersection Observer for scroll animations
function initScrollAnimations() {
    const elements = document.querySelectorAll('[data-animate]');
    if (!elements.length) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    elements.forEach(el => observer.observe(el));
}

// Animated counters for stats section
function initCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    if (!counters.length) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.dataset.counter);
                const suffix = el.dataset.suffix || '';
                animateCounter(el, target, suffix);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });
    counters.forEach(el => observer.observe(el));
}

function animateCounter(el, target, suffix) {
    let current = 0;
    const increment = target / 60;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        el.textContent = Math.floor(current) + suffix;
    }, 25);
}

// Flash message auto-dismiss
const flash = document.querySelector('.flash-message');
if (flash) {
    setTimeout(() => {
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(-10px)';
        setTimeout(() => flash.remove(), 300);
    }, 4000);
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
