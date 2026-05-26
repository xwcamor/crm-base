<?php

return [
    'singular' => 'Activity',
    'plural'   => 'Activities',

    'index_title'    => 'My activities',
    'index_subtitle' => 'Calls, emails, meetings, notes and pending tasks.',

    'panel_title'      => 'Activities',
    'panel_empty'      => 'No activities yet. Add the first one to start tracking.',
    'add'              => 'Add activity',
    'edit'             => 'Edit activity',
    'delete'           => 'Delete activity',
    'delete_confirm'   => 'Delete this activity? This cannot be easily undone.',

    'type'             => 'Type',
    'types' => [
        'note'    => 'Note',
        'call'    => 'Call',
        'email'   => 'Email',
        'meeting' => 'Meeting',
        'task'    => 'Task',
    ],

    'subject'         => 'Subject',
    'subject_hint'    => 'Short title that identifies the activity. Shows up in the timeline.',
    'subject_placeholder' => 'E.g.: Follow-up call, Product demo, Proposal sent...',
    'body'            => 'Details',
    'body_hint'       => 'Detailed description of the activity. For emails you can paste the sent content; for calls what was discussed; for notes any relevant info.',
    'body_placeholder' => 'Describe the activity, what was discussed, next steps...',

    'due_at'          => 'Date and time',
    'due_at_hint'     => 'When this activity is scheduled (for meetings) or when it is due (for tasks).',
    'completed_at'    => 'Completed',
    'duration_min'    => 'Duration (minutes)',
    'duration_min_hint' => 'Estimated or actual duration in minutes. Optional.',
    'location'        => 'Location / URL',
    'location_hint'   => 'Physical address or video call link (Zoom, Meet, Teams). Optional.',
    'location_placeholder' => 'https://meet.google.com/... or "Conf room 3rd floor"',

    'outcome'         => 'Outcome',
    'outcome_hint'    => 'What happened in the call. Feeds channel effectiveness reports.',
    'outcomes' => [
        'answered'   => 'Answered',
        'voicemail'  => 'Left voicemail',
        'no_answer'  => 'No answer',
        'rejected'   => 'Rejected',
    ],

    'priority'        => 'Priority',
    'priority_hint'   => 'How urgent it is to complete this task. High priority appears first in your agenda.',
    'priorities' => [
        'low'    => 'Low',
        'medium' => 'Medium',
        'high'   => 'High',
    ],

    'status'          => 'Status',
    'status_pending'  => 'Pending',
    'status_completed'=> 'Completed',
    'status_overdue'  => 'Overdue',

    'filter_status'   => 'Status',
    'filter_status_all'       => 'All',
    'filter_status_pending'   => 'Pending',
    'filter_status_completed' => 'Completed',
    'filter_status_overdue'   => 'Overdue',
    'filter_scope_all'  => 'All',
    'filter_scope_mine' => 'Mine only',
    'filter_search'     => 'Search...',
    'filter_type_all'   => 'All types',
    'filter_priority_all' => 'All priorities',

    'filter_section_pipeline'        => 'Filter by pipeline',
    'filter_pipeline_all'            => 'All pipelines',
    'filter_pipeline_placeholder'    => 'Pipeline',
    'filter_stage_all'               => 'All stages',
    'filter_stage_placeholder'       => 'Stage',
    'filter_deal_status_all'         => 'All deal statuses',
    'filter_deal_status_placeholder' => 'Deal status',

    'col_parent'                     => 'Associated to',
    'col_pipeline'                   => 'Pipeline / Stage',
    'col_quote'                      => 'Quote',

    'mark_complete'   => 'Mark as completed',
    'mark_pending'    => 'Reopen',
    'save'            => 'Save',
    'cancel'          => 'Cancel',

    // Quick Note widget — always visible above tabs
    'quick_note_title'       => 'Add quick note',
    'quick_note_placeholder' => 'Write a note about this entity (call, reminder, observation...)',
    'quick_note_hint'        => 'Ctrl + Enter to save',
    'quick_note_save'        => 'Save note',
    'quick_note_success'     => 'Note added.',
    'quick_note_error'       => 'Could not save the note.',

    'created'         => 'Activity created.',
    'saved'           => 'Activity updated.',
    'deleted'         => 'Activity deleted.',
    'completed'       => 'Activity marked as completed.',
    'reopened'        => 'Activity reopened.',

    'field_required'    => 'This field is required for this type of activity.',
    'parent_not_found'  => 'The associated entity does not exist.',

    'logged_by'       => 'Logged by',
    'overdue_label'   => 'OVERDUE',
    'completed_on'    => 'Completed on :date',
    'due_on'          => 'Due on :date',
    'duration_label'  => ':min min',

    'widget_title'      => 'My agenda',
    'widget_empty'      => 'No pending activities.',
    'widget_see_all'    => 'See all',

    'parent_Deal'     => 'Deal',
    'parent_Company'  => 'Company',
    'parent_Contact'  => 'Contact',

    'attachment'                => 'Attachment',
    'attachment_hint'           => 'Attach a PDF, image, document or any relevant file (max 10 MB). Useful for proposals, signed contracts, screenshots.',
    'attach_file'               => 'Attach file',
    'download_attachment'       => 'Download attachment',
    'replace_attachment_hint'   => 'Uploading a new one replaces it.',

    'related_quote'             => 'Related quote',
    'related_quote_hint'        => 'Link this activity with a deal quote. Useful when logging a formal proposal send — you can trace which quote was sent.',
    'related_quote_placeholder' => 'Select a quote from the deal',
    'quote_link_label'          => 'Quote:',
    'log_send_button'           => 'Log send',

    'view_list'                 => 'List',
    'view_kanban'               => 'Kanban',
    'kanban_overdue'            => 'Overdue',
    'kanban_today'              => 'Today',
    'kanban_this_week'          => 'This week',
    'kanban_later'              => 'Later',
    'kanban_no_date'            => 'No date',
    'kanban_completed'          => 'Completed',
    'kanban_empty_column'       => 'No activities',
];
