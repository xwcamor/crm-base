/**
 * Columnas exportables del modulo StockTakes. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const stockTakesExportableColumns = (t) => [
    { key: 'id',           label: 'ID',                            default: true  },
    { key: 'reference',    label: t('stock_takes.reference'),      default: true  },
    { key: 'warehouse',    label: t('stock_takes.warehouse'),      default: true  },
    { key: 'status',       label: t('stock_takes.status'),         default: true  },
    { key: 'started_at',   label: t('stock_takes.started_at'),     default: true  },
    { key: 'completed_at', label: t('stock_takes.completed_at'),   default: true  },
    { key: 'note',         label: t('stock_takes.note'),           default: false },
    { key: 'created_at',   label: t('global.created_at'),          default: false },
    { key: 'creator',      label: t('global.created_by'),          default: false },
];

export const stockTakesExportEndpoints = () => ({
    excel: route('business_management.stock_takes.export_excel'),
    pdf:   route('business_management.stock_takes.export_pdf'),
    word:  route('business_management.stock_takes.export_word'),
    csv:   route('business_management.stock_takes.export_csv'),
});
