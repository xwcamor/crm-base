/**
 * planAppearance — catálogo de iconos y colores soportados para Plans.
 *
 * Single source of truth para:
 *   - El Form (selects de icono y color)
 *   - Index/Show/Trash/Modal (renderizar el icono del plan)
 *
 * Si quieres agregar otro icono, súmalo a PLAN_ICONS con su componente y un
 * label traducible. Nada más cambia en la app.
 */
import {
    SafetyCertificateOutlined, SafetyOutlined,
    FlagOutlined, GoldOutlined, ExperimentOutlined,
    AppstoreOutlined, ApartmentOutlined, BlockOutlined,
    BranchesOutlined, ProfileOutlined, BookOutlined,
    CompassOutlined,
} from '@ant-design/icons-vue';

// Paleta limitada (los colores que Ant Design Tag entiende como tokens).
// Si el usuario quiere un hex específico se podría ampliar a ColorPicker,
// pero un set acotado mantiene la estética consistente con Tags del sistema.
export const PLAN_COLORS = [
    { value: 'default', label: 'Gris',     swatch: '#d9d9d9' },
    { value: 'blue',    label: 'Azul',     swatch: '#1677ff' },
    { value: 'cyan',    label: 'Cian',     swatch: '#13c2c2' },
    { value: 'green',   label: 'Verde',    swatch: '#52c41a' },
    { value: 'gold',    label: 'Dorado',   swatch: '#faad14' },
    { value: 'orange',  label: 'Naranja',  swatch: '#fa8c16' },
    { value: 'red',     label: 'Rojo',     swatch: '#f5222d' },
    { value: 'purple',  label: 'Púrpura',  swatch: '#722ed1' },
    { value: 'magenta', label: 'Magenta',  swatch: '#eb2f96' },
];

// Catálogo de iconos profesionales (sin "estética AI"). Si en BD había
// referencias a iconos viejos (CrownOutlined, StarOutlined, ThunderboltOutlined,
// TrophyOutlined, GiftOutlined, FireOutlined, HeartOutlined, RocketOutlined,
// BulbOutlined) se renderizan con el fallback definido en resolveIconComponent.
export const PLAN_ICONS = [
    { value: 'SafetyCertificateOutlined', component: SafetyCertificateOutlined, label: 'Certificado' },
    { value: 'SafetyOutlined',            component: SafetyOutlined,            label: 'Seguridad' },
    { value: 'FlagOutlined',              component: FlagOutlined,              label: 'Bandera' },
    { value: 'GoldOutlined',              component: GoldOutlined,              label: 'Lingote' },
    { value: 'ExperimentOutlined',        component: ExperimentOutlined,        label: 'Experimento' },
    { value: 'AppstoreOutlined',          component: AppstoreOutlined,          label: 'Apps' },
    { value: 'ApartmentOutlined',         component: ApartmentOutlined,         label: 'Estructura' },
    { value: 'BlockOutlined',             component: BlockOutlined,             label: 'Bloque' },
    { value: 'BranchesOutlined',          component: BranchesOutlined,          label: 'Ramas' },
    { value: 'ProfileOutlined',           component: ProfileOutlined,           label: 'Perfil' },
    { value: 'BookOutlined',              component: BookOutlined,              label: 'Libro' },
    { value: 'CompassOutlined',           component: CompassOutlined,           label: 'Brújula' },
];

// Mapeo de iconos legacy AI-style → ícono profesional equivalente.
// Mantiene compatibilidad con planes ya guardados en BD que tenían estos
// valores en `plans.icon`, sin necesidad de migrar datos.
const LEGACY_ICON_MAP = {
    CrownOutlined:       SafetyCertificateOutlined,
    StarOutlined:        FlagOutlined,
    StarFilled:          FlagOutlined,
    RocketOutlined:      BranchesOutlined,
    ThunderboltOutlined: BranchesOutlined,
    TrophyOutlined:      SafetyCertificateOutlined,
    GiftOutlined:        BlockOutlined,
    FireOutlined:        FlagOutlined,
    HeartOutlined:       ProfileOutlined,
    BulbOutlined:        CompassOutlined,
};

/** Devuelve el componente del icono. Si el valor es uno "legacy AI-style"
 *  guardado en BD desde versiones anteriores, lo remapea a un ícono
 *  profesional equivalente. Si no se reconoce, devuelve null. */
export const resolveIconComponent = (iconName) => {
    if (!iconName) return null;
    const found = PLAN_ICONS.find(i => i.value === iconName)?.component;
    if (found) return found;
    return LEGACY_ICON_MAP[iconName] ?? null;
};

/** Color con fallback al default si el guardado no está en la paleta. */
export const resolveColor = (color) => {
    if (!color) return 'default';
    return PLAN_COLORS.find(c => c.value === color) ? color : 'default';
};
