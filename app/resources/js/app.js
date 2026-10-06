import { WheelChart } from '@eaprelsky/nocturna-wheel';
import '@eaprelsky/nocturna-wheel/css/nocturna-wheel.css';
import { renderAspectMatrix } from './aspect-matrix.js';
import { initializeDateTimeInputs } from './date-time-input';

const zodiacSigns = [
	{ key: 'aries', glyph: '♈', element: 'fire' },
	{ key: 'taurus', glyph: '♉', element: 'earth' },
	{ key: 'gemini', glyph: '♊', element: 'air' },
	{ key: 'cancer', glyph: '♋', element: 'water' },
	{ key: 'leo', glyph: '♌', element: 'fire' },
	{ key: 'virgo', glyph: '♍', element: 'earth' },
	{ key: 'libra', glyph: '♎', element: 'air' },
	{ key: 'scorpio', glyph: '♏', element: 'water' },
	{ key: 'sagittarius', glyph: '♐', element: 'fire' },
	{ key: 'capricorn', glyph: '♑', element: 'earth' },
	{ key: 'aquarius', glyph: '♒', element: 'air' },
	{ key: 'pisces', glyph: '♓', element: 'water' },
];
const referenceWheelRadii = {
	zodiacInner: 170,
	zodiacMiddle: 260,
	zodiacOuter: 320,
	aspectInner: 18,
	aspectOuter: 160,
};
const wheelHouseSystems = {
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

function baseWheelConfiguration(chartData) {
	return {
		radius: referenceWheelRadii,
		houseSettings: { lineColor: '#252525', textColor: '#202020', fontSize: 11 },
		astronomicalData: {
			ascendant: null,
			mc: null,
			latitude: chartData.latitude,
			houseSystem: wheelHouseSystems[chartData.houseSystem] || 'Placidus',
		},
		svg: {
			width: 760,
			height: 760,
			viewBox: '0 0 760 760',
			center: { x: 380, y: 380 },
		},
		theme: {
			backgroundColor: chartData.backgroundColor || '#fffdf9',
			textColor: '#374151',
			lineColor: '#9ca3af',
			lightLineColor: '#d1d5db',
			fontFamily: 'Aptos, Segoe UI, sans-serif',
		},
	};
}

function wheelPointAt(center, radius, longitude) {
	const radians = (longitude - 90) * Math.PI / 180;
	return {
		x: center.x + radius * Math.cos(radians),
		y: center.y + radius * Math.sin(radians),
	};
}

function addWheelDegreeTicks(svg, center, radii) {
	const group = svg.querySelector('.svg-group-zodiac');
	if (!group) return;

	const namespace = 'http://www.w3.org/2000/svg';
	const ticks = document.createElementNS(namespace, 'g');
	ticks.setAttribute('class', 'reference-degree-ticks');
	ticks.setAttribute('aria-hidden', 'true');
	for (let degree = 0; degree < 360; degree++) {
		const major = degree % 10 === 0;
		const medium = degree % 5 === 0;
		const startRadius = radii.zodiacMiddle - (major ? 8 : medium ? 5 : 2);
		const start = wheelPointAt(center, startRadius, degree);
		const end = wheelPointAt(center, radii.zodiacMiddle + 2, degree);
		const tick = document.createElementNS(namespace, 'line');
		tick.setAttribute('x1', start.x);
		tick.setAttribute('y1', start.y);
		tick.setAttribute('x2', end.x);
		tick.setAttribute('y2', end.y);
		tick.setAttribute('class', `reference-degree-tick${major ? ' major' : medium ? ' medium' : ''}`);
		ticks.append(tick);
	}
	group.append(ticks);
}

function addPlanetDegreeLabels(svg, planetSets) {
	const namespace = 'http://www.w3.org/2000/svg';
	for (const [planetCircle, planetSet] of planetSets) {
		Object.entries(planetSet).forEach(([planetName, planet]) => {
			const group = svg.querySelector(`.planet-element.planet-${CSS.escape(planetName)}.planet-${planetCircle}`);
			const icon = group?.querySelector('.planet-icon');
			if (!icon || !Number.isFinite(planet.lon)) return;

			const longitude = ((planet.lon % 360) + 360) % 360;
			const signIndex = Math.floor(longitude / 30);
			const withinSign = longitude - signIndex * 30;
			const degrees = Math.floor(withinSign);
			const minutes = Math.floor((withinSign - degrees) * 60);
			const sign = zodiacSigns[signIndex];
			const iconX = Number(icon.getAttribute('x'));
			const iconY = Number(icon.getAttribute('y'));
			const iconWidth = Number(icon.getAttribute('width'));
			const iconHeight = Number(icon.getAttribute('height'));
			const centerX = iconX + iconWidth / 2;
			const labelY = iconY + iconHeight + 8;
			const degreeLabel = document.createElementNS(namespace, 'text');
			degreeLabel.setAttribute('x', centerX - 7);
			degreeLabel.setAttribute('y', labelY);
			degreeLabel.setAttribute('text-anchor', 'end');
			degreeLabel.setAttribute('class', 'reference-planet-degree');
			degreeLabel.textContent = `${String(degrees).padStart(2, '0')}°`;
			const signIcon = document.createElementNS(namespace, 'image');
			signIcon.setAttribute('x', centerX - 5);
			signIcon.setAttribute('y', labelY - 7);
			signIcon.setAttribute('width', '10');
			signIcon.setAttribute('height', '10');
			signIcon.setAttribute('href', svg.querySelector(`.zodiac-sign-${sign.key}`)?.getAttribute('href') || '');
			signIcon.setAttribute('class', `reference-position-sign ${sign.element}`);
			const minuteLabel = document.createElementNS(namespace, 'text');
			minuteLabel.setAttribute('x', centerX + 7);
			minuteLabel.setAttribute('y', labelY);
			minuteLabel.setAttribute('text-anchor', 'start');
			minuteLabel.setAttribute('class', 'reference-planet-degree');
			minuteLabel.textContent = `${String(minutes).padStart(2, '0')}'`;
			group.append(degreeLabel, signIcon, minuteLabel);
		});
	}
}

function orientWheelLikeReference(svg, center, ascendant, houseLabelRadius) {
	const rotation = (270 + ascendant) % 360;
	const radians = rotation * Math.PI / 180;
	const cosine = Math.cos(radians);
	const sine = Math.sin(radians);
	const transformPoint = (x, y) => {
		const mirroredX = 2 * center.x - x;
		const offsetX = mirroredX - center.x;
		const offsetY = y - center.y;
		return {
			x: center.x + offsetX * cosine - offsetY * sine,
			y: center.y + offsetX * sine + offsetY * cosine,
		};
	};

	svg.querySelectorAll('line').forEach((line) => {
		const start = transformPoint(Number(line.getAttribute('x1')), Number(line.getAttribute('y1')));
		const end = transformPoint(Number(line.getAttribute('x2')), Number(line.getAttribute('y2')));
		line.setAttribute('x1', start.x);
		line.setAttribute('y1', start.y);
		line.setAttribute('x2', end.x);
		line.setAttribute('y2', end.y);
	});
	svg.querySelectorAll('circle').forEach((circle) => {
		const point = transformPoint(Number(circle.getAttribute('cx')), Number(circle.getAttribute('cy')));
		circle.setAttribute('cx', point.x);
		circle.setAttribute('cy', point.y);
	});
	svg.querySelectorAll('image').forEach((image) => {
		const width = Number(image.getAttribute('width'));
		const height = Number(image.getAttribute('height'));
		const point = transformPoint(Number(image.getAttribute('x')) + width / 2, Number(image.getAttribute('y')) + height / 2);
		image.setAttribute('x', point.x - width / 2);
		image.setAttribute('y', point.y - height / 2);
	});
	svg.querySelectorAll('text').forEach((text) => {
		const x = Number(text.getAttribute('x'));
		const y = Number(text.getAttribute('y'));
		if (!Number.isFinite(x) || !Number.isFinite(y)) return;
		let point = transformPoint(x, y);
		if (text.classList.contains('house-number')) {
			const offsetX = point.x - center.x;
			const offsetY = point.y - center.y;
			const currentRadius = Math.hypot(offsetX, offsetY);
			if (currentRadius > 0) {
				point = {
					x: center.x + offsetX * houseLabelRadius / currentRadius,
					y: center.y + offsetY * houseLabelRadius / currentRadius,
				};
			}
		}
		text.setAttribute('x', point.x);
		text.setAttribute('y', point.y);
	});
}

function styleNatalWheel(wheelElement, chartData, secondaryPlanets, radii) {
	const svg = wheelElement.querySelector('svg');
	if (!svg) return;
	const center = { x: 380, y: 380 };
	const enlargeAroundCenter = (image, size) => {
		const oldWidth = Number(image.getAttribute('width'));
		const oldHeight = Number(image.getAttribute('height'));
		const x = Number(image.getAttribute('x'));
		const y = Number(image.getAttribute('y'));
		image.setAttribute('x', x - (size - oldWidth) / 2);
		image.setAttribute('y', y - (size - oldHeight) / 2);
		image.setAttribute('width', size);
		image.setAttribute('height', size);
	};
	svg.querySelectorAll('.zodiac-sign').forEach((image) => enlargeAroundCenter(image, 35));
	svg.querySelectorAll('.planet-icon').forEach((image) => {
		enlargeAroundCenter(image, image.classList.contains('planet-secondary-icon') ? 21 : 28);
	});
	addWheelDegreeTicks(svg, center, radii);
	addPlanetDegreeLabels(svg, [['primary', chartData.planets], ['secondary', secondaryPlanets]]);
	orientWheelLikeReference(svg, center, chartData.ascendant, radii.zodiacInner + 18);
}

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
		const properties = ['fill', 'stroke', 'stroke-width', 'stroke-dasharray', 'stroke-linecap', 'stroke-linejoin', 'stroke-opacity', 'opacity', 'filter', 'font-family', 'font-size', 'font-weight', 'text-anchor'];
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
			context.fillStyle = wheelElement?.dataset.backgroundColor || '#fffdf9';
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
		if (!modeSelect) {
			const chart = new WheelChart({
				container: wheelElement,
				planets: chartData.planets,
				houses: chartData.houses,
				config: {
					...baseWheelConfiguration(chartData),
					primaryAspectSettings: { enabled: true, orb: 6 },
				},
			});
			chart.render();
			styleNatalWheel(wheelElement, chartData, {}, referenceWheelRadii);
			const coverMatrix = panel.querySelector('[data-aspect-matrix]');
			if (coverMatrix) renderAspectMatrix(coverMatrix, ['conjunction', 'opposition', 'trine', 'square', 'sextile'], 6);
			return;
		}

		const transitControls = panel.querySelector('[data-transit-controls]');
		const synastryControls = panel.querySelector('[data-synastry-controls]');
		const transitDateTime = panel.querySelector('[data-transit-date-time]');
		const transitButton = panel.querySelector('[data-calculate-transits]');
		const synastryChart = panel.querySelector('[data-synastry-chart]');
		const aspectOrb = panel.querySelector('[data-aspect-orb]');
		const aspectTypes = [...panel.querySelectorAll('[data-aspect-type]')];
		const aspectMatrix = panel.querySelector('[data-aspect-matrix]');
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
			const chart = new WheelChart({
				container: wheelElement,
				planets: chartData.planets,
				secondaryPlanets,
				houses: chartData.houses,
				config: {
					...baseWheelConfiguration(chartData),
					primaryAspectSettings: { enabled: hasAspects, orb, types: configuredAspects },
					secondaryAspectSettings: { enabled: mode !== 'none' && hasAspects, orb, types: configuredAspects },
					synastryAspectSettings: { enabled: mode !== 'none' && Object.keys(secondaryPlanets).length > 0 && hasAspects, orb, types: configuredAspects },
					svg: {
						width: 760,
						height: 760,
						viewBox: '0 0 760 760',
						center: { x: 380, y: 380 },
					},
					theme: {
						backgroundColor: chartData.backgroundColor || '#fffdf9',
						textColor: '#374151',
						lineColor: '#9ca3af',
						lightLineColor: '#d1d5db',
						fontFamily: 'Aptos, Segoe UI, sans-serif',
					},
				},
			});

			chart.render();
			styleNatalWheel(wheelElement, chartData, secondaryPlanets, referenceWheelRadii);
			if (aspectMatrix) {
				renderAspectMatrix(aspectMatrix, aspectTypes.filter((input) => input.checked).map((input) => input.value), orb);
			}
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
