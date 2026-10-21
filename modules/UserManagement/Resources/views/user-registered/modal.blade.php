{{--
    The form of a user, for adding one and for changing one.

    The sign-in method comes first: it decides where "Find employee" searches (Active Directory for LDAP, the employee
    directory of HR for Microsoft) and whether a directory username is recorded. "Find employee" is there only where it
    has somewhere to search, for who may add or change a user: see UserRegisteredController::lookupSources().
--}}
@push('style')
    <style>
        /* The microsoft mark beside the method: four squares. */
        #userModal .user-ms-mark {
            display: inline-grid;
            grid-template-columns: 7px 7px;
            gap: 1px;
        }

        #userModal .user-ms-mark > span {
            height: 7px;
        }

        #userModal .user-ms-mark > span:nth-child(1) {
            background: #f25022;
        }

        #userModal .user-ms-mark > span:nth-child(2) {
            background: #7fba00;
        }

        #userModal .user-ms-mark > span:nth-child(3) {
            background: #00a4ef;
        }

        #userModal .user-ms-mark > span:nth-child(4) {
            background: #ffb900;
        }

        #userModal .rm-dialog-body {
            padding: 8px 24px 4px;
        }

        #userModal .user-directory-note {
            font-size: 12px;
            line-height: 16px;
        }
    </style>
@endpush

