<?php include 'includes/admin_header.php'; ?>
<div class="card">
    <div class="table-container">
        <table class="data-table display" id="parses-table" style="width:100%"></table>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    $(document).ready(function () {
        $('#parses-table').DataTable({
            destroy: true,
            ajax: (d, cb) => apiCall('get_global_history', { type: 'parses' }).then(res => cb({ data: res })),
            columns: [
                { title: "Date", data: "timestamp", render: d => new Date(d).toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }) },
                { title: "User", data: "userEmail" },
                { title: "Method", data: "method" },
                { title: "Items", data: "data", orderable: false, render: data => { try { return JSON.parse(data).length; } catch (e) { return '?'; } } }
            ],
            order: [[0, 'asc']]
        });
    });
</script>