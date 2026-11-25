import { describe, expect, it } from 'vitest';
import { createValidator, RULES } from '../../public/js/validator.js';
import { loadServerRules, loadValidationCases } from './server-rules.js';

const rules = loadServerRules();
const cases = loadValidationCases();
const validator = createValidator(rules);

describe.each(rules.fields)('%s parity with the server rules', (field) => {
    it.each(cases[field].valid)('accepts %j', (value) => {
        expect(validator.validateField(field, value)).toBeNull();
    });

    it.each(cases[field].invalid)('rejects %j with the format message', (value) => {
        expect(validator.validateField(field, value)).toBe(rules.messages[field][RULES.format]);
    });
});

describe('required and optional fields', () => {
    it.each(cases.empty)('requires name and email when given %j', (value) => {
        for (const field of rules.required) {
            expect(validator.validateField(field, value)).toBe(rules.messages[field][RULES.required]);
        }
    });

    it.each(cases.empty)('lets the phone be empty when given %j', (value) => {
        expect(validator.validateField('phone', value)).toBeNull();
    });

    it('treats missing values as empty', () => {
        expect(validator.validateField('name', undefined)).toBe(rules.messages.name[RULES.required]);
    });
});

describe('length limits', () => {
    it('accepts a name at the limit counting characters, not bytes', () => {
        expect(validator.validateField('name', 'ñ'.repeat(rules.maxLength.name))).toBeNull();
    });

    it('rejects a name over the limit with the server message', () => {
        const message = validator.validateField('name', 'a'.repeat(rules.maxLength.name + 1));

        expect(message).toBe(rules.messages.name[RULES.length]);
        expect(message).toContain(String(rules.maxLength.name));
    });
});

describe('validate', () => {
    it('returns every error at once', () => {
        const errors = validator.validate({ name: '  ', email: 'x', phone: '1' });

        expect(Object.keys(errors)).toEqual(rules.fields);
    });

    it('returns no errors for a valid person', () => {
        expect(validator.validate({ name: 'Ana Pérez', email: 'ana@mail.com', phone: '' })).toEqual({});
    });
});

describe('normalize', () => {
    it('collapses inner spaces only in the fields the server says', () => {
        expect(rules.collapseWhitespace).toEqual(['name']);
        expect(validator.normalize('name', '  María \t José ')).toBe('María José');
        expect(validator.normalize('phone', ' 097680102 ')).toBe('097680102');
    });

    it('lowercases only the fields the server says', () => {
        expect(rules.lowercase).toEqual(['email']);
        expect(validator.normalize('email', ' Ana@Mail.COM ')).toBe('ana@mail.com');
        expect(validator.normalize('name', 'Ana')).toBe('Ana');
    });

    it('composes decomposed accents', () => {
        const decomposed = 'José';

        expect(validator.normalize('name', decomposed)).toBe('José');
    });
});
