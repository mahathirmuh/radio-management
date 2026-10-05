@extends('main.index')

@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/datatables/datatables.min.css') }}" />
@endpush

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Delegation Log</h5>
            <small class="text-muted">Approvals delegated between users</small>
        </div>
        <div class="card-body">
            <table id="delegate-table" class="table table-striped table-bordered w-100">
                <thead><tr>
                    <th>#</th><th>From</th><th>To</th><th>Note</th><th>Status</th><th>Created</th>
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
            $('#delegate-table').DataTable({
                processing: true, serverSide: true,
                ajax: "{{ route('api.approval-management.delegate.datatable') }}",
                columns: [
                    { data: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'from' },
                    { data: 'to' },
                    { data: 'note', render: d => d || '-' },
                    { data: 'status', render: d => d === 'ACTIVE' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' },
                    { data: 'created_at', render: d => d ? new Date(d).toLocaleString() : '-' },
                ]
            });
        });
    </script>
@endpush
