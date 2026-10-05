@extends('main.index')

@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/datatables/datatables.min.css') }}" />
@endpush

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Takeover Log</h5>
            <small class="text-muted">Approvals that were taken over</small>
        </div>
        <div class="card-body">
            <table id="takeover-table" class="table table-striped table-bordered w-100">
                <thead><tr>
                    <th>#</th><th>Taken By</th><th>Flow</th><th>Status</th><th>Updated</th>
                </tr></thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('javascript')
    <script src="{{ asset('assets/libs/datatables/datatables.min.js') }}"></script>
    <script>
        $(function () {
            $('#takeover-table').DataTable({
                processing: true, serverSide: true,
                ajax: "{{ route('api.approval-management.takeover.datatable') }}",
                columns: [
                    { data: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'taken_by' },
                    { data: 'flow_name' },
                    { data: 'status' },
                    { data: 'updated_at', render: d => d ? new Date(d).toLocaleString() : '-' },
                ]
            });
        });
    </script>
@endpush
