function initializeRegistrationForm() {
    const form = document.querySelector('form[name="registration_form"]');
    if (!form) return;

    const userTypeRadios = form.querySelectorAll('input[name="registration_form[userType]"]');
    
    function showCorrectFields() {
        // Find the currently selected user type
        const selectedType = form.querySelector('input[name="registration_form[userType]"]:checked')?.value;
        
        console.log('Selected type:', selectedType);

        // Hide all conditional sections first
        form.querySelectorAll('.user-type-fields').forEach(field => {
            field.style.display = 'none';
        });

        // If a type is selected, show the matching sections
        if (selectedType) {
            console.log('Showing fields for:', selectedType);
            form.querySelectorAll('.user-type-' + selectedType).forEach(field => {
                field.style.display = 'block';
            });
        }
    }

    // When a radio button is changed, update the form
    userTypeRadios.forEach(radio => {
        radio.addEventListener('change', showCorrectFields);
    });

    // Initial check
    showCorrectFields();
    
    // Also check after a short delay
    setTimeout(showCorrectFields, 50);
}

// Run initialization when DOM is fully loaded
document.addEventListener('DOMContentLoaded', initializeRegistrationForm);

// Also run on window load in case DOMContentLoaded already fired
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRegistrationForm);
} else {
    initializeRegistrationForm();
}
