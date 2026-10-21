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
                /** The words of the pages of the radios, in the language of the page (RadioManagement::common.ui). */
                words: @json(__('RadioManagement::common.ui')),
                /** The names to show of what the register keeps in Indonesian (an archive reason, the kind of a history line). */
                names: {
                    archive_reasons: @json(__('RadioManagement::common.archive_reasons')),
                    history_actions: @json(__('RadioManagement::common.history_actions')),
                },
                /** The name to show of a value kept: names[group][value], else the value itself. */
                name(group, value) {
                    const names = RadioUi.names[group] || {};
                    return Object.prototype.hasOwnProperty.call(names, value) ? names[value] : String(value ?? '');
                },
                /** A text of the language files with :name in it, each filled with values.name (as rmFill). */
                fill(text, values = {}) {
                    return String(text ?? '').replace(/:([A-Za-z_][A-Za-z0-9_]*)/g, (match, name) =>
                        (Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match));
                },
                /** The language of the page, for the plural rule of choice(). */
                locale: @json(app()->getLocale()),
                /**
                 * A text with a form for one and for more ("{1} ...|[2,*] ..."), as rmChoice: the first form whose range
                 * fits, else the forms without their ranges, picked by the plural rule of the language (as Laravel).
                 */
                choice(text, count, values = {}) {
                    const parts = String(text ?? '').split('|');
                    const n = Number(count);
                    const condition = /^\s*[{[]([^\[\]{}]*)[}\]]/;
                    let chosen = null;
                    for (const part of parts) {
                        const found = condition.exec(part);
                        if (!found) continue;
                        const [from, to] = found[1].includes(',') ? found[1].split(',').map(side => side.trim()) : [found[1].trim(), found[1].trim()];
                        if ((from === '*' || n >= Number(from)) && (to === '*' || n <= Number(to))) {
                            chosen = part.slice(found[0].length);
                            break;
                        }
                    }
                    if (chosen === null) {
                        const forms = parts.map(part => part.replace(condition, ''));
                        chosen = forms[/^en/i.test(RadioUi.locale) && n !== 1 ? 1 : 0] ?? forms[0];
                    }
                    return RadioUi.fill(chosen.trim(), { count, ...values });
                },
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
                        const none = RadioUi.esc(RadioUi.words.no_issues);
                        return `<span class="rm-ok" data-bs-toggle="tooltip" data-bs-title="${none}" aria-label="${none}"><i class="ti ti-circle-check"></i></span>`;
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
                    if (xhr.status === 403) return [RadioUi.esc(RadioUi.words.no_permission)];
                    return [RadioUi.esc(body.message || RadioUi.words.error)];
                },
                fail(xhr) {
                    Swal.fire(RadioUi.words.failed, RadioUi.errors(xhr).join('<br>'), 'error');
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
