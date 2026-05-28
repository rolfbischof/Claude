// Navigation scroll effect
const navHeader = document.getElementById('nav-header');
window.addEventListener('scroll', () => {
  navHeader.classList.toggle('scrolled', window.scrollY > 30);
}, { passive: true });

// Mobile hamburger menu
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('nav-links');

hamburger.addEventListener('click', () => {
  const isOpen = navLinks.classList.toggle('open');
  hamburger.setAttribute('aria-label', isOpen ? 'Menü schliessen' : 'Menü öffnen');
  document.body.style.overflow = isOpen ? 'hidden' : '';
});

navLinks.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => {
    navLinks.classList.remove('open');
    document.body.style.overflow = '';
    hamburger.setAttribute('aria-label', 'Menü öffnen');
  });
});

// Intersection Observer for scroll animations
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      const delay = entry.target.dataset.delay || 0;
      setTimeout(() => {
        entry.target.classList.add('animated');
      }, delay);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

// Stagger delays for grid items
document.querySelectorAll('[data-animate], .advantage, .testimonial').forEach((el, i) => {
  const siblings = el.parentElement.querySelectorAll('[data-animate], .advantage, .testimonial');
  const index = Array.from(siblings).indexOf(el);
  el.style.transitionDelay = `${index * 80}ms`;
  observer.observe(el);
});

// Contact form – AJAX submission to ajax/contact.php
const form = document.getElementById('contact-form');
const formSuccess = document.getElementById('form-success');
const formError   = document.getElementById('form-error');

if (form) {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const orig = btn.textContent;
    btn.textContent = 'Wird gesendet...';
    btn.disabled = true;
    if (formError) formError.hidden = true;

    try {
      const res  = await fetch('/ajax/contact.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      if (data.ok) {
        form.hidden = true;
        if (formSuccess) formSuccess.hidden = false;
      } else {
        if (formError) { formError.textContent = data.msg || 'Fehler beim Senden.'; formError.hidden = false; }
        btn.textContent = orig;
        btn.disabled = false;
      }
    } catch {
      if (formError) { formError.textContent = 'Verbindungsfehler. Bitte versuchen Sie es erneut.'; formError.hidden = false; }
      btn.textContent = orig;
      btn.disabled = false;
    }
  });
}

// Smooth active nav link highlighting
const sections = document.querySelectorAll('section[id]');
const navLinksAll = document.querySelectorAll('.nav-links a[href^="#"]');

const sectionObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const id = entry.target.getAttribute('id');
      navLinksAll.forEach(link => {
        link.classList.toggle('active', link.getAttribute('href') === `#${id}`);
      });
    }
  });
}, { rootMargin: '-40% 0px -55% 0px' });

sections.forEach(s => sectionObserver.observe(s));
