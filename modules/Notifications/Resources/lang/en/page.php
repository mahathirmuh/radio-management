<?php

// The page "Notifikasi" (Notifications::index): every notification of whoever is signed in, by day, in tabs. "Mark all
// as read" and "Unread" are the bell's words (lang/<code>/ui.php); a notification's own title and text are kept in the
// language they were written in.

return [
    'tabs' => 'Notifications shown',
    'all' => 'All',
    // The tab of each category (UserNotification::CATEGORIES), also the word beside a title.
    'categories' => [
        'radio' => 'Radio',
        'documents' => 'Documents',
        'system' => 'System',
    ],
    'not_ready' => 'Notifications are not switched on in this database yet: run <code>php artisan migrate</code> first.',
    'empty' => [
        'all_read' => 'All notifications have been read',
        'all_read_text' => 'Nothing is waiting to be read.',
        'none' => 'No notifications yet',
        'none_text' => 'Notifications appear here when there is something you need to know.',
        'none_kind' => 'Nothing here yet',
        'none_kind_text' => 'Notifications of this kind appear here.',
    ],
    // The rows by day, and when each came: "12 minutes ago" today, "Yesterday, 16:40", "17 Oct, 08:00" before.
    'groups' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'earlier' => 'Earlier',
    ],
    'yesterday_at' => 'Yesterday, :time',
    'mark_read' => 'Mark as read',
    'range' => ':from – :to of :total',
    'previous' => 'Previous',
    'next' => 'Next',

    // The words of the page's script.
    'script' => [
        'marked' => 'All notifications marked as read.',
        'failed' => 'The notifications could not be marked as read. Try again.',
        'failed_one' => 'The notification could not be marked as read. Try again.',
    ],
];
