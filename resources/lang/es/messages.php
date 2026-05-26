<?php

return [
    // Cabeceras
    'id'                    => 'ID',
    'singular'              => 'Mensaje',
    'plural'                => 'Mensajes',
    'record'                => 'mensaje',
    'records'               => 'mensajes',
    'new'                   => 'Crear mensaje',
    'inbox'                 => 'Bandeja',
    'new_message'           => 'Nuevo mensaje',
    'edit_message'          => 'Editar mensaje',
    'message_detail'        => 'Detalle del mensaje',
    'unread'                => 'No leído',
    'read'                  => 'Leído',
    'empty_bell'            => 'No tienes mensajes',
    'empty_bell_hint'       => 'Aquí verás avisos, anuncios y debates del administrador.',
    'view_inbox'            => 'Ver bandeja',

    // Campos
    'subject'               => 'Asunto',
    'subject_hint'          => 'Título del mensaje; lo verán los destinatarios en el listado de su bandeja.',
    'body'                  => 'Cuerpo',
    'body_hint'             => 'Contenido del mensaje. Acepta formato enriquecido (negrita, listas, enlaces).',
    'audience'              => 'Audiencia',
    'audience_type'         => 'Tipo de audiencia',
    'audience_type_hint'    => 'Define a quién llega: a todos, a un workspace o a un único usuario.',
    'audience_target'       => 'Destinatario',
    'audience_global'       => 'Todos los usuarios (Global)',
    'audience_tenant'       => 'Workspace',
    'audience_user'         => 'Usuario',
    'audience_select_tenant'=> 'Seleccionar workspace',
    'audience_select_tenant_hint' => 'Workspace cuyos usuarios recibirán el mensaje.',
    'audience_select_user'  => 'Seleccionar usuario',
    'audience_select_user_hint' => 'Usuario individual que recibirá el mensaje.',
    'allow_replies'         => 'Permitir respuestas / debate',
    'allow_replies_hint'    => 'Si está activado, los destinatarios podrán responder al mensaje.',
    'is_active'             => 'Activo',
    'is_active_hint'        => 'Si está desactivado, el mensaje queda oculto aunque esté publicado.',
    'is_active_required'    => 'Indica si el mensaje queda activo o inactivo.',
    'published_at'          => 'Publicado el',
    'expires_at'            => 'Vence el',
    'expires_at_hint'       => 'Fecha en que el mensaje deja de mostrarse. Vacío significa sin vencimiento.',
    'no_expiration'         => 'Sin vencimiento',
    'created_by'            => 'Creado por',
    'created_at'            => 'Creado el',
    'status_published'      => 'Publicado',
    'status_draft'          => 'Borrador',
    'status_expired'        => 'Vencido',

    // Stats
    'recipients_count'      => 'Destinatarios',
    'read_count'            => 'Leídos',
    'replies_count'         => 'Respuestas',
    'read_pct'              => '% leído',

    // Acciones
    'save_draft'            => 'Guardar borrador',
    'save_and_publish'      => 'Guardar y publicar',
    'publish_now'           => 'Publicar ahora',
    'reply'                 => 'Responder',
    'send_reply'            => 'Enviar respuesta',
    'mark_all_read'         => 'Marcar todo como leído',
    'view_message'          => 'Ver mensaje',
    'duplicate'             => 'Duplicar mensaje',
    'restore_hint'          => 'Restaura el mensaje a su estado anterior.',

    // Filtros
    'filter_subject'        => 'Buscar por asunto',
    'filter_audience'       => 'Filtrar por audiencia',
    'filter_active'         => 'Estado',
    'only_unread'           => 'No leídos',
    'only_repliable'        => 'Permiten respuesta',
    'tab_all'               => 'Todos',
    'badge_new'             => 'Nuevo',

    // Empty states
    'inbox_empty_title'     => 'Sin mensajes',
    'inbox_empty_hint'      => 'Cuando recibas un anuncio, aparecerá aquí.',
    'messages_empty_title'  => 'Sin mensajes creados',
    'messages_empty_hint'   => 'Crea tu primer anuncio para enviarlo a tus usuarios.',
    'replies_empty'         => 'Aún no hay respuestas.',

    // Mensajes flash
    'created_success'       => 'Mensaje creado correctamente.',
    'updated_success'       => 'Mensaje actualizado.',
    'deleted_success'       => 'Mensaje eliminado.',
    'published_success'     => 'Mensaje publicado.',
    'reply_sent'            => 'Respuesta enviada.',
    'mark_all_read_success' => 'Se marcaron :count mensajes como leídos.',

    // Validación
    'subject_required'           => 'El asunto es obligatorio.',
    'subject_unique'             => 'Ya existe un mensaje con este asunto.',
    'body_required'              => 'El cuerpo es obligatorio.',
    'audience_type_required'     => 'Selecciona la audiencia del mensaje.',
    'audience_id_required'       => 'Selecciona el destinatario.',
    'reply_body_required'        => 'Escribe una respuesta antes de enviar.',
    'reply_body_max'             => 'La respuesta no puede superar 5000 caracteres.',
    'confirm_subject_mismatch'   => 'El asunto ingresado no coincide.',

    // Errores
    'not_a_recipient'      => 'No tienes acceso a este mensaje.',
    'replies_not_allowed'  => 'Este mensaje no permite respuestas.',

    // Confirmación de baja
    'delete_title'         => 'Eliminar mensaje',
    'delete_warning'       => 'Esta acción soft-elimina el mensaje. Para confirmar, escribe el asunto exacto.',
    'delete_subject_label' => 'Asunto a confirmar',
    'delete_reason_label'  => 'Motivo de la baja',

    // Notificaciones in-app
    'notify_new_reply_title' => 'Nueva respuesta',
    'notify_new_reply_body'  => ':user respondió a "  :subject "',

    // Tier 1: exports / imports / edit-all
    'export_title'              => 'Reporte de mensajes',
    'export_filename'           => 'mensajes',
    'export_limit_exceeded'     => 'El reporte excede el límite (:count > :limit) para el formato :format.',
    'import_template_filename'  => 'plantilla_mensajes.xlsx',
    'edit_all_title'            => 'Edición masiva de mensajes',
    'edit_all_subtitle'         => 'Edita asunto y estado de varios mensajes a la vez. Pulsa "Guardar todo" para confirmar.',
    'edit_all_changes'          => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all'         => 'Guardar todo',
    'edit_all_discard'          => 'Descartar cambios',
    'edit_all_no_results'       => 'No hay mensajes para editar con los filtros actuales.',

    // Bulk + flash extra
    'bulk_in_queue'             => 'Operación masiva en cola (:count registros).',

    // Tour onboarding
    'tour' => [
        'step_filters_title'   => 'Filtros',
        'step_filters_body'    => 'Filtra mensajes por asunto, audiencia, estado y fecha. Combina varios filtros simultáneamente.',
        'step_export_title'    => 'Exportar e Importar',
        'step_export_body'     => 'Descarga mensajes en CSV, Excel, PDF o Word. También puedes importar mensajes desde una plantilla.',
        'step_favorites_title' => 'Favoritos',
        'step_favorites_body'  => 'Marca con la estrella tus mensajes prioritarios; aparecerán arriba en el listado.',
        'step_bulk_title'      => 'Operaciones masivas',
        'step_bulk_body'       => 'Selecciona varias filas para activarlas, desactivarlas o eliminarlas en lote.',
    ],
];
