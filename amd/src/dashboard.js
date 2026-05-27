/**
 * Academic Panel dashboard interactivity.
 *
 * @module     local_academicpanel/dashboard
 */

const formatPercent = (value) => {
    return value.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }) + '%';
};

const updateChart = (chart, series) => {
    chart.querySelectorAll('.local-academicpanel-bar-item').forEach((row) => {
        const value = parseFloat(row.getAttribute('data-' + series) || '0');
        const fill = row.querySelector('.local-academicpanel-bar-fill');
        const label = row.querySelector('.local-academicpanel-bar-value');
        if (fill) {
            fill.style.setProperty('--bar-value', value + '%');
        }
        if (label) {
            label.textContent = formatPercent(value);
        }
    });
};

const bindChart = (chart) => {
    const panel = chart.closest('.local-academicpanel-chart-panel');
    const buttons = panel ? panel.querySelectorAll('[data-academicpanel-series]') : [];
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const series = button.getAttribute('data-academicpanel-series');
            buttons.forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            updateChart(chart, series);
        });
    });
};

export const init = () => {
    document.querySelectorAll('[data-academicpanel-bar-chart]').forEach(bindChart);
};
