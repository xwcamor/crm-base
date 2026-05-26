<?php

return [
    'singular' => 'Stage',
    'plural'   => 'Stages',

    'manage_title'    => 'Manage stages',
    'manage_subtitle' => 'Define your kanban columns: name, color, probability and behavior.',
    'add'             => 'Add stage',
    'add_first'       => 'Create first stage',
    'edit'            => 'Edit stage',
    'delete'          => 'Delete stage',
    'delete_confirm'  => 'Delete stage ":name"? This is reversible (trash).',
    'empty'           => 'This pipeline has no stages configured yet.',
    'no_pipeline_warning' => 'You must be viewing a pipeline (Show) to manage its stages.',

    // Fields
    'name'              => 'Name',
    'name_placeholder'  => 'E.g.: Prospecting, Qualification, Proposal...',
    'name_hint'         => 'The name your team will see in the kanban. Free text — use whatever language and terms your team prefers.',
    'description'       => 'Description',
    'description_hint'  => 'Internal notes about when a deal moves to this stage.',
    'color'             => 'Color',
    'color_hint'        => 'Column color in the kanban. Tip: gray→blue→orange→green for advancing stages.',
    'probability_pct'   => 'Close probability (%)',
    'probability_hint'  => '0-100. Used for weighted forecast: forecast = Σ deal_value × probability. Won=100, Lost=0.',
    'rot_days'          => 'Stale alert (days)',
    'rot_days_hint'     => 'If a deal sits in this stage longer than N days, it gets flagged as "rotting". 0 = no alert.',
    'is_won'            => 'Won stage (WON)',
    'is_won_hint'       => 'Mark this stage as positive-terminal. Deals reaching it count as closed-won.',
    'is_lost'           => 'Lost stage (LOST)',
    'is_lost_hint'      => 'Mark this stage as negative-terminal. Deals reaching it count as closed-lost.',
    'is_active'         => 'Active',
    'is_active_hint'    => 'If inactive, it does not show as a target when moving deals (but existing deals in it remain visible).',

    // Validation / errors
    'name_unique'         => 'A stage with that name already exists in this pipeline.',
    'won_lost_exclusive'  => 'A stage cannot be both WON and LOST.',
    'has_deals'           => 'Cannot delete: the stage has :count deal(s). Move them to another stage first.',

    // Messages
    'created'    => 'Stage created.',
    'saved'      => 'Stage updated.',
    'deleted'    => 'Stage deleted.',
    'reordered'  => 'Stage order updated.',

    // Tags
    'tag_won'   => 'WON',
    'tag_lost'  => 'LOST',

    // Actions
    'save'           => 'Save',
    'cancel'         => 'Cancel',
    'move_up'        => 'Move up',
    'move_down'      => 'Move down',
    'drag_to_reorder' => 'Drag to reorder',
];
