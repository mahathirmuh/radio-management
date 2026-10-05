//FORM ASK
$(".swa-confirm").on("click", function (e) {
    let frmID = "#" + $(this).closest("form").attr("id");
    e.preventDefault();
    Swal.fire({
        title: "Data is Correct?",
        text: "Data will be saved!",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-check"></i> Save',
        cancelButtonText: '<i class="ti ti-x"></i> Cancel',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            var fail = false;
            var fail_log = [];
            //validate fields
            $(frmID)
                .find("input, textarea, select")
                .each(function () {
                    if ($(this).prop("required")) {
                        if ($(this).val() == null || $(this).val() == "") {
                            fail = true;
                            let label = $(
                                'label[for="' + $(this).attr("id") + '"]'
                            ).text();
                            if (label == null || label == "") {
                                label = $(
                                    'label[for="' +
                                        $(this).attr("id") +
                                        "-ts-control" +
                                        '"]'
                                ).text();
                            }
                            if (label !== "") {
                                fail_log.push(
                                    " " + "<br> <b>" + label + "</b>"
                                );
                            }
                        }
                    }
                });
            if (!fail) {
                $(frmID).submit();
            } else {
                Swal.fire(
                    "Save Failed!",
                    "Mandatory Form " + fail_log + " <br> Required",
                    "error"
                );
            }
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Canceled!", "Data not saved!", "error");
        }
    });
});

//FORM DELETE
$(".swa-delete").on("click", function (e) {
    let frmID = "#" + $(this).closest("form").attr("id");
    e.preventDefault();
    Swal.fire({
        title: "Delete Data?",
        text: "Data will be Deleted!",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-check"></i> Delete',
        cancelButtonText: '<i class="ti ti-x"></i> Cancel',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            $(frmID).submit();
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Canceled!", "Undeleted Data!", "error");
        }
    });
});

//FORM ONCLICK CONFIRM
function SwaConfirm(event, button) {
    event.preventDefault();
    const href = button.getAttribute("data-href");

    Swal.fire({
        title: "Sure?",
        text: "Data will be Process!",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-check"></i> Process',
        cancelButtonText: '<i class="ti ti-x"></i> Cancel',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = href;
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Canceled!", "Canceled Action!", "warning");
        }
    });
}

function SwaReset(event, button) {
    event.preventDefault();
    const href = button.getAttribute("data-href");

    Swal.fire({
        title: "Sure?",
        text: "Data will be Process!",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-check"></i> Reset',
        cancelButtonText: '<i class="ti ti-x"></i> Cancel',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = href;
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Canceled!", "Canceled Action!", "warning");
        }
    });
}

//FORM ONCLICK DELETE
function SwaDelete(event, id) {
    let frmID = "#" + id;
    event.preventDefault();
    Swal.fire({
        title: "Delete Data?",
        text: "Data will be Deleted!",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-check"></i> Delete',
        cancelButtonText: '<i class="ti ti-x"></i> Cancel',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            $(frmID).submit();
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Canceled!", "Undeleted Data!", "warning");
        }
    });
}

//LOGOUT
$(".swa-logout").on("click", function (e) {
    let frmID = "#" + $(this).closest("form").attr("id");
    e.preventDefault();
    Swal.fire({
        title: "Logout?",
        text: "Account will be Logout!",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-check"></i> Logout',
        cancelButtonText: '<i class="ti ti-x"></i> Cancel',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            $(frmID).submit();
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            Swal.fire("Canceled!", "Logout Cancel!", "error");
        }
    });
});
