<?php

return [
    'singular'              => 'Perfil',
    'plural'                => 'Perfiles',
    'new'                   => 'Nuevo perfil',
    'edit_title'            => 'Editar perfil',
    'index_subtitle'        => 'Define qué puede hacer cada tipo de usuario en tu workspace.',
    'form_create_hint'      => 'Crea un perfil con permisos específicos para tu equipo.',

    'name'                  => 'Nombre',
    'name_hint'             => 'Identificador del perfil; aparece al asignarlo a usuarios.',
    'description'           => 'Descripción',
    'description_hint'      => 'Breve resumen de para qué sirve este perfil.',
    'tenant'                => 'Workspace',
    'tenant_placeholder'    => 'Workspace al que pertenece',
    'tenant_hint'           => 'Deja vacío para crear un perfil global (super only).',
    'permissions'           => 'Permisos',
    'permissions_count'     => 'Permisos',
    'permissions_hint'      => 'Marcá los permisos que tendrá este perfil. Se agrupan por módulo.',
    'no_permissions_available' => 'No hay permisos disponibles para asignar.',
    'users_count'           => 'Usuarios',

    'confirm_delete'        => '¿Eliminar este perfil?',
    'protected'             => 'Protegido',
    'tag_system'            => 'Sistema',
    'tag_global'            => 'Global',
    'no_permissions'        => 'Este perfil no tiene permisos asignados.',

    // Index/Filters
    'search_placeholder'    => 'Buscar por nombre…',
    'is_active'             => 'Estado',
    'scope'                 => 'Alcance',
    'confirm_duplicate'     => '¿Duplicar este perfil con sus permisos?',
    'name_unique'           => 'Hay nombres de perfil duplicados en este lote.',

    // Delete
    'delete_title'          => 'Eliminar perfil',
    'delete_warning_title'  => '¿Eliminar este perfil?',
    'delete_warning_desc'   => 'El perfil pasa a la papelera. Podés restaurarlo en los próximos 30 días desde Trash. Los users que tengan este rol asignado lo pierden.',
    'delete_blocked_title'  => 'No se puede eliminar',
    'delete_blocked_users'  => 'No se puede eliminar un perfil con usuarios asignados.',
    'delete_blocked_users_count' => 'Este perfil está asignado a :count usuario(s). Reasignalos a otro perfil primero.',
    'bulk_delete_about'     => 'Vas a eliminar :count perfiles.',

    // Trash
    'trash_subtitle'        => 'Perfiles eliminados (recuperables por 30 días).',
    'trash_super_warning'   => 'Como super puedes eliminar permanentemente. Esta acción NO se puede revertir.',
    'force_delete_warning'  => 'El perfil se borra de la DB junto con sus asignaciones. NO se puede recuperar.',

    // Onboarding tour (6 pasos: filters, saved-views, columns, favorites, bulk, system_logs)
    'tour' => [
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra perfiles por nombre, estado, alcance y fechas. Los filtros activos aparecen como chips.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinación favorita de filtros + columnas + orden y aplícala después con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu elección. Las marcadas como obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e Importar',
        'step5_body'  => 'Exporta el listado a CSV. Importa perfiles desde un archivo (requiere plan con operaciones masivas).',
        'step6_title' => 'Editar todo',
        'step6_body'  => 'Edita nombre, descripcion y estado de varios perfiles en una sola pantalla y guarda todo junto.',
        'step7_title' => 'Favoritos ★',
        'step7_body'  => 'La estrella ★ marca un perfil como favorito. Los favoritos aparecen siempre arriba del listado.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes para activar, desactivar o eliminar varios perfiles a la vez.',
        'step_audit_title' => 'Logs del sistema',
        'step_audit_body'  => 'Historial de cambios de perfiles: quién, qué y cuándo. Útil para auditorías.',
    ],
    'export_title'        => 'Reporte de Perfiles',
    'edit_all_title'      => 'Perfil - Editar Todo',
    'edit_all_subtitle'   => 'Edita nombre, descripcion y estado de muchos perfiles a la vez. Los perfiles del sistema no son editables.',
    'edit_all_changes'    => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all'   => 'Guardar todo',
    'edit_all_discard'    => 'Descartar cambios',
    'edit_all_no_results' => 'No hay perfiles que coincidan con el filtro.',
    'table_headers'       => [
        'editable_name'        => 'Nombre (editable)',
        'editable_description' => 'Descripcion (editable)',
        'editable_status'      => 'Estado (editable)',
    ],
];