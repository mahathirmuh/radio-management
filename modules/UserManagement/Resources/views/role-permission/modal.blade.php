@push('style')
    <style>
        #formRoleModal .modal-dialog {
            max-width: 800px;
        }

        #formRoleModal .rm-dialog-body {
            padding: 8px 24px 4px;
        }

        #formRoleModal .rm-form-row {
            grid-template-columns: 150px minmax(0, 1fr);
        }

        #formRoleModal .role-half {
            width: 50%;
        }

        @media (max-width: 767.98px) {
            #formRoleModal .rm-form-row {
                grid-template-columns: minmax(0, 1fr);
            }

            #formRoleModal .role-half {
                width: 100%;
            }
        }

        /* The menus and what the role may do in each: scrolls under a header that stays. */
        .menu-permission-wrapper {
            max-height: 340px;
            overflow-y: auto;
            border: 1px solid var(--rm-gray-200);
            border-radius: 8px;
        }

        #menuPermissionTable {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #menuPermissionTable thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 8px 12px;
            background: var(--rm-gray-50);
            box-shadow: inset 0 -1px 0 var(--rm-gray-200);
            color: var(--rm-gray-500);
            font-size: 12px;
            line-height: 16px;
            font-weight: 600;
            text-transform: none;
        }

        #menuPermissionTable thead th.perm-access {
            width: 80px;
            padding: 6px 0;
        }

        #menuPermissionTable thead th.perm-kind {
            width: 72px;
            padding: 8px 0;
        }

        #menuPermissionTable .perm-access-head {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        #menuPermissionTable tbody td {
            padding: 7px 12px;
            box-shadow: inset 0 1px 0 var(--rm-gray-100);
            color: var(--rm-gray-900);
            font-size: 13px;
            line-height: 18px;
            vertical-align: middle;
        }

        #menuPermissionTable tbody td.text-center {
            padding-left: 0;
            padding-right: 0;
        }

        #menuPermissionTable tr.menu-group-row td {
            background: var(--rm-gray-50);
            font-weight: 600;
        }

        #menuPermissionTable tr.menu-group-row .ti {
            color: var(--rm-gray-400);
        }

        /* A menu under a group is set in (36 from the edge). */
        #menuPermissionTable tr.menu-child-row td:first-child {
            padding-left: 36px;
        }

        /* The boxes: 16px, radius 4; ticked blue, a box that cannot be changed pale. */
        #menuPermissionTable .form-check-input {
            float: none;
            width: 16px;
            height: 16px;
            margin: 0;
            border: 0;
            border-radius: 4px;
            background-color: #fff;
            box-shadow: inset 0 0 0 1px var(--rm-gray-300);
            cursor: pointer;
        }

        #menuPermissionTable .form-check-input:checked {
            background-color: var(--rm-brand-600);
            box-shadow: none;
        }

        #menuPermissionTable .form-check-input:disabled {
            opacity: 1;
            background-color: var(--rm-gray-100);
            box-shadow: inset 0 0 0 1px var(--rm-gray-200);
            cursor: default;
        }

        #menuPermissionTable .form-check-input:checked:disabled {
            background-color: var(--rm-brand-300);
            box-shadow: none;
        }
    </style>
@endpush

