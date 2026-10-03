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
	const wheelElement = document.querySelector('[data-natal-wheel]');
	const createNatalImage = async () => {
		const svg = wheelElement?.querySelector('svg');
		if (!svg) return '';
		const copy = svg.cloneNode(true);
		// Keep stored wheel images canonical; screen enlargement must not accumulate in later PDFs.
		copy.setAttribute('viewBox', '0 0 760 760');
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
	return wheelElement?.__withNatalWheel ? wheelElement.__withNatalWheel(createNatalImage) : createNatalImage();
};

document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('[data-wheel-panel]').forEach((panel) => {
		const wheelElement = panel.querySelector('[data-natal-wheel]');
		const chartData = JSON.parse(wheelElement.dataset.chart);
		const modeSelect = panel.querySelector('[data-secondary-mode]');
		const transitControls = panel.querySelector('[data-transit-controls]');
		const synastryControls = panel.querySelector('[data-synastry-controls]');
		const transitDateTime = panel.querySelector('[data-transit-date-time]');
		const transitButton = panel.querySelector('[data-calculate-transits]');
		const synastryChart = panel.querySelector('[data-synastry-chart]');
		const aspectOrb = panel.querySelector('[data-aspect-orb]');
		const aspectTypes = [...panel.querySelectorAll('[data-aspect-type]')];
		const status = panel.querySelector('[data-wheel-status]');
		let secondaryPlanets = {};

		const renderWheel = () => {
			wheelElement.replaceChildren();
			const orb = Number(aspectOrb.value);
			const configuredAspects = Object.fromEntries(aspectTypes.map((input) => [input.value, {
				enabled: input.checked,
				orb,
			}]));
			const hasAspects = aspectTypes.some((input) => input.checked);
			const mode = modeSelect.value;
			const houseSystems = {
				placidus: 'Placidus',
				koch: 'Koch',
				equal: 'Equal',
				whole_sign: 'Whole Sign',
				regiomontanus: 'Regiomontanus',
				campanus: 'Campanus',
				porphyry: 'Porphyry',
				morinus: 'Morinus',
				topocentric: 'Topocentric',
			};
			const chart = new WheelChart({
				container: wheelElement,
				planets: chartData.planets,
				secondaryPlanets,
				houses: chartData.houses,
				config: {
					astronomicalData: {
						ascendant: null,
						mc: null,
						latitude: chartData.latitude,
						houseSystem: houseSystems[chartData.houseSystem] || 'Placidus',
					},
					primaryAspectSettings: { enabled: hasAspects, orb, types: configuredAspects },
					secondaryAspectSettings: { enabled: mode !== 'none' && hasAspects, orb, types: configuredAspects },
					synastryAspectSettings: { enabled: mode !== 'none' && Object.keys(secondaryPlanets).length > 0 && hasAspects, orb, types: configuredAspects },
					svg: {
						width: 760,
						height: 760,
						// A centred viewport of 760 / 1.2 enlarges the drawing without changing its geometry.
						viewBox: '63.333333 63.333333 633.333334 633.333334',
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

		const selectSynastryChart = () => {
			const option = synastryChart.selectedOptions[0];
			secondaryPlanets = option?.dataset.planets ? JSON.parse(option.dataset.planets) : {};
			status.textContent = '';
			renderWheel();
		};
		wheelElement.__withNatalWheel = async (createImage) => {
			if (modeSelect.value === 'none') {
				return createImage();
			}

			const previousMode = modeSelect.value;
			const previousSecondaryPlanets = secondaryPlanets;
			modeSelect.value = 'none';
			secondaryPlanets = {};
			renderWheel();
			try {
				return await createImage();
			} finally {
				modeSelect.value = previousMode;
				secondaryPlanets = previousSecondaryPlanets;
				renderWheel();
			}
		};

		modeSelect.addEventListener('change', () => {
			transitControls.hidden = modeSelect.value !== 'transits';
			synastryControls.hidden = modeSelect.value !== 'synastry';
			secondaryPlanets = {};
			status.textContent = '';
			if (modeSelect.value === 'synastry' && synastryChart.value) {
				selectSynastryChart();
			} else {
				renderWheel();
			}
		});
		synastryChart.addEventListener('change', selectSynastryChart);
		[...aspectTypes, aspectOrb].forEach((input) => input.addEventListener('change', renderWheel));
		const calculateTransits = async () => {
			transitButton.disabled = true;
			status.textContent = 'Calculando tránsitos…';
			try {
				const response = await fetch(panel.dataset.transitUrl, {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
					},
					body: JSON.stringify({ date_time: transitDateTime.value }),
				});
				const result = await response.json();
				if (!response.ok) {
					throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'No se pudieron calcular los tránsitos.');
				}
				secondaryPlanets = result.planets;
				status.textContent = 'Tránsitos calculados para la fecha y hora indicadas.';
				renderWheel();
			} catch (error) {
				status.textContent = error.message || 'No se pudieron calcular los tránsitos.';
			} finally {
				transitButton.disabled = false;
			}
		};
		transitButton.addEventListener('click', calculateTransits);
		panel.querySelector('[data-refresh-natal-wheel]')?.addEventListener('click', renderWheel);
		transitControls.hidden = modeSelect.value !== 'transits';
		synastryControls.hidden = modeSelect.value !== 'synastry';
		renderWheel();
		if (modeSelect.value === 'transits') calculateTransits();
	});
});
