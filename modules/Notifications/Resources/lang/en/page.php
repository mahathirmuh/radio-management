<?php

// The page "Notifikasi" (Notifications::index): every notification of whoever is signed in, by day. The title, "Mark all
// as read" and "Unread" are the bell's words (lang/<code>/ui.php); a notification's own title and text are kept in the
// language they were written in.

return [

    'tabs' => 'Notifications shown',
    'all' => 'All',
    'not_ready' => 'Notifications are not switched on in this database yet: run <code>php artisan migrate</code> first.',
    'empty' => [
        'all_read' => 'All notifications have been read',
        'all_read_text' => 'Nothing is waiting to be read.',
        'none' => 'No notifications yet',
        'none_text' => 'Notifications appear here when there is something you need to know.',
    ],
    // The rows by day.
    'groups' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'earlier' => 'Earlier',
    ],
    // The word of each kind, beside its title.
    'kinds' => [
        'edit_requested' => 'Approval',
        'edit_decided' => 'Request',
        'token_expiring' => 'API token',
        'register_weekly' => 'Register',
    ],
    'range' => ':from – :to of :total',
    'previous' => 'Previous',
    'next' => 'Next',

    // The words of the page's script.
    'script' => [

        'marked' => 'All notifications marked as read.',
        'failed' => 'The notifications could not be marked as read. Try again.',
    ],
];
