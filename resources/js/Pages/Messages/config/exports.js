/**
 * Columnas exportables del modulo Messages. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog que columnas exportar.
 */
export const messagesExportableColumns = (t) => [
    { key: 'id',             label: 'ID',                             default: true  },
    { key: 'subject',        label: t('messages.subject'),            default: true  },
    { key: 'audience_type',  label: t('messages.audience_type'),      default: true  },
    { key: 'audience_id',    label: t('messages.audience_target'),    default: false },
    { key: 'allow_replies',  label: t('messages.allow_replies'),      default: false },
    { key: 'is_active',      label: t('messages.is_active'),          default: true  },
    { key: 'status',         label: t('global.status'),               default: true  },
    { key: 'published_at',   label: t('messages.published_at'),       default: true  },
    { key: 'expires_at',     label: t('messages.expires_at'),         default: false },
    { key: 'created_at',     label: t('global.created_at'),           default: true  },
    { key: 'creator',        label: t('global.created_by'),           default: false },
];

export const messagesExportEndpoints = () => ({
    excel: route('communication.messages.export_excel'),
    pdf:   route('communication.messages.export_pdf'),
    word:  route('communication.messages.export_word'),
    csv:   route('communication.messages.export_csv'),
});
