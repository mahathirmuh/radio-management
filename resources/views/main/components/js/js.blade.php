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
        // Remove background color from table th (not from the tables of the design layer, whose header is grey).
        $('.table:not(.rm-table) thead th').css('background-color', 'transparent');
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
        paginationLabels = {},
        rowAttrs = null,
    }) {
        renderTable({
            container,
            data,
            columns,
            currentPage,
            limit,
            emptyText,
            loading,
            rowAttrs,
        });

        if (!loading && meta) {
            renderPagination({
                meta,
                limit,
                container: paginationContainer,
                onPageChange,
                onLimitChange,
                labels: paginationLabels,
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
     * @param {Function} [options.rowAttrs] (item, index) => attributes of the row's <tr>, already escaped (data-href, class)
     */
    function renderTable({
        container,
        data = [],
        columns = [],
        currentPage = 1,
        limit = 10,
        emptyText = 'No data found',
        loading = false,
        rowAttrs = null,
    }) {
        const tbody = $(`${container} tbody`);


        if (loading) {
            tbody.html(`<tr><td colspan="${columns.length}" class="text-center rm-empty">Loading...</td></tr>`);
            return;
        }

        if (!data || data.length == 0) {
            tbody.html(`<tr><td colspan="${columns.length}" class="text-center rm-empty">${emptyText}</td></tr>`);
            return;
        }

        const startNumber = (currentPage - 1) * limit + 1;
        let html = '';

        data.forEach((item, index) => {
            const attrs = typeof rowAttrs === 'function' ? rowAttrs(item, index) : '';
            html += attrs ? `<tr ${attrs}>` : '<tr>';
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
     * @param {Object} [options.labels] The words of the footer in the language of the page: showing, of, rows (the size menu),
     *     noun (what is counted, e.g. "radio"), previous, next
     */
    function renderPagination({
        meta,
        container = '.pagination-container',
        onPageChange = () => {},
        onLimitChange = () => {},
        limit = 10,
        labels = {},
    }) {
        const el = $(container);
        if (!meta || meta.total === 0) {
            return el.hide();
        }

        const words = { showing: 'Showing', of: 'of', rows: 'Rows per page', noun: '', previous: 'Previous page', next: 'Next page', ...labels };
        const current = Number(meta.current_page) || 1;
        const last = Number(meta.last_page) || 1;
        // The first and the last page, and the ones next to this one; "…" where pages are left out.
        const shown = [...new Set([1, last, current - 1, current, current + 1])].filter(n => n >= 1 && n <= last).sort((a, b) => a - b);
        const pages = shown.map((n, i) => (i && n - shown[i - 1] > 1 ? '<span class="rm-pager-dots">…</span>' : '') +
            (n === current ?
                `<button type="button" class="rm-pager-page btn-pagination is-on" data-page="${n}" aria-current="page">${n}</button>` :
                `<button type="button" class="rm-pager-page btn-pagination" data-page="${n}">${n}</button>`)).join('');
        const sizes = [10, 25, 50, 100].map(n => `<a href="#" class="dropdown-item rm-size-item pagination-limit${n == limit ? ' is-on' : ''}" data-limit="${n}">${n}</a>`).join('');

        const html = `
            <div class="rm-foot">
                <span class="rm-foot-text">${words.showing} ${meta.from || 0}–${meta.to || 0} ${words.of} ${meta.total}${words.noun ? ' ' + words.noun : ''}</span>
                <div class="rm-end">
                    <span class="rm-size">${words.rows}
                        <span class="dropdown">
                            <button type="button" class="rm-size-btn" data-bs-toggle="dropdown" aria-expanded="false">${Number(limit)}<i class="ti ti-chevron-down" aria-hidden="true"></i></button>
                            <span class="dropdown-menu dropdown-menu-end rm-menu rm-size-menu">${sizes}</span>
                        </span>
                    </span>
                    <span class="rm-pager">
                        <button type="button" class="rm-pager-arrow btn-previous" data-page="${current - 1}" aria-label="${words.previous}"${current <= 1 ? ' disabled' : ''}><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                        ${pages}
                        <button type="button" class="rm-pager-arrow btn-next" data-page="${current + 1}" aria-label="${words.next}"${current >= last ? ' disabled' : ''}><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                    </span>
                </div>
            </div>`;

        el.html(html).show();

        // Bind events
        el.find('.btn-pagination, .btn-previous, .btn-next').off('click').on('click', function() {
            const page = $(this).data('page');
            if (page) onPageChange(page);
        });

        el.find('.pagination-limit').off('click').on('click', function(event) {
            event?.preventDefault?.();
            onLimitChange(parseInt($(this).data('limit'), 10));
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

        columns.forEach(col => {
            if (typeof col === 'object' && col.key && col.sortable) {
                const th = table.find(`th[data-key="${col.key}"]`);
                if (!th.length) return;

                // Every sortable header shows its arrow: grey, or blue and pointing the way of the order on the column
                // the list is sorted by. The text goes back in as text, not as markup: a header may come from data
                // (the column titles of a workbook).
                const on = currentSortKey === col.key;
                const headerText = th.text().trim();
                th.html(`<span class="rm-sort-label ${col.headerClassName || ''}"></span><span class="rm-sort" aria-hidden="true"><i class="ti ti-chevron-${on && currentSortDir === 'desc' ? 'down' : 'up'}"></i></span>`);
                th.find('span.rm-sort-label').text(headerText);

                th.addClass('sortable')
                    .toggleClass('is-sorted', on)
                    .attr('aria-sort', on ? (currentSortDir === 'desc' ? 'descending' : 'ascending') : 'none')
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

{{-- The design layer's feedback (radio-ui.css): a short message at the foot of the window after something was done, and a
     button that shows it is busy while its request runs. --}}
<script>
    const rmFeedback = {
        /** The message goes in as text, never as markup. tone: success | error. */
        toast(text, tone = 'success') {
            const error = tone === 'error';
            return Swal.fire({
                toast: true,
                position: 'bottom',
                timer: 2600,
                showConfirmButton: false,
                icon: error ? 'error' : 'success',
                iconHtml: `<i class="ti ti-${error ? 'alert-circle' : 'circle-check'}"></i>`,
                titleText: String(text ?? ''),
                customClass: { container: 'rm-toast-wrap', popup: `rm-toast rm-toast-${error ? 'error' : 'success'}`, icon: 'rm-toast-icon', title: 'rm-toast-title' },
            });
        },
        /** A spinner in the button, and no second click until the answer is back. */
        busy(button, on) {
            $(button).toggleClass('is-busy', !!on).prop('disabled', !!on).attr('aria-busy', on ? 'true' : 'false');
        },
    };

    // A question before something is done, in the design's dialog: the icon in a round tile beside the title, the
    // buttons at the foot. tone: blue | green | orange | red | yellow; confirmKind: primary | success | destructive.
    // "text" goes in as text; "html" only when the caller has escaped what it puts in.
    const rmDialog = {
        confirm({ tone = 'blue', icon = 'help', title = '', text = '', html = null, confirmText = 'OK', cancelText = 'Batal',
            confirmKind = 'primary', input = undefined, inputAttributes = undefined, preConfirm = undefined } = {}) {
            return Swal.fire({
                title,
                ...(html === null ? { text } : { html }),
                icon: 'question',
                iconHtml: `<i class="ti ti-${icon}"></i>`,
                input,
                inputAttributes,
                preConfirm,
                showCancelButton: true,
                reverseButtons: true,
                focusCancel: confirmKind === 'destructive',
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                buttonsStyling: false,
                customClass: { popup: 'rm-swal', icon: `rm-swal-icon tone-${tone}`, confirmButton: `btn-rm ${confirmKind}`, cancelButton: 'btn-rm secondary' },
            });
        },
    };
</script>

{{-- The design layer's pickers (radio-ui.css): a multiple <select> drawn as a menu of boxes to tick (Sel), and a date
     field with its calendar (RangeCal, one date). The native fields stay in the page and keep the value, so a page reads
     them as before; what they show is drawn next to them. --}}
<script>
    const rmPickers = {
        months: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
        shortMonths: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        weekdays: ['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'],

        /** YYYY-MM-DD of a date (local time). */
        iso(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        },
        /** The date of YYYY-MM-DD, or null. */
        parse(text) {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(text || ''));
            return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
        },
        /** "01 Okt 2026". */
        label(text) {
            const date = rmPickers.parse(text);
            return date ? `${String(date.getDate()).padStart(2, '0')} ${rmPickers.shortMonths[date.getMonth()]} ${date.getFullYear()}` : '';
        },

        /**
         * A <select multiple> as a button with what is chosen (and how many) and a menu of boxes to tick, with "Pilih semua"
         * and "Kosongkan". The <select> is hidden and keeps the value; ticking triggers its "change".
         */
        multi(select, { all = 'Semua', pickAll = 'Pilih semua', clear = 'Kosongkan' } = {}) {
            const field = $(select);
            if (!field.length || field.data('rmPicker')) return;
            field.data('rmPicker', true).addClass('d-none').attr({ tabindex: '-1', 'aria-hidden': 'true' });
            const id = field.attr('id');
            const wrap = $('<div class="dropdown rm-sel-wrap"></div>');
            const button = $('<button type="button" class="rm-sel" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><span class="rm-sel-text"></span><span class="rm-sel-count d-none"></span><i class="ti ti-chevron-down" aria-hidden="true"></i></button>');
            const menu = $('<div class="dropdown-menu rm-menu rm-sel-menu"><div class="rm-sel-head"><a href="#" data-pick="all"></a><a href="#" data-pick="none"></a></div></div>');
            menu.find('[data-pick="all"]').text(pickAll);
            menu.find('[data-pick="none"]').text(clear);
            if (id) {
                button.attr('id', `${id}Button`);
                $(`label[for="${id}"]`).attr('for', `${id}Button`);
            }
            field.find('option').each(function() {
                const option = $('<label class="rm-sel-option"><input type="checkbox"><span></span></label>');
                option.find('input').val(this.value);
                option.find('span').text(this.textContent.trim());
                menu.append(option);
            });
            const show = () => {
                const chosen = field.find('option:selected').map(function() { return this.textContent.trim(); }).get();
                button.find('.rm-sel-text').text(chosen.length ? chosen.join(', ') : all).toggleClass('is-empty', !chosen.length);
                button.find('.rm-sel-count').text(chosen.length).toggleClass('d-none', chosen.length < 2);
                const values = (field.val() || []).map(String);
                menu.find('input').each(function() { this.checked = values.includes(this.value); });
            };
            menu.on('change', 'input', () => {
                field.val(menu.find('input:checked').map(function() { return this.value; }).get()).trigger('change');
            });
            menu.on('click', '[data-pick]', function(event) {
                event.preventDefault();
                field.val($(this).data('pick') === 'all' ? field.find('option').map(function() { return this.value; }).get() : []).trigger('change');
            });
            field.on('change', show);
            wrap.append(button, menu);
            field.after(wrap);
            show();
        },

        /**
         * A date field: the hidden input keeps YYYY-MM-DD; the button shows "01 Okt 2026" and opens the calendar under it
         * (Monday first). min() and max() give the first and last day that may be picked (YYYY-MM-DD or null).
         */
        date(input, { empty = 'Semua tanggal', min = () => null, max = () => rmPickers.iso(new Date()) } = {}) {
            const field = $(input);
            if (!field.length || field.data('rmPicker')) return;
            field.data('rmPicker', true);
            const id = field.attr('id');
            const wrap = $('<div class="rm-date-wrap"></div>');
            const button = $('<button type="button" class="rm-date" aria-haspopup="dialog" aria-expanded="false"><span class="rm-date-text"></span><i class="ti ti-calendar" aria-hidden="true"></i></button>');
            if (id) {
                button.attr('id', `${id}Button`);
                $(`label[for="${id}"]`).attr('for', `${id}Button`);
            }
            const panel = $('<div class="rm-cal" role="dialog" hidden></div>');
            let month = null;

            const show = () => {
                const value = field.val();
                button.find('.rm-date-text').text(value ? rmPickers.label(value) : empty).toggleClass('is-empty', !value);
            };
            const close = () => {
                panel.attr('hidden', 'hidden');
                button.attr('aria-expanded', 'false').removeClass('is-open');
                $(document).off('.rmDate' + id);
            };
            const pick = value => {
                field.val(value).trigger('change');
                close();
                button.trigger('focus');
            };
            const draw = () => {
                const today = rmPickers.iso(new Date());
                const first = rmPickers.parse(min());
                const last = rmPickers.parse(max());
                const start = new Date(month.getFullYear(), month.getMonth(), 1);
                const lead = (start.getDay() + 6) % 7;
                const days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
                const out = (date) => (first && date < first) || (last && date > last);
                const cells = [];
                for (let i = 0; i < 42; i++) {
                    const day = i - lead + 1;
                    if (day < 1 || day > days) {
                        cells.push('<span class="rm-cal-cell"></span>');
                        continue;
                    }
                    const date = new Date(month.getFullYear(), month.getMonth(), day);
                    const iso = rmPickers.iso(date);
                    const classes = ['rm-cal-day', iso === today ? 'is-today' : '', iso === field.val() ? 'is-on' : ''].join(' ').trim();
                    cells.push(`<span class="rm-cal-cell"><button type="button" class="${classes}" data-date="${iso}"${out(date) ? ' disabled' : ''}>${day}</button></span>`);
                }
                const before = new Date(month.getFullYear(), month.getMonth(), 0);
                const after = new Date(month.getFullYear(), month.getMonth() + 1, 1);
                const todayOk = !out(rmPickers.parse(today));
                panel.html(`
                    <div class="rm-cal-head">
                        <button type="button" class="rm-cal-nav" data-nav="-1" aria-label="Bulan sebelumnya"${first && before < first ? ' disabled' : ''}><i class="ti ti-chevron-left"></i></button>
                        <div class="rm-cal-title">${rmPickers.months[month.getMonth()]} ${month.getFullYear()}</div>
                        <button type="button" class="rm-cal-nav" data-nav="1" aria-label="Bulan berikutnya"${last && after > last ? ' disabled' : ''}><i class="ti ti-chevron-right"></i></button>
                    </div>
                    <div class="rm-cal-week">${rmPickers.weekdays.map(name => `<span>${name}</span>`).join('')}</div>
                    <div class="rm-cal-grid">${cells.join('')}</div>
                    <div class="rm-cal-foot">
                        <a href="#" data-act="clear">Kosongkan</a>
                        ${todayOk ? '<a href="#" data-act="today">Hari ini</a>' : ''}
                    </div>`);
            };
            const open = () => {
                month = rmPickers.parse(field.val()) || rmPickers.parse(max()) || new Date();
                month = new Date(month.getFullYear(), month.getMonth(), 1);
                draw();
                panel.removeAttr('hidden');
                button.attr('aria-expanded', 'true').addClass('is-open');
                // A click elsewhere, or Esc, closes it without a change.
                setTimeout(() => {
                    $(document).on('mousedown.rmDate' + id, event => {
                        if (!wrap[0].contains(event.target)) close();
                    });
                    $(document).on('keydown.rmDate' + id, event => {
                        if (event.key === 'Escape') {
                            close();
                            button.trigger('focus');
                        }
                    });
                }, 0);
            };

            button.on('click', () => (panel.attr('hidden') === undefined ? close() : open()));
            panel.on('click', '[data-nav]', function() {
                month = new Date(month.getFullYear(), month.getMonth() + Number($(this).data('nav')), 1);
                draw();
            });
            panel.on('click', '[data-date]', function() {
                pick($(this).data('date'));
            });
            panel.on('click', '[data-act]', function(event) {
                event.preventDefault();
                pick($(this).data('act') === 'today' ? rmPickers.iso(new Date()) : '');
            });
            field.on('change', show);
            wrap.append(button, panel);
            field.after(wrap);
            show();
        },
    };
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
