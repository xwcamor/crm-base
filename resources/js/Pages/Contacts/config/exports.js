/**
 * Columnas exportables del módulo Contacts. Independientes de las visibles
 * en pantalla — el usuario elige en el ExportDialog qué columnas exportar.
 */
export const contactsExportableColumns = (t) => [
    { key: 'id',         label: 'ID',                     default: true  },
    { key: 'name',       label: t('contacts.name'),      default: true  },
{ key: 'is_active',  label: t('contacts.is_active'), default: true  },
    { key: 'created_at', label: t('global.created_at'),   default: true  },
    { key: 'creator',    label: t('global.created_by'),   default: false },
];

export const contactsExportEndpoints = () => ({
    excel: route('crm.contacts.export_excel'),
    pdf:   route('crm.contacts.export_pdf'),
    word:  route('crm.contacts.export_word'),
    csv:   route('crm.contacts.export_csv'),
});
