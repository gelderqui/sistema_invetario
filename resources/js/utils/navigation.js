export const navigationItems = [
    {
        name: 'dashboard',
        label: 'Dashboard',
        icon: 'fa-solid fa-chart-line',
        permissions: ['dashboard'],
    },
    {
        name: 'productos-grupo',
        label: 'Catalogo',
        icon: 'fa-solid fa-boxes-stacked',
        permissions: ['categorias', 'productos', 'proveedores'],
        children: [
            {
                name: 'categorias',
                label: 'Categorias',
                icon: 'fa-solid fa-tags',
                permissions: ['categorias'],
            },
            {
                name: 'proveedores',
                label: 'Proveedores',
                icon: 'fa-solid fa-truck-field',
                permissions: ['proveedores'],
            },
            {
                name: 'productos',
                label: 'Productos',
                icon: 'fa-solid fa-box',
                permissions: ['productos'],
            },
        ],
    },
    {
        name: 'compras',
        label: 'Compras',
        icon: 'fa-solid fa-truck-ramp-box',
        permissions: ['compras'],
    },
    {
        name: 'inventario',
        label: 'Inventario',
        icon: 'fa-solid fa-warehouse',
        permissions: ['inventario'],
    },
    {
        name: 'pos',
        label: 'POS',
        icon: 'fa-solid fa-cash-register',
        permissions: ['pos.access'],
        disabled: true,
    },
    {
        name: 'reports',
        label: 'Reportes',
        icon: 'fa-solid fa-file-export',
        permissions: ['reports.view'],
        disabled: true,
    },
    {
        name: 'users',
        label: 'Usuarios',
        icon: 'fa-solid fa-users',
        permissions: ['users'],
    },
    {
        name: 'roles',
        label: 'Roles',
        icon: 'fa-solid fa-user-shield',
        permissions: ['roles'],
    },
];