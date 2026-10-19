<script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js?1684106062') }}" defer></script>
<script src="{{ asset('assets/js/apps.min.js?1684106062') }}" defer></script>
<script src="{{ asset('assets/js/prevent.js') }}" defer></script>
<script src="{{ asset('vendor/sweetalert/sweetalert.all.js') }}"></script>
<script src="{{ asset('assets/libs/jquery/jquery.min.js') }}"></script>
<script type="text/javascript" defer>
    let csrf = '{{ csrf_token() }}';
    let timezone = 'Asia/Jakarta';
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrf,
        }
    });
    $(window).on("load", function() {
        $("#loader").fadeOut("slow");
    });
    $(document).ready(function() {
        // Remove background color from table th
        $('.table thead th').css('background-color', 'transparent');
    });
</script>

<!-- Pagination -->
<script type="text/javascript">
    function paginate(id, pagination, callback, params = null) {
        let html = `
            <div class="d-flex flex-row justify-content-start align-items-center gap-3">
                <a role="button" class="btn-previous text-decoration-none ${ pagination.current_page <= 1 ? 'pe-none disabled' : 'cursor-pointer' }"
                    ${ pagination.current_page <= 1 ? 'disabled' : '' } onclick="paginateAction('${ id }', ${ pagination.current_page - 1 }, ${ callback }, ${ params })">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-left">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M15 6l-6 6l6 6" />
                    </svg>
                </a>

                <a role="button"
                    class="btn-pagination text-decoration-none ${ pagination.current_page == 1 ? 'mdka-btn-blue-500 text-white ' : '' } " onclick="paginateAction('${ id }', 1, ${ callback }, ${ params })"><span>1</span></a>
                ${pagination.current_page > 4 ? '<span class="">...</span>' : ''}

        `;

        // Calculate the start and end page range
        const startPage = Math.max(pagination.current_page - 2, 2);
        const endPage = Math.min(pagination.current_page + 2, pagination.last_page - 1);

        // Generate the page range array
        const pageRange = Array.from({
            length: endPage - startPage + 1
        }, (_, i) => startPage + i);

        // Generate the HTML for the page range
        for (let i = pageRange[0]; i <= pageRange[pageRange.length - 1]; i++) {
            html += `
                    <a role="button"
                        class="btn-pagination text-decoration-none ${ pagination.current_page == i ? 'mdka-btn-blue-500 text-white ' : '' } "
                        onclick="paginateAction('${ id }', ${ i }, ${ callback }, ${ params })">
                        <span>${i}</span>
                    </a>`;
        }

        html += `
                ${pagination.current_page < pagination.last_page - 3 ?  '<span class=""> ... </span>' : ''}
                ${pagination.last_page != 1  ? `<a role="button"
                    class="btn-pagination text-decoration-none ${ pagination.current_page == pagination.last_page ? 'mdka-btn-blue-500 text-white' : '' }" onclick="paginateAction('${ id }', ${ pagination.last_page }, ${ callback }, ${ params })"><span>${ pagination.last_page }</span></a>` : ''}

                <a role="button" class="btn-next ${ pagination.current_page >= pagination.last_page ? 'pe-none disabled' : 'cursor-pointer' }" onclick="paginateAction('${ id }', ${ pagination.current_page + 1 }, ${ callback }, ${ params })">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M9 6l6 6l-6 6" />
                    </svg>
                </a>
            </div>
        `;

        $(id).html(html);
        $(id).append('<input type="hidden" name="total" value="' + pagination.total + '">');
        $(id).append('<input type="hidden" name="last_page" value="' + pagination.last_page + '">');
        $(id).append('<input type="hidden" name="current_page" value="' + pagination.current_page + '">');
        $(id).append('<input type="hidden" name="limit" value="' + pagination.limit + '">');

        // $(id).val(pagination.current_page);
        $(id).change();
    }

    paginateAction = (id, page, callback, params) => {
        $(id).find('input[name="current_page"]').val(page)
        // console.log($(id).find('input[name="current_page"]').val());
        callback(params);
    }
</script>



<!-- Debounce -->
<script>
    function debounce(func, timeout = 350) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                func.apply(this, args);
            }, timeout);
        };
    }
</script>


