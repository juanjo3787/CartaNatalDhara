import { WheelChart } from '@eaprelsky/nocturna-wheel';
import '@eaprelsky/nocturna-wheel/css/nocturna-wheel.css';
import { initializeDateTimeInputs } from './date-time-input';

document.addEventListener('DOMContentLoaded', () => initializeDateTimeInputs());

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.card table').forEach(table => {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-scroll';
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', 'Tabla desplazable');
        table.before(wrapper);
        wrapper.append(table);
    });
});

window.getNatalWheelImage = async () => {
	const svg = document.querySelector('[data-natal-wheel] svg');
	if (!svg) return '';
	const copy = svg.cloneNode(true);
	const sourceNodes = [svg, ...svg.querySelectorAll('*')];
	const copiedNodes = [copy, ...copy.querySelectorAll('*')];
	const properties = ['fill', 'stroke', 'stroke-width', 'stroke-dasharray', 'stroke-linecap', 'stroke-linejoin', 'opacity', 'font-family', 'font-size', 'font-weight', 'text-anchor'];
	sourceNodes.forEach((source, index) => {
		const computed = window.getComputedStyle(source);
		const target = copiedNodes[index];
		properties.forEach((property) => {
			const value = computed.getPropertyValue(property);
			if (value) target.style.setProperty(property, value);
		});
	});
	copy.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
	const source = new XMLSerializer().serializeToString(copy);
	const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
	const url = URL.createObjectURL(blob);
	try {
		const image = await new Promise((resolve, reject) => {
			const element = new Image();
			element.onload = () => resolve(element);
			element.onerror = reject;
			element.src = url;
		});
		const canvas = document.createElement('canvas');
		canvas.width = 1200;
		canvas.height = 1200;
		const context = canvas.getContext('2d');
		context.fillStyle = '#ffffff';
		context.fillRect(0, 0, canvas.width, canvas.height);
		context.drawImage(image, 0, 0, canvas.width, canvas.height);
		return canvas.toDataURL('image/jpeg', 0.96);
	} finally {
		URL.revokeObjectURL(url);
	}
};

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('[data-natal-wheel]').forEach((wheelElement) => {
		const chartData = JSON.parse(wheelElement.dataset.chart);
		const renderWheel = () => {
			wheelElement.replaceChildren();
			const chart = new WheelChart({
				container: wheelElement,
				planets: chartData.planets,
				houses: chartData.houses,
				config: {
					astronomicalData: {
						ascendant: chartData.ascendant,
						mc: chartData.midheaven,
						latitude: chartData.latitude,
						houseSystem: 'Placidus',
					},
					svg: {
						width: 760,
						height: 760,
						viewBox: '0 0 760 760',
						center: { x: 380, y: 380 },
					},
					theme: {
						backgroundColor: '#ffffff',
						textColor: '#374151',
						lineColor: '#9ca3af',
						lightLineColor: '#d1d5db',
						fontFamily: 'Aptos, Segoe UI, sans-serif',
					},
				},
			});

			chart.render();
		};

		renderWheel();
		wheelElement.closest('[data-wheel-panel]')?.querySelector('[data-refresh-natal-wheel]')?.addEventListener('click', renderWheel);
	});
});
