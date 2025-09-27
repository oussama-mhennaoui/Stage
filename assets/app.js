import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

// Handle offer type change to disable/enable duration field
const offerTypeSelect = document.querySelector('.offer-type-selector');
const durationField = document.querySelector('.duration-field');

if (offerTypeSelect && durationField) {
    offerTypeSelect.addEventListener('change', function() {
        const isEmploi = this.value === 'emplois';
        durationField.disabled = isEmploi;
        
        if (isEmploi) {
            durationField.value = '0';
        } else if (!durationField.value) {
            durationField.value = '3'; // Default to 3 months for stages
        }
    });

    // Initialize on page load
    if (offerTypeSelect.value === 'emplois') {
        durationField.disabled = true;
        durationField.value = '0';
    }
}

// Handle registration form user type selection
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
    
    // Also check after a short delay to catch any dynamic loading
    setTimeout(showCorrectFields, 50);
}

// Run initialization when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeRegistrationForm();
    
    // Also run on window load in case DOMContentLoaded already fired
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initializeRegistrationForm, 1);
    } else {
        window.addEventListener('load', initializeRegistrationForm);
    }
});

import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
