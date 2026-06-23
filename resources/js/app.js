import flatpickr from 'flatpickr';
window.flatpickr = flatpickr;

import './../../vendor/power-components/livewire-powergrid/dist/powergrid';
import 'trix';
import './quill-editor';
import './axios';

// Drag-and-drop sorting for the FAQ page builder (sections & questions).
// Registered against Livewire's bundled Alpine before it boots.
import sort from '@alpinejs/sort';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(sort);
});
