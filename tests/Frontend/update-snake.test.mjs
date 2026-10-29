import test from 'node:test';
import assert from 'node:assert/strict';
import {createSnake, placeFood, stepSnake, turnSnake} from '../../resources/js/laiot/update-snake.js';

test('еда появляется только на свободной клетке', () => {
    assert.deepEqual(placeFood([[0, 0], [1, 0], [0, 1]], 2, 2, () => 0), [1, 1]);
    assert.equal(placeFood([[0, 0]], 1, 1), null);
});

test('разворот назад и второй поворот до шага не ломают тело', () => {
    const state = createSnake();
    assert.equal(turnSnake(state, 'left'), state);
    const up = turnSnake(state, 'up');
    assert.equal(turnSnake(up, 'left'), up);
    assert.deepEqual(stepSnake(up).direction, [0, -1]);
});

test('искра увеличивает длину и счёт, новая еда не пересекается со змейкой', () => {
    const state = createSnake();
    state.food = [state.snake[0][0] + 1, state.snake[0][1]];
    const next = stepSnake(state, () => 0);
    assert.equal(next.score, 1);
    assert.equal(next.snake.length, state.snake.length + 1);
    assert.ok(!next.snake.some(([x, y]) => x === next.food[0] && y === next.food[1]));
    assert.equal(state.score, 0);
});

test('стена завершает игру, освободившийся хвост можно занять', () => {
    const state = {...createSnake(), snake: [[19, 5], [18, 5], [17, 5]]};
    assert.equal(stepSnake(state).alive, false);
    const tail = {...createSnake(), snake: [[1, 1], [1, 2], [2, 2], [2, 1]], food: [4, 4]};
    assert.equal(stepSnake(tail).alive, true);
    assert.deepEqual(stepSnake(tail).snake[0], [2, 1]);
});

test('заполненное поле считается победой', () => {
    const state = {columns: 2, rows: 2, snake: [[0, 0], [0, 1], [1, 1]], direction: [1, 0], queued: null, food: [1, 0], score: 0, alive: true};
    const next = stepSnake(state);
    assert.equal(next.won, true);
    assert.equal(next.alive, false);
    assert.equal(next.food, null);
});
