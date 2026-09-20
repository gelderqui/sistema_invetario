function normalizePath(path) {
    if (typeof path !== 'string' || path.trim() === '') return null;

    const normalized = path.trim().replace(/\/$/, '');

    return normalized || '/';
}

export function getDefaultAuthorizedPath(user) {
    const permissions = [...(user?.permissions ?? [])]
        .filter((permission) => Boolean(normalizePath(permission.ruta)))
        .sort((a, b) => (a.orden ?? Number.MAX_SAFE_INTEGER) - (b.orden ?? Number.MAX_SAFE_INTEGER));

    return normalizePath(permissions[0]?.ruta);
}

export function hasRoutePermission(user, path) {
    const targetPath = normalizePath(path);

    if (!targetPath) return false;

    return (user?.permissions ?? []).some(
        (permission) => normalizePath(permission.ruta) === targetPath,
    );
}
