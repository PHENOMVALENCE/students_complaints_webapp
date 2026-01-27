<?php
/**
 * DataTables include - CSS, jQuery, DataTables JS, and init for .datatable tables.
 * Include this before </body> on any page that has a <table class="datatable">.
 */
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="datatables-theme.css">
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') return;
    var $ = jQuery;
    $('.datatable').each(function() {
        var $t = $(this);
        if ($t.hasClass('dataTable')) return;
        var opts = {
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            order: $t.hasClass('datatable-desc') ? [[0, 'desc']] : [[0, 'asc']],
            language: { emptyTable: 'No data available.', search: 'Search:', lengthMenu: 'Show _MENU_ entries', info: 'Showing _START_ to _END_ of _TOTAL_ entries', infoEmpty: 'Showing 0 to 0 of 0 entries', paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' } },
            columnDefs: []
        };
        $t.find('thead th[data-orderable="false"]').each(function(i) {
            var idx = $(this).index();
            opts.columnDefs.push({ orderable: false, targets: idx });
        });
        $t.DataTable(opts);
    });
});
</script>
