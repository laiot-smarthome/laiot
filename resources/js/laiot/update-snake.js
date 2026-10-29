// 2026-10-29 — чистая модель змейки: игра не обращается к серверу или состоянию обновления.
export const directions = {up: [0, -1], right: [1, 0], down: [0, 1], left: [-1, 0]};

export function placeFood(snake, columns, rows, random = Math.random) {
    const occupied = new Set(snake.map(([x, y]) => `${x}:${y}`));
    const available = [];
    for (let y = 0; y < rows; y++) for (let x = 0; x < columns; x++) if (!occupied.has(`${x}:${y}`)) available.push([x, y]);
    return available.length ? available[Math.min(available.length - 1, Math.floor(random() * available.length))] : null;
}

export function createSnake(columns = 20, rows = 14, random = Math.random) {
    const x = Math.floor(columns / 2), y = Math.floor(rows / 2);
    const snake = [[x, y], [x - 1, y], [x - 2, y]];
    return {columns, rows, snake, direction: [1, 0], queued: null, food: placeFood(snake, columns, rows, random), score: 0, alive: true, won: false};
}

export function turnSnake(state, name) {
    const direction = directions[name];
    if (!direction || !state.alive || state.queued || (direction[0] === -state.direction[0] && direction[1] === -state.direction[1])) return state;
    return {...state, queued: direction};
}

export function stepSnake(state, random = Math.random) {
    if (!state.alive) return state;
    const direction = state.queued || state.direction;
    const head = [state.snake[0][0] + direction[0], state.snake[0][1] + direction[1]];
    const grows = state.food && head[0] === state.food[0] && head[1] === state.food[1];
    const body = grows ? state.snake : state.snake.slice(0, -1);
    if (head[0] < 0 || head[0] >= state.columns || head[1] < 0 || head[1] >= state.rows || body.some(([x, y]) => x === head[0] && y === head[1])) return {...state, alive: false, queued: null};
    const snake = [head, ...state.snake];
    if (!grows) snake.pop();
    const food = grows ? placeFood(snake, state.columns, state.rows, random) : state.food;
    return {...state, snake, direction, queued: null, food, score: state.score + (grows ? 1 : 0), alive: food !== null, won: food === null};
}
