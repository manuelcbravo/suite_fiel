const DATE_FALLBACK = '\u2014';
const DATE_ONLY_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

function pad(value: number) {
    return String(value).padStart(2, '0');
}

function buildDate(year: string, month: string, day: string) {
    return `${day}/${month}/${year}`;
}

function normalizeValue(value: string) {
    const trimmed = value.trim();

    if (trimmed.includes(' ') && !trimmed.includes('T')) {
        return trimmed.replace(' ', 'T');
    }

    return trimmed;
}

function parseDate(value: string | Date | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const normalized = normalizeValue(value);

    if (normalized === '') {
        return null;
    }

    const dateOnlyMatch = normalized.match(DATE_ONLY_PATTERN);

    if (dateOnlyMatch) {
        const [, year, month, day] = dateOnlyMatch;

        return new Date(Number(year), Number(month) - 1, Number(day));
    }

    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function formatDate(
    value: string | Date | null | undefined,
    fallback = DATE_FALLBACK,
) {
    if (!value) {
        return fallback;
    }

    if (typeof value === 'string') {
        const normalized = normalizeValue(value);
        const dateOnlyMatch = normalized.match(DATE_ONLY_PATTERN);

        if (dateOnlyMatch) {
            const [, year, month, day] = dateOnlyMatch;

            return buildDate(year, month, day);
        }
    }

    const parsed = parseDate(value);

    if (!parsed) {
        return fallback;
    }

    return buildDate(
        String(parsed.getFullYear()),
        pad(parsed.getMonth() + 1),
        pad(parsed.getDate()),
    );
}

export function formatDateTime(
    value: string | Date | null | undefined,
    fallback = DATE_FALLBACK,
) {
    if (!value) {
        return fallback;
    }

    if (typeof value === 'string') {
        const normalized = normalizeValue(value);

        if (DATE_ONLY_PATTERN.test(normalized)) {
            return formatDate(normalized, fallback);
        }
    }

    const parsed = parseDate(value);

    if (!parsed) {
        return fallback;
    }

    return `${formatDate(parsed, fallback)} ${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
}
