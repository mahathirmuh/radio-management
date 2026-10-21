<?php

// Why a page or an action is refused: the role has not been given the menu, or not the permission (CheckMenuPermission);
// the account has been set to inactive (SignOutInactive).

return [
    'no_menu' => 'You do not have access to this menu.',
    'no_permission' => 'You are not allowed to perform this action.',
    'inactive' => 'This account is inactive.',
];
