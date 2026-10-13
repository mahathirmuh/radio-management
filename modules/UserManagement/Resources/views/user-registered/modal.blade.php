<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="userModal" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-md-down modal-dialog-centered user-form-dialog">
        <div class="modal-content">
            <div class="modal-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="modal-title mb-0">Title</h3>
                    <button type="button" class="btn-close position-static m-0" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="form">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="row">
                                <label for="name" class="col-md-4 col-form-label required">Name
                                </label>
                                <div class="col-md-8">
                                    <input type="text" id="name" class="form-control" name="name"
                                        placeholder="Name.." required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <label for="email" class="col-md-4 col-form-label required">Email
                                </label>
                                <div class="col-md-8">
                                    <input type="email" id="email" class="form-control" name="email"
                                        placeholder="Email.." required>
                                    <div class="form-hint">The work address Microsoft signs this person in with.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <label for="positionSelect" class="col-md-4 col-form-label required">Position
                                </label>
                                <div class="col-md-8">
                                    <select id="positionSelect" name="position" class="form-select" required>
                                        <option value="">Select position</option>
                                        @foreach ($positions as $position)
                                            <option value="{{ $position }}">{{ $position }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row">
                                <label for="roleSelect" class="col-md-4 col-form-label required">Role
                                </label>
                                <div class="col-md-8">
                                    <select id="roleSelect" name="role_id[]" class="form-select" required>
                                        <option value="">Select role</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-hint d-none" id="userOtherRolesNote"></div>
                                </div>

                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row">
                                <label for="statusSelect" class="col-md-4 col-form-label required">Status
                                </label>
                                <div class="col-md-8">
                                    <select id="statusSelect" name="active" class="form-select" required>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <div class="form-hint d-none" id="userOwnStatusNote">Your own account stays
                                        active.</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-end align-items-center gap-2"
                                    id="actionButtons">
                                    <a href="#" class="btn btn-link text-secondary text-decoration-none" data-bs-dismiss="modal"
                                        role="button">Cancel</a>

                                    <x-global.btn-detail label="Update" color="orange" size="md"
                                        onclick="userSubmit(event, this)" id="userModalUpdateButton" type="submit"
                                        data-type="edit" data-form-id="id">
                                        <x-slot name="icon">
                                            <i class="ti ti-device-floppy"></i>
                                        </x-slot>
                                    </x-global.btn-detail>

                                    <x-global.btn-detail label="Submit" color="blue" size="md"
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

{{-- A password for the e-mail + password form of the login page --}}
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="userPasswordModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-body p-0">
                <h3 class="modal-title mb-1">Set Password</h3>
                <p class="text-secondary mb-3" id="userPasswordWho"></p>
                <form id="userPasswordForm" autocomplete="off">
                    <div class="mb-2">
                        <label for="userPassword" class="form-label required">New password</label>
                        <input type="password" id="userPassword" class="form-control" autocomplete="new-password"
                            maxlength="72" required>
                        <div class="form-hint">At least 8 characters, with a letter and a number.</div>
                    </div>
                    <div class="mb-3">
                        <label for="userPasswordAgain" class="form-label required">Repeat the password</label>
                        <input type="password" id="userPasswordAgain" class="form-control"
                            autocomplete="new-password" maxlength="72" required>
                    </div>
                    <div class="form-hint mb-3">
                        With it the account can sign in through the e-mail + password form, next to Microsoft. It is
                        not sent to the user: pass it on yourself.
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <x-global.btn-detail label="Save" color="blue" size="sm" type="submit"
                            id="userPasswordSaveButton">
                            <x-slot name="icon">
                                <i class="ti ti-device-floppy"></i>
                            </x-slot>
                        </x-global.btn-detail>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    {{-- url --}}
    <script>
        const urlUserShow = "{{ route('user-management.user-registered.show', ':id') }}";
        const urlUserStore = "{{ route('user-management.user-registered.store') }}";
        const urlUserUpdate = "{{ route('user-management.user-registered.update', ':id') }}";
        const urlUserDestroy = "{{ route('user-management.user-registered.destroy', ':id') }}";
        const urlUserStatus = "{{ route('user-management.user-registered.status', ':id') }}";
        const urlUserPassword = "{{ route('user-management.user-registered.password', ':id') }}";
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
                        modal.find("#positionSelect").val(response.data.position || "");
                        // One role is chosen here: of the roles this form lists, the first the user holds.
                        const held = (response.data.role_id || []).map(String);
                        const listed = modal.find("#roleSelect option").map((i, option) => option.value).get();
                        modal.find("#roleSelect").val(listed.find((roleId) => roleId && held.includes(roleId)) || "");
                        setOtherRoles(modal, response.data.other_role_count);
                        setOwnStatus(modal, response.data.active, response.data.is_self);

                        modal.modal("show");
                    },
                    error: function(xhr, status, error) {
                        const r = xhr.responseJSON || {};
                        Swal.fire("Error!", r.message || "The user could not be loaded.", "error");
                    }
                });
            } else {
                modal.find("#form")[0].reset();
                setOtherRoles(modal, 0);
                setOwnStatus(modal, true, false);

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

        // Nobody sets their own account to inactive (the server refuses it as well): there the choice is shown, not offered.
        const setOwnStatus = (modal, active, own) => {
            modal.find("#statusSelect").val(active === false ? "0" : "1").prop("disabled", !!own);
            modal.find("#userOwnStatusNote").toggleClass("d-none", !own);
        };
    </script>

    {{-- Close Modal --}}
    <script>
        $(document).ready(function() {
            $("#userModal").on("hidden.bs.modal", function() {
                // Reset the form fields
                $(this).find("#form")[0].reset();

                setOtherRoles($(this), 0);
                setOwnStatus($(this), true, false);
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
            formData.append("position", modal.find("#positionSelect").val() || "");
            if (modal.find("#roleSelect").val()) {
                formData.append("role_id[]", modal.find("#roleSelect").val());
            }
            formData.append("active", modal.find("#statusSelect").val());

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

                    if (!formData.get("position")) {
                        errors.push("<strong>Position</strong> must be selected");
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

        // The reason the server gave, as plain text.
        const userReason = (xhr, fallback) => {
            const r = xhr.responseJSON || {};
            return [].concat(r.errors || r.message || fallback).join(" ");
        };

        const userRowName = (id) => $('#userTable [data-id="' + id + '"]').first().closest("tr").find(".user-name").text();

        const userStatus = (id, active) => {
            const name = userRowName(id) || "this user";
            Swal.fire({
                title: active ? "Activate this user?" : "Deactivate this user?",
                text: active ?
                    name + " will be able to sign in again." :
                    name + " will not be able to sign in, and is signed out. The account and its history stay.",
                icon: active ? "question" : "warning",
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-check"></i> ' + (active ? "Activate" : "Deactivate"),
                cancelButtonText: '<i class="ti ti-x"></i> Cancel',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }
                $.ajax({
                    url: urlUserStatus.replace(":id", id),
                    type: "PUT",
                    data: {
                        active: active ? 1 : 0
                    },
                    success: function(response) {
                        Swal.fire("Saved!", response.message, "success");
                        userRegisteredTable();
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: "error",
                            title: "Error!",
                            text: userReason(xhr, "The status could not be changed.")
                        });
                    }
                });
            });
        };

        const toggleUserPasswordModal = (id) => {
            const modal = $("#userPasswordModal");
            modal.data("userId", id);
            modal.find("#userPasswordForm")[0].reset();
            modal.find("#userPasswordWho").text(userRowName(id));
            modal.modal("show");
        };

        $(function() {
            $("#userPasswordModal").on("hidden.bs.modal", function() {
                $(this).find("#userPasswordForm")[0].reset();
            });

            $("#userPasswordForm").on("submit", function(event) {
                event.preventDefault();
                $("#userPasswordSaveButton").trigger("click");
            });

            $("#userPasswordSaveButton").on("click", function(event) {
                event.preventDefault();
                const modal = $("#userPasswordModal");
                const password = modal.find("#userPassword").val();
                const again = modal.find("#userPasswordAgain").val();

                let errors = [];
                if (password.length < 8 || !/[a-zA-Z]/.test(password) || !/[0-9]/.test(password)) {
                    errors.push("<strong>Password</strong> needs at least 8 characters, with a letter and a number");
                }
                if (password !== again) {
                    errors.push("The two passwords are <strong>not the same</strong>");
                }
                if (errors.length > 0) {
                    showError(errors);
                    return;
                }

                const button = $(this).prop("disabled", true);
                $.ajax({
                    url: urlUserPassword.replace(":id", modal.data("userId")),
                    type: "PUT",
                    data: {
                        password: password,
                        password_confirmation: again
                    },
                    success: function(response) {
                        modal.modal("hide");
                        Swal.fire("Saved!", response.message, "success");
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: "error",
                            title: "Error!",
                            text: userReason(xhr, "The password could not be saved.")
                        });
                    },
                    complete: function() {
                        button.prop("disabled", false);
                    }
                });
            });
        });

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
