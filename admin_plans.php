<?php include 'includes/admin_header.php'; ?>
<div class="grid-2">
    <div class="card">
        <h3 id="plan-form-title">Add New Plan</h3>
        <form id="plan-form">
            <input type="hidden" id="plan-id">
            <div class="form-group"><label for="plan-name">Plan Name</label><input type="text" id="plan-name" required>
            </div>
            <div class="form-group"><label for="plan-price">Price (BDT)</label><input type="number" id="plan-price"
                    step="0.01" required></div>
            <div class="form-group"><label for="plan-limit-monthly">Monthly Order Limit</label><input type="number"
                    id="plan-limit-monthly" placeholder="Leave blank for none"></div>
            <div class="form-group"><label for="plan-limit-daily">Daily Order Limit</label><input type="number"
                    id="plan-limit-daily" placeholder="Leave blank for none"></div>
            <div class="form-group"><label for="plan-limit-ai">Monthly AI Parsing Limit (Parcels)</label><input
                    type="number" id="plan-limit-ai" placeholder="Leave blank for none/unlimited"></div>
            <div class="form-group"><label for="plan-limit-bulk">Bulk Parse Limit (Per Request)</label><input
                    type="number" id="plan-limit-bulk" placeholder="Max parcels per AI request (default 30)"></div>
            <div class="form-group"><label for="plan-validity">Validity (Days)</label><input type="number"
                    id="plan-validity" required></div>
            <div class="form-group"><label for="plan-description">Description</label><textarea id="plan-description"
                    rows="3"></textarea></div>
            <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 15px;">
                <label>Feature Permissions</label>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                    <label
                        style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                            type="checkbox" id="plan-can-parse-ai"> Enable "Parse with AI"</label>
                    <label
                        style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                            type="checkbox" id="plan-can-correct-address"> Enable "Correct Address with AI"</label>
                    <label
                        style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                            type="checkbox" id="plan-can-autocomplete"> Enable "Parse & Autocomplete"</label>
                    <label
                        style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                            type="checkbox" id="plan-can-check-risk"> Enable "Check Risk"</label>
                    <label
                        style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                            type="checkbox" id="plan-can-show-ads"> Enable Ezoic Ads</label>
                </div>
            </div>
            <div class="form-group"><label
                    style="display:flex; align-items:center; gap: 8px; font-weight: normal;"><input type="checkbox"
                        id="plan-is-active" checked> Active</label></div>
            <button type="submit" class="btn-primary">Save Plan</button>
            <button type="button" id="clear-plan-form" class="btn-secondary">Clear</button>
        </form>
    </div>
    <div class="card">
        <h3>Existing Plans</h3>
        <div class="table-container">
            <table id="plans-table" class="data-table display" style="width:100%"></table>
        </div>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    $(document).ready(function () {
        function loadPlans() {
            $('#plans-table').DataTable({
                destroy: true,
                ajax: (d, cb) => apiCall('get_plans').then(res => cb({ data: res })),
                columns: [
                    { title: "Name", data: "name" },
                    { title: "Price", data: "price" },
                    { title: "Active", data: "is_active", render: d => d == 1 ? 'Yes' : 'No' },
                    { data: "order_limit_monthly", visible: false },
                    { data: "order_limit_daily", visible: false },
                    { data: "ai_parsing_limit", visible: false },
                    { data: "bulk_parse_limit", visible: false }, // Hidden col for data
                    { data: "validity_days", visible: false },
                    { data: "description", visible: false },
                    {
                        title: "Actions", data: "id", orderable: false, render: (d, t, r) => {
                            let btns = `<button class="edit-plan-btn btn-sm btn-primary" data-id="${d}">Edit</button>`;
                            if (r.name !== 'Free') {
                                btns += ` <button class="delete-plan-btn btn-sm btn-danger" data-id="${d}">Delete</button>`;
                            }
                            return btns;
                        }
                    }
                ]
            });
        }
        loadPlans();

        $('#plan-form').on('submit', async function (e) {
            e.preventDefault();
            try {
                await apiCall('save_plan', {
                    id: $('#plan-id').val() || null,
                    name: $('#plan-name').val(),
                    price: $('#plan-price').val(),
                    order_limit_monthly: $('#plan-limit-monthly').val() || null,
                    order_limit_daily: $('#plan-limit-daily').val() || null,
                    ai_parsing_limit: $('#plan-limit-ai').val() || 0,
                    bulk_parse_limit: $('#plan-limit-bulk').val() || 30, // New Field
                    validity_days: $('#plan-validity').val(),
                    description: $('#plan-description').val(),
                    is_active: $('#plan-is-active').is(':checked') ? 1 : 0,
                    can_parse_ai: $('#plan-can-parse-ai').is(':checked') ? 1 : 0,
                    can_autocomplete: $('#plan-can-autocomplete').is(':checked') ? 1 : 0,
                    can_check_risk: $('#plan-can-check-risk').is(':checked') ? 1 : 0,
                    can_correct_address: $('#plan-can-correct-address').is(':checked') ? 1 : 0,
                    can_show_ads: $('#plan-can-show-ads').is(':checked') ? 1 : 0
                });
                $('#plan-form')[0].reset();
                $('#plan-id').val('');
                $('#plan-form-title').text('Add New Plan');
                loadPlans();
            } catch (e) { alert('Error: ' + e.message); }
        });

        $('#clear-plan-form').on('click', () => { $('#plan-form')[0].reset(); $('#plan-id').val(''); $('#plan-form-title').text('Add New Plan'); });

        $('#plans-table').on('click', '.edit-plan-btn', function () {
            const data = $('#plans-table').DataTable().row($(this).parents('tr')).data();
            $('#plan-id').val(data.id);
            $('#plan-name').val(data.name);
            $('#plan-price').val(data.price);
            $('#plan-limit-monthly').val(data.order_limit_monthly);
            $('#plan-limit-daily').val(data.order_limit_daily);
            $('#plan-limit-ai').val(data.ai_parsing_limit);
            $('#plan-limit-bulk').val(data.bulk_parse_limit || 30); // New Field
            $('#plan-validity').val(data.validity_days);
            $('#plan-description').val(data.description);
            $('#plan-is-active').prop('checked', data.is_active == 1);
            $('#plan-can-parse-ai').prop('checked', data.can_parse_ai == 1);
            $('#plan-can-autocomplete').prop('checked', data.can_autocomplete == 1);
            $('#plan-can-check-risk').prop('checked', data.can_check_risk == 1);
            $('#plan-can-correct-address').prop('checked', data.can_correct_address == 1);
            $('#plan-can-show-ads').prop('checked', data.can_show_ads == 1);
            $('#plan-form-title').text('Edit Plan');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }).on('click', '.delete-plan-btn', async function () {
            if (confirm('Are you sure?')) {
                try { await apiCall('delete_plan', { id: $(this).data('id') }); loadPlans(); } catch (e) { alert('Error: ' + e.message); }
            }
        });
    });
</script>