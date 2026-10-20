{{-- Shared helpers for the Radio Management pages (included once per page). --}}
@once
    @push('style')
        <style>
            /* The look of the badges is the design's .rm-badge (radio-ui.css). */
            .radio-badge {
                white-space: nowrap;
            }

            .radio-issue {
                white-space: nowrap;
                cursor: help;
            }

            .radio-mono {
                font-family: var(--rm-mono);
            }

            .radio-muted {
                color: var(--rm-gray-500);
                font-size: 12px;
            }

            .radio-none {
                color: var(--rm-gray-400);
            }

            .radio-section-title {
                font-size: 0.7rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--tblr-secondary);
                border-bottom: 1px solid var(--tblr-border-color);
                padding-bottom: .25rem;
                margin-bottom: .5rem;
            }

            .radio-pre {
                white-space: pre-line;
            }
        </style>
    @endpush

    @push('javascript')
        <script>
            const RadioUi = {
                esc(value) {
                    if (value === null || value === undefined) return '';
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
                dash(value) {
                    return value === null || value === undefined || value === '' ?
                        '<span class="radio-none">—</span>' : RadioUi.esc(value);
                },
                /** The badge colour of the design for a colour the server names (Tabler's names). */
                tone(color) {
                    const tones = { secondary: 'gray', muted: 'gray', azure: 'blue', cyan: 'blue', indigo: 'purple', teal: 'green', lime: 'green', pink: 'red', danger: 'red', success: 'green', warning: 'yellow', info: 'blue', primary: 'blue' };
                    const tone = tones[color] || color;
                    return ['blue', 'green', 'yellow', 'orange', 'red', 'purple', 'gray'].includes(tone) ? tone : 'gray';
                },
                badge(label, color) {
                    return `<span class="rm-badge ${RadioUi.tone(color)} radio-badge">${RadioUi.esc(label)}</span>`;
                },
                /** The colour of the reason an archived radio went: lost red, written off orange, moved blue. */
                reasonColor(reason) {
                    const text = String(reason || '').toLowerCase();
                    if (text.includes('hilang')) return 'red';
                    if (text.includes('afkir') || text.includes('rusak')) return 'orange';
                    if (text.includes('pindah')) return 'blue';
                    return 'gray';
                },
                issues(list, max = 2) {
                    if (!list || !list.length) {
                        return '<span class="rm-ok" data-bs-toggle="tooltip" data-bs-title="Tidak ada masalah" aria-label="Tidak ada masalah"><i class="ti ti-circle-check"></i></span>';
                    }
                    const shown = list.slice(0, max).map(issue => {
                        const hint = issue.hint ? `${issue.label}: ${issue.hint}` : issue.label;
                        return `<span class="rm-badge ${RadioUi.tone(issue.color)} radio-issue" data-bs-toggle="tooltip" data-bs-title="${RadioUi.esc(hint)}">${RadioUi.esc(issue.label)}</span>`;
                    }).join('');
                    const rest = list.slice(max);
                    const more = rest.length ?
                        `<span class="rm-badge gray radio-issue" data-bs-toggle="tooltip" data-bs-title="${RadioUi.esc(rest.map(i => i.label).join(', '))}">+${rest.length}</span>` :
                        '';
                    return `<span class="rm-badges">${shown}${more}</span>`;
                },
                /** Accepts both { errors: [..] } and Laravel's { errors: { field: [..] } }. */
                errors(xhr) {
                    const body = xhr.responseJSON || {};
                    if (Array.isArray(body.errors)) return body.errors.map(RadioUi.esc);
                    if (body.errors && typeof body.errors === 'object') return Object.values(body.errors).flat().map(RadioUi.esc);
                    if (xhr.status === 403) return ['Anda tidak memiliki izin untuk aksi ini.'];
                    return [RadioUi.esc(body.message || 'Terjadi kesalahan, coba lagi.')];
                },
                fail(xhr) {
                    Swal.fire('Gagal', RadioUi.errors(xhr).join('<br>'), 'error');
                },
                /**
                 * Sends a request with its button switched off until the answer is back, so a
                 * double click cannot post the same thing twice. Returns the jqXHR, or null when
                 * a request from this button is still on its way.
                 */
                send(button, options) {
                    const $button = $(button);
                    if ($button.prop('disabled')) return null;
                    $button.prop('disabled', true).addClass('is-busy');
                    return $.ajax(options).always(() => $button.prop('disabled', false).removeClass('is-busy'));
                },
                /**
                 * After an archive / delete the current page may no longer exist ("Showing 0 - 0
                 * of 40"): returns the last page to load instead, or null when the page is fine.
                 */
                pageAfter(response, page) {
                    const meta = response.meta || {};
                    return !response.data.length && meta.total > 0 && page > 1 ? (meta.last_page || 1) : null;
                },
                tooltips(scope) {
                    $(scope || document).find('[data-bs-toggle="tooltip"]').tooltip({
                        trigger: 'hover'
                    });
                },
                today() {
                    const d = new Date();
                    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                },
                hexHasF(id) {
                    return Number(id) > 0 && Number(id).toString(16).toUpperCase().includes('F');
                },
                mdc(id) {
                    return Number(id) > 0 ? Number(id).toString(16).toUpperCase() : '';
                },
            };
        </script>
    @endpush
@endonce
