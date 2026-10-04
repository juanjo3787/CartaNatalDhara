export const ASPECT_DEFINITIONS = [
	{ key: 'conjunction', angle: 0, label: 'Conjunción', glyph: '☌' },
	{ key: 'opposition', angle: 180, label: 'Oposición', glyph: '☍' },
	{ key: 'trine', angle: 120, label: 'Trígono', glyph: '△' },
	{ key: 'square', angle: 90, label: 'Cuadratura', glyph: '□' },
	{ key: 'sextile', angle: 60, label: 'Sextil', glyph: '✶' },
];

export function findAspectMatches(firstLongitude, secondLongitude, enabledTypes, maximumOrb) {
	const normalizedDifference = ((firstLongitude - secondLongitude) % 360 + 360) % 360;
	const separation = Math.min(normalizedDifference, 360 - normalizedDifference);

	return ASPECT_DEFINITIONS
		.filter((aspectDefinition) => enabledTypes.includes(aspectDefinition.key))
		.map((aspectDefinition) => ({
			...aspectDefinition,
			orb: Math.abs(separation - aspectDefinition.angle),
		}))
		.filter((aspectDefinition) => aspectDefinition.orb <= maximumOrb)
		.sort((firstAspect, secondAspect) => firstAspect.orb - secondAspect.orb);
}

export function renderAspectMatrix(matrixElement, enabledTypes, maximumOrb) {
	const points = JSON.parse(matrixElement.dataset.points || '[]');
	const pointsByKey = new Map(points.map((point) => [point.key, point]));

	matrixElement.querySelectorAll('[data-aspect-cell]').forEach((cell) => {
		const firstPoint = pointsByKey.get(cell.dataset.first);
		const secondPoint = pointsByKey.get(cell.dataset.second);
		if (!firstPoint || !secondPoint) return;

		const matches = findAspectMatches(firstPoint.longitude, secondPoint.longitude, enabledTypes, maximumOrb);
		cell.replaceChildren();
		cell.title = matches.map((match) => `${firstPoint.label} · ${secondPoint.label}: ${match.label} (orbe ${match.orb.toFixed(1)}°)`).join('\n');
		cell.setAttribute('aria-label', cell.title || `${firstPoint.label} · ${secondPoint.label}: sin aspecto`);

		matches.forEach((match) => {
			const glyph = document.createElement('span');
			glyph.className = `aspect-glyph aspect-glyph--${match.key}`;
			glyph.textContent = match.glyph;
			glyph.setAttribute('aria-hidden', 'true');
			cell.append(glyph);
		});
	});
}