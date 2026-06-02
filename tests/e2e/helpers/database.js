import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const e2eDatabasePath = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '../../database/testing.sqlite',
);

export const e2eDatabaseEnv = {
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: e2eDatabasePath,
};