<script>
    function renderTableWithFeatures({
        container,
        data = [],
        columns = [],
        currentPage = 1,
        limit = 10,
        emptyText = 'No data found',
        loading = false,
        meta = null,
        onPageChange = () => {},
        onLimitChange = () => {},
        onSortChange = () => {},
        currentSortKey = null,
        currentSortDir = 'asc',
        paginationContainer = ' .pagination-container',
    }) {
        renderTable({
            container,
            data,
            columns,
            currentPage,
            limit,
            emptyText,
            loading,
        });

        if (!loading && meta) {
            renderPagination({
                meta,
                limit,
                container: paginationContainer,
                onPageChange,
                onLimitChange,
            });
        }

        if (!loading) {
            initSorting({
                container,
                columns,
                currentSortKey,
                currentSortDir,
                onSortChange,
            });
        }
    }


    /**
     * Renders table rows into a given table container.
     * @param {Object} options
     * @param {string} options.container CSS selector for the table element (should contain <tbody>)
     * @param {Array} options.data Array of data objects
     * @param {Array<string|Object>} options.columns Definitions for columns: string keys, 'index', or objects { key, className, render }
     * @param {number} [options.currentPage=1] Current page number (for index column)
     * @param {number} [options.limit=10] Number of items per page (for index column)
     * @param {string} [options.emptyText='No data found'] Message when no data
     * @param {boolean} [options.loading=false] If true, shows loading state
     */
    function renderTable({
        container,
        data = [],
        columns = [],
        currentPage = 1,
        limit = 10,
        emptyText = 'No data found',
        loading = false,
    }) {
        const tbody = $(`${container} tbody`);


        if (loading) {
            tbody.html(`<tr><td colspan="${columns.length}" class="text-center">Loading...</td></tr>`);
            return;
        }

        if (!data || data.length == 0) {
            tbody.html(`<tr><td colspan="${columns.length}" class="text-center">${emptyText}</td></tr>`);
            return;
        }

        const startNumber = (currentPage - 1) * limit + 1;
        let html = '';

        data.forEach((item, index) => {
            html += '<tr>';
            columns.forEach((col) => {
                if (col === 'index') {
                    html += `<td class="text-center">${startNumber + index}</td>`;
                } else if (typeof col === 'object') {
                    const value = typeof col.render === 'function' ? col.render(item, index) : item[col
                        .key] || '';
                    html += `<td class="${col.className || ''}">${value}</td>`;
                } else {
                    html += `<td>${item[col] || ''}</td>`;
                }
            });
            html += '</tr>';
        });

        tbody.html(html);
    }

    /**
     * Renders pagination controls into a container.
     * @param {Object} options
     * @param {Object} options.meta Pagination meta from server (total, current_page, last_page, from, to)
     * @param {string} [options.container='.pagination-container'] CSS selector for pagination wrapper
     * @param {Function} [options.onPageChange] Callback receiving new page number
     * @param {Function} [options.onLimitChange] Callback receiving new limit value
     * @param {number} [options.limit=10] Current limit (to highlight selected option)
     */
    function renderPagination({
        meta,
        container = '.pagination-container',
        onPageChange = () => {},
        onLimitChange = () => {},
        limit = 10,
    }) {
        const el = $(container);
        if (!meta || meta.total === 0) {
            return el.hide();
        }

        let html = `
        <div class="d-flex justify-content-between align-items-center">
            <div class=" d-flex flex-row justify-content-start align-items-center g-3 gap-3">
    `;

        const prevDisabled = meta.current_page <= 1 ? 'pe-none disabled' : 'cursor-pointer';
        html +=
            `<a class="btn-previous text-decoration-none ${prevDisabled}" data-page="${meta.current_page - 1}">←</a>`;
        html +=
            `<a data-page="1" class="btn-pagination cursor-pointer text-decoration-none ${meta.current_page === 1 ? 'mdka-btn-blue-500 text-white' : ''}">1</a>`;

        if (meta.current_page > 4) html += `<span class="">...</span>`;

        if (meta.last_page > 2) {
            const start = Math.max(meta.current_page - 2, 2);
            const end = Math.min(meta.current_page + 2, meta.last_page - 1);
            for (let i = start; i <= end; i++) {
                html +=
                    `<a data-page="${i}" class="btn-pagination cursor-pointer text-decoration-none ${meta.current_page === i ? 'mdka-btn-blue-500 text-white' : ''}">${i}</a>`;
            }
        }

        if (meta.current_page < meta.last_page - 3) html += `<span class="">...</span>`;

        if (meta.last_page > 1) {
            html +=
                `<a data-page="${meta.last_page}" class="btn-pagination cursor-pointer text-decoration-none ${meta.current_page === meta.last_page ? 'mdka-btn-blue-500 text-white' : ''}">${meta.last_page}</a>`;
        }

        const nextDisabled = meta.current_page >= meta.last_page ? 'pe-none disabled' : 'cursor-pointer';
        html += `<a class="btn-next ${nextDisabled}" data-page="${meta.current_page + 1}">→</a>`;

        html += `</div>
                    <div class=" d-flex flex-row justify-content-end align-items-center gap-2">
                        <div class="mb-0 text-nowrap">Showing ${meta.from || 0} - ${meta.to || 0} of ${meta.total}</div>
                        <div class="mb-0 text-nowrap">Show :</div>
                        <select class="form-select pagination-limit">
                            <option value="10" ${limit == 10 ? 'selected' : ''}>10</option>
                            <option value="25" ${limit == 25 ? 'selected' : ''}>25</option>
                            <option value="50" ${limit == 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${limit == 100 ? 'selected' : ''}>100</option>
                        </select>
                    </div>
                </div>`;

        el.html(html).show();

        // Bind events
        el.find('.btn-pagination, .btn-previous, .btn-next').off('click').on('click', function() {
            const page = $(this).data('page');
            if (page) onPageChange(page);
        });

        el.find('.pagination-limit').off('change').on('change', function() {
            onLimitChange(parseInt($(this).val(), 10));
        });
    }

    function initSorting({
        container,
        columns = [],
        currentSortKey = null,
        currentSortDir = 'asc',
        onSortChange = () => {},
    }) {
        const table = $(container);
        const upIcon = `
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-up">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M6 15l6 -6l6 6" />
            </svg>`;
        const downIcon = `
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-down">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M6 9l6 6l6 -6" />
            </svg>`;

        columns.forEach(col => {
            if (typeof col === 'object' && col.key && col.sortable) {
                const th = table.find(`th[data-key="${col.key}"]`);
                if (!th.length) return;

                // Determine icon based on current sort state
                let iconHtml = '';
                if (currentSortKey === col.key) {
                    iconHtml = currentSortDir === 'asc' ? upIcon : downIcon;
                }

                // Wrap header text and icon in flex container. The text goes back in as text,
                // not as markup: a header may come from data (the column titles of a workbook).
                const headerText = th.text().trim();
                th.html(`
                <div class="d-flex align-items-center w-100 cursor-pointer">
                    <span class="flex-grow-1 text-wrap ${col.headerClassName}"></span>
                    <span class="sort-icon small">${iconHtml}</span>
                </div>
            `);
                th.find('span.flex-grow-1').text(headerText);

                th.addClass('sortable')
                    .off('click')
                    .on('click', () => {
                        const newDir = (currentSortKey === col.key && currentSortDir === 'asc') ? 'desc' :
                            'asc';
                        onSortChange(col.key, newDir);
                    });
            }
        });
    }
