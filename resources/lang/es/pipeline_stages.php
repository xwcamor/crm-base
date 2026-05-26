<?php

return [
    'singular' => 'Etapa',
    'plural'   => 'Etapas',

    'manage_title'    => 'Gestionar etapas',
    'manage_subtitle' => 'Define las columnas del kanban: nombre, color, probabilidad y comportamiento.',
    'add'             => 'Agregar etapa',
    'add_first'       => 'Crear primera etapa',
    'edit'            => 'Editar etapa',
    'delete'          => 'Eliminar etapa',
    'delete_confirm'  => '¿Eliminar la etapa ":name"? Esta acción es reversible (papelera).',
    'empty'           => 'Este pipeline no tiene etapas configuradas todavía.',
    'no_pipeline_warning' => 'Necesitas estar viendo un pipeline en el detalle (Show) para gestionar sus etapas.',

    // Campos
    'name'              => 'Nombre',
    'name_placeholder'  => 'Ej: Prospección, Calificación, Propuesta...',
    'name_hint'         => 'El nombre que verá tu equipo en el kanban. Es texto libre — usa el idioma y términos que tu equipo prefiera.',
    'description'       => 'Descripción',
    'description_hint'  => 'Notas internas sobre cuándo un deal pasa a esta etapa.',
    'color'             => 'Color',
    'color_hint'        => 'Color de la columna en el kanban. Sugerencia: gris→azul→naranja→verde para etapas que avanzan.',
    'probability_pct'   => 'Probabilidad de cierre (%)',
    'probability_hint'  => '0-100. Usado para el forecast ponderado: forecast = Σ valor_deal × probabilidad. Won=100, Lost=0.',
    'rot_days'          => 'Alerta de estancamiento (días)',
    'rot_days_hint'     => 'Si un deal lleva más de N días en esta etapa, se marca como "rotting" (estancado). 0 = sin alerta.',
    'is_won'            => 'Etapa de ganada (WON)',
    'is_won_hint'       => 'Marca esta etapa como terminal positiva. Los deals que llegan aquí se cuentan como cerrados-ganados.',
    'is_lost'           => 'Etapa de perdida (LOST)',
    'is_lost_hint'      => 'Marca esta etapa como terminal negativa. Los deals que llegan aquí se cuentan como cerrados-perdidos.',
    'is_active'         => 'Activa',
    'is_active_hint'    => 'Si está inactiva, no aparece como destino al mover deals (pero los existentes en ella siguen visibles).',

    // Validaciones / errores
    'name_unique'         => 'Ya existe una etapa con ese nombre en este pipeline.',
    'won_lost_exclusive'  => 'Una etapa no puede ser WON y LOST a la vez.',
    'has_deals'           => 'No se puede eliminar: la etapa tiene :count deal(s). Muévelos a otra etapa primero.',

    // Mensajes
    'created'    => 'Etapa creada.',
    'saved'      => 'Etapa actualizada.',
    'deleted'    => 'Etapa eliminada.',
    'reordered'  => 'Orden de etapas actualizado.',

    // Tags
    'tag_won'   => 'GANADA',
    'tag_lost'  => 'PERDIDA',

    // Acciones
    'save'           => 'Guardar',
    'cancel'         => 'Cancelar',
    'move_up'        => 'Mover arriba',
    'move_down'      => 'Mover abajo',
    'drag_to_reorder' => 'Arrastra para reordenar',
];
