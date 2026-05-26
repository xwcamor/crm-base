/**
 * Columnas exportables del módulo Pipelines. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const pipelinesExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('pipelines.name'),      default: true  },
{ key: 'is_active',  label: t('pipelines.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const pipelinesExportEndpoints = () => ({
    excel: route('crm.pipelines.export_excel'),
    pdf:   route('crm.pipelines.export_pdf'),
    word:  route('crm.pipelines.export_word'),
    csv:   route('crm.pipelines.export_csv'),
});
