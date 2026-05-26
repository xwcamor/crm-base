<?php

return [
    'singular'      => 'Pipeline',
    'plural'        => 'Pipelines',
    'record'        => 'pipeline',
    'records'       => 'pipelines',
    'new'           => 'Crear pipeline',
    'id'            => 'N°',

    'index_title'    => 'Pipelines',
    'index_subtitle' => 'Configurá los embudos de venta (Sales, Renewal, Onboarding...). Cada pipeline tiene sus propias etapas (kanban columns).',
    'create_title'   => 'Crear pipeline',
    'create_subtitle'=> 'Define un nuevo embudo de ventas.',
    'edit_title'     => 'Editar pipeline',
    'delete_title'   => 'Eliminar pipeline',
    'show_title'     => 'Pipeline — Información',
    'trash_title'    => 'Papelera de pipelines',
    'form_create_hint' => 'Define un nuevo embudo de ventas con sus etapas.',
    'empty_hint'      => 'Crea el primer pipeline para empezar a registrar oportunidades.',
    'name_placeholder' => 'Ej: Sales Pipeline 2026',

    'name'        => 'Nombre del pipeline',
    'name_hint'   => 'Identificador interno del embudo (ej: "Sales 2026", "Renewals", "Onboarding"). Aparece en el selector cuando creas oportunidades.',
    'description' => 'Descripción',
    'description_hint' => 'Para qué se usa este pipeline. Ayuda al equipo a elegir el correcto cuando hay varios.',
    'description_placeholder' => 'Ej: Flujo principal de ventas B2B — usar para deals nuevos de cuenta',
    'color'       => 'Color',
    'color_placeholder' => '#1677ff',
    'stages_count'      => 'Etapas',
    'open_deals_count'  => 'Deals abiertos',
    'color_hint'  => 'Color visual del pipeline en kanban y reportes. Si tienes varios pipelines (Sales/Renewals/Onboarding) un color por cada uno los hace fáciles de distinguir.',
    'is_default'  => 'Pipeline default',
    'is_default_hint' => 'Si está activo, los deals nuevos usan este pipeline automáticamente sin tener que elegir manualmente. Solo uno puede ser default por workspace.',
    'sort_order'  => 'Orden',
    'sort_order_hint' => 'Posición en el selector de pipelines. Menor número aparece primero. Útil cuando tienes muchos pipelines para que los más usados queden arriba.',
    'is_active'   => 'Estado',
    'is_active_hint' => 'Si está inactivo, no aparece en el selector al crear nuevos deals (pero los deals existentes siguen funcionando).',
    'filter_name' => 'Nombre',

    'edit_hint'   => 'Modificar este registro',
    'delete_hint' => 'Eliminar (queda en papelera)',
    'restore_hint'=> 'Volverá a estar disponible en el listado principal.',

    'created' => 'Registro creado.',
    'saved'   => 'Registro actualizado.',
    'deleted' => 'Registro eliminado.',

    'delete_about'                 => 'Vas a eliminar ":name". Quedará en papelera.',
    'has_open_deals_title'         => 'No se puede eliminar este pipeline',
    'has_open_deals'               => 'Este pipeline tiene :count deal(s) abierto(s). Reasígnalos a otro pipeline o ciérralos (won/lost) antes de eliminar.',
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Export
    'export_filename'           => 'exportacion_clientes',
    'import_template_filename'  => 'plantilla-clientes.xlsx',
    'export_title'              => 'Reporte de Clientes',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',
    'export_format_limit_hint'  => 'Máximo :limit filas para este formato. Usa CSV para datasets grandes.',
    'export_no_limit_hint'      => 'Sin límite — recomendado para datasets grandes.',

    // Validation
    'name_required'            => 'El campo nombre es obligatorio.',
    'name_unique'              => 'Este cliente ya existe.',
    'name_duplicate_in_batch'  => 'Nombre duplicado dentro del mismo batch.',
    'is_active_required'       => 'El campo estado es obligatorio.',

    // Edit All
    'edit_all_title'    => 'Cliente — Editar Todo',
    'edit_all_subtitle' => 'Edita nombre y estado de muchos clientes a la vez. Click "Guardar todo" para confirmar, "Cancelar" para descartar.',
    'edit_all_changes'  => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all' => 'Guardar todo',
    'edit_all_discard'  => 'Descartar cambios',
    'edit_all_no_results' => 'No hay clientes que coincidan con el filtro.',

    'table_headers' => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],

    // Show — kanban de etapas
    'stages_title'       => 'Etapas del pipeline',
    'stages_empty'       => 'Este pipeline no tiene etapas configuradas.',
    'stage_won'          => 'GANADA',
    'stage_lost'         => 'PERDIDA',
    'stage_open_deals'   => 'Deals abiertos',
    'stage_total_value'  => 'Valor total',
    'stage_rot_after'    => 'Alerta tras',

    // Onboarding tour
    'tour' => [
        'step1_title' => 'Bienvenido a Pipelines',
        'step1_body'  => 'Configura los embudos de venta (Sales, Renewals, Onboarding...). Cada pipeline tiene sus propias etapas. Tour rápido en menos de 1 minuto.',
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra pipelines por nombre, estado, fechas e ID. Los filtros activos aparecen como chips arriba de la tabla.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic. Cada usuario tiene las suyas propias.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección. Las marcadas como "obligatorias" no se pueden ocultar.',
        'step5_title' => 'Exportar & Importar',
        'step5_body'  => 'Exporta a Excel/PDF/Word en segundo plano — se te notificará cuando esté listo. Importa desde Excel/CSV con vista previa antes de confirmar.',
        'step6_title' => 'Editar muchos a la vez',
        'step6_body'  => '"Editar todo" permite modificar nombre y estado de varios pipelines juntos. Después se confirman todos los cambios en un solo guardado.',
        'step7_title' => 'Favoritos',
        'step7_body'  => 'La estrella marca un pipeline como favorito. Los favoritos aparecen siempre arriba del listado y cada usuario tiene los suyos.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes — aparece una barra para activar, desactivar, eliminar o restaurar. Los lotes grandes se procesan en segundo plano.',
        'step9_title' => '¿Necesitas un repaso?',
        'step9_body'  => 'Reabre este tour cuando quieras con el botón de ayuda. También tienes "Recientes" en el menú del avatar — los últimos pipelines que viste.',
    ],
];
