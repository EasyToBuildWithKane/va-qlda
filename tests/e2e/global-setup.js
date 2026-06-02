import { ensureE2EDatabase } from './helpers/seed.js';

export default async function globalSetup() {
    ensureE2EDatabase();
}
