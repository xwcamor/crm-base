import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Permisos del usuario actual, derivados de los shared props de Inertia.
 * Espeja el `Gate::before` del backend: super pasa todas las checks.
 *
 *   const { can, isSuper, canSeeAudit } = useAuth();
 *   if (can('regions.create')) { ... }
 */
export function useAuth() {
    const page = usePage();

    const roles = computed(() => page.props.auth?.user?.roles ?? []);

    const can = (permission) => {
        const u = page.props.auth?.user;
        if (!u) return false;
        if (u.roles?.includes('super')) return true;
        return u.permissions?.includes(permission) ?? false;
    };

    const isSuper = computed(() => roles.value.includes('super'));

    const canSeeAudit = computed(() =>
        roles.value.includes('super') || roles.value.includes('admin'),
    );

    return { can, isSuper, canSeeAudit };
}
