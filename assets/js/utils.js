// --- HELPER FUNCTIONS ---
async function apiCall(action, body = {}) {
    try {
        const response = await fetch('api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...body }),
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || `HTTP error! status: ${response.status}`);
        }
        return data;
    } catch (error) {
        console.error('API Call Error:', action, error);
        throw error;
    }
}


function displayApiResponse(data) {
    const responseContainer = $('#apiResponse');
    responseContainer.empty().show().removeClass('message success error');

    // Case 1: Steadfast Bulk Success (DataTable)
    if (data && data.status === 200 && Array.isArray(data.data) && data.data.length > 0) {
        let tableHtml = `
        <p class="message success" style="display:block;">Successfully created ${data.data.length} Steadfast order(s).</p>
        <table id="api-response-datatable" class="display api-response-table" style="width:100%"></table>
    `;
        responseContainer.html(tableHtml);

        $('#api-response-datatable').DataTable({
            data: data.data,
            columns: [
                { title: "Invoice", data: "invoice" },
                { title: "Consignment ID", data: "consignment_id" },
                { title: "Tracking Code", data: "tracking_code" },
                { title: "Recipient", data: "recipient_name" },
                { title: "Phone", data: "recipient_phone" },
                { title: "Address", data: "recipient_address" },
                { title: "COD Amount", data: "cod_amount" },
                { title: "Note", data: "note" },
                { title: "Status", data: "status", render: data => `<span class="status-success">${data}</span>` }
            ],
            destroy: true, pageLength: 5, lengthChange: true, searching: true,
            scrollX: true
        });
    }
    // Case 2: NEW - Steadfast Single Order Success (DataTable)
    else if (data && data.status === 200 && data.consignment && data.consignment.consignment_id) {
        let tableHtml = `
        <p class="message success" style="display:block;">${data.message || 'Successfully created 1 Steadfast order.'}</p>
        <table id="api-response-datatable" class="display api-response-table" style="width:100%"></table>
    `;
        responseContainer.html(tableHtml);

        // DataTable needs an array, so we wrap the single 'consignment' object in []
        $('#api-response-datatable').DataTable({
            data: [data.consignment],
            columns: [
                { title: "Invoice", data: "invoice" },
                { title: "Consignment ID", data: "consignment_id" },
                { title: "Tracking Code", data: "tracking_code" },
                { title: "Recipient", data: "recipient_name" },
                { title: "Phone", data: "recipient_phone" },
                { title: "Address", data: "recipient_address" },
                { title: "COD Amount", data: "cod_amount" },
                { title: "Status", data: "status", render: data => `<span class="status-success">${data}</span>` },
                { title: "Note", data: "note" },
                { title: "Created At", data: "created_at", render: data => new Date(data).toLocaleString() }
            ],
            destroy: true, pageLength: 5, lengthChange: false, searching: false,
            scrollX: true
        });
    }
    // Case 3: Pathao Single Order Success (DataTable)
    else if (data && data.code === 200 && data.type === 'success' && data.data && data.data.consignment_id) {
        let tableHtml = `
        <p class="message success" style="display:block;">Successfully created 1 Pathao order.</p>
        <table id="api-response-datatable" class="display api-response-table" style="width:100%"></table>
    `;
        responseContainer.html(tableHtml);

        $('#api-response-datatable').DataTable({
            data: [data.data],
            columns: [
                { title: "Consignment ID", data: "consignment_id" },
                { title: "Merchant Order ID", data: "merchant_order_id" },
                { title: "Order Status", data: "order_status" },
                { title: "Delivery Fee", data: "delivery_fee" }
            ],
            destroy: true, pageLength: 5, lengthChange: false, searching: false
        });
    }
    // Case 4: Pathao Bulk Order Accepted (Notification Message)
    else if (data && data.code === 202 && data.type === 'success') {
        let messageHtml = `<p class="message success" style="display:block; text-align:left; line-height: 1.6;">
        <strong style="font-size: 16px;">Request Accepted</strong><br>${data.message}
     </p>`;
        responseContainer.html(messageHtml);
    }
    // Case 5: Redx Hybrid/Background Process Response (DataTable)
    else if (data && data.bg_process === true && Array.isArray(data.results)) {
        let successCount = data.success_count || 0;
        let totalCount = data.results.length;
        let messageHtml = `<p class="message success" style="display:block;">Processed ${totalCount} Redx order(s). Success: ${successCount}</p>`;

        let tableHtml = `
        ${messageHtml}
        <table id="api-response-datatable" class="display api-response-table" style="width:100%"></table>
        `;
        responseContainer.html(tableHtml);

        $('#api-response-datatable').DataTable({
            data: data.results,
            columns: [
                { title: "Order ID", data: "order_id" },
                {
                    title: "Tracking ID",
                    data: "response",
                    render: function (response) {
                        return response && response.tracking_id ? `<strong>${response.tracking_id}</strong>` : '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: "Area",
                    data: "debug_area",
                    render: function (area) {
                        return area ? `${area.name} <small class="text-muted">(${area.id})</small>` : '<span class="text-muted">N/A</span>';
                    }
                },
                {
                    title: "Status",
                    data: "status",
                    render: function (status) {
                        return status === 201
                            ? `<span class="status-success">Success (${status})</span>`
                            : `<span class="status-error">Error (${status})</span>`;
                    }
                }
            ],
            destroy: true, pageLength: 10, lengthChange: true, searching: true,
            scrollX: true,
            order: [[3, 'asc']] // Sort by status (success usually first/last depending on code)
        });
    }
    // Case 5: Fallback for Errors and other formats
    else {
        let type = (data && (data.error || (data.type && data.type !== 'success'))) ? 'error' : 'success';
        responseContainer.html(`<pre>${JSON.stringify(data, null, 2)}</pre>`);
        responseContainer.addClass(`message ${type}`);
    }
}

const showMessage = (element, text, type, duration = 5000) => {
    element.textContent = text;
    element.className = `message ${type}`;
    element.style.display = 'block';
    setTimeout(() => { element.style.display = 'none'; }, duration);
};

// Global Helper: Normalize Phone Number (Used by both parsers)
function normalizePhoneNumber(phone) {
    if (!phone) return phone;
    let p = phone.replace(/[\s-]/g, '');
    if (p.startsWith('+88')) p = p.substring(3);
    else if (p.startsWith('88')) p = p.substring(2);
    return p;
}

// Global Helper: Convert Bengali Numerals to English
// Global Helper: Convert Bengali Numerals to English
function convertBengaliToEnglishNumerals(str) {
    if (!str) return str;
    const bengali = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str.toString().replace(/[०-৯]/g, (char) => {
        const index = bengali.indexOf(char);
        return index > -1 ? english[index] : char;
    });
}
