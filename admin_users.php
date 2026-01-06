<?php include 'includes/admin_header.php'; ?>
<div class="card">
    <div class="table-container">
        <table class="data-table display" id="users-table" style="width:100%"></table>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    function loadAllUsers() {
        $('#users-table').DataTable({
            destroy: true,
            ajax: (d, cb) => apiCall('list_users').then(res => cb({ data: res })),
            columns: [
                { title: "Joined", data: "created_at", render: d => new Date(d).toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }) },
                { title: "Email", data: "email" }, { title: "Plan", data: "plan_name" },
                { title: "Verified", data: "is_verified", render: d => d ? '✅' : '❌' },
                { title: "Actions", data: "id", orderable: false, render: d => `<button class="btn-view-details btn-sm" data-uid="${d}">Details</button>` }
            ],
            order: [[0, 'asc']]
        });
    }

    $(document).ready(function () {
        loadAllUsers();

        // User Details Modal Logic
        $('#users-table').on('click', '.btn-view-details', async function () {
            const uid = $(this).data('uid');
            const $modal = $('#user-details-modal');
            const $modalContent = $('#user-details-content');
            $modalContent.html('<p>Loading user details...</p>');
            $modal.show();

            try {
                const [details, plans] = await Promise.all([
                    apiCall('get_user_details', { uid }),
                    apiCall('get_plans')
                ]);

                const planInfo = details.plan || {};
                const planOptions = plans.map(p => `<option value="${p.id}" ${p.id == planInfo.plan_id ? 'selected' : ''}>${p.name}</option>`).join('');

                const modalHtml = `
                    <div class="details-modal-section">
                        <h4>Plan Information</h4>
                        <div class="plan-info">
                            <div style="margin-bottom: 8px;">
                                <strong>Current Plan:</strong> ${planInfo.plan_name || 'N/A'} <br>
                                <span style="font-size:11px; color:#666;">Expires: ${planInfo.plan_expiry_date || 'N/A'}</span>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; background: #f1f1f1; padding: 8px; border-radius: 4px;">
                                <select id="admin-user-plan-select" style="padding: 5px; flex: 1;">${planOptions}</select>
                                <button id="update-user-plan-btn" class="btn-primary btn-sm" data-uid="${uid}">Change Plan</button>
                            </div>
                        </div>
                    </div>
                    <div class="details-modal-section">
                        <h4>User Permissions</h4>
                        <div class="permissions-form" style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                                <input type="checkbox" id="user-can-manual-parse" ${details.plan.can_manual_parse == 1 ? 'checked' : ''}> Enable Manual/Local Parsing
                            </label>
                            <button id="update-user-perms-btn" class="btn-primary btn-sm" data-uid="${uid}">Save Permissions</button>
                        </div>
                    </div>
                    <div class="details-modal-section"><h4>Stores (${details.stores.length})</h4><table id="stores-details-table" class="display details-table" style="width:100%"></table></div>
                    <div class="details-modal-section"><h4>Recent Parses (${details.parses.length})</h4><table id="parses-details-table" class="display details-table" style="width:100%"></table></div>
                    <div class="details-modal-section"><h4>Recent Orders (${details.orders.length})</h4><table id="orders-details-table" class="display details-table" style="width:100%"></table></div>
                `;
                $modalContent.html(modalHtml);

                $('#stores-details-table').DataTable({
                    destroy: true, data: details.stores,
                    columns: [{ title: 'Store Name', data: 'store_name' }, { title: 'Courier', data: 'courier_type' }],
                    pageLength: 5, lengthChange: false, searching: false
                });

                $('#parses-details-table').DataTable({
                    destroy: true, data: details.parses,
                    columns: [
                        { title: 'Date', data: 'timestamp', render: data => new Date(data).toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }) },
                        { title: 'Method', data: 'method' },
                        {
                            title: 'Parsed Data', data: 'data', render: data => {
                                try { return `<pre style="white-space: pre-wrap; word-break: break-all; max-width: 450px; max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 5px;">${$('<div/>').text(JSON.stringify(JSON.parse(data), null, 2)).html()}</pre>`; } catch (e) { return String(data); }
                            }
                        }
                    ],
                    pageLength: 5, lengthChange: false, searching: false, order: [[0, 'asc']]
                });

                $('#orders-details-table').DataTable({
                    destroy: true, data: details.orders,
                    columns: [
                        { title: 'Date', data: 'timestamp', render: data => new Date(data).toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }) },
                        { title: 'Store ID', data: 'store_id' },
                        {
                            title: 'API Response', data: 'api_response', render: data => {
                                try {
                                    const resp = JSON.parse(data);
                                    let status = (resp.status === 'success' || resp.message === 'Order created successfully') ? '<strong style="color:green;">Success</strong>' : '<strong style="color:red;">Failed</strong>';
                                    return `${status}<br><pre style="white-space: pre-wrap; word-break: break-all; max-width: 450px; font-size: 12px; background: #f9f9f9; padding: 5px;">${$('<div/>').text(JSON.stringify(resp, null, 2)).html()}</pre>`;
                                } catch (e) { return `<pre style="white-space: pre-wrap;">${$('<div/>').text(String(data)).html()}</pre>`; }
                            }
                        }
                    ],
                    pageLength: 5, lengthChange: false, searching: false, order: [[0, 'asc']]
                });

            } catch (error) { $modalContent.html('<p class="error">Failed to load user details.</p>'); }
        });

        $('#user-details-modal').on('click', '#update-user-perms-btn', async function () {
            const uid = $(this).data('uid');
            const canManualParse = $('#user-can-manual-parse').is(':checked') ? 1 : 0;
            try { await apiCall('update_user_role', { uid, canManualParse }); alert('Permissions updated.'); } catch (e) { alert('Failed: ' + e.message); }
        });

        $('#user-details-modal').on('click', '#update-user-plan-btn', async function () {
            const uid = $(this).data('uid');
            const plan_id = $('#admin-user-plan-select').val();
            if (!confirm('Change plan? This resets validity/quotas.')) return;
            try { await apiCall('update_user_plan', { uid, plan_id }); alert('Plan updated.'); $('#user-details-modal').hide(); } catch (e) { alert('Failed: ' + e.message); }
        });
    });
</script>