<div class="modal fade rm-modal wide" data-bs-backdrop="static" data-bs-keyboard="false" id="userModal" aria-labelledby="userModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="rm-dialog-head">
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title modal-title" id="userModalTitle">{{ __('UserManagement::users.form.add_title') }}</h3>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('UserManagement::users.form.close') }}"><i
                        class="ti ti-x"></i></button>
            </div>
            <form id="form" autocomplete="off">
                <div class="rm-dialog-body">
                    <div class="rm-form-rows">
                        @if (count($signInMethods) > 1)
                            {{-- How the account is entered, asked first: it decides where "Find employee" searches and
                                 whether a directory username is recorded. LDAP: it has a directory username, and the login
                                 form checks its password against Active Directory. Microsoft: it has none. Always asked
                                 once the column is there (before its migration there is one method, so no choice). --}}
                            <div class="rm-form-row" role="radiogroup" aria-labelledby="userMethodLabel">
                                <span class="rm-form-label required pt-0" id="userMethodLabel">{{ __('UserManagement::users.form.method') }}</span>
                                <div class="rm-radios">
                                    @foreach ($signInMethods as $method)
                                        <label class="rm-radio">
                                            <input type="radio" name="sign_in_method"
                                                value="{{ $method }}">
                                            <span>
                                                <span class="rm-radio-label">
                                                    @if ($method === 'LDAP')
                                                        <i class="ti ti-server" aria-hidden="true"></i>
                                                    @else
                                                        <span class="user-ms-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                                                    @endif
                                                    {{ $method }}
                                                </span>
                                                <span class="rm-radio-sub">{{ $signInMeanings[$method] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (array_filter($lookupSources))
                            {{-- Above the name, because it fills in the name and the address (and, with LDAP, the directory
                                 username): choosing them is surer than typing them. Where it searches follows the method:
                                 Active Directory for LDAP, the employee directory of HR for Microsoft (see lookupSources()). --}}
                            <div class="rm-form-row" id="userLookupRow">
                                <label for="employeeLookup" class="rm-form-label">{{ __('UserManagement::users.form.find') }}</label>
                                <div class="rm-form-field rm-field">
                                    <div class="rm-search w-100">
                                        <i class="ti ti-search" aria-hidden="true"></i>
                                        <input type="search" id="employeeLookup"
                                            placeholder="{{ __('UserManagement::users.form.hr.placeholder') }}" autocomplete="off">
                                    </div>
                                    <div class="rm-hint" id="employeeLookupHint">{{ __('UserManagement::users.form.hr.hint') }}</div>
                                    <div id="employeeMatches" aria-live="polite"></div>
                                </div>
                            </div>
                        @endif

                        <div class="rm-form-row">
                            <label for="name" class="rm-form-label required">{{ __('UserManagement::users.form.name') }}</label>
                            <div class="rm-form-field rm-field">
                                <input type="text" id="name" class="form-control" name="name"
                                    placeholder="{{ __('UserManagement::users.form.name_placeholder') }}" autocomplete="off" required>
                            </div>
                        </div>
                        <div class="rm-form-row">
                            <label for="email" class="rm-form-label required">{{ __('UserManagement::users.form.email') }}</label>
                            <div class="rm-form-field rm-field">
                                <input type="email" id="email" class="form-control" name="email"
                                    placeholder="{{ __('UserManagement::users.form.email_placeholder') }}" autocomplete="off" required>
                            </div>
                        </div>

                        @if (count($signInMethods) > 1)
                            <div class="rm-form-row" id="userDirectoryRow">
                                <label for="ldapUsername" class="rm-form-label">{{ __('UserManagement::users.form.directory_username') }}</label>
                                <div class="rm-form-field rm-field">
                                    <input type="text" id="ldapUsername" class="form-control" name="ldap_username"
                                        maxlength="128" autocomplete="off"
                                        placeholder="{{ __('UserManagement::users.form.directory_placeholder') }}">
                                    <div class="rm-hint">{{ __('UserManagement::users.form.directory_hint') }}</div>
                                    @unless ($ldapConfigured)
                                        {{-- Said, instead of the choice going away: a setting that went missing
                                             should show where it is missed. --}}
                                        <div class="rm-hint text-orange" id="userLdapOffNote">{{ __('UserManagement::users.form.ldap_off') }}</div>
                                    @endunless
                                </div>
                            </div>

                            {{-- With Microsoft there is no box, not a greyed one: nothing is recorded. --}}
                            <div class="rm-form-row d-none" id="userMicrosoftNote">
                                <span></span>
                                <div class="rm-alert" role="alert">
                                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                                    <span>{{ __('UserManagement::users.form.microsoft_note') }}</span>
                                </div>
                            </div>
                        @endif

                        <div class="rm-form-row">
                            <label for="roleSelect" class="rm-form-label required">{{ __('UserManagement::users.form.role') }}</label>
                            <div class="rm-form-field rm-field">
                                <select id="roleSelect" name="role_id[]" class="form-select" required>
                                    <option value="">{{ __('UserManagement::users.form.select_role') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <div class="rm-hint d-none" id="userOtherRolesNote"></div>
                            </div>
                        </div>

                        <div class="rm-form-row">
                            <label for="statusSelect" class="rm-form-label required">{{ __('UserManagement::users.form.status') }}</label>
                            <div class="rm-form-field rm-field">
                                <select id="statusSelect" name="active" class="form-select" required>
                                    <option value="1">{{ __('UserManagement::users.form.active') }}</option>
                                    <option value="0">{{ __('UserManagement::users.form.inactive') }}</option>
                                </select>
                                <div class="rm-hint d-none" id="userOwnStatusNote">{{ __('UserManagement::users.form.own_status') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rm-dialog-foot is-plain" id="actionButtons">
                    <a href="#" class="rm-link-muted" data-bs-dismiss="modal" role="button">{{ __('UserManagement::users.form.cancel') }}</a>
                    <button type="submit" class="btn-rm update" onclick="userSubmit(event, this)" id="userModalUpdateButton"
                        data-type="edit" data-form-id="id"><i class="ti ti-check" aria-hidden="true"></i> {{ __('UserManagement::users.form.update') }}</button>
                    <button type="submit" class="btn-rm primary" onclick="userSubmit(event, this)" id="userModalSubmitButton"
                        data-type="submit" data-form-id="id"><i class="ti ti-check" aria-hidden="true"></i> {{ __('UserManagement::users.form.submit') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- A password for the e-mail + password form of the login page. Not for an account that signs in with its
     directory password: that one has none here. --}}
<div class="modal fade rm-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="userPasswordModal" aria-labelledby="userPasswordTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="userPasswordForm" autocomplete="off">
            <div class="rm-dialog-head">
                <span class="rm-dialog-icon"><i class="ti ti-key" aria-hidden="true"></i></span>
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title modal-title" id="userPasswordTitle">{{ __('UserManagement::users.password.title') }}</h3>
                    <p class="rm-dialog-sub" id="userPasswordWho"></p>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('UserManagement::users.form.close') }}"><i
                        class="ti ti-x"></i></button>
            </div>
            <div class="rm-dialog-body is-form">
                <div class="rm-field">
                    <label for="userPassword" class="form-label required">{{ __('UserManagement::users.password.new') }}</label>
                    <input type="password" id="userPassword" class="form-control" autocomplete="new-password"
                        maxlength="72" required placeholder="{{ __('UserManagement::users.password.new_placeholder') }}">
                </div>
                <div class="rm-field">
                    <label for="userPasswordAgain" class="form-label required">{{ __('UserManagement::users.password.again') }}</label>
                    <input type="password" id="userPasswordAgain" class="form-control"
                        autocomplete="new-password" maxlength="72" required placeholder="{{ __('UserManagement::users.password.again') }}">
                    <div class="rm-hint">{{ __('UserManagement::users.password.hint') }}</div>
                </div>
            </div>
            <div class="rm-dialog-foot">
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('UserManagement::users.password.cancel') }}</button>
                <button type="submit" class="btn-rm primary" id="userPasswordSaveButton">{{ __('UserManagement::users.password.save') }}</button>
            </div>
        </form>
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
        // The words of the form and of setting a password, in the language of the page (UserManagement::users).
        const userFormText = @json(__('UserManagement::users.form'));
        const userPasswordText = @json(__('UserManagement::users.password'));
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
            // What the search box says and asks, for each place it can look (placeholder, hint, failed, none).
            const sources = {
                hr: { url: urlUserEmployees, ...userFormText.hr },
                directory: { url: urlUserDirectory, ...userFormText.directory },
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
                const list = $('<div class="rm-matches mt-2">');
                found.forEach((person) => {
                    $('<button type="button" class="rm-match">')
                        .append($('<span class="rm-match-name">').text(person.name || person.username || ""))
                        .append($('<span class="rm-match-line">').text(person.email || ""))
                        // Active Directory: the username they sign in with. HR: the staff number, which tells apart two
                        // people who are recorded with one address.
                        .append($('<span class="rm-match-id">').text(fromDirectory ? person.username : person.employee_id))
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
                        .text(userFormText.more)
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

            const stateProperty = {
                'edit': {
                    'title': userFormText.edit_title,
                    'actionButtons': ['edit']
                },
                'add': {
                    'title': userFormText.add_title,
                    'actionButtons': ['submit']
                }
            };

            // Hide all action buttons first
            modal.find("#actionButtons button").addClass("d-none");

            if (stateProperty[state]) {
                modalTitle.text(stateProperty[state].title);

                // Show only the buttons specified for this state
                stateProperty[state].actionButtons.forEach(buttonType => {
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
                        Swal.fire(userFormText.error, r.message || userFormText.load_failed, "error");
                    }
                });
            } else {
                modal.find("#form").trigger("reset");
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
                .text(rmFill(userFormText.other_roles, { count }));
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
                $(this).find("#form").trigger("reset");

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

            rmDialog.confirm({
                layout: "center",
                tone: "sky",
                icon: "help-circle",
                title: userFormText.confirm_title,
                text: state === "submit" ? userFormText.confirm_add : userFormText.confirm_update,
                confirmText: '<i class="ti ti-check"></i> ' + userFormText.save,
                cancelText: '<i class="ti ti-x"></i> ' + userFormText.cancel,
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }
                let errors = [];

                if (!formData.get("name")) {
                    errors.push(userFormText.name_required);
                }

                if (!formData.get("email")) {
                    errors.push(userFormText.email_required);
                }

                const keepsOtherRoles = state !== "submit" && Number(modal.data("otherRoles")) > 0;
                if (!formData.getAll("role_id[]").length && !keepsOtherRoles) {
                    errors.push(userFormText.role_required);
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
                        rmFeedback.toast(userFormText.saved);
                        userRegisteredTable();
                    },
                    error: function(xhr, status, error) {
                        // Show the reason the server gave ("text" is rendered as plain text, never as HTML).
                        const r = xhr.responseJSON || {};
                        const reasons = [].concat(r.errors || r.message || r.error || userFormText.save_failed);
                        Swal.fire({
                            icon: "error",
                            title: userFormText.error,
                            text: reasons.join(" "),
                        });
                    },
                });
            });
        };

        // The reason the server gave, as plain text.
        const userReason = (xhr, fallback) => {
            const r = xhr.responseJSON || {};
            return [].concat(r.errors || r.message || fallback).join(" ");
        };

        const userRowName = (id) => $('#userTable [data-id="' + id + '"]').first().closest("tr").find(".user-name").text();

        const userStatus = (id, active) => {
            $('[data-bs-toggle="tooltip"]').tooltip("hide");
            const name = userRowName(id) || userFormText.the_user;
            rmDialog.confirm({
                tone: active ? "green" : "orange",
                icon: active ? "user-check" : "alert-triangle",
                title: active ? userFormText.activate_title : userFormText.deactivate_title,
                text: rmFill(active ? userFormText.activate_text : userFormText.deactivate_text, { name }),
                confirmText: active ? userFormText.activate : userFormText.deactivate,
                cancelText: userFormText.cancel,
                confirmKind: active ? "success" : "primary",
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
                        rmFeedback.toast(response.message);
                        userRegisteredTable();
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: "error",
                            title: userFormText.error,
                            text: userReason(xhr, userFormText.status_failed)
                        });
                    }
                });
            });
        };

        const toggleUserPasswordModal = (id) => {
            $('[data-bs-toggle="tooltip"]').tooltip("hide");
            const modal = $("#userPasswordModal");
            modal.data("userId", id);
            modal.find("#userPasswordForm").trigger("reset");
            modal.find("#userPasswordWho").text(userRowName(id));
            modal.modal("show");
        };

        $(function() {
            $("#userPasswordModal").on("hidden.bs.modal", function() {
                $(this).find("#userPasswordForm").trigger("reset");
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
                    errors.push(userPasswordText.too_weak);
                }
                if (password !== again) {
                    errors.push(userPasswordText.not_same);
                }
                if (errors.length > 0) {
                    showError(errors);
                    return;
                }

                rmFeedback.busy("#userPasswordSaveButton", true);
                $.ajax({
                    url: urlUserPassword.replace(":id", modal.data("userId")),
                    type: "PUT",
                    data: {
                        password: password,
                        password_confirmation: again
                    },
                    success: function(response) {
                        modal.modal("hide");
                        rmFeedback.toast(response.message);
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: "error",
                            title: userPasswordText.error,
                            text: userReason(xhr, userPasswordText.failed)
                        });
                    },
                    complete: function() {
                        rmFeedback.busy("#userPasswordSaveButton", false);
                    }
                });
            });
        });

        const userDelete = (id) => {
            $('[data-bs-toggle="tooltip"]').tooltip("hide");
            rmDialog.confirm({
                tone: "red",
                icon: "trash",
                title: userFormText.delete_title,
                text: userFormText.delete_text,
                confirmText: '<i class="ti ti-trash"></i> ' + userFormText.delete_confirm,
                cancelText: userFormText.cancel,
                confirmKind: "destructive",
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }
                $.ajax({
                    url: urlUserDestroy.replace(":id", id),
                    type: "DELETE",
                    success: function(response) {
                        rmFeedback.toast(response.message);
                        // The row's tooltip would otherwise stay on screen after the row is gone.
                        $('.tooltip').remove();
                        // Step back when the deleted row was the only one on this page.
                        if (currentPage > 1 && $('#userTable tbody tr').length <= 1) {
                            currentPage -= 1;
                        }
                        userRegisteredTable();
                    },
                    error: function(xhr) {
                        let errorMessage = userFormText.delete_failed;
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        Swal.fire(userFormText.error, errorMessage, "error");
                    }
                });
            });
        };
    </script>
@endpush
