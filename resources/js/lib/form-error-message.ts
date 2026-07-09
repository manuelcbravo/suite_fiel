type ErrorValue = string | string[] | null | undefined;

type ErrorBag = Record<string, ErrorValue>;

const preferredErrorKeys = ['detalles', 'detalle', 'message', 'error'];

export function resolveFormErrorMessage(
    errors: ErrorBag | undefined,
    fallback = 'Verifica los campos marcados.',
) {
    if (!errors) {
        return fallback;
    }

    for (const key of preferredErrorKeys) {
        const message = normalizeErrorValue(errors[key]);

        if (message) {
            return message;
        }
    }

    return (
        Object.values(errors).map(normalizeErrorValue).find(Boolean) || fallback
    );
}

function normalizeErrorValue(value: ErrorValue) {
    if (Array.isArray(value)) {
        return (
            value.find(
                (message) =>
                    typeof message === 'string' && message.trim() !== '',
            ) || null
        );
    }

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}
