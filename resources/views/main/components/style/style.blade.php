 <link href="{{ asset('assets/css/apps.min.css?1684106062') }}" rel="stylesheet" />
 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.36.0/tabler-icons.min.css">
 <link href="{{ asset('assets/css/fonts/fonts.css') }}" rel="stylesheet" />
 <link href="{{ asset('assets/css/custom.css') }}" rel="stylesheet" />
 @stack('style')
 <style> 
     body {
         font-feature-settings: "cv03", "cv04", "cv11";
     }

     tbody {
         font-size: 0.75rem !important;
     }

     .dataTables_length,
     .dataTables_info,
     .dataTables_filter {
         font-size: 0.7rem !important;
     }

     table.dataTable td,
     .form-control,
     .form-control-plaintext,
     .form-select,
     .input-group-text,
     .col-form-label {
         background-position: right .35rem center;
         background-size: 14px 9px;
     }

     /* .k-grid-footer,
    .k-grid-header,
    .k-grid-content {
        padding-right: 0px !important;
    } */

     table.dataTable td,
     .form-control,
     .form-control-plaintext,
     .form-select,
     .input-group-text,
     .col-form-label,
     .k-grid td,
     .k-grid th,
     .k-grid .k-grid-edit-row td {
         padding: .375rem .75rem;
     }

     .select2-choices {
         min-height: 150px;
         max-height: 150px;
         overflow-y: auto;
     }

     /* .form-control,
    .form-select {
        height: 36px;
    } */

     table.dataTable thead th,
     .table-sm-ts thead tr th,
     .k-header {
         font-weight: bolder;
         color: #000;
         font-size: 0.75rem;
         text-transform: capitalize !important;
     }

     .dataTables_paginate .pagination .paginate_button {
         margin: 0 4px;
     }

     .dataTables_paginate .pagination .paginate_button .page-link {
         font-size: 0.75rem !important;
     }



     table.dataTable tfoot th,
     .k-footer-template {
         font-weight: bolder;
         color: #000;
         font-size: 0.75rem;
         text-transform: capitalize !important;
     }

     .dataTables_length select {
         width: 68px !important;
     }

     .select2-container .select2-selection--single .select2-selection__rendered,
     .form-control,
     .form-select,
     .form-control-plaintext,
     .input-group-text,
     .form-check-input,
     .form-check-label,
     input[type="date"],
     .table-sm-ts tbody tr td {
         font-size: 0.65rem
     }


     .form-check-label {
         padding-top: 0.175rem;
     }

     @media (min-width: 992px) {
         .modal-xxl {
             --tblr-modal-width: 720px;
         }
     }

     @media (min-width: 1200px) {
         .modal-xxl {
             --tblr-modal-width: 1140px;
         }
     }


     @media (min-width: 1440px) {
         .modal-xxl {
             --tblr-modal-width: 1280px;
         }
     }

     @media (min-width: 1580px) {
         .modal-xxl {
             --tblr-modal-width: 1440px;
         }
     }

     .form-label,
     .col-form-label {
         font-size: 0.7rem;
     }

     .select2-selection .select2-selection--single {
         padding-top: 5px !important;
         padding-bottom: 5px !important;
     }

     .select2-container .select2-selection--single .select2-selection__placeholder,
     input::placeholder,
     input[type="date"]::placeholder,
     textarea::placeholder {
         font-style: italic !important;
     }

     .select2-container--bootstrap-5 .select2-selection__rendered {
         line-height: 22px;
         /* Adjust the line-height as needed */
         height: 22px;
         /* Adjust the height as needed */
     }

     .nav-pills .nav-link.active,
     .nav-pills .show>.nav-link {
         color: var(--tblr-white);
         background-color: var(--tblr-primary);
     }



     body {
         font-size: 13px;
     }

     .actives {
         background: var(--tblr-navbar-active-bg);
     }

     .dropdown-item {
         font-size: 0.8rem;
     }

     .dropdown-item.sidebar-item.active,
     .nav-item.active {
         background-color: rgba(var(--tblr-blue-rgb), var(--tblr-bg-opacity)) !important;
         color: var(--tblr-blue-fg) !important;
     }

     .dropdown-menu-columns {
         /* background-color: rgb(220, 228, 237); */
         /* background-color: rgb(255, 255, 255); */
         background-color: rgb(239, 244, 251);
         color: #000;
     }

     .navbar-vertical {
         overflow: auto !important;
     }

     .nav-menu-app {
         overflow: auto;
     }

     .navbar-expand-lg {
         transition: width 0.2s ease-in-out;
     }

     .page-wrapper,
     .navbar-expand-md {
         transition: margin-left 0.2s ease-in-out;
     }

     @media (min-width: 992px) {
         .nav-menu-app {
             border-width: 1px;
             border-style: solid;
             border-color: rgba(0, 0, 0, 0.175);
             /* Warna hitam dengan 17.5% opacity */
             /* Ganti warna sesuai kebutuhan */
         }
     }

     @media (max-width: 991.98px) {
         .nav-menu-app {
             border: none;
         }
     }
 </style>
