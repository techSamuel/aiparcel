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
                { title: "ID", data: "id" },
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
                            <div style="margin-bottom: 8px; font-size: 12px; border-top: 1px solid #eee; padding-top: 5px;">
                                <strong>Usage & Limits:</strong><br>
                                Orders: ${planInfo.monthly_order_count || 0} / ${(parseInt(planInfo.order_limit_monthly) + parseInt(planInfo.extra_order_limit || 0))}<br>
                                AI Parse: ${planInfo.monthly_ai_parsed_count || 0} / ${(parseInt(planInfo.ai_parsing_limit) + parseInt(planInfo.extra_ai_parsed_limit || 0))}
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
                    <div class="details-modal-section">
                        <h4>Manual Adjustments (Testing)</h4>
                        <div style="background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <div><label style="font-size: 11px; color: #666; display:block;">Used Orders (+/-)</label><input type="number" id="adj-order-delta" value="0" style="width: 100%; padding: 5px;"></div>
                                <div><label style="font-size: 11px; color: #666; display:block;">Used AI (+/-)</label><input type="number" id="adj-ai-delta" value="0" style="width: 100%; padding: 5px;"></div>
                                <div><label style="font-size: 11px; color: #666; display:block;">Validity Days (+/-)</label><input type="number" id="adj-validity-delta" value="0" style="width: 100%; padding: 5px;"></div>
                            </div>
                            <button id="btn-manual-adjust" class="btn-primary btn-sm" data-uid="${uid}" style="width: 100%;">Apply Adjustments</button>
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
                            title: 'Parsed Data', data: 'data', render: (data, type, row) => {
                                try {
                                    // Verify it's valid JSON list first
                                    const parsed = JSON.parse(data);
                                    if (Array.isArray(parsed) && parsed.length > 0) {
                                        return renderItemTableBtn(row.id, data);
                                    }
                                    return 'No Items';
                                } catch (e) { return 'Invalid Data'; }
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
                                    let isSuccess = false;

                                    // Robust status check
                                    if (resp.status === 'success' || resp.message === 'Order created successfully') isSuccess = true;
                                    if (resp.bg_process === true) isSuccess = true; // Redx
                                    if (resp.type && resp.type === 'success') isSuccess = true;

                                    let status = isSuccess ? '<strong style="color:green;">Success</strong>' : '<strong style="color:red;">Failed</strong>';
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

        $('#user-details-modal').on('click', '#btn-manual-adjust', async function () {
            const uid = $(this).data('uid');
            const order_delta = $('#adj-order-delta').val();
            const ai_delta = $('#adj-ai-delta').val();
            const validity_delta = $('#adj-validity-delta').val();

            if (order_delta == 0 && ai_delta == 0 && validity_delta == 0) return alert('Please enter at least one adjustment value.');
            if (!confirm('Apply these manual adjustments?')) return;

            try {
                await apiCall('manual_adjust_user', { uid, order_delta, ai_delta, validity_delta });
                alert('Adjustments applied successfully.');
                $('#user-details-modal').hide();
            } catch (e) {
                alert('Failed: ' + e.message);
            }
        });
    });
</script>