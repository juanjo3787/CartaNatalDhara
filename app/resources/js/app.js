import { WheelChart } from '@eaprelsky/nocturna-wheel';
import '@eaprelsky/nocturna-wheel/css/nocturna-wheel.css';

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
