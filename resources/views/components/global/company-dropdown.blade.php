<div class="dropdown">
    <a id="company-code-dropdown-navbar-btn" href="#" class="btn btn-sm px-3 py-2 dropdown-toggle rounded-3"
        data-bs-toggle="dropdown">
        <span class="mt-1">
            {{ $selected->name }}
        </span>
    </a>
    <div id="company-code-dropdown-navbar" class="dropdown-menu">
        <div class="p-3">
            <input id="search-company" type="text" class="form-control" placeholder="Search company"
                aria-label="Search" autocomplete="off">
        </div>
        <div style="height: 300px;overflow-y: scroll">
            @foreach ($companies as $company)
                <a class="dropdown-item" role="button" id="item-{{ $company->company_code }}"
                    onclick="changeCompany('{{ $company->company_code }}', '{{ $company->name }}')">{{ $company->name }}
                    -
                    {{ $company->company_orangehr_code }}</a>
            @endforeach
        </div>
    </div>
    <form id="formChangeCompany" action="{{ route('set_company_code') }}" method="POST">
        @csrf
        <input type="hidden" name="company_code" id="company_code">
    </form>
</div>

@push('javascript')
    <script src="{{ asset('assets/libs/jquery/jquery-cookie.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('#search-company').on('input', debounce(() => {
                let search = $('#search-company').val();
                let items = $('#company-code-dropdown-navbar a');

                items.each((index, item) => {
                    let text = $(item).text();
                    if (!text.toLowerCase().includes(search.toLowerCase())) {
                        $(item).addClass('d-none');
                    } else {
                        $(item).removeClass('d-none');
                    }
                })
            }));
        })

        function changeCompany(companyCode, companyName) {
            $('#company-code-dropdown-navbar-btn').html(
                `<div class="spinner-border spinner-border-sm text-facebook me-2" role="status"></div>
                <span class="mt-1">${companyName}</span>`
            );

            $('#company_code').val(companyCode);

            $('#formChangeCompany').submit();
        }
    </script>
@endpush
