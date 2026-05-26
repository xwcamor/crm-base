<?php

return [
    // Page
    'title'              => 'My profile',
    'subtitle'           => 'Manage your personal info and account settings.',

    // Tabs
    'tab_info'           => 'Information',
    'tab_password'       => 'Password',
    'tab_preferences'    => 'Preferences',

    // Info section
    'name'               => 'Name',
    'email'              => 'Email',
    'email_readonly_hint' => 'Email identifies your account and cannot be changed here. Contact your administrator if you need to change it.',
    'tenant'             => 'Organization',
    'country'            => 'Country',
    'timezone'           => 'Timezone',
    'timezone_hint'      => 'Leave "Use workspace\'s" to inherit from your workspace or country.',
    'timezone_inherit'   => 'Use workspace\'s',
    'preferred_currency_code'        => 'Preferred currency',
    'preferred_currency_placeholder' => 'Inherit from workspace',
    'preferred_currency_hint'        => 'Overrides the workspace default currency for your personal display only. Leave empty to inherit from tenant.',
    'roles'              => 'Roles',
    'member_since'       => 'Member since',
    'save_info'          => 'Save information',

    // Password section
    'password_title'     => 'Change password',
    'password_subtitle'  => 'A strong password has at least 8 characters, letters and numbers.',
    'current_password'   => 'Current password',
    'new_password'       => 'New password',
    'confirm_password'   => 'Confirm new password',
    'change_password'    => 'Change password',
    'no_password_hint'   => 'You don\'t have a local password yet (you signed in with Google). Set one to also be able to log in with email.',
    'password_updated'   => 'Password updated successfully.',
    'current_password_incorrect' => 'The current password is incorrect.',

    // Preferences section
    'preferences_title'  => 'App preferences',
    'preferences_hint'   => 'These preferences can also be changed from the top bar.',
    'preferred_language' => 'Preferred language',
    'preferred_theme'    => 'Theme',
    'tour_status'        => 'Onboarding tours',
    'tours_completed'    => '{0} :count tours completed|{1} 1 tour completed|[2,*] :count tours completed',
    'reset_tours'        => 'Reset tours',
    'reset_tours_done'   => 'Tours reset. You\'ll see them again next time you enter each module.',
];
