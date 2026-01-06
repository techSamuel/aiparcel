<?php include 'includes/admin_header.php'; ?>
<div class="card">
    <div class="table-container">
        <table class="data-table display" id="orders-table" style="width:100%"></table>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    $(document).ready(function () {
        $('#orders-table').DataTable({
            destroy: true,
            ajax: (d, cb) => apiCall('get_global_history', { type: 'orders' }).then(res => cb({ data: res })),
            columns: [
                { title: "Date", data: "timestamp", render: d => new Date(d).toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }) },
                { title: "User", data: "userEmail" },
                { title: "Store ID", data: "store_id" },
                {
                    title: 'Status', data: 'api_response', orderable: false, render: data => {
                        try { const resp = JSON.parse(data); return (resp.status === 'success' || resp.message === 'Order created successfully') ? '<span style="color:green;">Success</span>' : '<span style="color:red;">Failed</span>'; } catch (e) { return 'Unknown'; }
                    }
                }
            ],
            order: [[0, 'asc']]
        });
    });
</script>