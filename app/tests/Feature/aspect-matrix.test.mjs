import assert from 'node:assert/strict';
import { test } from 'node:test';
import { findAspectMatches } from '../../resources/js/aspect-matrix.js';

test('finds conjunctions across the Aries boundary', () => {
	const matches = findAspectMatches(359, 1, ['conjunction'], 3);

	assert.equal(matches.length, 1);
	assert.equal(matches[0].key, 'conjunction');
	assert.equal(matches[0].orb, 2);
});

test('finds oppositions using the shortest angular distance', () => {
	const matches = findAspectMatches(2, 181, ['opposition'], 3);

	assert.equal(matches.length, 1);
	assert.equal(matches[0].key, 'opposition');
	assert.equal(matches[0].orb, 1);
});

test('respects enabled aspect types and the configured orb', () => {
	assert.deepEqual(findAspectMatches(0, 90, ['trine'], 6), []);
	assert.equal(findAspectMatches(0, 85, ['square'], 4).length, 0);
	assert.equal(findAspectMatches(0, 85, ['square'], 5)[0].orb, 5);
});