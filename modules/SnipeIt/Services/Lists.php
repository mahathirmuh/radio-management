<?php

namespace Modules\SnipeIt\Services;

/**
 * What the menu "Snipe-IT" shows: its pages (GROUPS, each a page of the sidebar with a tab per list), and of each list the
 * endpoint of the API and the columns. The columns are all that leaves the server: whatever else Snipe-IT answers (a
 * licence key, a phone number, an address) is never sent to the browser.
 *
 * A column: 'path' (where its value is in a row of the API, dotted), 'type' (how it is shown: text, link, strong, code,
 * number, date, badge, bool, list, changes; asset: the row's picture with the value; open: the button to the row's page,
 * the last column), 'sub' (a second line under it), 'words' (a group of SnipeIt::snipe whose word is shown
 * for the value; also for the second line: 'subWords'), 'tone' (where the value is that colours a badge), 'sort' (what
 * Snipe-IT orders the list by when the column is clicked; checked against Snipe-IT 6.2). Its name is
 * SnipeIt::snipe.columns.<key>.
 */
final class Lists
{
    /** The pages after the overview, in the order of the sidebar, and their lists (tabs) in order: the first opens first. */
    public const GROUPS = [
        'assets' => ['assets'],
        'users' => ['users'],
        'catalog' => ['models', 'categories', 'manufacturers', 'statuslabels', 'suppliers', 'fields', 'fieldsets', 'depreciations'],
        'people' => ['departments', 'locations', 'companies', 'groups'],
        'stock' => ['licenses', 'accessories', 'consumables', 'components', 'kits'],
        'history' => ['activity', 'maintenances'],
    ];

    /** The activity log can be cut to one kind of action: what Snipe-IT stores for it (it shows "create" as "create new"). */
    public const ACTIONS = ['checkout', 'checkin from', 'create', 'update', 'delete', 'audit'];

