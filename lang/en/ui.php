<?php

// The texts of the frame of every page (top bar, sidebar, bell, footer) and of the shared helpers of the pages' scripts
// (main/components/js): tables, pagination, pickers, dialogs, the session's warning.

return [
    'ok' => 'OK',
    'cancel' => 'Cancel',
    'close' => 'Close',
    'sorry' => 'Sorry!',

    'language' => [
        'title' => 'Language',
    ],

    'topbar' => [
        'toggle_sidebar' => 'Expand or collapse the menu',
        'breadcrumb' => 'Breadcrumb',
        'user_menu' => 'User menu',
        'notifications' => 'Notifications',
        'sign_out' => 'Sign out',
    ],

    'sidebar' => [
        'label' => 'Main menu',
        'open' => 'Open the menu',
        'sign_out' => 'Sign out (:name)',
        'edit_requests' => '{1} :count request waiting|[2,*] :count requests waiting',
        'attention' => '{1} :count item needs attention|[2,*] :count items need attention',
    ],

    'bell' => [
        'title' => 'Notifications',
        'read_all' => 'Mark all as read',
        'empty' => 'No notifications yet',
        'see_all' => 'See all notifications',
        'unread' => 'Unread',
    ],

    'changelog' => [
        'open' => 'See what changed in each version',
        'title' => 'Version history',
        'sub' => 'Running version: v:version',
        'loading' => 'Loading the version history…',
        'failed' => 'The version history could not be loaded. Close it and open it again to try once more.',
        'empty' => 'No release notes yet.',
        'current' => 'This version',
        // The timeline: the kinds of release, the band where a major version begins, and the kinds of change (the headings
        // of CHANGELOG.md) in the legend.
        'minor' => 'Minor release',
        'patch' => 'Patch release',
        'major' => 'Major release',
        'era' => 'Version :major',
        'kinds' => [
            'added' => 'Added',
            'changed' => 'Changed',
            'fixed' => 'Fixed',
            'removed' => 'Removed',
            'security' => 'Security',
            'deprecated' => 'Deprecated',
        ],
    ],

    'table' => [
        'loading' => 'Loading...',
        'empty' => 'No data found',
        'showing' => 'Showing',
        'of' => 'of',
        'rows' => 'Rows per page',
        'previous' => 'Previous page',
        'next' => 'Next page',
        // How the pages write 1011: 1,011.
        'thousands' => ',',
    ],

    'pickers' => [
        'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'short_months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        // Monday first.
        'weekdays' => ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
        'all' => 'All',
        'pick_all' => 'Select all',
        'clear' => 'Clear',
        'any_date' => 'Any date',
        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'today' => 'Today',
    ],

    'session' => [
        'hours' => '{1} :count hour|[2,*] :count hours',
        'minutes' => '{1} :count minute|[2,*] :count minutes',
        'ending' => 'Your session is about to end',
        'idle' => 'There has been no activity for almost :minutes minutes.',
        'maximum' => 'For security a session lasts at most :maximum; save your work.',
        'countdown' => 'You will be signed out automatically in :countdown.',
        'stay' => 'Stay signed in',
        'sign_in_again' => 'Sign in again now',
        'sign_out' => 'Sign out',
        'later' => 'Later',
        'ended' => 'Session ended',
        'ended_text' => 'Your session has ended. Please sign in again.',
        'sign_in' => 'Sign in',
    ],

    'flash' => [
        'success' => 'Success',
        'error' => 'Error',
        'warning' => 'Warning',
    ],

    'loader' => [
        'title' => 'Processing your document',
        'upload' => 'Your upload is being handled. Do not leave this page yet, or the progress may be lost.',
        'download' => 'Almost there. Your data is being fetched; stay on this page to avoid delays.',
    ],
];
