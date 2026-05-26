<?php

return [
    'singular' => 'Actividad',
    'plural'   => 'Actividades',

    'index_title'    => 'Mis actividades',
    'index_subtitle' => 'Llamadas, emails, reuniones, notas y tareas pendientes.',

    'panel_title'      => 'Actividades',
    'panel_empty'      => 'Sin actividades todavía. Agrega la primera para empezar a hacer seguimiento.',
    'add'              => 'Agregar actividad',
    'edit'             => 'Editar actividad',
    'delete'           => 'Eliminar actividad',
    'delete_confirm'   => '¿Eliminar esta actividad? Esta acción no se puede deshacer fácilmente.',

    // Tipos
    'type'             => 'Tipo',
    'types' => [
        'note'    => 'Nota',
        'call'    => 'Llamada',
        'email'   => 'Email',
        'meeting' => 'Reunión',
        'task'    => 'Tarea',
    ],

    // Campos
    'subject'         => 'Asunto',
    'subject_hint'    => 'Título corto que identifica la actividad. Aparece en el timeline.',
    'subject_placeholder' => 'Ej: Llamada de seguimiento, Demo del producto, Envío de propuesta...',
    'body'            => 'Detalles',
    'body_hint'       => 'Descripción detallada de la actividad. Para emails puedes pegar el contenido enviado; para llamadas qué se conversó; para notas cualquier información relevante.',
    'body_placeholder' => 'Describe la actividad, qué se conversó, próximos pasos...',

    'due_at'          => 'Fecha y hora',
    'due_at_hint'     => 'Cuándo está programada esta actividad (para reuniones) o cuándo vence (para tareas).',
    'completed_at'    => 'Completada',
    'duration_min'    => 'Duración (minutos)',
    'duration_min_hint' => 'Duración estimada o real en minutos. Opcional.',
    'location'        => 'Lugar / URL',
    'location_hint'   => 'Dirección física o link de videollamada (Zoom, Meet, Teams). Opcional.',
    'location_placeholder' => 'https://meet.google.com/... o "Sala de reuniones piso 3"',

    'outcome'         => 'Resultado',
    'outcome_hint'    => 'Qué pasó en la llamada. Alimenta reportes de efectividad por canal.',
    'outcomes' => [
        'answered'   => 'Contestó',
        'voicemail'  => 'Dejé voicemail',
        'no_answer'  => 'No contestó',
        'rejected'   => 'Rechazó',
    ],

    'priority'        => 'Prioridad',
    'priority_hint'   => 'Qué tan urgente es completar esta tarea. Las de prioridad alta aparecen primero en tu agenda.',
    'priorities' => [
        'low'    => 'Baja',
        'medium' => 'Media',
        'high'   => 'Alta',
    ],

    // Estado
    'status'          => 'Estado',
    'status_pending'  => 'Pendiente',
    'status_completed'=> 'Completada',
    'status_overdue'  => 'Atrasada',

    // Filtros del index global
    'filter_status'   => 'Estado',
    'filter_status_all'       => 'Todas',
    'filter_status_pending'   => 'Pendientes',
    'filter_status_completed' => 'Completadas',
    'filter_status_overdue'   => 'Atrasadas',
    'filter_scope_all'  => 'Todas',
    'filter_scope_mine' => 'Solo las mías',
    'filter_search'     => 'Buscar...',
    'filter_type_all'   => 'Todos los tipos',
    'filter_priority_all' => 'Todas las prioridades',

    // Filtros por estado del Deal padre (solo aplican a activities de deals)
    'filter_section_pipeline'        => 'Filtrar por pipeline',
    'filter_pipeline_all'            => 'Todos los pipelines',
    'filter_pipeline_placeholder'    => 'Pipeline',
    'filter_stage_all'               => 'Todas las etapas',
    'filter_stage_placeholder'       => 'Etapa',
    'filter_deal_status_all'         => 'Todos los estados',
    'filter_deal_status_placeholder' => 'Estado del deal',

    // Columnas
    'col_parent'                     => 'Asociada a',
    'col_pipeline'                   => 'Pipeline / Etapa',
    'col_quote'                      => 'Cotización',

    // Acciones
    'mark_complete'   => 'Marcar como completada',
    'mark_pending'    => 'Reabrir',
    'save'            => 'Guardar',
    'cancel'          => 'Cancelar',

    // Widget de nota rápida (Quick Note) — visible siempre arriba de las tabs
    'quick_note_title'       => 'Agregar nota rápida',
    'quick_note_placeholder' => 'Escribe una nota sobre esta entidad (llamada, recordatorio, observación...)',
    'quick_note_hint'        => 'Ctrl + Enter para guardar',
    'quick_note_save'        => 'Guardar nota',
    'quick_note_success'     => 'Nota agregada.',
    'quick_note_error'       => 'No se pudo guardar la nota.',

    // Mensajes
    'created'         => 'Actividad creada.',
    'saved'           => 'Actividad actualizada.',
    'deleted'         => 'Actividad eliminada.',
    'completed'       => 'Actividad marcada como completada.',
    'reopened'        => 'Actividad reabierta.',

    // Validacion
    'field_required'    => 'Este campo es obligatorio para este tipo de actividad.',
    'parent_not_found'  => 'La entidad asociada no existe.',

    // Visual labels para el timeline
    'logged_by'       => 'Registrado por',
    'overdue_label'   => 'ATRASADA',
    'completed_on'    => 'Completada el :date',
    'due_on'          => 'Vence el :date',
    'duration_label'  => ':min min',

    // Dashboard widget
    'widget_title'      => 'Mi agenda',
    'widget_empty'      => 'Sin actividades pendientes.',
    'widget_see_all'    => 'Ver todas',

    // Parent labels
    'parent_Deal'     => 'Oportunidad',
    'parent_Company'  => 'Empresa',
    'parent_Contact'  => 'Contacto',

    // Attachment
    'attachment'                => 'Archivo adjunto',
    'attachment_hint'           => 'Adjunta un PDF, imagen, documento o cualquier archivo relevante (máx 10 MB). Útil para guardar propuestas, contratos firmados, capturas de pantalla.',
    'attach_file'               => 'Adjuntar archivo',
    'download_attachment'       => 'Descargar adjunto',
    'replace_attachment_hint'   => 'Subir uno nuevo lo reemplaza.',

    'related_quote'             => 'Cotización relacionada',
    'related_quote_hint'        => 'Vincula esta actividad con una cotización del deal. Útil cuando registras el envío de una propuesta formal — queda trazado qué quote se mandó.',
    'related_quote_placeholder' => 'Selecciona una cotización del deal',
    'quote_link_label'          => 'Cotización:',
    'log_send_button'           => 'Registrar envío',

    // Kanban view
    'view_list'                 => 'Lista',
    'view_kanban'               => 'Kanban',
    'kanban_overdue'            => 'Atrasadas',
    'kanban_today'              => 'Hoy',
    'kanban_this_week'          => 'Esta semana',
    'kanban_later'              => 'Más adelante',
    'kanban_no_date'            => 'Sin fecha',
    'kanban_completed'          => 'Completadas',
    'kanban_empty_column'       => 'Sin actividades',
];
