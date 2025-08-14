import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'durationField'];
    
    connect() {
        // Log for debugging
        console.log('Offer Type Controller connected');
        
        // Initialize the duration field state when the page loads
        this.updateDurationField();
        
        // Add event listener for change events
        this.selectTarget.addEventListener('change', this.updateDurationField.bind(this));
    }
    
    updateDurationField() {
        console.log('updateDurationField called');
        
        const selectedType = this.selectTarget.value;
        const durationField = this.durationFieldTarget;
        
        console.log('Selected type:', selectedType);
        
        if (selectedType === 'emplois') {
            console.log('Disabling duration field');
            // For 'emploi' type, disable and set value to 0
            durationField.disabled = true;
            durationField.value = '0';
            durationField.classList.add('bg-gray-100', 'cursor-not-allowed');
        } else {
            console.log('Enabling duration field');
            // For other types, enable and set default value if empty
            durationField.disabled = false;
            durationField.classList.remove('bg-gray-100', 'cursor-not-allowed');
            
            if (!durationField.value || durationField.value === '0') {
                durationField.value = '3'; // Default to 3 months for stages
            }
        }
    }
}