</script>

<script>
    $(function() {
        $('[data-bs-toggle="tooltip"]').tooltip({
            trigger: 'hover'
        });

        $('[data-bs-toggle="tooltip"]').on('click', function() {
            $(this).tooltip('hide')
        });
    });
</script>

{{-- Utils --}}
<script>
    const showError = (errors) => {
        Swal.fire(
            'Sorry!',
            errors.join('<br>'),
            'error'
        );
    };

    function formatTime(dateTimeString) {
        if (!dateTimeString) return '';
        const time = new Date(dateTimeString);
        return time.toTimeString().slice(0, 5); // ambil HH:MM
    }

    function formatDate(dateTimeString) {
        if (!dateTimeString) return '';
        const date = new Date(dateTimeString);
        return date.toISOString().slice(0, 10); // ambil YYYY-MM-DD
    }
</script>

{{-- The clocks of the session (App\Services\SessionClock, EndExpiredSession): a warning before it ends, and the login
     page once it has, which says why. The server decides; this script only asks it in time. --}}
@auth
    @php
        $sessionClock = app(\App\Services\SessionClock::class);
        $sessionMaximum = $sessionClock->absoluteMinutes();
    @endphp
    <script>
        const sessionTimer = {
            urls: @json(['status' => route('session.status'), 'keepAlive' => route('session.keep-alive'), 'login' => route('login')]),
            idleMinutes: @json($sessionClock->idleMinutes()),
            maximum: @json($sessionMaximum % 60 === 0 ? $sessionMaximum / 60 . ' jam' : $sessionMaximum . ' menit'),
            // How long before each timeout (seconds) the person is warned.
            warnIdle: 120,
            warnAbsolute: 300,
            timer: null,
            ticking: null,
            warnedAbsolute: false,
            ending: false,

            /** "1:05" for 65 seconds. */
            clock(seconds) {
                const whole = Math.max(0, Math.round(seconds));
                return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
            },
            isEnd(xhr) {
                return !!xhr && (xhr.status === 401 || xhr.status === 419);
            },
            /** Asks the server again in so many seconds. */
            wait(seconds) {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.check(), Math.max(1, Math.round(seconds)) * 1000);
            },
            /** The server's answer (the seconds left): a warning when one is due, else waiting until it is. */
            plan(left) {
                const idle = Math.max(0, Number(left && left.idle_left) || 0);
                const absolute = Math.max(0, Number(left && left.absolute_left) || 0);
                if (absolute <= this.warnAbsolute && !this.warnedAbsolute) return this.warn('absolute', idle, absolute);
                if (idle <= this.warnIdle) return this.warn('idle', idle, absolute);
                this.wait(Math.min(idle - this.warnIdle, this.warnedAbsolute ? absolute : absolute - this.warnAbsolute));
            },
            /** Asking does not count as activity. A refusal because the session is over is seen to by ended(), for every request. */
            check() {
                if (this.ending) return;
                $.ajax({ url: this.urls.status, dataType: 'json', cache: false })
                    .done(left => this.plan(left))
                    .fail(xhr => { if (!this.isEnd(xhr)) this.wait(60); });
            },
            keepAlive() {
                $.ajax({ url: this.urls.keepAlive, type: 'POST', dataType: 'json' })
                    .done(left => this.plan(left))
                    .fail(xhr => { if (!this.isEnd(xhr)) this.wait(60); });
            },
            signOut() {
                this.ending = true;
                clearTimeout(this.timer);
                $('#logout-form').trigger('submit');
            },
            /**
             * The session ends soon (seconds left of each clock): for idleness (the person may stay), or at its maximum
             * length (they sign in again). The countdown is of what is warned of; the dialog closes when the session
             * really ends if nothing is done, the earlier of the two.
             */
            warn(kind, idle, absolute) {
                clearTimeout(this.timer);
                const forIdle = kind === 'idle';
                if (!forIdle) this.warnedAbsolute = true;
                const shown = forIdle ? Math.min(idle, absolute) : absolute;
                const deadline = Date.now() + shown * 1000;
                Swal.fire({
                    icon: 'warning',
                    title: 'Sesi akan berakhir',
                    html: (forIdle ? `Tidak ada aktivitas selama hampir ${this.idleMinutes} menit.` : `Demi keamanan, satu sesi paling lama ${this.maximum}; simpan pekerjaan Anda.`)
                        + ` Anda akan keluar otomatis dalam <b class="session-countdown">${this.clock(shown)}</b>.`,
                    showCancelButton: true,
                    confirmButtonText: forIdle ? 'Tetap masuk' : 'Login ulang sekarang',
                    cancelButtonText: forIdle ? 'Keluar' : 'Nanti',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    // Two seconds more: by the time it asks again, the server has ended the session.
                    timer: (Math.min(idle, absolute) + 2) * 1000,
                    timerProgressBar: true,
                    didOpen: () => {
                        this.ticking = setInterval(() => {
                            const countdown = Swal.getHtmlContainer()?.querySelector('.session-countdown');
                            if (countdown) countdown.textContent = this.clock((deadline - Date.now()) / 1000);
                        }, 1000);
                    },
                    willClose: () => clearInterval(this.ticking),
                }).then(result => {
                    if (result.isConfirmed) return forIdle ? this.keepAlive() : this.signOut();
                    // "Keluar" signs out; "Nanti" is the person being there, as "Tetap masuk" is.
                    if (result.dismiss === 'cancel') return forIdle ? this.signOut() : this.keepAlive();
                    this.check();
                });
            },
            /** A request of the page was refused because the session is over: to the login page. */
            ended(xhr) {
                if (!this.isEnd(xhr) || this.ending) return;
                this.ending = true;
                clearTimeout(this.timer);
                // The server ended it, and the login page says why.
                if (xhr.responseJSON && xhr.responseJSON.session_ended) {
                    window.location.href = this.urls.login;
                    return;
                }
                Swal.fire({
                    icon: 'info',
                    title: 'Sesi berakhir',
                    text: 'Sesi Anda sudah berakhir. Silakan login lagi.',
                    confirmButtonText: 'Login',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                }).then(() => { window.location.href = this.urls.login; });
            },
        };
        $(function() {
            sessionTimer.plan(@json($sessionClock->left(request()->session())));
            $(document).on('ajaxError', (event, xhr) => sessionTimer.ended(xhr));
        });
    </script>
@endauth
@stack('javascript')
