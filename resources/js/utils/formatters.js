export function formatMoney(value) {
    const amount = Number(value ?? 0);

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
}

/**
 * Convierte importes escritos con punto o coma decimal a un número.
 * También entiende valores ya formateados, por ejemplo: 190,234,234.34.
 */
export function parseMoneyInput(value) {
    if (typeof value === 'number') return value;

    const raw = String(value ?? '').trim().replace(/\s/g, '');
    if (!raw) return 0;

    let normalized = null;

    if (/^[+-]?\d+$/.test(raw)) {
        normalized = raw;
    } else if (/^[+-]?(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d+)?$/.test(raw)) {
        normalized = raw.replaceAll(',', '');
    } else if (raw.includes('.') && raw.includes(',') && /^[+-]?(?:\d+|\d{1,3}(?:\.\d{3})+),\d+$/.test(raw)) {
        normalized = raw.replaceAll('.', '').replace(',', '.');
    } else if (!raw.includes('.') && (raw.match(/,/g)?.length ?? 0) === 1) {
        normalized = /^[+-]?\d{1,3},\d{3}$/.test(raw)
            ? raw.replace(',', '')
            : (/^[+-]?\d+,\d+$/.test(raw) ? raw.replace(',', '.') : null);
    } else if (/^[+-]?\d{1,3}(?:,\d{3}){2,}$/.test(raw)) {
        normalized = raw.replaceAll(',', '');
    } else if (/^[+-]?\d{1,3}(?:\.\d{3}){2,}$/.test(raw)) {
        normalized = raw.replaceAll('.', '');
    }

    const amount = normalized === null ? NaN : Number(normalized);

    return Number.isFinite(amount) ? amount : NaN;
}

/** Formato único para campos monetarios editables. */
export function formatMoneyInput(value) {
    return formatMoney(parseMoneyInput(value));
}
