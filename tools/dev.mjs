import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { createConnection } from 'node:net';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const vite = resolve(root, 'node_modules', 'vite', 'bin', 'vite.js');
const startQueueWorker = process.env.START_QUEUE_WORKER !== '0';
const phpCandidates = [
    process.env.PHP_BINARY,
    'php',
    'D:\\xampp\\php\\php.exe',
].filter(Boolean);

if (!existsSync(vite)) {
    console.error('Vite is not installed. Run npm install first.');
    process.exit(1);
}

let stopping = false;
const children = new Set();

function start(name, command, args, { restart = false } = {}) {
    const child = spawn(command, args, {
        cwd: root,
        env: process.env,
        stdio: 'inherit',
        windowsHide: true,
    });

    children.add(child);
    child.once('error', (error) => {
        console.error(`[${name}] ${error.message}`);
        if (!restart) stop(1);
    });
    child.once('exit', (code, signal) => {
        children.delete(child);
        if (!stopping) {
            console.error(`[${name}] stopped unexpectedly (${signal ?? `exit ${code}`}).`);
            if (restart) {
                console.log(`[${name}] restarting in 1 second...`);
                setTimeout(() => {
                    if (!stopping) start(name, command, args, { restart: true });
                }, 1000);
            } else {
                stop(code || 1);
            }
        }
    });
    return child;
}

function stop(exitCode = 0) {
    if (stopping) return;
    stopping = true;
    for (const child of children) {
        if (!child.killed) child.kill('SIGTERM');
    }
    const timer = setTimeout(() => {
        for (const child of children) {
            if (!child.killed) child.kill('SIGKILL');
        }
        process.exit(exitCode);
    }, 3000);
    timer.unref();
    Promise.allSettled([...children].map((child) => new Promise((done) => child.once('exit', done))))
        .finally(() => process.exit(exitCode));
}

process.on('SIGINT', () => stop(0));
process.on('SIGTERM', () => stop(0));

function isPortListening(port) {
    return new Promise((resolvePort) => {
        const socket = createConnection({ host: '127.0.0.1', port });
        const finish = (listening) => {
            socket.destroy();
            resolvePort(listening);
        };
        socket.setTimeout(500);
        socket.once('connect', () => finish(true));
        socket.once('timeout', () => finish(false));
        socket.once('error', () => finish(false));
    });
}

const php = phpCandidates.find((candidate) => candidate === 'php' || existsSync(candidate));
if (!php) {
    console.error('PHP was not found. Install PHP or set the PHP_BINARY environment variable.');
    process.exit(1);
}

const [laravelRunning, viteRunning] = await Promise.all([isPortListening(8001), isPortListening(5173)]);

if (laravelRunning && viteRunning && !startQueueWorker) {
    console.log('Development services are already running.');
    console.log('Laravel: http://127.0.0.1:8001');
    console.log('Vite: http://127.0.0.1:5173');
    process.exit(0);
}

if (laravelRunning) {
    console.log('Laravel is already running at http://127.0.0.1:8001');
} else {
    console.log('Starting Laravel at http://127.0.0.1:8001');
    start('Laravel', php, ['artisan', 'serve', '--host=127.0.0.1', '--port=8001', '--no-reload']);
}

if (startQueueWorker) {
    console.log('Starting Laravel queue worker');
    start('Queue', php, ['-d', 'max_execution_time=0', 'artisan', 'queue:work', '--queue=api-imports,default', '--sleep=1', '--tries=1', '--timeout=600'], { restart: true });
} else {
    console.log('Laravel queue worker is disabled by START_QUEUE_WORKER=0.');
}

if (viteRunning) {
    console.log('Vite is already running at http://127.0.0.1:5173');
} else {
    console.log('Starting Vite at http://127.0.0.1:5173');
    start('Vite', process.execPath, [vite, '--host=127.0.0.1', '--port=5173', '--strictPort']);
}
