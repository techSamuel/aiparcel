<?php include 'includes/admin_header.php'; ?>
<div class="grid-2">
    <div class="card">
        <h3 id="payment-method-form-title">Add New Payment Method</h3>
        <form id="payment-method-form">
            <input type="hidden" id="payment-method-id">
            <div class="form-group"><label for="payment-method-name">Method Name</label><input type="text"
                    id="payment-method-name" required></div>
            <div class="form-group"><label for="payment-method-details">Account Details</label><textarea
                    id="payment-method-details" rows="3" required></textarea></div>
            <div class="form-group"><label for="payment-method-instructions">Instructions</label><textarea
                    id="payment-method-instructions" rows="3"></textarea></div>
            <div class="form-group"><label
                    style="display:flex; align-items:center; gap: 8px; font-weight: normal;"><input type="checkbox"
                        id="payment-method-is-active" checked> Active</label></div>
            <button type="submit" class="btn-primary">Save Method</button>
            <button type="button" id="clear-payment-method-form" class="btn-secondary">Clear</button>
        </form>
    </div>
    <div class="card">
        <h3>Active Payment Methods</h3>
        <div class="table-container">
            <table id="payment-methods-table" class="data-table display" style="width:100%"></table>
        </div>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    $(document).ready(function () {
        function loadPaymentMethods() {
            $('#payment-methods-table').DataTable({
                destroy: true,
                ajax: (d, cb) => apiCall('get_payment_methods').then(res => cb({ data: res })),
                columns: [
                    { title: "Name", data: "name" },
                    { title: "Active", data: "is_active", render: d => d == 1 ? 'Yes' : 'No' },
                    { data: 'account_details', visible: false },
                    { data: 'instructions', visible: false },
                    { title: "Actions", data: "id", orderable: false, render: d => `<button class="edit-pm-btn btn-sm btn-primary" data-id="${d}">Edit</button> <button class="delete-pm-btn btn-sm btn-danger" data-id="${d}">Delete</button>` }
                ]
            });
        }
        loadPaymentMethods();

        $('#payment-method-form').on('submit', async function (e) {
            e.preventDefault();
            try {
                await apiCall('save_payment_method', {
                    id: $('#payment-method-id').val() || null, name: $('#payment-method-name').val(),
                    account_details: $('#payment-method-details').val(), instructions: $('#payment-method-instructions').val(),
                    is_active: $('#payment-method-is-active').is(':checked') ? 1 : 0
                });
                this.reset(); $('#payment-method-id').val(''); $('#payment-method-form-title').text('Add New Method');
                loadPaymentMethods(); // Reload table
            } catch (e) { alert('Error: ' + e.message); }
        });

        $('#clear-payment-method-form').on('click', () => { $('#payment-method-form')[0].reset(); $('#payment-method-id').val(''); $('#payment-method-form-title').text('Add New Method'); });

        $('#payment-methods-table').on('click', '.edit-pm-btn', function () {
            const data = $('#payment-methods-table').DataTable().row($(this).parents('tr')).data();
            $('#payment-method-id').val(data.id);
            $('#payment-method-name').val(data.name);
            $('#payment-method-details').val(data.account_details);
            $('#payment-method-instructions').val(data.instructions);
            $('#payment-method-is-active').prop('checked', data.is_active == 1);
            $('#payment-method-form-title').text('Edit Method');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }).on('click', '.delete-pm-btn', async function () {
            if (confirm('Are you sure?')) {
                try {
                    await apiCall('delete_payment_method', { id: $(this).data('id') });
                    loadPaymentMethods();
                } catch (e) { alert('Error: ' + e.message); }
            }
        });
    });
</script>