    /**
     * endpoint: the API's; image: where Snipe-IT's picture of a row is; detail: where in a row the asset is whose detail (a
     * page here, SnipeIt::asset) the row leads to, or the user (SnipeIt::user) with detailRoute snipe-it.users.show;
     * web: else Snipe-IT's own page of a row ({id}: the row's, {item}: the page of the item an entry of the activity log is
     * about, an asset's detail here); order: [column, asc|desc] when nobody chose one; local: Snipe-IT answers the whole list
     * at once (no pages, no search), so pages and search are done here; filters: what the list can be cut by.
     */
    private const LISTS = [
        'assets' => [
            'endpoint' => 'hardware',
            'detail' => 'id',
            'image' => 'image',
            'order' => ['updated', 'desc'],
            'filters' => ['category', 'status'],
            'columns' => [
                // The picture, the tag, and the name under it; the button to the detail at the end of the row.
                'asset_tag' => ['path' => 'asset_tag', 'type' => 'asset', 'sub' => 'name', 'sort' => 'asset_tag'],
                'serial' => ['path' => 'serial', 'type' => 'code', 'sort' => 'serial'],
                'model' => ['path' => 'model.name', 'sort' => 'model'],
                'category' => ['path' => 'category.name', 'sort' => 'category'],
                'status' => ['path' => 'status_label.name', 'type' => 'badge', 'tone' => 'status_label.status_meta', 'sort' => 'status_label'],
                'holder' => ['path' => 'assigned_to.name', 'sub' => 'assigned_to.type', 'subWords' => 'kinds', 'sort' => 'assigned_to'],
                'location' => ['path' => 'location.name', 'sort' => 'location'],
                'updated' => ['path' => 'updated_at', 'type' => 'date', 'sort' => 'updated_at'],
                'open' => ['path' => 'id', 'type' => 'open'],
            ],
        ],
        'models' => [
            'endpoint' => 'models',
            'web' => 'models/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'model_number' => ['path' => 'model_number', 'type' => 'code', 'sort' => 'model_number'],
                'manufacturer' => ['path' => 'manufacturer.name', 'sort' => 'manufacturer'],
                'category' => ['path' => 'category.name', 'sort' => 'category'],
                'fieldset' => ['path' => 'fieldset.name'],
                'assets' => ['path' => 'assets_count', 'type' => 'number', 'sort' => 'assets_count'],
            ],
        ],
        'categories' => [
            'endpoint' => 'categories',
            'web' => 'categories/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'kind' => ['path' => 'category_type', 'words' => 'category_types', 'sort' => 'category_type'],
                'items' => ['path' => 'item_count', 'type' => 'number'],
                'assets' => ['path' => 'assets_count', 'type' => 'number', 'sort' => 'assets_count'],
            ],
        ],
        'manufacturers' => [
            'endpoint' => 'manufacturers',
            'web' => 'manufacturers/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'website' => ['path' => 'url', 'type' => 'code'],
                'assets' => ['path' => 'assets_count', 'type' => 'number', 'sort' => 'assets_count'],
                'licenses' => ['path' => 'licenses_count', 'type' => 'number'],
                'accessories' => ['path' => 'accessories_count', 'type' => 'number'],
                'consumables' => ['path' => 'consumables_count', 'type' => 'number'],
            ],
        ],
        'statuslabels' => [
            'endpoint' => 'statuslabels',
            'web' => 'statuslabels/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'kind' => ['path' => 'type', 'type' => 'badge', 'words' => 'status_types', 'tone' => 'type'],
                'assets' => ['path' => 'assets_count', 'type' => 'number', 'sort' => 'assets_count'],
                'notes' => ['path' => 'notes'],
            ],
        ],
        'suppliers' => [
            'endpoint' => 'suppliers',
            'web' => 'suppliers/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'contact' => ['path' => 'contact', 'sub' => 'email'],
                'phone' => ['path' => 'phone'],
                'assets' => ['path' => 'assets_count', 'type' => 'number'],
                'licenses' => ['path' => 'licenses_count', 'type' => 'number'],
            ],
        ],
        'fields' => [
            'endpoint' => 'fields',
            'local' => true,
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'strong'],
                'element' => ['path' => 'type', 'words' => 'elements'],
                'format' => ['path' => 'format', 'type' => 'code'],
                'choices' => ['path' => 'field_values_array', 'type' => 'list'],
                'unique' => ['path' => 'is_unique', 'type' => 'bool'],
            ],
        ],
        'fieldsets' => [
            'endpoint' => 'fieldsets',
            'web' => 'fields/fieldsets/{id}',
            'local' => true,
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link'],
                'fields' => ['path' => 'fields.rows', 'type' => 'list'],
                'models' => ['path' => 'models.total', 'type' => 'number'],
            ],
        ],
        'depreciations' => [
            'endpoint' => 'depreciations',
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'strong'],
                'months' => ['path' => 'months', 'type' => 'number'],
                'minimum' => ['path' => 'depreciation_min', 'type' => 'number'],
            ],
        ],
        'users' => [
            'endpoint' => 'users',
            'detail' => 'id',
            'detailRoute' => 'snipe-it.users.show',
            'order' => ['name', 'asc'],
            'filters' => ['department', 'location', 'active'],
            'columns' => [
                // The name and the e-mail under it; the button to the detail at the end of the row.
                'name' => ['path' => 'name', 'type' => 'strong', 'sub' => 'email', 'sort' => 'first_name'],
                'username' => ['path' => 'username', 'type' => 'code', 'sort' => 'username'],
                'employee_num' => ['path' => 'employee_num', 'type' => 'code', 'sort' => 'employee_num'],
                'jobtitle' => ['path' => 'jobtitle', 'sort' => 'jobtitle'],
                'department' => ['path' => 'department.name', 'sort' => 'department'],
                'location' => ['path' => 'location.name', 'sort' => 'location'],
                'assets' => ['path' => 'assets_count', 'type' => 'number'],
                'licenses' => ['path' => 'licenses_count', 'type' => 'number'],
                'active' => ['path' => 'activated', 'type' => 'bool', 'words' => 'active', 'sort' => 'activated'],
                'open' => ['path' => 'id', 'type' => 'open'],
            ],
        ],
        'departments' => [
            'endpoint' => 'departments',
            'web' => 'departments/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'location' => ['path' => 'location.name'],
                'manager' => ['path' => 'manager.name'],
                'users' => ['path' => 'users_count', 'type' => 'number', 'sort' => 'users_count'],
            ],
        ],
        'locations' => [
            'endpoint' => 'locations',
            'web' => 'locations/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'parent' => ['path' => 'parent.name'],
                'city' => ['path' => 'city'],
                'assets' => ['path' => 'assets_count', 'type' => 'number', 'sort' => 'assets_count'],
                'assigned' => ['path' => 'assigned_assets_count', 'type' => 'number'],
                'users' => ['path' => 'users_count', 'type' => 'number'],
            ],
        ],
        'companies' => [
            'endpoint' => 'companies',
            'web' => 'companies/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'assets' => ['path' => 'assets_count', 'type' => 'number'],
                'users' => ['path' => 'users_count', 'type' => 'number'],
                'licenses' => ['path' => 'licenses_count', 'type' => 'number'],
                'accessories' => ['path' => 'accessories_count', 'type' => 'number'],
                'consumables' => ['path' => 'consumables_count', 'type' => 'number'],
                'components' => ['path' => 'components_count', 'type' => 'number'],
            ],
        ],
        'groups' => [
            'endpoint' => 'groups',
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'strong'],
                'users' => ['path' => 'users_count', 'type' => 'number'],
                'created' => ['path' => 'created_at', 'type' => 'date'],
            ],
        ],
        'licenses' => [
            'endpoint' => 'licenses',
            'web' => 'licenses/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'manufacturer' => ['path' => 'manufacturer.name'],
                'category' => ['path' => 'category.name'],
                'seats' => ['path' => 'seats', 'type' => 'number', 'sort' => 'seats'],
                'free_seats' => ['path' => 'free_seats_count', 'type' => 'number'],
                'purchased' => ['path' => 'purchase_date', 'type' => 'date', 'sort' => 'purchase_date'],
                'expires' => ['path' => 'expiration_date', 'type' => 'date', 'sort' => 'expiration_date'],
            ],
        ],
        'accessories' => [
            'endpoint' => 'accessories',
            'web' => 'accessories/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'category' => ['path' => 'category.name'],
                'manufacturer' => ['path' => 'manufacturer.name'],
                'model_number' => ['path' => 'model_number', 'type' => 'code'],
                'location' => ['path' => 'location.name'],
                'qty' => ['path' => 'qty', 'type' => 'number', 'sort' => 'qty'],
                'remaining' => ['path' => 'remaining_qty', 'type' => 'number'],
            ],
        ],
        'consumables' => [
            'endpoint' => 'consumables',
            'web' => 'consumables/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'category' => ['path' => 'category.name'],
                'manufacturer' => ['path' => 'manufacturer.name'],
                'item_no' => ['path' => 'item_no', 'type' => 'code'],
                'location' => ['path' => 'location.name'],
                'qty' => ['path' => 'qty', 'type' => 'number', 'sort' => 'qty'],
                'remaining' => ['path' => 'remaining', 'type' => 'number'],
            ],
        ],
        'components' => [
            'endpoint' => 'components',
            'web' => 'components/{id}',
            'order' => ['name', 'asc'],
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link', 'sort' => 'name'],
                'category' => ['path' => 'category.name'],
                'serial' => ['path' => 'serial', 'type' => 'code'],
                'location' => ['path' => 'location.name'],
                'qty' => ['path' => 'qty', 'type' => 'number', 'sort' => 'qty'],
                'remaining' => ['path' => 'remaining', 'type' => 'number'],
            ],
        ],
        'kits' => [
            'endpoint' => 'kits',
            'web' => 'kits/{id}',
            'columns' => [
                'name' => ['path' => 'name', 'type' => 'link'],
                'created' => ['path' => 'created_at', 'type' => 'date'],
            ],
        ],
        'activity' => [
            'endpoint' => 'reports/activity',
            'web' => '{item}',
            'order' => ['time', 'desc'],
            'filters' => ['action'],
            'columns' => [
                'time' => ['path' => 'created_at', 'type' => 'date', 'sort' => 'created_at'],
                'action' => ['path' => 'action_type', 'type' => 'badge', 'words' => 'actions', 'tone' => 'action_type'],
                'item' => ['path' => 'item.name', 'type' => 'link', 'sub' => 'item.type', 'subWords' => 'kinds'],
                'target' => ['path' => 'target.name', 'sub' => 'target.type', 'subWords' => 'kinds'],
                'by' => ['path' => 'admin.name'],
                'changes' => ['path' => 'log_meta', 'type' => 'changes'],
                'note' => ['path' => 'note'],
            ],
        ],
        'maintenances' => [
            'endpoint' => 'maintenances',
            'detail' => 'asset.id',
            'order' => ['start', 'desc'],
            'columns' => [
                'title' => ['path' => 'title', 'type' => 'strong', 'sort' => 'title'],
                'asset' => ['path' => 'asset.asset_tag', 'type' => 'link', 'sub' => 'asset.name'],
                'kind' => ['path' => 'asset_maintenance_type', 'sort' => 'asset_maintenance_type'],
                'start' => ['path' => 'start_date', 'type' => 'date', 'sort' => 'start_date'],
                'end' => ['path' => 'completion_date', 'type' => 'date', 'sort' => 'completion_date'],
                'supplier' => ['path' => 'supplier.name'],
                'cost' => ['path' => 'cost', 'type' => 'number', 'sort' => 'cost'],
            ],
        ],
    ];

    /** @return array<string, mixed> */
    public static function get(string $list): array
    {
        return self::LISTS[$list];
    }

    public static function has(string $list): bool
    {
        return isset(self::LISTS[$list]);
    }

    /** The page a list is on. */
    public static function groupOf(string $list): string
    {
        foreach (self::GROUPS as $group => $lists) {
            if (in_array($list, $lists, true)) {
                return $group;
            }
        }

        return array_key_first(self::GROUPS);
    }

    /** The columns as the page draws its table: key, name, how it is shown, whether a click orders by it. */
    public static function heads(string $list): array
    {
        $heads = [];
        foreach (self::LISTS[$list]['columns'] as $key => $column) {
            $heads[] = [
                'key' => $key,
                'label' => __('SnipeIt::snipe.columns.' . $key),
                'type' => $column['type'] ?? 'text',
                'sortable' => isset($column['sort']),
            ];
        }

        return $heads;
    }
}
