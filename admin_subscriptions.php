<?php include 'includes/admin_header.php'; ?>
<div class="card">
    <h3>Subscription Purchase Requests</h3>
    <div class="table-container">
        <table id="subscriptions-table" class="data-table display" style="width:100%"></table>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    $(document).ready(function () {
        $('#subscriptions-table').DataTable({
            destroy: true,
            ajax: (d, cb) => apiCall('get_subscription_orders').then(res => cb({ data: res })),
            columns: [
                { title: "Date", data: "created_at", render: d => new Date(d).toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }) },
                { title: "User", data: "user_email" }, { title: "Plan", data: "plan_name" },
                { title: "Details", data: null, render: (d, t, r) => `From: ${r.sender_number}<br>TrxID: ${r.transaction_id}` },
                { title: "Status", data: "status" },
                {
                    title: "Actions", data: "id", orderable: false, render: (d, t, r) =>
                        (r.status === 'pending') ? `<button class="approve-sub-btn btn-sm btn-success" data-id="${d}">Approve</button> <button class="reject-sub-btn btn-sm btn-danger" data-id="${d}">Reject</button>` : 'Completed'
                }
            ],
            order: [[0, 'asc']]
        });

        $('#subscriptions-table').on('click', '.approve-sub-btn, .reject-sub-btn', async function () {
            const status = $(this).hasClass('approve-sub-btn') ? 'approved' : 'rejected';
            if (confirm(`Are you sure you want to ${status} this?`)) {
                try {
                    await apiCall('update_subscription_status', { id: $(this).data('id'), status });
                    const updatedData = await apiCall('get_subscription_orders');
                    $('#subscriptions-table').DataTable().clear().rows.add(updatedData).draw();
                } catch (e) { alert('Failed: ' + e.message); }
            }
        });
    });
</script>