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

    const lastComma = raw.lastIndexOf(',');
    const lastDot = raw.lastIndexOf('.');
    let normalized = raw;

    if (lastComma !== -1 && lastDot !== -1) {
        const decimalSeparator = lastComma > lastDot ? ',' : '.';
        const thousandsSeparator = decimalSeparator === ',' ? '.' : ',';

        normalized = raw.replaceAll(thousandsSeparator, '');
        if (decimalSeparator === ',') normalized = normalized.replace(',', '.');
    } else if (lastComma !== -1) {
        const commas = raw.match(/,/g)?.length ?? 0;
        const decimalLength = raw.length - lastComma - 1;
        normalized = commas > 1 || decimalLength === 3 ? raw.replaceAll(',', '') : raw.replace(',', '.');
    } else if (lastDot !== -1) {
        const dots = raw.match(/\./g)?.length ?? 0;
        const decimalLength = raw.length - lastDot - 1;
        if (dots > 1 || decimalLength === 3) normalized = raw.replaceAll('.', '');
    }

    const amount = Number(normalized);

    return Number.isFinite(amount) ? amount : NaN;
}

/** Formato único para campos monetarios editables. */
export function formatMoneyInput(value) {
    return formatMoney(parseMoneyInput(value));
}
