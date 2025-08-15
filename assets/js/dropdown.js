// Use event delegation for better performance and Turbo compatibility
document.addEventListener('click', function(e) {
    // Handle dropdown toggle
    const button = e.target.closest('#user-menu-button');
    if (button) {
        e.preventDefault();
        e.stopPropagation();
        const dropdown = document.getElementById('user-dropdown');
        if (dropdown) {
            const isHidden = dropdown.classList.contains('hidden');
            closeAllDropdowns();
            if (isHidden) {
                dropdown.classList.remove('hidden');
            }
        }
        return;
    }

    // Handle clicks outside dropdown to close it
    if (!e.target.closest('#user-menu')) {
        closeAllDropdowns();
    }
});

// Close dropdowns when pressing Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAllDropdowns();
    }
});

// Close all dropdowns
function closeAllDropdowns() {
    const dropdowns = document.querySelectorAll('.absolute[class*="w-48"]');
    dropdowns.forEach(d => d.classList.add('hidden'));
}

// Initialize any dropdowns when Turbo navigates
if (typeof Turbo !== 'undefined') {
    document.addEventListener('turbo:load', closeAllDropdowns);
    document.addEventListener('turbo:render', closeAllDropdowns);
    document.addEventListener('turbo:frame-render', closeAllDropdowns);
}
