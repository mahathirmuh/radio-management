@props([
    'accept' => ['.xlsx', '.xls', '.xlsb'],
    'name' => 'file',
    'multiple' => false,
])

@push('style')
    <style>
        .dropzone {
            border: 2px dashed #CBD5E1;
            border-radius: 5px;
            padding: 50px;
            text-align: center;
            font-size: 20px;
            background: #F8FAFC;
        }

        .dropzone.dragover {
            background-color: #e9ecef;
        }
    </style>
@endpush

<div id="dropzone-{{ $name }}" class="dropzone mb-3">
    <div class="text-center d-flex flex-column">
        <span class="fs-5 fw-bolder mb-0">Tarik & letakan dokumen Anda disini</span>
        <span class="fs-6 mb-3">Format file berupa: ({{ implode(', ', $accept) }})</span>
        <button type="button" class="btn btn-sm px-2 py-1 rounded-2">Pilih Dokumen</button>
    </div>
</div>

<div id="fileList-{{ $name }}" class=" d-flex flex-column align-items-center justify-content-start gap-3">

</div>

<input type="file" id="fileInput-{{ $name }}" style="display: none;" accept=" {{ implode(',', $accept) }}"
    name="{{ $name }}" {{ $multiple ? 'multiple' : '' }}>

@push('javascript')
    <script>
        $(document).ready(function() {
            var $dropzone = $('#dropzone-{{ $name }}');
            var $fileInput = $('#fileInput-{{ $name }}');
            const excelIcon = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M4 0C2.89543 0 2 0.89543 2 2V22C2 23.1046 2.89543 24 4 24H20C21.1046 24 22 23.1046 22 22V0H4ZM22 1.57356e-05V4L18 1.53859e-05L22 1.57356e-05Z"
                    fill="#E2E8F0" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M18 0V3.99998H22L18 0Z" fill="#94A3B8" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M22 8V4H18L22 8Z" fill="#CBD5E1" />
                <rect x="1" y="10" width="22" height="10" rx="1" fill="#10B981" />
                <path
                    d="M4.51562 18H5.61719L6.94922 15.9258H6.98438L8.31641 18H9.48047L7.625 15.1875V15.1758L9.51172 12.3633H8.37891L7.07422 14.4844H7.03125L5.72266 12.3633H4.53125L6.35547 15.1484V15.1602L4.51562 18ZM10.5547 18H14.1484V17.1523H11.5625V12.3633H10.5547V18ZM17.1914 18.1406C18.543 18.1406 19.3906 17.4531 19.3906 16.3555V16.3516C19.3906 15.5 18.8945 15.0234 17.707 14.7734L17.1016 14.6445C16.3984 14.4961 16.1133 14.2461 16.1133 13.8477V13.8438C16.1133 13.3633 16.5586 13.0703 17.1836 13.0703C17.8281 13.0664 18.2461 13.3789 18.3164 13.8164L18.3242 13.8633H19.2891L19.2852 13.8125C19.2148 12.9062 18.4375 12.2227 17.1875 12.2227C15.9688 12.2227 15.1016 12.9023 15.1016 13.9102V13.9141C15.1016 14.7539 15.6016 15.293 16.7539 15.5352L17.3594 15.6602C18.0859 15.8164 18.3789 16.0625 18.3789 16.4688V16.4727C18.3789 16.957 17.9023 17.293 17.2227 17.293C16.5078 17.293 16.0039 16.9805 15.957 16.5078L15.9531 16.4688H14.9766L14.9805 16.5234C15.0508 17.4961 15.8828 18.1406 17.1914 18.1406Z"
                    fill="white" />
            </svg>`;

            const pngIcon = `<svg width="24" height="24" viewBox="0 0 36 36" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M6 0C4.34315 0 3 1.34315 3 3V33C3 34.6569 4.34315 36 6 36H30C31.6569 36 33 34.6569 33 33V0H6ZM33 2.36034e-05V6L27 2.30789e-05L33 2.36034e-05Z"
                    fill="#E2E8F0" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M27 0V5.99998H33L27 0Z" fill="#94A3B8" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M33 12V6H27L33 12Z" fill="#CBD5E1" />
                <rect x="1.5" y="15" width="33" height="15" rx="1.5" fill="#3B82F6" />
                <path
                    d="M6.27166 27V18.2727H9.22053C9.90518 18.2727 10.4648 18.3963 10.8995 18.6435C11.337 18.8878 11.6609 19.2188 11.8711 19.6364C12.0813 20.054 12.1864 20.5199 12.1864 21.0341C12.1864 21.5483 12.0813 22.0156 11.8711 22.4361C11.6637 22.8565 11.3427 23.1918 10.908 23.4418C10.4734 23.6889 9.91655 23.8125 9.23757 23.8125H7.12393V22.875H9.20348C9.67223 22.875 10.0487 22.794 10.3327 22.6321C10.6168 22.4702 10.8228 22.2514 10.9506 21.9759C11.0813 21.6974 11.1467 21.3835 11.1467 21.0341C11.1467 20.6847 11.0813 20.3722 10.9506 20.0966C10.8228 19.821 10.6154 19.6051 10.3285 19.4489C10.0415 19.2898 9.66087 19.2102 9.18643 19.2102H7.32848V27H6.27166ZM20.8093 18.2727V27H19.7866L15.0309 20.1477H14.9457V27H13.8888V18.2727H14.9116L19.6843 25.142H19.7695V18.2727H20.8093ZM28.8899 21C28.7962 20.7131 28.6726 20.456 28.5192 20.2287C28.3686 19.9986 28.1882 19.8026 27.978 19.6406C27.7706 19.4787 27.5348 19.3551 27.2706 19.2699C27.0064 19.1847 26.7166 19.142 26.4013 19.142C25.8842 19.142 25.4141 19.2756 24.9908 19.5426C24.5675 19.8097 24.2308 20.2031 23.9808 20.723C23.7308 21.2429 23.6058 21.8807 23.6058 22.6364C23.6058 23.392 23.7322 24.0298 23.9851 24.5497C24.2379 25.0696 24.5803 25.4631 25.0121 25.7301C25.4439 25.9972 25.9297 26.1307 26.4695 26.1307C26.9695 26.1307 27.4098 26.0241 27.7905 25.8111C28.174 25.5952 28.4723 25.2912 28.6854 24.8991C28.9013 24.5043 29.0092 24.0398 29.0092 23.5057L29.3331 23.5739H26.7081V22.6364H30.032V23.5739C30.032 24.2926 29.8786 24.9176 29.5717 25.4489C29.2678 25.9801 28.8473 26.392 28.3104 26.6847C27.7763 26.9744 27.1626 27.1193 26.4695 27.1193C25.6967 27.1193 25.0178 26.9375 24.4325 26.5739C23.8501 26.2102 23.3956 25.6932 23.0689 25.0227C22.745 24.3523 22.5831 23.5568 22.5831 22.6364C22.5831 21.946 22.6754 21.3253 22.8601 20.7741C23.0476 20.2202 23.3118 19.7486 23.6527 19.3594C23.9936 18.9702 24.397 18.6719 24.8629 18.4645C25.3288 18.2571 25.8416 18.1534 26.4013 18.1534C26.8615 18.1534 27.2905 18.223 27.6882 18.3622C28.0888 18.4986 28.4453 18.6932 28.7578 18.946C29.0732 19.196 29.3359 19.4957 29.5462 19.8452C29.7564 20.1918 29.9013 20.5767 29.9808 21H28.8899Z"
                    fill="white" />
            </svg>`;

            const pdfIcon = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M4 0C2.89543 0 2 0.89543 2 2V22C2 23.1046 2.89543 24 4 24H20C21.1046 24 22 23.1046 22 22V0H4ZM22 1.57356e-05V4L18 1.53859e-05L22 1.57356e-05Z"
                    fill="#E2E8F0" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M18 0V3.99998H22L18 0Z" fill="#94A3B8" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M22 8V4H18L22 8Z" fill="#CBD5E1" />
                <rect x="1" y="10" width="22" height="10" rx="1" fill="#F43F5E" />
                <path
                    d="M4.73828 18H5.74609V16.1367H6.98828C8.13281 16.1367 8.90625 15.3789 8.90625 14.2539V14.2461C8.90625 13.1172 8.13281 12.3633 6.98828 12.3633H4.73828V18ZM6.74219 13.1875C7.46484 13.1875 7.88672 13.5742 7.88672 14.25V14.2578C7.88672 14.9336 7.46484 15.3242 6.74219 15.3242H5.74609V13.1875H6.74219ZM10.0195 18H12.1328C13.8203 18 14.7852 16.9648 14.7852 15.1602V15.1523C14.7852 13.3867 13.8086 12.3633 12.1328 12.3633H10.0195V18ZM11.0273 17.1523V13.2109H12C13.1055 13.2109 13.7578 13.9297 13.7578 15.168V15.1758C13.7578 16.4414 13.1211 17.1523 12 17.1523H11.0273ZM15.9414 18H16.9492V15.6992H19.3125V14.8789H16.9492V13.2109H19.5352V12.3633H15.9414V18Z"
                    fill="white" />
            </svg>`;

            const jpgIcon = `<svg width="24" height="24" viewBox="0 0 36 36" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M6 0C4.34315 0 3 1.34315 3 3V33C3 34.6569 4.34315 36 6 36H30C31.6569 36 33 34.6569 33 33V0H6ZM33 2.36034e-05V6L27 2.30789e-05L33 2.36034e-05Z"
                    fill="#E2E8F0" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M27 0V5.99998H33L27 0Z" fill="#94A3B8" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M33 12V6H27L33 12Z" fill="#CBD5E1" />
                <rect x="1.5" y="15" width="33" height="15" rx="1.5" fill="#A855F7" />
                <path
                    d="M10.8723 18.2727H11.9292V24.5114C11.9292 25.0682 11.8269 25.5412 11.6223 25.9304C11.4178 26.3196 11.1294 26.6151 10.7573 26.8168C10.3851 27.0185 9.9462 27.1193 9.44052 27.1193C8.96325 27.1193 8.53853 27.0327 8.16637 26.8594C7.79421 26.6832 7.5016 26.4332 7.28853 26.1094C7.07546 25.7855 6.96893 25.4006 6.96893 24.9545H8.0087C8.0087 25.2017 8.06978 25.4176 8.19194 25.6023C8.31694 25.7841 8.48739 25.9261 8.7033 26.0284C8.91921 26.1307 9.16495 26.1818 9.44052 26.1818C9.7445 26.1818 10.003 26.1179 10.2161 25.9901C10.4292 25.8622 10.5911 25.6747 10.7019 25.4276C10.8155 25.1776 10.8723 24.8722 10.8723 24.5114V18.2727ZM14.0471 27V18.2727H16.9959C17.6806 18.2727 18.2402 18.3963 18.6749 18.6435C19.1124 18.8878 19.4363 19.2188 19.6465 19.6364C19.8567 20.054 19.9618 20.5199 19.9618 21.0341C19.9618 21.5483 19.8567 22.0156 19.6465 22.4361C19.4391 22.8565 19.1181 23.1918 18.6834 23.4418C18.2488 23.6889 17.6919 23.8125 17.013 23.8125H14.8993V22.875H16.9789C17.4476 22.875 17.824 22.794 18.1081 22.6321C18.3922 22.4702 18.5982 22.2514 18.726 21.9759C18.8567 21.6974 18.9221 21.3835 18.9221 21.0341C18.9221 20.6847 18.8567 20.3722 18.726 20.0966C18.5982 19.821 18.3908 19.6051 18.1039 19.4489C17.8169 19.2898 17.4363 19.2102 16.9618 19.2102H15.1039V27H14.0471ZM27.6301 21C27.5364 20.7131 27.4128 20.456 27.2594 20.2287C27.1088 19.9986 26.9284 19.8026 26.7182 19.6406C26.5108 19.4787 26.275 19.3551 26.0108 19.2699C25.7466 19.1847 25.4569 19.142 25.1415 19.142C24.6245 19.142 24.1543 19.2756 23.731 19.5426C23.3077 19.8097 22.9711 20.2031 22.7211 20.723C22.4711 21.2429 22.3461 21.8807 22.3461 22.6364C22.3461 23.392 22.4725 24.0298 22.7253 24.5497C22.9782 25.0696 23.3205 25.4631 23.7523 25.7301C24.1841 25.9972 24.6699 26.1307 25.2097 26.1307C25.7097 26.1307 26.15 26.0241 26.5307 25.8111C26.9142 25.5952 27.2125 25.2912 27.4256 24.8991C27.6415 24.5043 27.7495 24.0398 27.7495 23.5057L28.0733 23.5739H25.4483V22.6364H28.7722V23.5739C28.7722 24.2926 28.6188 24.9176 28.312 25.4489C28.008 25.9801 27.5875 26.392 27.0506 26.6847C26.5165 26.9744 25.9029 27.1193 25.2097 27.1193C24.437 27.1193 23.758 26.9375 23.1728 26.5739C22.5904 26.2102 22.1358 25.6932 21.8091 25.0227C21.4853 24.3523 21.3233 23.5568 21.3233 22.6364C21.3233 21.946 21.4157 21.3253 21.6003 20.7741C21.7878 20.2202 22.052 19.7486 22.3929 19.3594C22.7338 18.9702 23.1373 18.6719 23.6032 18.4645C24.0691 18.2571 24.5819 18.1534 25.1415 18.1534C25.6017 18.1534 26.0307 18.223 26.4284 18.3622C26.829 18.4986 27.1855 18.6932 27.498 18.946C27.8134 19.196 28.0762 19.4957 28.2864 19.8452C28.4966 20.1918 28.6415 20.5767 28.7211 21H27.6301Z"
                    fill="white" />
            </svg>`;

            // Dragover event
            $dropzone.on('dragover', function(e) {
                e.preventDefault();
                $dropzone.addClass('dragover');
            });

            // Dragleave event
            $dropzone.on('dragleave', function() {
                $dropzone.removeClass('dragover');
            });

            // Drop event
            $dropzone.on('drop', function(e) {
                e.preventDefault();
                $dropzone.removeClass('dragover');
                var files = e.originalEvent.dataTransfer.files;
                handleFiles(files);

                // Update file input value
                var dt = new DataTransfer();
                for (var i = 0; i < files.length; i++) {
                    dt.items.add(files[i]);
                }

                for (var i = 0; i < $fileInput[0].files.length; i++) {
                    dt.items.add($fileInput[0].files[i]);
                }
                $fileInput[0].files = dt.files
            });

            // Click event to open file dialog
            $dropzone.on('click', function() {
                $fileInput.click();
            });

            // Change event to handle file selection
            $fileInput.on('change', function() {
                var files = $fileInput[0].files;
                handleFiles(files);

            });

            // Function to handle files
            function handleFiles(files) {
                $.each(files, function(index, file) {
                    // Hide dropzone if not multiple
                    if (!$fileInput.prop('multiple')) {
                        $dropzone.addClass('d-none');
                    }

                    // Calculate file date
                    const fileDate = new Date(file.lastModifiedDate);
                    const day = fileDate.getDate();
                    const month = fileDate.toLocaleString('default', {
                        month: 'short'
                    });
                    const year = fileDate.getFullYear();


                    // Calculate file size
                    let fileSizeText = '';
                    const fileSizeInBytes = file.size;
                    const fileSizeInKB = fileSizeInBytes / 1024;
                    const fileSizeInMB = fileSizeInKB / 1024;
                    const fileSizeInGB = fileSizeInMB / 1024;

                    if (fileSizeInGB > 1) {
                        fileSizeText = fileSizeInGB.toFixed(2) + 'GB';
                    } else if (fileSizeInMB > 1) {
                        fileSizeText = fileSizeInMB.toFixed(2) + 'MB';
                    } else {
                        fileSizeText = fileSizeInKB.toFixed(2) + 'KB';
                    }

                    // Check file extension
                    let fileExtensionText = '';
                    const fileExtension = file.name.split('.').pop().toLowerCase();

                    if (fileExtension === 'xlsx' || fileExtension === 'xls' || fileExtension === 'xlsb') {
                        fileExtensionText = excelIcon;
                    } else if (fileExtension === 'png') {
                        fileExtensionText = pngIcon;
                    } else if (fileExtension === 'jpg' || fileExtension === 'jpeg') {
                        fileExtensionText = jpgIcon;
                    } else if (fileExtension === 'pdf') {
                        fileExtensionText = pdfIcon;
                    }


                    // Add file to list
                    $('#fileList-{{ $name }}').append(`
                    <div id="fileInfo-${index}" class="w-100 position-relative">
                        <div class="w-100 d-flex align-items-center justify-content-between gap-3">
                            <div class=" d-flex align-items-center gap-2 justify-content-start">
                            ${fileExtensionText}
                                <div class="d-flex flex-column">
                                    <span id="fileInfoText-${index}" class="fs-5 fw-bold text-wrap">${file.name}</span>
                                    <div class="fs-6">
                                        <span id="fileInfoDate-${index}">${day} ${month} ${year}</span>
                                        <span>•</span>
                                        <span id="fileInfoSize-${index}">${fileSizeText}</span>
                                    </div>
                                </div>

                            </div>
                            <a role="button" onclick="removeFile{{ $name }}('${index}')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-trash text-red">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 7l16 0" />
                                    <path d="M10 11l0 6" />
                                    <path d="M14 11l0 6" />
                                    <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                    <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                </svg>
                            </a>
                        </div>
                    </div>`);
                });
            }

        });

        // Function to empty file & reshow dropzone
        function removeFile{{ $name }}(index) {
            $('#fileInfo-' + index).remove();
            $('#dropzone-{{ $name }}').removeClass('d-none');

            const input = $('#fileInput-{{ $name }}')[0];
            const files = input.files;
            var dt = new DataTransfer();

            for (var i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }

            input.files = dt.files;

            // $(element).closest('.w-100').remove();
            // if ($('#fileInfo-{{ $name }} .w-100').length === 0) {
            //     $('#dropzone-{{ $name }}').removeClass('d-none');
            //     $('#fileInfo-{{ $name }}').addClass('d-none');
            // }
        }
    </script>
@endpush
