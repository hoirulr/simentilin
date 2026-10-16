import { createPopper } from '@popperjs/core';
import './bootstrap';
import ApexCharts from 'apexcharts';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

// Expose globals for TailAdmin components
window.createPopper = createPopper;
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;

// Note: Livewire 4 bundles Alpine.js automatically. DO NOT call Alpine.start().

// Initialize interactive TailAdmin chart widgets if element exists
function initTailAdminCharts() {
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne?.());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo?.());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree?.());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix?.());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight?.());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen?.());
    }
}

document.addEventListener('DOMContentLoaded', initTailAdminCharts);
document.addEventListener('livewire:navigated', initTailAdminCharts);