<div class="modal fade rm-modal" data-bs-backdrop="static" id="formRoleModal" aria-labelledby="formRoleTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="rm-dialog-head">
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title modal-title" id="formRoleTitle">Title</h3>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Close"><i
                        class="ti ti-x"></i></button>
            </div>
            <form id="form">
                <div class="rm-dialog-body">
                    <div class="rm-form-rows">
                        <div class="rm-form-row">
                            <label for="roleName" class="rm-form-label required">Role</label>
                            <div class="rm-form-field rm-field role-half">
                                <input type="text" id="roleName" class="form-control" name="role_name" placeholder="Role.."
                                    required>
                            </div>
                        </div>
                        <div class="rm-form-row">
                            <label for="homePageSelect" class="rm-form-label">Home Page</label>
                            <div class="rm-form-field rm-field role-half">
                                <select id="homePageSelect" name="home_page" class="form-select">
                                    <option value=""></option>
                                    @php
                                        $homeRoots = $menus->whereNull('parent_id')->sortBy([['order', 'asc'], ['title', 'asc']]);
                                        $homeChildren = $menus->whereNotNull('parent_id')->groupBy('parent_id');
                                    @endphp
                                    @foreach ($homeRoots as $root)
                                        @php $rootChildren = $homeChildren[$root->id] ?? collect(); @endphp
                                        @if ($rootChildren->isNotEmpty())
                                            <option value="{{ $root->id }}">{{ $root->title }}</option>
                                            <optgroup label="{{ $root->title }}">
                                                @foreach ($rootChildren->sortBy([['order', 'asc'], ['title', 'asc']]) as $child)
                                                    <option value="{{ $child->id }}">{{ $child->title }}</option>
                                                @endforeach
                                            </optgroup>
                                        @else
                                            <option value="{{ $root->id }}">{{ $root->title }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="rm-form-row">
                            <span class="rm-form-label pt-0">Menu &amp; Permission</span>
                            @php
                                // Menu now has a parent/child hierarchy. Render root menus (and their
                                // children indented underneath) instead of a flat alphabetical list.
                                $rootMenus = $menus->whereNull('parent_id')->sortBy([['order', 'asc'], ['title', 'asc']]);
                                $menuChildren = $menus->whereNotNull('parent_id')->groupBy('parent_id');
                            @endphp
                            <div class="menu-permission-wrapper">
                                <table id="menuPermissionTable">
                                    <thead>
                                        <tr>
                                            <th>Menu</th>
                                            <th class="perm-access">
                                                <span class="perm-access-head">Access
                                                    <input class="form-check-input" type="checkbox" id="selectAll"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Allows a full access to the system" aria-label="Every menu" /></span>
                                            </th>
                                            @foreach ($permission as $perm)
                                                <th class="perm-kind text-center">{{ $perm->name }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rootMenus as $menu)
                                            @php $children = $menuChildren[$menu->id] ?? collect(); @endphp
                                            <tr @class(['menu-group-row' => $children->isNotEmpty()])>
                                                <td>
                                                    @if ($children->isNotEmpty())
                                                        <i class="ti ti-folder me-1" aria-hidden="true"></i>{{ $menu->title }}
                                                    @else
                                                        {{ $menu->title }}
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input menu-access-checkbox"
                                                        name="menu_list[]" type="checkbox"
                                                        id="menuAccess{{ $menu->id }}"
                                                        data-menu-id="{{ $menu->id }}"
                                                        value="{{ $menu->id }}" aria-label="{{ $menu->title }}: access" />
                                                </td>
                                                @foreach ($permission as $perm)
                                                    <td class="text-center">
                                                        <input class="form-check-input menu-permission-checkbox"
                                                            name="menu_permission[{{ $menu->id }}][]"
                                                            type="checkbox"
                                                            data-menu-id="{{ $menu->id }}"
                                                            value="{{ $perm->id }}" aria-label="{{ $menu->title }}: {{ $perm->name }}" />
                                                    </td>
                                                @endforeach
                                            </tr>
                                            @foreach ($children->sortBy([['order', 'asc'], ['title', 'asc']]) as $child)
                                                <tr class="menu-child-row">
                                                    <td>{{ $child->title }}</td>
                                                    <td class="text-center">
                                                        <input class="form-check-input menu-access-checkbox"
                                                            name="menu_list[]" type="checkbox"
                                                            id="menuAccess{{ $child->id }}"
                                                            data-menu-id="{{ $child->id }}"
                                                            data-parent-id="{{ $menu->id }}"
                                                            value="{{ $child->id }}" aria-label="{{ $child->title }}: access" />
                                                    </td>
                                                    @foreach ($permission as $perm)
                                                        <td class="text-center">
                                                            <input class="form-check-input menu-permission-checkbox"
                                                                name="menu_permission[{{ $child->id }}][]"
                                                                type="checkbox"
                                                                data-menu-id="{{ $child->id }}"
                                                                value="{{ $perm->id }}" aria-label="{{ $child->title }}: {{ $perm->name }}" />
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rm-dialog-foot is-plain" id="actionButtons">
                    <a href="#" class="rm-link-muted" data-bs-dismiss="modal" role="button" id="roleCloseLink">Cancel</a>
                    <button type="submit" class="btn-rm update" onclick="formRoleSubmit(event, this)" id="modalUpdateButton"
                        data-type="edit" data-form-id="id"><i class="ti ti-check" aria-hidden="true"></i> Update</button>
                    <button type="submit" class="btn-rm primary" onclick="formRoleSubmit(event, this)" id="modalSubmitButton"
                        data-type="submit" data-form-id="id"><i class="ti ti-check" aria-hidden="true"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('javascript')
    <script type="text/javascript">
        /** What the dialog is doing now: Add, Edit or Preview (View: nothing in it can be changed). */
        const roleForm = {
            mode: 'Add',
        };

        /** A row's permissions follow its access box; in View none can be ticked, whatever the role has. */
        function syncMenuPermissionRowState(menuId) {
            var accessChecked = $('.menu-access-checkbox[data-menu-id="' + menuId + '"]').prop('checked');
            var permissionCheckboxes = $('.menu-permission-checkbox[data-menu-id="' + menuId + '"]');

            permissionCheckboxes.prop('disabled', roleForm.mode === 'Preview' || !accessChecked);
            if (!accessChecked) {
                permissionCheckboxes.prop('checked', false);
            }
        }

        const formRoleModal = (id, action, dataId) => {
            const modalId = `#${id}`;
            $('[data-bs-toggle="tooltip"]').tooltip('hide');
            roleForm.mode = action;
            $(modalId + ' #roleCloseLink').text(action === 'Preview' ? 'Close' : 'Cancel');

            $(modalId + ' #homePageSelect').select2({
                theme: "bootstrap-5",
                placeholder: 'Select Home Page',
                multiple: false,
                dropdownParent: $(modalId),
                width: '100%',
            });

            if (action !== 'Add') {
                //This section was for View
                $.ajax({
                    type: 'GET',
                    url: urlRolePermissionView.replace(':id', dataId),
                    success: function(response) {

                        $(modalId + ' #roleName').val(response.data.role_name);
                        $(modalId + ' #homePageSelect').val(response.data.home_page).change();

                        var menuList = response.data.menu_list;
                        var menuPermission = response.data.menu_permission || {};

                        // Reset every row to "no access" first, then re-enable the ones the role has.
                        $('.menu-access-checkbox').each(function() {
                            $(this).prop('checked', false);
                            syncMenuPermissionRowState($(this).data('menu-id'));
                        });

                        menuList.forEach((menu) => {
                            var menuId = menu.id;

                            // Set the checkbox state based on the menu ID
                            $('input[name="menu_list[]"][value="' + menuId + '"]').prop('checked', true);

                            syncMenuPermissionRowState(menuId);

                            var permissionIds = menuPermission[menuId] || [];
                            permissionIds.forEach((permissionId) => {
                                $('input[name="menu_permission[' + menuId + '][]"][value="' + permissionId + '"]')
                                    .prop('checked', true);
                            });
                        })

                        $(modalId).modal('show');
                    },
                    error: function(xhr) {
                        let errorMessage = '';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            errorMessage = [].concat(xhr.responseJSON.errors).join(' ');
                        } else {
                            errorMessage = xhr.statusText;
                        }
                        Swal.fire({
                            title: 'Oops...',
                            text: errorMessage,
                            icon: 'error',
                            showConfirmButton: true,
                        });
                    }
                });
                if (action == 'Preview') {
                    // Disable all input elements inside the form
                    $(modalId + ' :input').prop('readonly', true);
                    $(modalId + ' select').prop('disabled', true);
                    $(modalId + ' :radio').prop('disabled', true);
                    $(modalId + ' :checkbox').prop('disabled', true);

                    $(modalId + ' #modalSubmitButton').addClass('d-none');
                    $(modalId + ' #modalUpdateButton').addClass('d-none');

                    $(modalId + ' .modal-title').text('View role');

                } else if (action == 'Edit') {
                    $(modalId + ' :input').prop('readonly', false);
                    $(modalId + ' select').prop('disabled', false);
                    $(modalId + ' :radio').prop('disabled', false);
                    $(modalId + ' :checkbox').prop('disabled', false);

                    $(modalId + ' .modal-title').text('Edit role');
                    $(modalId + ' #modalSubmitButton').addClass('d-none');
                    $(modalId + ' #modalUpdateButton').removeClass('d-none');
                    $(modalId + ' #modalUpdateButton').attr('data-form-id', dataId);
                }
            } else {
                //this section when modal state was ADD
                $(modalId + ' :input').prop('readonly', false);
                $(modalId + ' select').val(null).trigger('change');
                $(modalId + ' select').prop('disabled', false);
                $(modalId + ' :radio').prop('disabled', false);
                $(modalId + ' :checkbox').prop('disabled', false).prop('checked', false);
                $('.menu-access-checkbox').each(function() {
                    syncMenuPermissionRowState($(this).data('menu-id'));
                });
                $(modalId + ' .modal-title').text('Add role');

                $(modalId + ' #modalUpdateButton').addClass('d-none');
                $(modalId + ' #modalSubmitButton').removeClass('d-none');

                $(modalId).modal('show');
            }

            $(modalId + ' #modalUpdateButton').attr('data-form-submit', action);
            $(modalId + ' #modalSubmitButton').attr('data-form-submit', action);
        }

        const formRoleSubmit = (event, button) => {
            event.preventDefault();
            let data = $('#form').serialize();
            let state = button.getAttribute('data-form-submit');
            let id = button.getAttribute('data-form-id');
            let type, url;

            if (state === 'Add') {
                type = 'POST';
                url = urlRolePermissionAdd;
            } else {
                type = 'PUT';
                url = urlRolePermissionEdit.replace(':id', id)
            }

            data += '&_token=' + encodeURIComponent('{{ csrf_token() }}');

            rmDialog.confirm({
                layout: 'center',
                tone: 'sky',
                icon: 'help-circle',
                title: 'Is the data correct?',
                text: 'This action will ' + (state === 'Add' ? 'add' : 'update') + ' the role!',
                confirmText: '<i class="ti ti-check"></i> Save',
                cancelText: '<i class="ti ti-x"></i> Cancel',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }
                $.ajax({
                    type: type,
                    url: url,
                    data: data,
                    success: function(response) {
                        rmFeedback.toast(response.message);
                        $('#formRoleModal').modal('hide');
                        dataTable();
                    },
                    error: function(xhr) {
                        let errorMessage = '';
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            errorMessage = Object.values(xhr.responseJSON.errors).join(' ');
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else {
                            errorMessage = xhr.statusText;
                        }
                        Swal.fire({
                            title: 'Oops...',
                            text: errorMessage,
                            icon: 'error',
                            showConfirmButton: true,
                        });
                    }
                });
            });
        }

        $('#formRoleModal').on('hidden.bs.modal', function() {
            $('#formRoleModal #form').trigger('reset');
        });

        $('#selectAll').on('click', function() {
            var isChecked = $(this).prop('checked');
            $('.menu-access-checkbox').prop('checked', isChecked);
            $('.menu-access-checkbox').each(function() {
                syncMenuPermissionRowState($(this).data('menu-id'));
            });
            if (isChecked) {
                $('.menu-permission-checkbox').prop('checked', true);
            }
        });

        $(document).on('change', '.menu-access-checkbox', function() {
            const box = $(this);
            const menuId = box.data('menu-id');
            syncMenuPermissionRowState(menuId);

            // The sidebar shows a page only when its group is granted too, and a group that is
            // switched off hides its whole branch. Keep the boxes in step, so that what is ticked
            // here is what the role gets to see.
            const parentId = box.data('parent-id');
            if (box.prop('checked') && parentId) {
                $('#menuAccess' + parentId).prop('checked', true);
                syncMenuPermissionRowState(parentId);
            }
            if (!box.prop('checked')) {
                $('.menu-access-checkbox[data-parent-id="' + menuId + '"]').each(function() {
                    $(this).prop('checked', false);
                    syncMenuPermissionRowState($(this).data('menu-id'));
                });
            }
        });
    </script>
@endpush
