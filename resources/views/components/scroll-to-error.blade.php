<div x-data
    x-on:scroll-to-error.window="
        setTimeout(() => {
            const el = Array.from(document.querySelectorAll('[data-flux-error]')).find(e => e.textContent.trim())
                || document.querySelector('[data-invalid]');
            if (!el) return;

            const sticky = el.closest('.sticky');
            if (!sticky) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            const rect = el.getBoundingClientRect();
            const inView = rect.top >= 0 && rect.bottom <= window.innerHeight;

            if (!inView) {
                const offset = rect.top - sticky.getBoundingClientRect().top;
                const cell = sticky.parentElement;
                const cellBottom = cell.getBoundingClientRect().bottom + window.scrollY;
                const target = cellBottom - sticky.scrollHeight + offset - (window.innerHeight / 2);
                window.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
            }
        }, 50)
    "
    class="hidden"></div>
