<?php

return [
    // Titles
    'singular'      => 'Usuario',
    'plural'        => 'Usuarios',
    'record'        => 'usuario',
    'records'       => 'usuarios',
    'new'           => 'Crear usuario',

    // Page titles
    'index_title'   => 'Usuarios',
    'index_subtitle'=> 'Gestiona los usuarios del workspace.',
    'create_title'  => 'Crear usuario',
    'create_subtitle'=> 'Completa los datos para crear un nuevo usuario.',
    'show_title'    => 'Información del usuario',
    'edit_title'    => 'Editar usuario',
    'edit_subtitle' => 'Modifica los datos del usuario.',
    'delete_title'  => 'Eliminar usuario',
    'delete_subtitle'=> 'Esta acción mueve el usuario a la papelera.',
    'trash_title'   => 'Papelera de usuarios',
    'trash_subtitle'=> 'Usuarios eliminados (solo super puede gestionar).',

    // Columns
    'id'            => 'N°',
    'name'          => 'Nombre',
    'name_hint'     => 'Nombre completo del usuario tal como aparecerá en la interfaz.',
    'email'         => 'Correo',
    'email_hint'    => 'Email único usado para iniciar sesión y recibir notificaciones.',
    'password'      => 'Contraseña',
    'password_hint' => 'Mínimo 6 caracteres. El usuario podrá cambiarla después.',
    'password_confirmation' => 'Confirmar contraseña',
    'photo'         => 'Foto',
    'is_active'     => 'Estado',
    'is_active_hint'=> 'Si está inactivo, el usuario no podrá iniciar sesión.',
    'role'          => 'Perfil',
    'role_hint'     => 'Define qué módulos y acciones puede ejecutar el usuario.',
    'tenant'        => 'Workspace',
    'tenant_hint'   => 'Workspace al que pertenece. Solo super puede asignarlo.',
    'country_hint'  => 'País del usuario; se usa para formato de fechas y zona horaria por defecto.',
    'locale_hint'   => 'Idioma y formato regional preferido en la interfaz.',
    'slug'          => 'Slug',
    'deleted_at'    => 'Eliminado el',
    'deleted_by'    => 'Eliminado por',
    'reason'        => 'Motivo',

    // Tabs / sections
    'tab_general'   => 'Información general',
    'tab_audit'     => 'Historial',
    'general_info'  => 'Información general',

    // Filters
    'filter_name'      => 'Nombre',
    'filter_email'     => 'Correo',
    'filter_status'    => 'Estado',
    'filter_workspace' => 'Workspace',
    'search_placeholder' => 'Buscar por nombre…',

    // Actions
    'view'             => 'Ver',
    'edit'             => 'Editar',
    'delete'           => 'Eliminar',
    'view_hint'        => 'Ver detalles del usuario',
    'edit_hint'        => 'Modificar el usuario',
    'delete_hint'      => 'Eliminar (queda en papelera)',
    'create_first'     => 'Crear primer usuario',
    'detail_drawer'    => 'Detalle del usuario',
    'no_users'         => 'No hay usuarios todavía.',
    'no_users_with_filters' => 'No se encontraron usuarios con esos filtros.',

    // Mensajes flash
    'created'       => 'Usuario creado.',
    'saved'         => 'Usuario actualizado.',
    'deleted'       => 'Usuario eliminado.',
    'restored'      => 'Usuario restaurado.',

    // Export / Import
    'export_filename'           => 'exportacion_usuarios',
    'import_template_filename'  => 'plantilla-usuarios.xlsx',
    'export_title'              => 'Reporte de Usuarios',
    'export_limit_exceeded'     => 'El export en :format excede el límite (:count filas vs :limit máximo). Usa CSV para datasets grandes (sin límite).',

    // Validación delete
    'deleted_description_required' => 'Indica el motivo del borrado.',
    'deleted_description_min'      => 'El motivo debe tener al menos 3 caracteres.',
    'name_unique'                  => 'Hay nombres de usuario duplicados en este lote.',
    'deleted_description_max'      => 'El motivo no puede superar los 1000 caracteres.',

    // Confirms
    'delete_about'         => 'Vas a eliminar el usuario ":name". Quedará en papelera.',
    'delete_self_blocked'  => 'No puedes eliminar tu propia cuenta.',
    'edit_all_title'      => 'Usuario - Editar Todo',
    'edit_all_subtitle'   => 'Edita nombre y estado de muchos usuarios a la vez. El email se cambia uno por uno desde el formulario.',
    'edit_all_changes'    => '{0} Sin cambios|{1} 1 cambio pendiente|[2,*] :count cambios pendientes',
    'edit_all_save_all'   => 'Guardar todo',
    'edit_all_discard'    => 'Descartar cambios',
    'edit_all_no_results' => 'No hay usuarios que coincidan con el filtro.',
    'table_headers'       => [
        'editable_name'   => 'Nombre (editable)',
        'editable_status' => 'Estado (editable)',
    ],
    'tour' => [
        'step2_title' => 'Filtros',
        'step2_body'  => 'Busca y filtra usuarios por nombre, email, estado, rol y fechas. Los filtros activos aparecen como chips.',
        'step3_title' => 'Vistas guardadas',
        'step3_body'  => 'Guarda tu combinacion favorita de filtros + columnas + orden y aplicala despues con un clic.',
        'step4_title' => 'Columnas',
        'step4_body'  => 'Muestra/oculta columnas y se recuerda tu eleccion. Las obligatorias no se pueden ocultar.',
        'step5_title' => 'Exportar e Importar',
        'step5_body'  => 'Exporta el listado a CSV. Importa usuarios desde un archivo (requiere plan con operaciones masivas).',
        'step6_title' => 'Editar todo',
        'step6_body'  => 'Edita nombre y estado de varios usuarios en una sola pantalla y guarda todo junto.',
        'step8_title' => 'Operaciones masivas',
        'step8_body'  => 'Selecciona filas con los checkboxes para activar, desactivar o eliminar varios usuarios a la vez.',
        'step_audit_title' => 'Logs del sistema',
        'step_audit_body'  => 'Historial de cambios de usuarios: quien, que y cuando. Util para auditorias.',
    ],
];