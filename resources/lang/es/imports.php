<?php

return [
    // Modal & sections
    'title'                => 'Importar registros',
    'file'                 => 'Archivo',
    'download_template'    => 'Descargar plantilla',
    'mode'                 => 'Modo de importación',
    'preview_result'       => 'Resultado de la previsualización',
    'errors'               => 'Errores',
    'preview_changes'      => 'Vista previa de cambios',

    // Dropzone
    'drag_file_strong'     => 'Arrastrá tu archivo',
    'drag_or_click'        => 'o haz clic para seleccionar',
    'formats_hint'         => 'Formatos: .xlsx, .xls, .csv · Máximo 10 MB',

    // Modes
    'mode_update_or_create'      => 'Actualizar o crear',
    'mode_update_or_create_hint' => 'Si ya existe un registro con el mismo nombre, lo actualiza. Si no existe, lo crea.',
    'mode_create_only'           => 'Solo crear',
    'mode_create_only_hint'      => 'Crea solo los registros nuevos. Omite los nombres ya existentes.',

    // Stats
    'stat_create'          => 'Crear',
    'stat_update'          => 'Actualizar',
    'stat_skip'            => 'Omitir',
    'stat_errors'          => 'Errores',

    // Action tags
    'action_create'        => 'Crear',
    'action_update'        => 'Actualizar',
    'action_skip'          => 'Omitir',

    // Alerts
    'no_changes'           => 'No hay cambios que aplicar',
    'no_changes_desc'      => 'Todos los registros del archivo ya existen idénticos en la base.',
    'rows_with_problems'   => ':count fila tiene problemas',
    'rows_with_problems_plural' => ':count filas tienen problemas',
    'rows_with_problems_desc'   => 'Estas filas se omitirán al confirmar. Las demás se importarán normalmente.',

    // Errors
    'process_failed'             => 'No se pudo procesar el archivo. Verifica el formato.',
    'err_unique_violation'       => 'El archivo contiene valores que ya existen en otro registro (duplicado de un campo único). Revisa los datos y reinténtalo.',
    'err_not_null_violation'     => 'Falta un campo obligatorio en alguna fila del archivo. Verifica que todas las columnas requeridas estén completas.',
    'err_foreign_key_violation'  => 'Una fila hace referencia a un registro relacionado que no existe (ej. región o locale inválido).',

    // Table columns
    'col_row'              => 'Fila',
    'col_name'             => 'Nombre',
    'col_active'           => 'Activo',
    'col_action'           => 'Acción',
    'col_value'            => 'Valor',
    'col_error'            => 'Error',

    // Buttons
    'preview_import'       => 'Previsualizar import',
    'confirm_import'       => 'Confirmar import (:count)',

    // Row-level error messages
    'err_name_required'    => 'El nombre es obligatorio.',
    'err_name_too_long'    => 'El nombre supera los 255 caracteres.',
    'err_duplicate_in_file' => 'Duplicado: ya aparece en la fila :row de este archivo.',
    'err_base_code_required'  => 'La moneda base es obligatoria (3 letras ISO).',
    'err_quote_code_required' => 'La moneda quote es obligatoria (3 letras ISO).',
    'err_codes_must_differ'   => 'Las monedas base y quote deben ser distintas.',
    'err_rate_required'       => 'La tasa es obligatoria y debe ser mayor a 0.',
    'err_valid_at_required'   => 'La fecha y hora de vigencia es obligatoria.',
    'err_subject_required'  => 'El asunto es obligatorio.',
    'err_subject_too_long'  => 'El asunto supera los 200 caracteres.',
    'err_body_required'     => 'El cuerpo es obligatorio.',
    'err_invalid_audience'  => 'Valor inválido en audience_type. Usa: global, tenant o user.',
    'file_required'         => 'Debes adjuntar un archivo.',
    'file_mimes'            => 'Formatos permitidos: xlsx, xls, csv, txt.',
    'file_max'              => 'El archivo supera el máximo permitido.',

    // Template
    'template_filename'         => 'plantilla-regiones.xlsx',
    'template_author'           => 'Sistema',
    'template_is_active_help'   => 'Valores aceptados: 1, 0, true, false, sí, no, activo, inactivo. Vacío = activo por defecto.',
    'template_sample_1'         => 'América del Sur',
    'template_sample_2'         => 'Europa Oriental',
];
