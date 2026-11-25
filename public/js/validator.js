export const RULES = Object.freeze({
    required: 'required',
    length: 'length',
    format: 'format',
});

const UNICODE_FORM = 'NFC';
const WHITESPACE_RUN = /\s+/gu;

export function createValidator(rules) {
    const patterns = Object.fromEntries(
        Object.entries(rules.patterns).map(([field, source]) => [field, new RegExp(source, rules.flags)]),
    );

    const message = (field, rule) => rules.messages[field][rule];

    function normalize(field, value) {
        let text = typeof value === 'string' ? value.trim().normalize(UNICODE_FORM) : '';

        if (rules.collapseWhitespace.includes(field)) {
            text = text.replace(WHITESPACE_RUN, ' ');
        }
        if (rules.lowercase.includes(field)) {
            text = text.toLowerCase();
        }

        return text;
    }

    function failedRule(field, value) {
        if (value === '') {
            return rules.required.includes(field) ? RULES.required : null;
        }

        const maxLength = rules.maxLength[field];
        if (maxLength !== undefined && [...value].length > maxLength) {
            return RULES.length;
        }

        return patterns[field].test(value) ? null : RULES.format;
    }

    function validateField(field, rawValue) {
        const rule = failedRule(field, normalize(field, rawValue));

        return rule === null ? null : message(field, rule);
    }

    function validate(values) {
        const errors = {};
        for (const field of rules.fields) {
            const error = validateField(field, values[field]);
            if (error !== null) {
                errors[field] = error;
            }
        }

        return errors;
    }

    return { normalize, validateField, validate };
}
