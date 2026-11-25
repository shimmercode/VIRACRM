/**
 * The single place review test scripts obtain the demo password.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * The password used to be a literal in three test scripts
 * and a class constant. That is a credential in Git: readable by anyone, and
 * impossible to rotate without a commit. It is now read from the environment
 * (VIRA_REVIEW_DEMO_PASSWORD), the same variable the seeder uses, so there is
 * exactly one source and rotating it rotates every test at once.
 *
 * The fallback reads .env, because the check scripts are run by a developer who
 * already has the file — they are not run by an attacker who has the repository.
 * If neither is present, the script fails loudly instead of guessing.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');

export function reviewPassword() {
  if (process.env.VIRA_REVIEW_DEMO_PASSWORD) {
    return process.env.VIRA_REVIEW_DEMO_PASSWORD;
  }

  const envFile = path.join(ROOT, '.env');

  if (fs.existsSync(envFile)) {
    for (const line of fs.readFileSync(envFile, 'utf8').split('\n')) {
      const m = /^VIRA_REVIEW_DEMO_PASSWORD=(.*)$/.exec(line.trim());
      if (m && m[1].trim() !== '') return m[1].trim().replace(/^["']|["']$/g, '');
    }
  }

  console.error(
    'VIRA_REVIEW_DEMO_PASSWORD is not set and .env has no value.\n'
    + 'The review seeder requires it, so tests cannot run without it.\n'
    + 'Set it in the environment — it must match what the seeder used.'
  );
  process.exit(2);
}
