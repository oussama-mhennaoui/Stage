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

import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
