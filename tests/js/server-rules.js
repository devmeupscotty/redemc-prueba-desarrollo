import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const PROJECT_ROOT = process.cwd();
const CASES_FILE = join(PROJECT_ROOT, 'tests', 'fixtures', 'validation-cases.json');
const EXPORT_RULES = "require 'vendor/autoload.php'; echo json_encode(App\\PersonValidator::clientRules());";

export function loadServerRules() {
    return JSON.parse(execFileSync('php', ['-r', EXPORT_RULES], { cwd: PROJECT_ROOT, encoding: 'utf8' }));
}

export function loadValidationCases() {
    return JSON.parse(readFileSync(CASES_FILE, 'utf8'));
}
