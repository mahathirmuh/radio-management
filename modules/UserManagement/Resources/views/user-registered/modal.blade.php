{{--
    The form of a user, for adding one and for changing one.

    The sign-in method comes first: it decides where "Find employee" searches (Active Directory for LDAP, the employee
    directory of HR for Microsoft) and whether a directory username is recorded. "Find employee" is there only where it
    has somewhere to search, for who may add or change a user: see UserRegisteredController::lookupSources().
--}}
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="userModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-lg-down modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="modal-title mb-0">Title</h3>
                    <button type="button" class="btn-close position-static m-0" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="form" autocomplete="off">
                    <div class="row g-3">
                        @if (count($signInMethods) > 1)
                            {{-- How the account is entered, asked first: it decides where "Find employee" searches and
                                 whether a directory username is recorded. LDAP: it has a directory username, and the login
                                 form checks its password against Active Directory. Microsoft: it has none. Always asked
                                 once the column is there (before its migration there is one method, so no choice). --}}
                            <div class="col-12">
                                <div class="row">
                                    <label class="col-md-4 col-form-label required">Sign-in method
                                    </label>
                                    <div class="col-md-8">
                                        @foreach ($signInMethods as $method)
                                            <label class="form-check">
                                                <input class="form-check-input" type="radio" name="sign_in_method"
                                                    value="{{ $method }}">
                                                <span class="form-check-label">
                                                    <i class="ti {{ $method === 'LDAP' ? 'ti-server' : 'ti-brand-windows' }} me-1"></i>{{ $method }}
                                                </span>
                                                <span class="form-check-description">{{ $signInMeanings[$method] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if (array_filter($lookupSources))
                            {{-- Above the name, because it fills in the name and the address (and, with LDAP, the directory
                                 username): choosing them is surer than typing them. Where it searches follows the method:
                                 Active Directory for LDAP, the employee directory of HR for Microsoft (see lookupSources()). --}}
                            <div class="col-12" id="userLookupRow">
                                <div class="row">
                                    <label for="employeeLookup" class="col-md-4 col-form-label">Find employee
                                    </label>
                                    <div class="col-md-8">
                                        <input type="search" id="employeeLookup" class="form-control"
                                            placeholder="Name or work email.." autocomplete="off">
                                        <small class="form-hint" id="employeeLookupHint">
                                            Optional. Choosing somebody fills in their name and work address, which
                                            is the address Microsoft signs them in with.
                                        </small>
                                        <div id="employeeMatches" aria-live="polite"></div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-12">
                            <div class="row">
                                <label for="name" class="col-md-4 col-form-label required">Name
                                </label>
                                <div class="col-md-8">
                                    <input type="text" id="name" class="form-control" name="name"
                                        placeholder="Name.." autocomplete="off" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row">
                                <label for="email" class="col-md-4 col-form-label required">Email
                                </label>
                                <div class="col-md-8">
                                    <input type="email" id="email" class="form-control" name="email"
                                        placeholder="Email.." autocomplete="off" required>
                                </div>
                            </div>
                        </div>

                        @if (count($signInMethods) > 1)
                            <div class="col-12" id="userDirectoryRow">
                                <div class="row">
                                    <label for="ldapUsername" class="col-md-4 col-form-label">Directory username
                                    </label>
                                    <div class="col-md-8">
                                        <input type="text" id="ldapUsername" class="form-control" name="ldap_username"
                                            maxlength="128" autocomplete="off"
                                            placeholder="Taken from the email if left blank">
                                        <small class="form-hint">
                                            The Active Directory account this person signs in with. Choosing somebody in
                                            Find employee fills it in; left blank it is taken from the part of the email
                                            before the &commat;. It is checked against the directory when the account is
                                            saved. No password is stored here; the one they already use is checked against
                                            the directory each time.
                                        </small>
                                        @unless ($ldapConfigured)
                                            {{-- Said, instead of the choice going away: a setting that went missing
                                                 should show where it is missed. --}}
                                            <small class="form-hint text-orange" id="userLdapOffNote">
                                                Active Directory is not set up on this server (LDAP_URL and LDAP_BIND_DN
                                                in .env). Until it is, this account cannot sign in with its directory
                                                password; it can with Microsoft.
                                            </small>
                                        @endunless
                                    </div>
                                </div>
                            </div>

                            {{-- With Microsoft there is no box, not a greyed one: nothing is recorded. --}}
                            <div class="col-12 d-none" id="userMicrosoftNote">
                                <div class="row">
                                    <div class="col-md-8 offset-md-4">
                                        <div class="alert alert-info mb-0 py-2" role="alert">
                                            No directory username is recorded for this account. The email above must
                                            be the address Microsoft knows this person by &mdash; that is how
                                            Microsoft sign-in finds the account.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

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
                    </div>

                    <div class="d-flex justify-content-end align-items-center gap-2 mt-3" id="actionButtons">
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
                </form>
            </div>
        </div>
    </div>
</div>

{{-- A password for the e-mail + password form of the login page. Not for an account that signs in with its
     directory password: that one has none here. --}}
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="userPasswordModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <h3 class="modal-title mb-0">Set Password</h3>
                    <button type="button" class="btn-close position-static m-0" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
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
        const urlUserEmployees = "{{ route('user-management.user-registered.employees') }}";
        const urlUserDirectory = "{{ route('user-management.user-registered.directory') }}";
        // Where "Find employee" searches for each sign-in method: "directory" (Active Directory), "hr", or null (not offered).
        const userLookupSources = @json($lookupSources);
        // The role a new user starts with (the one that only reads), when this form lists it.
        const userDefaultRole = @json($defaultRoleId ? (string) $defaultRoleId : '');
    </script>

    {{-- sign-in method --}}
    <script>
        // The directory username belongs to LDAP. With Microsoft none is recorded: the box goes, and what was typed in it.
        // Where "Find employee" searches follows the method. (Without a choice on this page the method is Microsoft.)
        const setSignInMethod = (modal, method) => {
            const choices = modal.find('input[name="sign_in_method"]');
            if (choices.length) {
                if (!choices.filter((i, choice) => choice.value === method).length) {
                    method = choices.first().val();
                }
                choices.each((i, choice) => {
                    choice.checked = choice.value === method;
                });

                const directory = method === "LDAP";
                modal.find("#userDirectoryRow").toggleClass("d-none", !directory);
                modal.find("#userMicrosoftNote").toggleClass("d-none", directory);
                if (!directory) {
                    modal.find("#ldapUsername").val("");
                }
            } else {
                method = "Microsoft";
            }
            setLookupSource(userLookupSources[method] || null);
        };

        $(function() {
            $("#userModal").on("change", 'input[name="sign_in_method"]', function() {
                setSignInMethod($("#userModal"), this.value);
            });
        });
    </script>

    {{-- find employee --}}
    <script>
        // Empties the search and drops an answer that is still on its way; and says where the search looks. (Nothing to do
        // on a page without the search.)
        let resetEmployeeLookup = () => {};
        let setLookupSource = () => {};

        $(function() {
            const lookup = $("#employeeLookup");
            const matches = $("#employeeMatches");
            if (!lookup.length) {
                return;
            }
            const minimum = {{ (int) $directoryMinimum }};
            let asked = 0;
            let source = null;
            // What the search box says and asks, for each place it can look.
            const sources = {
                hr: {
                    url: urlUserEmployees,
                    placeholder: "Name or work email..",
                    hint: "Optional. Choosing somebody fills in their name and work address, which is the address Microsoft signs them in with.",
                    failed: "The employee directory could not be searched just now (the reason is in the application log). Type the name and the address yourself.",
                    none: "No match in the employee directory. Type the address yourself if you know it.",
                },
                directory: {
                    url: urlUserDirectory,
                    placeholder: "Name, username or email..",
                    hint: "Optional. Searches Active Directory: choosing somebody fills in their name, email and directory username as the directory has them.",
                    failed: "Active Directory could not be searched just now (the reason is in the application log). Type the name, the address and the directory username yourself.",
                    none: "No match in Active Directory. Check the spelling, or type the details yourself.",
                },
            };

            resetEmployeeLookup = () => {
                asked += 1;
                lookup.val("");
                matches.empty();
            };

            // Another place to look starts an empty search: what was found in the other one is no answer here.
            setLookupSource = (next) => {
                next = sources[next] ? next : null;
                const changed = next !== source;
                source = next;
                $("#userLookupRow").toggleClass("d-none", source === null);
                if (source !== null) {
                    lookup.attr("placeholder", sources[source].placeholder);
                    $("#employeeLookupHint").text(sources[source].hint);
                }
                if (changed) {
                    resetEmployeeLookup();
                }
            };

            // Put in as text, never as markup: the names and addresses come from another system.
            const show = (found, failed, more) => {
                matches.empty();
                const words = sources[source];
                if (!found.length) {
                    // Nobody matched, or the directory could not be searched (why, is in the application log): they are
                    // said apart, or a setting that is wrong would look like a directory with nobody in it.
                    $('<div class="mt-2 user-directory-note">')
                        .addClass(failed ? "text-orange" : "text-secondary")
                        .text(failed ? words.failed : words.none)
                        .appendTo(matches);
                    return;
                }

                const fromDirectory = source === "directory";
                const list = $('<div class="list-group list-group-flush mt-2 border rounded">');
                found.forEach((person) => {
                    $('<button type="button" class="list-group-item list-group-item-action py-2">')
                        .append($('<span class="d-block fw-bold">').text(person.name || person.username || ""))
                        .append($('<span class="d-block text-secondary">').text(person.email || ""))
                        // Active Directory: the username they sign in with. HR: the staff number, which tells apart two
                        // people who are recorded with one address.
                        .append($('<span class="d-block text-secondary user-directory-id">').text(fromDirectory ? person.username : person.employee_id))
                        .on("click", () => {
                            const modal = $("#userModal");
                            modal.find("#name").val(person.name || person.username || "");
                            modal.find("#email").val(person.email || "");
                            if (fromDirectory) {
                                modal.find("#ldapUsername").val(person.username);
                            }
                            resetEmployeeLookup();
                        })
                        .appendTo(list);
                });
                list.appendTo(matches);
                if (more) {
                    $('<div class="mt-1 user-directory-note text-secondary">')
                        .text("More people match than are shown: type more of the name.")
                        .appendTo(matches);
                }
            };

            const search = () => {
                const typed = lookup.val().trim();
                const mine = ++asked;
                if (source === null || typed.length < minimum) {
                    matches.empty();
                    return;
                }
                $.ajax({
                    url: sources[source].url,
                    type: "GET",
                    dataType: "json",
                    data: {
                        q: typed
                    },
                    // Only the answer to what was typed last (in the place that is chosen now) is shown.
                    success: (response) => {
                        if (mine === asked) {
                            show(response.data || [], response.failed === true, response.more === true);
                        }
                    },
                    error: () => {
                        if (mine === asked) {
                            show([], true, false);
                        }
                    },
                });
            };

            lookup.on("input", debounce(search, 400)).on("keydown", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    search();
                }
            });
        });
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
                        // One role is chosen here: of the roles this form lists, the first the user holds.
                        const held = (response.data.role_id || []).map(String);
                        const listed = modal.find("#roleSelect option").map((i, option) => option.value).get();
                        modal.find("#roleSelect").val(listed.find((roleId) => roleId && held.includes(roleId)) || "");
                        setOtherRoles(modal, response.data.other_role_count);
                        setOwnStatus(modal, response.data.active, response.data.is_self);
                        setSignInMethod(modal, response.data.sign_in_method);
                        modal.find("#ldapUsername").val(response.data.ldap_username || "");
                        resetEmployeeLookup();

                        modal.modal("show");
                    },
                    error: function(xhr, status, error) {
                        const r = xhr.responseJSON || {};
                        Swal.fire("Error!", r.message || "The user could not be loaded.", "error");
                    }
                });
            } else {
                modal.find("#form")[0].reset();
                modal.find("#roleSelect").val(userDefaultRole);
                setOtherRoles(modal, 0);
                setOwnStatus(modal, true, false);
                setSignInMethod(modal, "LDAP");
                resetEmployeeLookup();

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
                setSignInMethod($(this), "LDAP");
                resetEmployeeLookup();
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
            if (modal.find("#roleSelect").val()) {
                formData.append("role_id[]", modal.find("#roleSelect").val());
            }
            formData.append("active", modal.find("#statusSelect").val());
            // Sent only where the form asks for it; a directory username only with LDAP.
            const signInMethod = modal.find('input[name="sign_in_method"]:checked').val();
            if (signInMethod) {
                formData.append("sign_in_method", signInMethod);
                formData.append("ldap_username", signInMethod === "LDAP" ? modal.find("#ldapUsername").val().trim() : "");
            }

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
