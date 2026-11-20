import { spawn, spawnSync } from 'node:child_process';
import { once } from 'node:events';
import { mkdtempSync, writeFileSync, rmSync, rmdirSync } from 'node:fs';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { randomBytes } from 'node:crypto';
import { setTimeout } from 'node:timers/promises';

const portReservation = createServer().listen(0, '127.0.0.1');
await once(portReservation, 'listening');
const address = '127.0.0.1:' + portReservation.address().port;
await new Promise((done) => portReservation.close(done));
const directory = mkdtempSync(join(tmpdir(), 'bloomify-browser-'));
const database = join(directory, 'bloomify.sqlite');
writeFileSync(database, '');
const pushPublicKey = Buffer.from('046b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c2964fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5', 'hex').toString('base64url');
const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_KEY: 'base64:' + randomBytes(32).toString('base64'),
    APP_URL: 'http://' + address,
    APP_CONFIG_CACHE: join(directory, 'config.php'),
    APP_ROUTES_CACHE: join(directory, 'routes.php'),
    BLOOMIFY_BROWSER_BASE_URL: 'http://' + address,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    DB_URL: '',
    DATABASE_URL: '',
    CACHE_DRIVER: 'array',
    SESSION_DRIVER: 'file',
    SESSION_SECURE_COOKIE: 'false',
    MAIL_MAILER: 'array',
    CLOUDINARY_URL: '',
    PLANTID_API_KEY: '',
    WEB_PUSH_SUBJECT: 'mailto:browser-test@example.test',
    WEB_PUSH_PUBLIC_KEY: pushPublicKey,
    WEB_PUSH_PRIVATE_KEY: 'test-only',
};

function run(program, args) {
    const result = spawnSync(program, args, { env, stdio: 'inherit' });
    if (result.error) {
        throw result.error;
    }
    if (result.status !== 0) {
        process.exitCode = result.status ?? 1;
        return false;
    }
    return true;
}

let server;
try {
    if (run('php', ['artisan', 'migrate', '--seed', '--force'])) {
        const router = resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
        server = spawn('php', ['-S', address, router], { env, cwd: 'public', stdio: 'ignore' });
        await once(server, 'spawn');
        for (let attempts = 0; ; attempts++) {
            const response = await fetch(env.APP_URL + '/login', { signal: AbortSignal.timeout(1000) }).catch(() => null);
            if (response?.ok) {
                break;
            }
            if (server.exitCode !== null || attempts >= 30) {
                throw new Error('The isolated Bloomify browser test server did not start.');
            }
            await setTimeout(100);
        }
        run(process.execPath, ['node_modules/@playwright/test/cli.js', 'test', ...process.argv.slice(2)]);
    }
} finally {
    if (server && server.pid && server.exitCode === null && server.signalCode === null) {
        const exited = once(server, 'exit');
        server.kill();
        await exited;
    }
    for (const suffix of ['', '-journal', '-shm', '-wal']) {
        rmSync(database + suffix, { force: true });
    }
    rmdirSync(directory);
}
