<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="userModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-lg-down modal-dialog-centered ">
        <div class="modal-content p-3">
            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body p-0">
                <div class=" mb-2">
                    <h3 class="modal-title mb-2">Title</h3>
                </div>
                <form id="form">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="row">
                                <label for="name" class="col-md-4 col-form-label required">Name
                                </label>
                                <div class="col-md-6">
                                    <input type="text" id="name" class="form-control" name="name"
                                        placeholder="Name.." required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <label for="email" class="col-md-4 col-form-label required">Email
                                </label>
                                <div class="col-md-6">
                                    <input type="email" id="email" class="form-control" name="email"
                                        placeholder="Email.." required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <label for="companySelect" class="col-md-4 col-form-label required">Company
                                </label>
                                <div class="col-md-8">
                                    <select id="companySelect" name="company" class="form-select" required>
                                        <option></option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->company_orangehr_code }}">
                                                {{ $company->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row">
                                <label class="col-md-4 col-form-label required">Role
                                </label>
                                <div class="col-md-8">
                                    <div class="table-responsive border rounded">
                                        <table class="table table-sm table-vcenter mb-0" id="userRoleTable">
                                            <thead>
                                                <tr class="mdka-bg-gray-50">
                                                    <th class="py-2 text-center" width="60">
                                                        <input class="form-check-input" type="checkbox"
                                                               id="selectAllRoles" />
                                                    </th>
                                                    <th class="py-2">Role</th>
                                                    <th class="py-2">Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($roles as $role)
                                                    <tr>
                                                        <td class="text-center">
                                                            <input class="form-check-input user-role-checkbox"
                                                                   name="role_id[]" type="checkbox"
                                                                   value="{{ $role->id }}" />
                                                        </td>
                                                        <td>{{ $role->name }}</td>
                                                        <td>{{ $role->func_role }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="form-hint d-none" id="userOtherRolesNote"></div>
                                </div>

                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-end gap-2" id="actionButtons">
                                    <x-global.btn-detail label="Update" color="orange" size="sm"
                                        onclick="userSubmit(event, this)" id="userModalUpdateButton" type="submit"
                                        data-type="edit" data-form-id="id">
                                        <x-slot name="icon">
                                            <i class="ti ti-device-floppy"></i>
                                        </x-slot>
                                    </x-global.btn-detail>

                                    <x-global.btn-detail label="Submit" color="blue" size="sm"
                                        onclick="userSubmit(event, this)" id="userModalSubmitButton" type="submit"
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
    {{-- select2 declaration --}}
    <script>
        $(function() {
            $('#userModal #companySelect').select2({
                theme: "bootstrap-5",
                placeholder: 'Select Company',
                allowClear: true,
                multiple: false,
                dropdownParent: $('#userModal'),
                width: '100%',
                closeOnSelect: false
            });
        });
    </script>

    {{-- role checkbox table --}}
    <script>
        $('#selectAllRoles').click(function() {
            $('.user-role-checkbox').prop('checked', $(this).prop('checked'));
        });
    </script>

    {{-- url --}}
    <script>
        const urlUserShow = "{{ route('user-management.user-registered.show', ':id') }}";
        const urlUserStore = "{{ route('user-management.user-registered.store') }}";
        const urlUserUpdate = "{{ route('user-management.user-registered.update', ':id') }}";
        const urlUserDestroy = "{{ route('user-management.user-registered.destroy', ':id') }}";
    </script>

    {{-- toggle Modal --}}
    <script>
        const toggleUserModal = (modalId, state, id = null) => {
            const modal = $("#" + modalId);
            const modalTitle = modal.find(".modal-title");
            const submitButton = modal.find("#" + modalId + "SubmitButton");

            const stateProperty = {
                'edit': {
                    'title': 'Edit User',
                    'actionButtons': ['edit']
                },
                'add': {
                    'title': 'Add New User',
                    'actionButtons': ['submit']
                }
            };

            // Hide all action buttons first
            modal.find("#actionButtons button").addClass("d-none");

            if (stateProperty[state]) {
                modalTitle.text(stateProperty[state].title)
                    .removeClass("text-warning text-primary")
                    .addClass(stateProperty[state].titleClass);


                // Show only the buttons specified for this state
                if (stateProperty[state] && stateProperty[state].actionButtons) {
                    const buttonsToShow = stateProperty[state].actionButtons;

                    buttonsToShow.forEach(buttonType => {
                        // Get button with matching data-type attribute
                        const button = modal.find(`button[data-type="${buttonType}"]`);

                        // Show the button
                        button.removeClass("d-none");

                        // Set data-form-id for all visible buttons if in edit mode
                        if (state === "edit" && id) {
                            button.attr("data-form-id", id);
                        } else {
                            button.removeAttr("data-form-id");
                        }
                    });
                }
            }


            if (state === "edit" && id) {
                $.ajax({
                    url: urlUserShow.replace(":id", id),
                    type: "GET",
                    dataType: "json",
                    success: function(response) {
                        modal.find("#name").val(response.data.name);
                        modal.find("#email").val(response.data.email);
                        modal.find("#companySelect").val(response.data.company_orangehr_code).trigger(
                            "change");

                        modal.find(".user-role-checkbox").prop("checked", false);
                        (response.data.role_id || []).forEach((roleId) => {
                            modal.find('.user-role-checkbox[value="' + roleId + '"]').prop("checked",
                                true);
                        });
                        setOtherRoles(modal, response.data.other_role_count);

                        modal.modal("show");
                    },
                    error: function(xhr, status, error) {
                        const r = xhr.responseJSON || {};
                        Swal.fire("Error!", r.message || "The user could not be loaded.", "error");
                    }
                });
            } else {
                modal.find("#form")[0].reset();
                modal.find("#companySelect").val(null).trigger("change");
                modal.find(".user-role-checkbox").prop("checked", false);
                setOtherRoles(modal, 0);

                modal.modal("show");
            }
        };

        // Roles the user holds that this form does not list (the caller may not manage them).
        // They stay on save, so the form may then be saved without any box ticked.
        const setOtherRoles = (modal, count) => {
            count = Number(count) || 0;
            modal.data("otherRoles", count);
            modal.find("#userOtherRolesNote")
                .toggleClass("d-none", count === 0)
                .text(count + " other role(s) of this user are not listed here because you cannot manage them. They stay as they are.");
        };
    </script>

    {{-- Close Modal --}}
    <script>
        $(document).ready(function() {
            $("#userModal").on("hidden.bs.modal", function() {
                // Reset the form fields
                $(this).find("#form")[0].reset();

                // Clear Select2 selections
                $(this).find("#companySelect").val(null).trigger("change");
                $(this).find(".user-role-checkbox").prop("checked", false);
                setOtherRoles($(this), 0);
            });
        });
    </script>

    {{-- Submit Data --}}
    <script>
        const userSubmit = (event, button) => {
            event.preventDefault();

            let modal = $("#userModal");
            let state = button.getAttribute("data-type");
            let id = button.getAttribute("data-form-id");

            let formData = new FormData();

            formData.append("name", modal.find("#name").val().trim());
            formData.append("email", modal.find("#email").val().trim());
            formData.append("company_id", modal.find("#companySelect").val());
            modal.find(".user-role-checkbox:checked").each(function() {
                formData.append("role_id[]", $(this).val());
            });

            let type, url;

            if (state === "submit") {
                type = "POST";
                url = urlUserStore;
            } else {
                type = "POST";
                url = urlUserUpdate.replace(":id", id);
                formData.append("_method", "PUT");
            }

            Swal.fire({
                title: "Is the data correct?",
                text: "This action will " + (state === "submit" ? "add" : "update") + " the user!",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-check"></i> Save',
                cancelButtonText: '<i class="ti ti-x"></i> Cancel',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    let errors = [];

                    if (!formData.get("name")) {
                        errors.push("<strong>Name</strong> cannot be empty");
                    }

                    if (!formData.get("email")) {
                        errors.push("<strong>Email</strong> cannot be empty");
                    }

                    if (!formData.get("company_id")) {
                        errors.push("<strong>Company</strong> must be selected");
                    }

                    const keepsOtherRoles = state !== "submit" && Number(modal.data("otherRoles")) > 0;
                    if (!formData.getAll("role_id[]").length && !keepsOtherRoles) {
                        errors.push("<strong>Role</strong> must be selected");
                    }

                    if (errors.length > 0) {
                        showError(errors);
                        return;
                    }

                    $.ajax({
                        url: url,
                        type: type,
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            modal.modal("hide");
                            Swal.fire("Success!", "User data has been saved!", "success");
                            userRegisteredTable();


                        },
                        error: function(xhr, status, error) {
                            // Show the reason the server gave ("text" is rendered as plain text, never as HTML).
                            const r = xhr.responseJSON || {};
                            const reasons = [].concat(r.errors || r.message || r.error || "There was a problem saving the user!");
                            Swal.fire({
                                icon: "error",
                                title: "Error!",
                                text: reasons.join(" "),
                            });
                        },
                    });
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    Swal.fire("Canceled!", "No changes were made!", "error");
                }
            });
        };

        const userDelete = (id) => {
            Swal.fire({
                title: "Are you sure?",
                text: "This action cannot be undone!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-check"></i> Yes, delete it!',
                cancelButtonText: '<i class="ti ti-x"></i> Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) { // ✅ Only proceed if user clicks "Yes"
                    $.ajax({
                        url: urlUserDestroy.replace(":id", id),
                        type: "DELETE",
                        success: function(response) {
                            Swal.fire("Deleted!", response.message, "success");
                            // The row's tooltip would otherwise stay on screen after the row is gone.
                            $('.tooltip').remove();
                            // Step back when the deleted row was the only one on this page.
                            if (currentPage > 1 && $('#userTable tbody tr').length <= 1) {
                                currentPage -= 1;
                            }
                            userRegisteredTable();
                        },
                        error: function(xhr) {
                            let errorMessage = "Something went wrong!";
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                            Swal.fire("Error!", errorMessage, "error");
                        }
                    });
                }
            });
        };
    </script>
@endpush
