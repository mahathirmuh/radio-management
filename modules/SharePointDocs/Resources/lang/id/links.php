<?php

// Why a page cannot show what the other source knows (ScanLinks): the column "File scan" of a sheet of the workbook (the
// files of the folder), and the columns Masterlist and Catatan of the page of the folder (the rows of the sheet). Said
// on the page, which is shown as it always is; :why is GraphException's reason.

return [
    'column_failed' => 'Kolom :column belum bisa ditampilkan. :why',
    'column_too_many' => 'Kolom :column belum bisa ditampilkan: isi folder :folder terlalu banyak untuk dibaca seluruhnya, jadi status filenya tidak bisa dihitung.',
    'column_no_link' => 'Kolom ":link" tidak ditemukan di sheet :sheet, jadi status file scan tidak bisa dihitung.',
    'register_failed' => 'Kolom Masterlist dan Catatan belum bisa ditampilkan. :why',
    'register_no_sheet' => 'Sheet :sheet tidak ada di workbook :workbook, jadi kolom Masterlist dan Catatan tidak bisa ditampilkan.',
    'register_too_long' => 'Sheet :sheet terlalu panjang untuk dibaca seluruhnya, jadi kolom Masterlist dan Catatan tidak bisa dihitung.',
    'register_no_link' => 'Kolom ":link" tidak ditemukan di sheet :sheet, jadi kolom Masterlist dan Catatan tidak bisa ditampilkan.',
];
