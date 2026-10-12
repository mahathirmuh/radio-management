@push('style')
    <style>
        /* Keep the menu/permission matrix scrollable with a pinned header */
        .menu-permission-wrapper {
            max-height: 340px;
            overflow-y: auto;
        }

        #menuPermissionTable thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            /* Solid (opaque) background so scrolled rows don't bleed through the sticky header */
            background-color: #eef1f6 !important;
            box-shadow: inset 0 -1px 0 #e6e7e9;
        }

        #menuPermissionTable tr.menu-group-row td {
            background-color: #f5f7fb;
        }

        /* Indent child menus so the hierarchy reads at a glance */
        #menuPermissionTable tr.menu-child-row .menu-child-label {
            display: inline-block;
            padding-left: 1.5rem;
            position: relative;
        }

        #menuPermissionTable tr.menu-child-row .menu-child-label::before {
            content: "";
            position: absolute;
            left: 0.5rem;
            top: 50%;
            width: 0.6rem;
            height: 1px;
            background-color: #adb5bd;
        }
    </style>
@endpush

<div class="modal fade" id="formRoleModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">
        <div class="modal-content p-3">
            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body p-1">
                <div class=" mb-2">
                    <h3 class="modal-title mb-2">Title</h3>
                </div>
                <form id="form">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="row">
                                <label for="roleName" class="col-md-2 col-form-label required">Role
                                </label>
                                <div class="col-md-5">
                                    <input type="text" id="roleName" class="form-control" name="role_name"
                                           placeholder="Role.." required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <label for="homePageSelect" class="col-md-2 col-form-label ">Home Page
                                </label>
                                <div class="col-md-5">
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
                        </div>

                        <div class="col-12">
                            <div class="row">
                                <label class="col-md-2 col-form-label">Menu &amp; Permission
                                </label>
                                <div class="col">
                                    @php
                                        // Menu now has a parent/child hierarchy. Render root menus (and their
                                        // children indented underneath) instead of a flat alphabetical list.
                                        $rootMenus = $menus->whereNull('parent_id')->sortBy([['order', 'asc'], ['title', 'asc']]);
                                        $menuChildren = $menus->whereNotNull('parent_id')->groupBy('parent_id');
                                    @endphp
                                    <div class="table-responsive border rounded menu-permission-wrapper">
                                        <table class="table table-sm table-vcenter mb-0" id="menuPermissionTable">
                                            <thead>
                                                <tr class="mdka-bg-gray-50">
                                                    <th class="py-2">
                                                        Menu
                                                    </th>
                                                    <th class="py-2 text-center" width="90">
                                                        Access
                                                        <br>
                                                        <input class="form-check-input" type="checkbox" id="selectAll"
                                                               data-bs-toggle="tooltip" data-bs-placement="top"
                                                               title="Allows a full access to the system" />
                                                    </th>
                                                    @foreach ($permission as $perm)
                                                        <th class="py-2 text-center" width="90">{{ $perm->name }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($rootMenus as $menu)
                                                    @php $children = $menuChildren[$menu->id] ?? collect(); @endphp
                                                    <tr @class(['menu-group-row' => $children->isNotEmpty()])>
                                                        <td>
                                                            @if ($children->isNotEmpty())
                                                                <i class="ti ti-folder me-1 text-muted"></i>
                                                                <span class="fw-semibold">{{ $menu->title }}</span>
                                                            @else
                                                                {{ $menu->title }}
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input menu-access-checkbox"
                                                                   name="menu_list[]" type="checkbox"
                                                                   id="menuAccess{{ $menu->id }}"
                                                                   data-menu-id="{{ $menu->id }}"
                                                                   value="{{ $menu->id }}" />
                                                        </td>
                                                        @foreach ($permission as $perm)
                                                            <td class="text-center">
                                                                <input class="form-check-input menu-permission-checkbox"
                                                                       name="menu_permission[{{ $menu->id }}][]"
                                                                       type="checkbox"
                                                                       data-menu-id="{{ $menu->id }}"
                                                                       value="{{ $perm->id }}" />
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                    @foreach ($children->sortBy([['order', 'asc'], ['title', 'asc']]) as $child)
                                                        <tr class="menu-child-row">
                                                            <td>
                                                                <span class="menu-child-label">{{ $child->title }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                <input class="form-check-input menu-access-checkbox"
                                                                       name="menu_list[]" type="checkbox"
                                                                       id="menuAccess{{ $child->id }}"
                                                                       data-menu-id="{{ $child->id }}"
                                                                       data-parent-id="{{ $menu->id }}"
                                                                       value="{{ $child->id }}" />
                                                            </td>
                                                            @foreach ($permission as $perm)
                                                                <td class="text-center">
                                                                    <input class="form-check-input menu-permission-checkbox"
                                                                           name="menu_permission[{{ $child->id }}][]"
                                                                           type="checkbox"
                                                                           data-menu-id="{{ $child->id }}"
                                                                           value="{{ $perm->id }}" />
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

                        <div class="col-12 mt-3">
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-end gap-2" id="actionButtons">
                                    <x-global.btn-detail label="Update" color="orange" size="sm"
                                                         onclick="formRoleSubmit(event, this)" id="modalUpdateButton" type="submit"
                                                         data-type="edit" data-form-id="id">
                                        <x-slot name="icon">
                                            <i class="ti ti-device-floppy"></i>
                                        </x-slot>
                                    </x-global.btn-detail>

                                    <x-global.btn-detail label="Submit" color="blue" size="sm"
                                                         onclick="formRoleSubmit(event, this)" id="modalSubmitButton" type="submit"
                                                         data-type="submit" data-form-id="id">
                                        <x-slot name="icon">
                                            <i class="ti ti-device-floppy"></i>
                                        </x-slot>
                                    </x-global.btn-detail>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
@push('javascript')
    <script type="text/javascript">
        function syncMenuPermissionRowState(menuId) {
            var accessChecked = $('.menu-access-checkbox[data-menu-id="' + menuId + '"]').prop('checked');
            var permissionCheckboxes = $('.menu-permission-checkbox[data-menu-id="' + menuId + '"]');

            permissionCheckboxes.prop('disabled', !accessChecked);
            if (!accessChecked) {
                permissionCheckboxes.prop('checked', false);
            }
        }

        const formRoleModal = (id, action, dataId) => {
            const modalId = `#${id}`;
            var dataId = dataId;

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
                    // data: formData,
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
                            errorMessage = xhr.responseJSON.errors;
                        } else {
                            errorMessage = xhr.statusText;
                        }
                        Swal.fire({
                            title: 'Oops...',
                            html: errorMessage,
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
                    // $(modalId + ' #submitButton').addClass('d-none');

                    $(modalId + ' #modalSubmitButton').addClass('d-none');
                    $(modalId + ' #modalUpdateButton').addClass('d-none');

                    // Hide the submit button

                    $(modalId + ' .modal-title').text('View Role');

                } else if (action == 'Edit') {
                    //custom edit
                    $(modalId + ' :input').prop('readonly', false);
                    $(modalId + ' select').prop('disabled', false);
                    $(modalId + ' :radio').prop('disabled', false);
                    $(modalId + ' :checkbox').prop('disabled', false);

                    $(modalId + ' .modal-title').text('Edit Role');
                    $(modalId + ' #modalSubmitButton').addClass('d-none');
                    if (modalId == '#formRoleModal') {
                        //custom action
                    }

                    // $(modalId + ' #submitButton').removeClass('d-none');

                    $(modalId + ' #modalSubmitButton').addClass('d-none');
                    $(modalId + ' #modalUpdateButton').removeClass('d-none');


                    $(modalId + ' #lineManagerSelect').attr('disabled', true);


                    // $(modalId + ' #submitButton').text('Update');
                    // $(modalId + ' #submitButton').removeClass('btn-primary');
                    // $(modalId + ' #submitButton').addClass('btn-warning');

                    $(modalId + ' #modalUpdateButton').attr('data-form-id', dataId);
                }

                // $(modalId + ' #status').show();
                // $(modalId + ' #submitButton').attr('data-form-id', dataId);
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
                $(modalId + ' .modal-title').text('Add Role');

                $(modalId + ' #modalUpdateButton').addClass('d-none');

                // $(modalId + ' #submitButton').removeClass('d-none')

                $(modalId + ' #modalSubmitButton').removeClass('d-none');


                // $(modalId + ' #status').hide();


                // $(modalId + ' #submitButton').text('Submit');
                // $(modalId + ' #submitButton').addClass('btn-primary');
                // $(modalId + ' #submitButton').removeClass('btn-warning');

                $(modalId).modal('show');

            }

            // $(modalId + ' #submitButton').attr('data-form-submit', action);
            $(modalId + ' #modalUpdateButton').attr('data-form-submit', action);
            $(modalId + ' #modalSubmitButton').attr('data-form-submit', action);
        }
        const formRoleSubmit = (event, button) => {
            event.preventDefault();
            let data = $('#form').serialize();
            let state = button.getAttribute('data-form-submit');
            let id = button.getAttribute('data-form-id');

            if (state === 'Add') {
                type = 'POST';
                url = urlRolePermissionAdd;
            } else {
                type = 'PUT';
                url = urlRolePermissionEdit.replace(':id', id)
            }

            data += '&_token=' + encodeURIComponent('{{ csrf_token() }}');

            Swal.fire({
                title: 'Data is Correct?',
                text: 'Data will be ' + state + 'ed!',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-check"></i> Save',
                cancelButtonText: '<i class="ti ti-x"></i> Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Collect data from input, select, and textarea elements within the div
                    $.ajax({
                        type: type,
                        url: url,
                        data: data,
                        success: function(response) {
                            Swal.fire({
                                title: state + 'ed!',
                                text: response.message,
                                icon: 'success',
                                showConfirmButton: false,
                                timer: 2000,
                                timerProgressBar: true
                            });
                            $('.modal').modal('hide')
                            dataTable();
                        },
                        error: function(xhr) {
                            let errorMessage = '';
                            if (xhr.responseJSON && xhr.responseJSON.errors) {
                                // Loop through the errors array and concatenate them
                                errorMessage = Object.values(xhr.responseJSON.errors).join('<br>');
                            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            } else {
                                errorMessage = xhr.statusText;
                            }
                            Swal.fire({
                                title: 'Oops...',
                                html: errorMessage,
                                icon: 'error',
                                showConfirmButton: true,
                            });
                        }
                    });

                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    Swal.fire(
                        'Canceled!',
                        'Data not saved!',
                        'error'
                    )
                }
            });
        }


        $('#formRoleModal').on('hidden.bs.modal', function() {
            $('#formRoleModal #form')[0].reset();
        });



        $('#selectAll').click(function() {
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
