import { usePage } from '@inertiajs/vue3';

/**
 * Plugin Vue que registra `$t()` global, leyendo del shared prop `translations`
 * que el middleware HandleInertiaRequests inyecta en cada response.
 *
 * Usage en templates:
 *   {{ $t('global.active') }}             → "Activo"
 *   {{ $t('regions.name') }}              → "Nombre"
 *   {{ $t('global.disclosure', { terms: 'X', privacy: 'Y' }) }}  → reemplazo
 *
 * Usage en script setup:
 *   import { useI18n } from '@/Plugins/i18n';
 *   const { t } = useI18n();
 *   const label = t('global.active');
 *
 * Convención:
 *   - key se expresa como 'namespace.subkey.subsubkey' (ej. 'regions.is_active')
 *   - Si no encuentra la traducción, devuelve la key tal cual (para detectar
 *     keys ausentes en desarrollo en lugar de mostrar undefined).
 *   - Soporta reemplazo de :placeholders al estilo Laravel.
 */

/** Resuelve un key dot-path en un objeto anidado. */
function resolveKey(translations, key) {
    if (!translations) return null;
    const parts = key.split('.');
    let cur = translations;
    for (const p of parts) {
        if (cur && typeof cur === 'object' && p in cur) {
            cur = cur[p];
        } else {
            return null;
        }
    }
    return typeof cur === 'string' ? cur : null;
}

/** Reemplaza :placeholders con los valores de replace dict. */
function applyReplacements(str, replacements) {
    if (!replacements) return str;
    return Object.entries(replacements).reduce(
        (acc, [k, v]) => acc.replaceAll(`:${k}`, String(v)),
        str,
    );
}

/** Función plana — útil cuando no se quiere llamar usePage adentro. */
export function translate(translations, key, replacements) {
    const found = resolveKey(translations, key);
    if (found === null) return key;  // fallback: la key cruda
    return applyReplacements(found, replacements);
}

/** Composable para usar dentro de <script setup>. */
export function useI18n() {
    const page = usePage();
    return {
        t: (key, replacements) => translate(page.props.translations, key, replacements),
    };
}

/** Plugin para registrarlo como $t global en el template. */
export default {
    install(app) {
        app.config.globalProperties.$t = function (key, replacements) {
            const translations = this.$page?.props?.translations;
            return translate(translations, key, replacements);
        };
    },
};
