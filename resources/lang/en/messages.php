<?php

return [
    // Headers
    'id'                    => 'ID',
    'singular'              => 'Message',
    'plural'                => 'Messages',
    'record'                => 'message',
    'records'               => 'messages',
    'new'                   => 'Create message',
    'inbox'                 => 'Inbox',
    'new_message'           => 'New message',
    'edit_message'          => 'Edit message',
    'message_detail'        => 'Message detail',
    'unread'                => 'Unread',
    'read'                  => 'Read',
    'empty_bell'            => 'You have no messages',
    'empty_bell_hint'       => 'Announcements, notices and threads from the administrator will appear here.',
    'view_inbox'            => 'View inbox',

    // Fields
    'subject'               => 'Subject',
    'subject_hint'          => 'Message title; recipients see it in their inbox list.',
    'body'                  => 'Body',
    'body_hint'             => 'Message content. Supports rich text (bold, lists, links).',
    'audience'              => 'Audience',
    'audience_type'         => 'Audience type',
    'audience_type_hint'    => 'Who receives the message: everyone, a workspace, or a single user.',
    'audience_target'       => 'Recipient',
    'audience_global'       => 'All users (Global)',
    'audience_tenant'       => 'Workspace',
    'audience_user'         => 'User',
    'audience_select_tenant'=> 'Select workspace',
    'audience_select_tenant_hint' => 'Workspace whose users will receive the message.',
    'audience_select_user'  => 'Select user',
    'audience_select_user_hint' => 'Individual user who will receive the message.',
    'allow_replies'         => 'Allow replies / discussion',
    'allow_replies_hint'    => 'If enabled, recipients can reply to the message.',
    'is_active'             => 'Active',
    'is_active_hint'        => 'If disabled, the message is hidden even when published.',
    'is_active_required'    => 'Specify whether the message stays active or inactive.',
    'published_at'          => 'Published at',
    'expires_at'            => 'Expires at',
    'expires_at_hint'       => 'Date the message stops being shown. Empty means no expiration.',
    'no_expiration'         => 'No expiration',
    'created_by'            => 'Created by',
    'created_at'            => 'Created at',
    'status_published'      => 'Published',
    'status_draft'          => 'Draft',
    'status_expired'        => 'Expired',

    // Stats
    'recipients_count'      => 'Recipients',
    'read_count'            => 'Read',
    'replies_count'         => 'Replies',
    'read_pct'              => '% read',

    // Actions
    'save_draft'            => 'Save draft',
    'save_and_publish'      => 'Save and publish',
    'publish_now'           => 'Publish now',
    'reply'                 => 'Reply',
    'send_reply'            => 'Send reply',
    'mark_all_read'         => 'Mark all as read',
    'view_message'          => 'View message',
    'duplicate'             => 'Duplicate message',
    'restore_hint'          => 'Restore the message to its previous state.',

    // Filters
    'filter_subject'        => 'Search by subject',
    'filter_audience'       => 'Filter by audience',
    'filter_active'         => 'Status',
    'only_unread'           => 'Unread',
    'only_repliable'        => 'Repliable',
    'tab_all'               => 'All',
    'badge_new'             => 'New',

    // Empty states
    'inbox_empty_title'     => 'No messages',
    'inbox_empty_hint'      => 'When you receive an announcement, it will appear here.',
    'messages_empty_title'  => 'No messages yet',
    'messages_empty_hint'   => 'Create your first announcement to send it to your users.',
    'replies_empty'         => 'No replies yet.',

    // Flash messages
    'created_success'       => 'Message created successfully.',
    'updated_success'       => 'Message updated.',
    'deleted_success'       => 'Message deleted.',
    'published_success'     => 'Message published.',
    'reply_sent'            => 'Reply sent.',
    'mark_all_read_success' => ':count messages marked as read.',

    // Validation
    'subject_required'           => 'Subject is required.',
    'subject_unique'             => 'A message with this subject already exists.',
    'body_required'              => 'Body is required.',
    'audience_type_required'     => 'Select the audience.',
    'audience_id_required'       => 'Select a recipient.',
    'reply_body_required'        => 'Write a reply before sending.',
    'reply_body_max'             => 'Reply cannot exceed 5000 characters.',
    'confirm_subject_mismatch'   => 'Subject does not match.',

    // Errors
    'not_a_recipient'      => 'You do not have access to this message.',
    'replies_not_allowed'  => 'Replies are disabled on this message.',

    // Delete confirmation
    'delete_title'         => 'Delete message',
    'delete_warning'       => 'This soft-deletes the message. To confirm, type the exact subject.',
    'delete_subject_label' => 'Confirm subject',
    'delete_reason_label'  => 'Reason',

    // In-app notifications
    'notify_new_reply_title' => 'New reply',
    'notify_new_reply_body'  => ':user replied to "  :subject "',

    // Tier 1: exports / imports / edit-all
    'export_title'              => 'Messages report',
    'export_filename'           => 'messages',
    'export_limit_exceeded'     => 'The report exceeds the limit (:count > :limit) for the :format format.',
    'import_template_filename'  => 'messages_template.xlsx',
    'edit_all_title'            => 'Bulk edit messages',
    'edit_all_subtitle'         => 'Edit subject and status of multiple messages at once. Click "Save all" to confirm.',
    'edit_all_changes'          => '{0} No changes|{1} 1 pending change|[2,*] :count pending changes',
    'edit_all_save_all'         => 'Save all',
    'edit_all_discard'          => 'Discard changes',
    'edit_all_no_results'       => 'No messages to edit with the current filters.',

    // Bulk + flash extra
    'bulk_in_queue'             => 'Bulk operation queued (:count records).',

    // Tour onboarding
    'tour' => [
        'step_filters_title'   => 'Filters',
        'step_filters_body'    => 'Filter messages by subject, audience, status and date. You can combine multiple filters.',
        'step_export_title'    => 'Export and Import',
        'step_export_body'     => 'Download messages as CSV, Excel, PDF or Word. You can also import messages from a template.',
        'step_favorites_title' => 'Favorites',
        'step_favorites_body'  => 'Star your priority messages; they will appear at the top of the list.',
        'step_bulk_title'      => 'Bulk operations',
        'step_bulk_body'       => 'Select multiple rows to activate, deactivate or delete them in bulk.',
    ],
];
