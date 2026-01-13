let userCourierStores = {};
let geminiApiKey = null;
let aiBulkParseLimit = 50; // Default limit
let aiSpamCharLimit = 2000; // Default spam limit
let isPremiumUser = false;
let currentUser = null;
let userPermissions = {};
let currentParserFields = [];
let helpContent = '';
let duplicatePhoneData = {}; // Stores duplicate order info keyed by phone
const DEFAULT_PARSER_FIELDS = [
    { id: 'customerName', label: 'Customer Name', required: true },
    { id: 'phone', label: 'Phone', required: true },
    { id: 'address', label: 'Address', required: true },
    { id: 'amount', label: 'Amount', required: true },
    { id: 'productName', label: 'Product Name', required: false },
    { id: 'note', label: 'Note', required: false },
    { id: 'orderId', label: 'OrderID', required: false }
];

// --- DOM ELEMENT REFS ---
const appView = document.getElementById('app-view');
const logoutBtn = document.getElementById('logoutBtn');
const userInfo = document.getElementById('userInfo');
const storeSelector = document.getElementById('storeSelector');
const rawTextInput = document.getElementById('rawText');
const parsedDataContainer = document.getElementById('parsedDataContainer');
const parcelCountSpan = document.getElementById('parcelCount');
const totalCodSpan = document.getElementById('totalCod');
const createOrderBtn = document.getElementById('createOrderBtn');
const loader = document.getElementById('loader');
const parseLocallyBtn = document.getElementById('parseLocallyBtn');
const parseWithAIBtn = document.getElementById('parseWithAIBtn');
const parseAndAutocompleteBtn = document.getElementById('parseAndAutocompleteBtn');
const checkAllRiskBtn = document.getElementById('checkAllRiskBtn');

const openStoreModalBtn = document.getElementById('openStoreModalBtn');
const openSettingsModalBtn = document.getElementById('openSettingsModalBtn');
const openHistoryModalBtn = document.getElementById('openHistoryModalBtn');
const openProfileModalBtn = document.getElementById('openProfileModalBtn');
const openUpgradeModalBtn = document.getElementById('openUpgradeModalBtn');
const openSubscriptionHistoryModalBtn = document.getElementById('openSubscriptionHistoryModalBtn');
const openHelpModalBtn = document.getElementById('openHelpModalBtn');
const planStatusView = document.getElementById('plan-status-view');

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', async () => {
    try {
        console.log('Dashboard: Starting initialization...');
        const session = await apiCall('check_session');
        console.log('Dashboard: Session response:', session);

        if (session.loggedIn && session.user) {
            currentUser = session.user;
            isPremiumUser = session.user.plan_id > 1;
            console.log('Dashboard: User logged in, calling renderAppView...');
            await renderAppView();
            console.log('Dashboard: renderAppView completed successfully');
        } else {
            console.error('Dashboard: Not logged in, redirecting to login...');
            document.body.innerHTML = '<div style="padding:20px; text-align:center;"><h2>Not Logged In</h2><p>Please <a href="/">login</a> to continue.</p></div>';
        }
    } catch (e) {
        console.error('Dashboard Init Error:', e);
        document.body.innerHTML = '<div style="padding:20px; text-align:center;"><h2>Error Loading Dashboard</h2><p style="color:red;">' + e.message + '</p><pre style="text-align:left; background:#f5f5f5; padding:10px; overflow:auto;">' + e.stack + '</pre><button onclick="location.reload();">Retry</button></div>';
    }
});

// --- GOOGLE SIGNUP PIXEL TRACKING ---
// Check URL parameters for new_user=1 from google-auth.php
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('new_user') === '1' && urlParams.get('google_login') === 'success') {
    try {
        if (typeof fbq !== 'undefined') {
            fbq('track', 'CompleteRegistration', { method: 'Google' });
            // Clean URL to prevent double tracking on refresh
            const newUrl = window.location.pathname + window.location.search.replace(/[\?&]new_user=1/, '').replace(/[\?&]google_login=success/, '');
            window.history.replaceState({}, document.title, newUrl);
        }
    } catch (e) { console.error('Google Pixel Error:', e); }
}

// --- EMAIL SIGNUP PIXEL TRACKING ---
if (sessionStorage.getItem('new_email_signup') === 'true') {
    try {
        if (typeof fbq !== 'undefined') {
            fbq('track', 'CompleteRegistration', { method: 'Email' });
            sessionStorage.removeItem('new_email_signup'); // Clean up
        }
    } catch (e) { console.error('Email Pixel Error:', e); }
}

// --- CORE APP FUNCTIONS ---
async function renderAppView() {
    userInfo.textContent = currentUser.displayName || currentUser.email;

    const data = await apiCall('load_user_data');
    userCourierStores = data.stores || {};
    geminiApiKey = data.geminiApiKey;
    aiBulkParseLimit = parseInt(data.aiBulkParseLimit) || 50; // Load limit (Restored)
    aiSpamCharLimit = parseInt(data.aiSpamCharLimit) || 2000;

    // Update Placeholder with Dynamic Limit & Bengali Text
    // This is now handled by updateRawTextPlaceholder() to ensure it persists across mode toggles

    userPermissions = data.permissions || {};
    helpContent = data.helpContent || '<p>No help guide has been set up by the administrator.</p>';

    currentUser.lastSelectedStoreId = data.lastSelectedStoreId;

    loadUserStores();
    updateFeatureVisibilityBasedOnPlan();
    await renderPlanStatus();

    // help modal content
    if (document.getElementById('help-content-container')) {
        document.getElementById('help-content-container').innerHTML = helpContent;
        if (openHelpModalBtn) openHelpModalBtn.onclick = () => $('#help-modal').show();
    }

    // Parser Settings
    let savedSettings = data.parserSettings;
    let fields = [...DEFAULT_PARSER_FIELDS];
    let smartParsingEnabled = true;

    if (savedSettings) {
        if (Array.isArray(savedSettings)) {
            if (savedSettings.length > 0) fields = savedSettings;
        } else if (typeof savedSettings === 'object' && savedSettings !== null) {
            if (Array.isArray(savedSettings.fields) && savedSettings.fields.length > 0) {
                fields = savedSettings.fields;
            }
            if (typeof savedSettings.smart_parsing !== 'undefined') {
                smartParsingEnabled = savedSettings.smart_parsing;
            }
            if (savedSettings.custom_note) {
                $('#customNoteTemplate').val(savedSettings.custom_note.template || '');
                $('#autoApplyNote').prop('checked', !!savedSettings.custom_note.auto_apply);
            }
        }
    }

    currentParserFields = fields;
    renderParserFields();

    const toggle = document.getElementById('smartParseToggle');
    if (toggle) {
        toggle.checked = smartParsingEnabled;
        updateRawTextPlaceholder();
    }

    // Sorting Event Listener
    const sortDropdown = document.getElementById('sortParcels');
    if (sortDropdown) {
        sortDropdown.addEventListener('change', function () {
            sortParcels(this.value);
        });
    }
}

// Sorting Function
function sortParcels(criteria) {
    const container = $('#parsedDataContainer');
    const cards = container.children('.parcel-card').get();

    cards.sort(function (a, b) {
        const dataA = JSON.parse($(a).attr('data-order-data'));
        const dataB = JSON.parse($(b).attr('data-order-data'));

        if (criteria === 'warning') {
            // Priority: Invalid > Duplicate > Normal
            const isInvalidA = $(a).hasClass('invalid-parcel');
            const isInvalidB = $(b).hasClass('invalid-parcel');
            const isDupA = $(a).find('.duplicate-warning-badge').length > 0;
            const isDupB = $(b).find('.duplicate-warning-badge').length > 0;

            if (isInvalidA && !isInvalidB) return -1;
            if (!isInvalidA && isInvalidB) return 1;
            if (isDupA && !isDupB) return -1;
            if (!isDupA && isDupB) return 1;
            return 0;
        }

        if (criteria === 'name_asc') {
            return (dataA.recipient_name || '').localeCompare(dataB.recipient_name || '');
        }
        if (criteria === 'name_desc') {
            return (dataB.recipient_name || '').localeCompare(dataA.recipient_name || '');
        }

        if (criteria === 'phone_asc') {
            return (dataA.recipient_phone || '').localeCompare(dataB.recipient_phone || '');
        }
        if (criteria === 'phone_desc') {
            return (dataB.recipient_phone || '').localeCompare(dataA.recipient_phone || '');
        }

        return 0; // Default/None (keep existing order roughly, though stable sort depends on browser)
    });

    $.each(cards, function (idx, itm) { container.append(itm); });
}

async function renderPlanStatus() {
    try {
        const status = await apiCall('get_subscription_data');
        // Ensure API Key is persistent
        if (status.geminiApiKey) {
            geminiApiKey = status.geminiApiKey;
        }

        if (status.permissions) {
            userPermissions = status.permissions;
        }
        isPremiumUser = status.plan_id > 1;
        updateFeatureVisibilityBasedOnPlan();

        let usageHTML = '';
        if (status.order_limit_monthly) {
            const percentage = Math.min((status.monthly_order_count / status.order_limit_monthly) * 100, 100);
            usageHTML = `<p>Orders this cycle: <strong>${status.monthly_order_count} / ${status.order_limit_monthly}</strong></p>
                       <div class="progress-bar"><div class="progress-bar-inner" style="width:${percentage}%"></div></div>`;
        } else if (status.order_limit_daily) {
            usageHTML = `<p>Orders today: <strong>${status.daily_order_count} / ${status.order_limit_daily}</strong></p>`;
        }

        // NEW: AI Usage
        if (status.ai_parsing_limit > 0) {
            const aiPercentage = Math.min((status.monthly_ai_parsed_count / status.ai_parsing_limit) * 100, 100);
            usageHTML += `<div style="margin-top:8px; border-top: 1px dashed #eee; padding-top:5px;">
                            <p style="font-size:13px; margin-bottom: 2px;">AI Parsing: <strong>${status.monthly_ai_parsed_count} / ${status.ai_parsing_limit}</strong></p>
                            <div class="progress-bar" style="height:6px; margin-top:0;"><div class="progress-bar-inner" style="width:${aiPercentage}%; background-color: #9b59b6;"></div></div>
                          </div>`;
        }
        if (status.plan_expiry_date) {
            // Force Asia/Dhaka timezone
            const expiry = new Date(status.plan_expiry_date + 'T00:00:00+06:00').toLocaleDateString('en-GB', { timeZone: 'Asia/Dhaka' });
            planStatusView.innerHTML = `<h3>Current Plan: <strong>${status.plan_name}</strong></h3>${usageHTML}<p>Expires on: <strong>${expiry}</strong></p>`;
        } else {
            planStatusView.innerHTML = `<h3>Current Plan: <strong>${status.plan_name}</strong></h3>${usageHTML}<p>Expires on: <strong>N/A</strong></p>`;
        }
        planStatusView.style.display = 'block';
    } catch (e) {
        planStatusView.innerHTML = `<p class="error">${e.message}</p>`;
        planStatusView.style.display = 'block';
    }
}

function updateFeatureVisibilityBasedOnPlan() {
    const canParseAI = userPermissions.can_parse_ai && geminiApiKey;
    $('#parseWithAIBtn').toggle(!!canParseAI);
    $('#parseAndAutocompleteBtn').toggle(userPermissions.can_autocomplete);
    $('#checkAllRiskBtn').toggle(userPermissions.can_check_risk);

    // NEW: Control Visibility of Local Parsing Features
    // Default is OFF unless explicitly enabled
    const canManualParse = userPermissions.can_manual_parse === true;
    $('#parseLocallyBtn').toggle(canManualParse);
    $('#openSettingsModalBtn').toggle(canManualParse);

    // Also hide the Smart Parse Toggle if they can't manual parse/settings
    $('.toggle-switch-container').toggle(canManualParse);

    // Show parsing input area if any main feature is enabled
    const anyFeatureEnabled = canParseAI || userPermissions.can_autocomplete || canManualParse || userPermissions.can_check_risk;
    $('#parsing-input-area').toggle(!!anyFeatureEnabled);
}

function loadUserStores() {
    $('#storeList, #storeSelector').empty();
    if (Object.keys(userCourierStores).length === 0) {
        $('#storeList').html('<li>No stores found.</li>');
        $('#storeSelector').html(`<option value="">Please add a store first</option>`);
        updateCreateOrderButtonText();
        return;
    }
    for (const id in userCourierStores) {
        const store = userCourierStores[id];
        // Support both camelCase and snake_case property names
        const courierType = store.courierType || store.courier_type || 'unknown';
        const storeName = store.storeName || store.store_name || 'Unknown Store';
        // Ensure lowercase class for CSS matching (redx, pathao, steadfast)
        const badgeClass = courierType.toLowerCase();
        $('#storeList').append(`<li><span>${storeName} <span class="courier-badge ${badgeClass}">${courierType}</span></span><div class="store-actions"><button class="edit-store-btn" data-id="${id}">Edit</button><button class="delete-store-btn" data-id="${id}">&times;</button></div></li>`);
        $('#storeSelector').append(`<option value="${id}">${storeName}</option>`);
    }

    // Set selected value if it exists in the list
    // Set selected value if it exists in the list
    const savedStoreId = currentUser.lastSelectedStoreId || currentUser.last_selected_store_id;
    if (savedStoreId && userCourierStores[savedStoreId]) {
        storeSelector.value = savedStoreId;
    } else {
        // Default to first available
        storeSelector.value = Object.keys(userCourierStores)[0];
    }
    updateCreateOrderButtonText();
}

function updateCreateOrderButtonText() {
    const selectedStoreId = storeSelector.value;
    if (selectedStoreId && userCourierStores[selectedStoreId]) {
        const store = userCourierStores[selectedStoreId];
        const courierType = store.courierType || store.courier_type || 'unknown';
        // Capitalize first letter: redx -> Redx
        createOrderBtn.textContent = `Create ${courierType.charAt(0).toUpperCase() + courierType.slice(1)} Order(s)`;
    } else {
        createOrderBtn.textContent = 'Create Order(s)';
    }
}

// Persist Store Selection
storeSelector.addEventListener('change', async function () {
    updateCreateOrderButtonText();
    const selectedId = this.value;
    if (selectedId) {
        try {
            await apiCall('update_profile', { lastSelectedStoreId: selectedId });
            currentUser.lastSelectedStoreId = selectedId;
        } catch (e) { console.error("Failed to save store preference", e); }
    }
});

function updateSummary() {
    const parcelCards = $('.parcel-card');
    let totalCod = 0;
    parcelCards.each(function () {
        totalCod += Number(JSON.parse($(this).data('orderData')).amount) || 0;
    });
    parcelCountSpan.textContent = parcelCards.length;
    totalCodSpan.textContent = `${totalCod} BDT`;
}

// --- PARCEL & CARD LOGIC ---
function createParcelCard(parcelData) {
    // --- Validation Logic ---
    const customerName = parcelData.recipient_name || parcelData.customerName || 'NoNamedCustomer'; // Default Name
    // PROCESSED: Convert Bengali numerals to English for Phone and Amount
    const phoneRaw = parcelData.recipient_phone || parcelData.phone || parcelData.customerPhone || 'N/A';
    // 1. Convert Bengali -> English
    // 2. Normalize (Remove +88/88)
    const phone = normalizePhoneNumber(convertBengaliToEnglishNumerals(phoneRaw));

    const address = parcelData.recipient_address || parcelData.address || parcelData.customerAddress || 'N/A';
    const orderId = parcelData.order_id || parcelData.orderId || 'N/A';

    const amountRaw = parcelData.cod_amount || parcelData.amount || 0;
    const amount = parseFloat(convertBengaliToEnglishNumerals(amountRaw));
    const productName = parcelData.item_description || parcelData.productName || 'N/A';
    const note = parcelData.note || 'N/A';

    // Update parcelData with cleaned values so we can use them later (e.g. for Total COD)
    parcelData.recipient_name = customerName;
    parcelData.recipient_phone = phone;
    parcelData.recipient_address = address;
    parcelData.cod_amount = amount;
    parcelData.amount = amount; // IMPORTANT: standardize on 'amount' for calculation
    parcelData.order_id = orderId;
    parcelData.item_description = productName;
    parcelData.note = note;
    // Store original note to prevent recursive appending when re-applying templates
    if (typeof parcelData.original_note === 'undefined') {
        parcelData.original_note = note;
    }

    const card = $(`<div class="parcel-card"></div>`);
    // IMPORTANT: Use .attr() so it's accessible via .attr() later (and visible in DOM inspector)
    card.attr('data-order-data', JSON.stringify(parcelData));
    card.data('orderData', JSON.stringify(parcelData)); // Keep cache in sync
    const phoneForCheck = (phone || '').replace(/\s+/g, '');
    const isPhoneValid = /^01[3-9]\d{8}$/.test(phoneForCheck);

    // Strict Validation: Phone, Address and Name (Price can be 0 now)
    const isAddressValid = address && address !== 'N/A' && address !== 'null' && address.length > 5;
    const isPriceValid = !isNaN(amount) && amount >= 0;

    // Check if mandatory fields are missing/invalid
    let missingFields = [];
    if (!isPhoneValid) missingFields.push('Phone');
    if (!isAddressValid) missingFields.push('Address');
    // Price checks
    if (!isPriceValid) missingFields.push('Price');

    const isInvalid = missingFields.length > 0;
    let errorHtml = '';

    if (isInvalid) {
        card.addClass('invalid-parcel');
        errorHtml = `<div class="validation-error" style="color:#dc3545; font-weight:bold; font-size:12px; margin-bottom:5px;">⚠️ Missing: ${missingFields.join(', ')}</div>`;
    }

    const checkRiskDisabled = !isPhoneValid || !userPermissions.can_check_risk;
    const checkRiskTitle = !userPermissions.can_check_risk ? 'This is a premium feature.' : 'Check customer risk';

    // Correct Address Button State
    const isCorrected = parcelData.ai_address_corrected === true;
    const correctAddressDisabled = !userPermissions.can_correct_address || isCorrected;
    const correctAddressTitle = !userPermissions.can_correct_address ? 'This is a premium feature.' : (isCorrected ? 'Address already corrected by AI' : 'Correct Address With AI');
    const correctAddressText = isCorrected ? 'Corrected ✔️' : 'Correct Address With AI';

    // Check for duplicate order
    const duplicateInfo = duplicatePhoneData[phone];
    let duplicateBadgeHtml = '';

    if (duplicateInfo) {
        // Priority 1: Local Duplicate (Danger)
        if (duplicateInfo.is_local_duplicate) {
            duplicateBadgeHtml += `
            <div class="duplicate-warning-badge" style="background: #eafaeb; border: 1px solid #d63384; border-radius: 4px; padding: 8px; margin-top: 8px; font-size: 12px; background-color: #fff0f0; border-color: #ff0000;">
                <strong style="color: #d60000;">⚠️ ডুপ্লিকেট ডাটা (${duplicateInfo.local_count}x)</strong><br>
                <span style="color: #a00;">এই নম্বরটি বর্তমান লিস্টে একাধিকবার আছে!</span>
            </div>`;
        }

        // Priority 2: Database Duplicate (Warning)
        // If it has courier_type (prop from DB response), show history
        if (duplicateInfo.courier_type) {
            duplicateBadgeHtml += `
            <div class="duplicate-warning-badge" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 8px; margin-top: 8px; font-size: 12px;">
                <strong style="color: #856404;">⚠️ পূর্বের অর্ডার</strong><br>
                <span style="color: #664d03;">কুরিয়ার: ${duplicateInfo.courier_type.toUpperCase()} | ট্র্যাকিং: ${duplicateInfo.tracking_id || 'N/A'}</span><br>
                <span style="color: #664d03;">অর্ডার আইডি: ${duplicateInfo.order_id || 'N/A'} | তারিখ: ${new Date(duplicateInfo.created_at).toLocaleDateString('en-GB')}</span>
            </div>`;
        }
    }

    // Checking for 0 COD Warning
    let codWarningHtml = '';
    if (amount === 0) {
        codWarningHtml = `<div style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 4px; margin-top: 4px; border-radius: 4px; font-size: 0.85em; display: inline-block;">⚠️ Warning: COD is set to 0</div>`;
    }

    card.html(`
        <div class="details">
            ${errorHtml}
            <div style="font-weight:bold; margin-bottom:2px;">${customerName} <span style="font-weight:normal;">(${phone})</span></div>
            <div style="margin-bottom:4px;">Address: <span class="address-text">${address}</span></div>
            <div style="font-size:0.9em; color:#555;">
                OID: ${orderId} | COD: <strong>${amount} BDT</strong> | Item: ${productName}
            </div>
            ${codWarningHtml}
            <div style="margin-top: 5px;">
                <input type="text" class="input-note" value="${note !== 'N/A' ? note : ''}" placeholder="Add Note..." style="width: 100%; border: 1px solid #ddd; padding: 4px; font-size: 12px; border-radius: 4px;">
            </div>
            ${duplicateBadgeHtml}
        </div>
        <div class="parcel-actions">
            <button class="check-risk-btn" data-phone="${phoneForCheck}" ${checkRiskDisabled ? 'disabled' : ''} title="${checkRiskTitle}">Check Risk</button>
            <button class="correct-address-btn" ${correctAddressDisabled ? 'disabled' : ''} title="${correctAddressTitle}">${correctAddressText}</button>
            <button class="edit-btn" title="Edit Details">Edit ✏️</button>
            <button class="remove-btn">&times;</button>
        </div>
        <div class="fraud-results-container" style="display: none;"></div>
    `);

    // --- Event Listeners ---

    // Note Sync
    card.find('.input-note').on('input', function () {
        const newData = JSON.parse(card.attr('data-order-data'));
        newData.note = $(this).val();
        card.attr('data-order-data', JSON.stringify(newData));
        card.data('orderData', JSON.stringify(newData));
    });

    // Edit Button Click
    card.find('.edit-btn').on('click', function () {
        openEditModal(card);
    });

    parsedDataContainer.appendChild(card[0]);
    validateAllParcels(); // Update button state
    updateSummary(); // Update totals
} // Close createParcelCard

// --- Edit Modal Logic ---
let currentEditCard = null;
const editModal = $('#edit-parcel-modal');
const editNameInput = $('#edit-name');
const editPhoneInput = $('#edit-phone');
const editAddressInput = $('#edit-address');
const editAmountInput = $('#edit-amount');
const editProductInput = $('#edit-product');
const editNoteInput = $('#edit-note');
const saveParcelBtn = $('#save-parcel-btn');
const closeEditModalBtn = editModal.find('.close-btn');

// Close Logic
closeEditModalBtn.on('click', function () { editModal.hide(); });
// Outside click listener REMOVED for Edit Modal as per user request
// $(window).on('click', function (event) {
//     if ($(event.target).is(editModal)) { editModal.hide(); }
// });

function openEditModal(card) {
    currentEditCard = card;
    const data = JSON.parse(card.attr('data-order-data'));

    // Populate Inputs
    editNameInput.val(data.recipient_name || '');
    editPhoneInput.val(data.recipient_phone || '');
    editAddressInput.val(data.recipient_address || '');
    editAmountInput.val(data.cod_amount || 0);
    editProductInput.val(data.item_description || '');
    editNoteInput.val(data.note === 'N/A' || data.note === 'null' ? '' : data.note); // Handle N/A note

    editModal.show();
}

saveParcelBtn.on('click', function () {
    if (!currentEditCard) return;

    // 1. Get Values
    const newName = editNameInput.val().trim();
    const newPhone = editPhoneInput.val().trim();
    const newAddress = editAddressInput.val().trim();
    const newAmount = parseFloat(editAmountInput.val()) || 0;
    const newProduct = editProductInput.val().trim();
    const newNote = editNoteInput.val().trim();

    // 2. Update Data Object
    let data = JSON.parse(currentEditCard.attr('data-order-data'));
    data.recipient_name = newName;
    data.recipient_phone = newPhone;
    data.recipient_address = newAddress;
    data.cod_amount = newAmount;
    data.amount = newAmount; // Sync
    data.item_description = newProduct;
    data.note = newNote;

    // 3. Update DOM (Re-render content mostly)
    currentEditCard.find('.details').html(`
        <div style="font-weight:bold; margin-bottom:2px;">${newName || 'No Name'} <span style="font-weight:normal;">(${newPhone})</span></div>
        <div style="margin-bottom:4px;">Address: <span class="address-text">${newAddress}</span></div>
        <div style="font-size:0.9em; color:#555;">
            OID: ${data.order_id} | COD: <strong>${newAmount} BDT</strong> | Item: ${newProduct}
        </div>
        <div style="margin-top: 5px;">
            <input type="text" class="input-note" value="${newNote}" placeholder="Add Note..." style="width: 100%; border: 1px solid #ddd; padding: 4px; font-size: 12px; border-radius: 4px;">
        </div>
        ${currentEditCard.find('.duplicate-warning-badge').length ? currentEditCard.find('.duplicate-warning-badge')[0].outerHTML : ''}
    `);

    // Re-attach note listener since we replaced HTML
    currentEditCard.find('.input-note').on('input', function () {
        const d = JSON.parse(currentEditCard.attr('data-order-data'));
        d.note = $(this).val();
        currentEditCard.attr('data-order-data', JSON.stringify(d));
        currentEditCard.data('orderData', JSON.stringify(d));
    });

    // 4. Validate
    const pPhone = (newPhone || '').replace(/\s+/g, '');
    const isPhoneValid = /^01[3-9]\d{8}$/.test(pPhone);
    const isAddressValid = newAddress && newAddress !== 'N/A' && newAddress.length > 5;
    const isPriceValid = !isNaN(newAmount) && newAmount > 0;

    if (isPhoneValid && isAddressValid && isPriceValid) {
        currentEditCard.removeClass('invalid-parcel');
    } else {
        currentEditCard.addClass('invalid-parcel');
    }

    // 5. Update Attributes & Check Risk Button
    currentEditCard.attr('data-order-data', JSON.stringify(data));
    currentEditCard.data('orderData', JSON.stringify(data));

    const checkBtn = currentEditCard.find('.check-risk-btn');
    checkBtn.attr('data-phone', pPhone);
    checkBtn.prop('disabled', !isPhoneValid);

    editModal.hide();
    validateAllParcels();
    updateSummary();
});



// --- Validation Helper ---
function validateAllParcels() {
    const invalidCards = $('.parcel-card.invalid-parcel').length;
    const createBtn = $('#createOrderBtn');
    if (invalidCards > 0) {
        createBtn.prop('disabled', true).text(`Fix ${invalidCards} Invalid Parcel(s)`);
        createBtn.css('opacity', '0.6').css('cursor', 'not-allowed');
    } else {
        createBtn.prop('disabled', false);
        updateCreateOrderButtonText();
        createBtn.css('opacity', '1').css('cursor', 'pointer');
    }
}

async function correctSingleAddress(buttonElement) {
    const $button = $(buttonElement);
    const $card = $button.closest('.parcel-card');
    const $addressTextSpan = $card.find('.address-text');
    let parcelData = JSON.parse($card.data('orderData'));

    let addressKey;
    if (parcelData.address) addressKey = 'address';
    else if (parcelData.customerAddress) addressKey = 'customerAddress';
    else if (parcelData.recipient_address) addressKey = 'recipient_address';

    const originalAddress = addressKey ? parcelData[addressKey] : null;

    if (!originalAddress) { alert('No address to correct.'); return; }

    $button.prop('disabled', true).text('Correcting...');
    try {
        const result = await apiCall('correct_address_ai', { address: originalAddress });
        if (result.corrected_address) {
            parcelData[addressKey] = result.corrected_address;
            parcelData.ai_address_corrected = true; // Mark as corrected
            $card.attr('data-order-data', JSON.stringify(parcelData)); // Update attr
            $card.data('orderData', JSON.stringify(parcelData)); // Update data
            $addressTextSpan.text(result.corrected_address);

            $button.text('Corrected ✔️');
            // Button remains disabled naturally since we don't re-enable it on success

            // Re-validate after correction
            const address = result.corrected_address;
            const isAddressValid = address && address !== 'N/A' && address !== 'null' && address.length > 5;

            // Re-check other mandatory fields from data
            const phoneForCheck = (parcelData.recipient_phone || parcelData.phone || parcelData.customerPhone || '').replace(/\s+/g, '');
            const isPhoneValid = /^01[3-9]\d{8}$/.test(phoneForCheck);
            const amount = parseFloat(parcelData.cod_amount || parcelData.amount || 0);
            const isPriceValid = !isNaN(amount) && amount >= 0;

            let missingFields = [];
            if (!isPhoneValid) missingFields.push('Phone');
            if (!isAddressValid) missingFields.push('Address');
            if (!isPriceValid) missingFields.push('Price');

            if (missingFields.length === 0) {
                $card.removeClass('invalid-parcel');
                // Also hide dynamic error if any (re-rendering might be cleaner, but simple class toggle works for border)
                // Ideally we should re-call logic but since createParcelCard is complex, let's just create a helper or trust re-validation
                $card.find('.validation-error').remove();
            }
            validateAllParcels();

        } else { throw new Error("AI did not return a corrected address."); }
    } catch (error) {
        alert(`Error correcting address: ${error.message}`);
        $button.text('Correction Failed');
        // Re-enable only on error
        setTimeout(() => { $button.prop('disabled', false).html('Correct Address With AI'); }, 3000);
    }
}

async function checkFraudRisk(buttonElement) {
    const phoneNumber = buttonElement.dataset.phone;
    if (!phoneNumber) return;

    const card = buttonElement.closest('.parcel-card');
    const resultsContainer = card.querySelector('.fraud-results-container');

    buttonElement.disabled = true;
    buttonElement.textContent = 'Checking...';
    resultsContainer.style.display = 'block';
    resultsContainer.innerHTML = '<div class="loader" style="display:block; margin: 10px auto; height: 20px; width: 20px;"></div>';

    try {
        const data = await apiCall('check_fraud_risk', { phone: phoneNumber });
        let totalOrders = 0; let totalDelivered = 0;
        data.forEach(courier => {
            totalOrders += parseInt(courier.orders) || 0;
            totalDelivered += parseInt(courier.delivered) || 0;
        });
        const successRatio = totalOrders > 0 ? ((totalDelivered / totalOrders) * 100).toFixed(1) : 0;
        let ratioColor = '#27ae60';
        if (successRatio < 80) ratioColor = '#f39c12';
        if (successRatio < 60) ratioColor = '#c0392b';

        let tableHTML = `<table class="fraud-results-table"><thead><tr><th>Courier</th><th>Orders</th><th>Delivered</th><th>Cancelled</th><th>Cancel Rate</th></tr></thead><tbody>`;
        data.forEach(courier => {
            tableHTML += `<tr><td>${courier.courier}</td><td>${courier.orders}</td><td>${courier.delivered}</td><td>${courier.cancelled}</td><td>${courier.cancel_rate}</td></tr>`;
        });
        tableHTML += '</tbody></table>';

        // Extract server source from data (all rows should have same server)
        const serverSource = data[0]?.server || 'Unknown';
        const serverName = serverSource.replace(/https?:\/\//, '').split('/')[0];

        const uniqueId = `details-${phoneNumber}-${Date.now()}`;
        const finalHTML = `
            <div style="padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span style="font-weight: 600; font-size: 13px;">
                        Delivery Success Ratio: 
                        <strong style="color: ${ratioColor}; font-size: 15px;">${successRatio}%</strong>
                        <span style="font-size: 11px; color: #888; margin-left: 8px;">(${serverName})</span>
                    </span>
                    <button class="toggle-details-btn btn-secondary btn-sm" data-target="#${uniqueId}" style="white-space: nowrap;">Show Details</button>
                </div>
                <div id="${uniqueId}" style="display: none; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                    ${tableHTML}
                </div>
            </div>`;
        resultsContainer.innerHTML = finalHTML;
        buttonElement.textContent = 'Checked';

        resultsContainer.querySelector('.toggle-details-btn').addEventListener('click', function () {
            const targetSelector = this.getAttribute('data-target');
            const detailsDiv = resultsContainer.querySelector(targetSelector);
            if (detailsDiv) {
                const isHidden = detailsDiv.style.display === 'none';
                detailsDiv.style.display = isHidden ? 'block' : 'none';
                this.textContent = isHidden ? 'Hide Details' : 'Show Details';
            }
        });

    } catch (error) {
        resultsContainer.innerHTML = `<p class="message error" style="display:block; text-align:left; padding: 8px;">Error: ${error.message}</p>`;
        buttonElement.textContent = 'Check Failed';
    }
}

// --- PARSING HELPERS ---

/**
 * Converts Bengali numerals (০-৯) to English digits (0-9).
 */
function convertBengaliToEnglish(str) {
    const bengaliDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    return str.replace(/[০-৯]/g, (match) => bengaliDigits.indexOf(match));
}

/**
 * Enhanced Smart Auto-Parser: Identifies fields from unstructured order text.
 * @param {string} orderText - The raw text block for a single parcel.
 * @returns {object} - Parsed parcel data.
 */
function identifyAndParseOrder(orderText) {
    const parsedData = {
        orderId: null,
        customerName: null,
        productName: null,
        amount: null,
        customerAddress: null,
        customerPhone: null,
        note: null
    };

    // --- PRE-PROCESSING: Convert ALL Bengali digits to English FIRST ---
    let lines = orderText.split('\n').map(line => {
        let converted = '';
        for (const char of line.trim()) {
            // Bengali digits: ০ (U+09E6) to ৯ (U+09EF)
            const code = char.charCodeAt(0);
            if (code >= 0x09E6 && code <= 0x09EF) {
                converted += (code - 0x09E6).toString();
            } else {
                converted += char;
            }
        }
        return converted;
    }).filter(line => line.length > 0);

    console.log('Pre-converted lines:', lines); // DEBUG

    if (lines.length === 0) return parsedData;

    const assignedLines = new Set();

    // --- PASS 1: Extract Phone Number (Highest Priority) ---
    // Lines are already converted. Check for BD phone: 01X + 8 digits
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        // Remove spaces, dashes, etc.
        const normalized = lines[i].replace(/[\s\-\(\)\.]/g, '');
        console.log('Phone check - normalized:', normalized); // DEBUG

        // Valid BD phone patterns:
        // - 01XXXXXXXXX (11 digits)
        // - 8801XXXXXXXXX (13 digits)
        // - +8801XXXXXXXXX (14 chars)
        let phoneDigits = null;
        if (/^\+?880?0?1[3-9]\d{8}$/.test(normalized)) {
            const match = normalized.match(/1[3-9]\d{8}$/);
            if (match) phoneDigits = '0' + match[0];
        } else if (/^01[3-9]\d{8}$/.test(normalized)) {
            phoneDigits = normalized;
        }

        if (phoneDigits) {
            console.log('Phone detected:', phoneDigits); // DEBUG
            parsedData.customerPhone = phoneDigits;
            assignedLines.add(i);
            break;
        }
    }

    // --- PASS 2: Extract Order ID (BEFORE Amount) ---
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const line = lines[i];
        const normalized = line.replace(/[\s\-]/g, '');

        // Skip if line has common address/name patterns
        if (/[,।]/.test(line) || /road|house|village|গ্রাম|রোড/i.test(line)) continue;

        // Pattern 1: Pure digit string with 7+ digits (but NOT a phone pattern)
        if (/^\d{7,}$/.test(normalized)) {
            if (!/^0?1[3-9]\d{8}$/.test(normalized) && !/^880?1[3-9]\d{8}$/.test(normalized)) {
                parsedData.orderId = normalized;
                assignedLines.add(i);
                continue;
            }
        }

        // Pattern 2: Alphanumeric with prefixes like ORD-, INV-, #
        const orderIdMatch = line.match(/^(order|id|ref|inv|oid|#)?[\s:\-#]*([A-Za-z0-9\-\_]{6,30})$/i);
        if (orderIdMatch && orderIdMatch[2]) {
            if (/\d/.test(orderIdMatch[2]) || orderIdMatch[2].length >= 10) {
                parsedData.orderId = orderIdMatch[2];
                assignedLines.add(i);
                break;
            }
        }
    }

    // --- PASS 3: Extract Amount ---
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const line = lines[i];

        // Match amount patterns (max 6 digits)
        const amountMatch = line.match(/^(BDT|৳|Tk\.?|Cash|টাকা|taka)?[\s]*([\d]{1,6})([\.,]\d{1,2})?[\s]*(BDT|৳|Tk|টাকা|taka|\/-)?$/i);
        if (amountMatch && amountMatch[2]) {
            const potentialAmount = parseFloat(amountMatch[2] + (amountMatch[3] || '').replace(',', '.'));
            // Sanity check: COD amounts are typically 50-99999
            if (potentialAmount >= 50 && potentialAmount <= 99999) {
                parsedData.amount = potentialAmount;
                assignedLines.add(i);
                break;
            }
        }
    }

    // --- PASS 4: Extract Address (Look for structural keywords) ---
    const addressKeywords = /(house|road|block|sector|holding|village|para|thana|district|division|union|upazila|zilla|sadar|post|p\.o\.|বাসা|রোড|গ্রাম|থানা|জেলা|বিভাগ|ইউনিয়ন|উপজেলা|হোল্ডিং|পোস্ট|সদর|এলাকা|মহল্লা)/i;
    const addressPatterns = /\d+[\s,\/\-]+\w+|[A-Za-z\u0980-\u09FF]+[\s,]+[A-Za-z\u0980-\u09FF]+[\s,]+[A-Za-z\u0980-\u09FF]+/; // e.g., "123 Main Street" or comma-separated phrases
    let bestAddressIndex = -1;
    let bestAddressScore = 0;

    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const line = lines[i];
        let score = 0;
        if (addressKeywords.test(line)) score += 50;
        if (line.includes(',') || line.includes('।')) score += 20; // Comma or Bengali full stop often in addresses
        if (addressPatterns.test(line)) score += 15;
        if (line.length > 30) score += 10; // Longer lines are more likely addresses
        if (line.length > 50) score += 10;

        if (score > bestAddressScore) {
            bestAddressScore = score;
            bestAddressIndex = i;
        }
    }
    if (bestAddressIndex !== -1 && bestAddressScore >= 20) { // Threshold of 20
        parsedData.customerAddress = lines[bestAddressIndex];
        assignedLines.add(bestAddressIndex);
    }

    // --- PASS 5: Extract Customer Name (Look for titles or short plain text lines) ---
    const nameTitles = /^(মোঃ|মোহাম্মদ|md\.?|mr\.?|mrs\.?|miss\.?|ms\.?|sheikh|sk\.?|ভাই|আপু|bhai|apu|begum)/i;
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const line = lines[i];
        if (nameTitles.test(line)) {
            parsedData.customerName = line;
            assignedLines.add(i);
            break;
        }
    }
    // Fallback for name: A short line (2-5 words) that is mostly text, no special chars
    if (!parsedData.customerName) {
        for (let i = 0; i < lines.length; i++) {
            if (assignedLines.has(i)) continue;
            const line = lines[i];
            const wordCount = line.split(/\s+/).length;
            const hasNumbers = /\d/.test(line);
            const hasSpecialChars = /[,।#@\/\-\:]/.test(line);
            if (wordCount >= 1 && wordCount <= 5 && !hasNumbers && !hasSpecialChars && line.length < 40) {
                parsedData.customerName = line;
                assignedLines.add(i);
                break;
            }
        }
    }

    // --- PASS 6: Extract Product Name (Look for quantities or item descriptions) ---
    const productPatterns = /((\d+)\s*(x|×|পিস|pcs?|টি|piece|kg|gm|gram|inch|ইঞ্চি|সাইজ|size))|((x|×|পিস|pcs?|টি|piece|kg|gm|gram)\s*(\d+))/i;
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        if (productPatterns.test(lines[i])) {
            parsedData.productName = lines[i];
            assignedLines.add(i);
            break;
        }
    }

    // --- PASS 7: Assign remaining lines ---
    // Priority: Address (if not found) -> Product Name (if not found) -> Note
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const line = lines[i];

        if (!parsedData.customerAddress) {
            parsedData.customerAddress = line;
        } else if (!parsedData.productName) {
            parsedData.productName = line;
        } else {
            parsedData.note = (parsedData.note ? parsedData.note + ', ' : '') + line;
        }
        assignedLines.add(i);
    }

    return parsedData;
}

// --- EVENT LISTENERS ---
logoutBtn.addEventListener('click', async () => {
    await apiCall('logout');
    window.location.reload();
});

// Modals
function injectModalContent() {
    $('#store-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Store Management</h2><span class="close-btn">&times;</span></div><div class="store-management"><div class="add-store-container form-group"><h3>Add / Edit Store</h3><input type="hidden" id="editingStoreId"><select id="courierTypeSelector"><option value="steadfast">Steadfast</option><option value="pathao">Pathao</option><option value="redx">Redx</option></select><input type="text" id="storeName" placeholder="Store Name"><div id="steadfast-fields"><input type="password" id="newApiKey" placeholder="Steadfast API Key"><input type="password" id="newSecretKey" placeholder="Steadfast Secret Key"></div><div id="pathao-fields" style="display:none; flex-direction:column; gap:10px;"><input type="text" id="pathaoClientId" placeholder="Pathao Client ID"><input type="text" id="pathaoClientSecret" placeholder="Pathao Client Secret"><input type="text" id="pathaoUsername" placeholder="Pathao Username (Email)"><input type="password" id="pathaoPassword" placeholder="Pathao Password"><input type="number" id="pathaoStoreId" placeholder="Pathao Store ID"></div><div id="redx-fields" style="display:none;"><input type="text" id="redxToken" placeholder="Redx Token"></div><button id="addStoreBtn" style="margin-top:10px;">Add Store</button></div><div class="store-list-container"><h3>Your Saved Stores</h3><ul id="storeList"></ul></div></div><div id="store-message" class="message" style="display:none;"></div></div>`);
    $('#settings-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Local Parser Settings</h2><span class="close-btn">&times;</span></div><div id="parserSettings"><h4>Active Fields (Drag to reorder)</h4><ul id="parserFields"></ul><div id="availableFieldsWrapper"><h4>Available Fields</h4><div class="available-fields-container" id="availableFields"></div></div><div class="instructions-bn" style="margin-top:20px; font-size: 14px; line-height: 1.6;"><h4>How to use Parser Settings</h4><ul><li>Arrange the fields above by dragging them into the same order as your pasted text lines.</li><li>Check 'Required' if a line must exist for the parcel to be valid.</li><li>When pasting multiple parcels, separate each one with a blank line.</li></ul></div></div></div>`);
    // History modal structure is already in HTML, just needs dynamic content logic
    // Profile modal structure is in JS in Monolith, let's keep it here
    $('#profile-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Profile Settings</h2><span class="close-btn">&times;</span></div><div class="profile-form"><h3>Update Your Profile</h3><div class="form-group" style="gap:5px;"><label>Display Name</label><input type="text" id="updateNameInput" placeholder="Enter your name"><button id="updateNameBtn">Update Name</button></div><hr style="margin: 20px 0;"><div class="form-group" style="gap:5px;"><label>New Password</label><input type="password" id="updatePasswordInput" placeholder="Enter a new password"><button id="updatePasswordBtn">Update Password</button></div></div><div id="profile-message" class="message" style="display:none;"></div></div>`);

    $('.modal .close-btn').on('click', function () { $(this).closest('.modal').hide(); });
    // $('.modal').on('click', function (e) { if (e.target === this) $(this).hide(); });
}
injectModalContent();

openStoreModalBtn.addEventListener('click', () => $('#store-modal').show());
openSettingsModalBtn.addEventListener('click', () => $('#settings-modal').show());
openHistoryModalBtn.addEventListener('click', () => { $('#history-modal').show(); $('#parseHistoryTabBtn').trigger('click'); });
openProfileModalBtn.addEventListener('click', () => {
    $('#updateNameInput').val(currentUser.displayName || '');
    $('#updatePasswordInput').val('');
    $('#profile-modal').show();
});

// Store Actions
// Store Actions
$('#store-modal').on('click', '#addStoreBtn', async function () {
    const courierType = $('#courierTypeSelector').val();
    let credentials = {};

    if (courierType === 'pathao') {
        credentials = {
            clientId: $('#pathaoClientId').val(), clientSecret: $('#pathaoClientSecret').val(),
            username: $('#pathaoUsername').val(), password: $('#pathaoPassword').val(), storeId: $('#pathaoStoreId').val()
        };
    } else if (courierType === 'redx') {
        credentials = { token: $('#redxToken').val() };
    } else {
        credentials = { apiKey: $('#newApiKey').val(), secretKey: $('#newSecretKey').val() };
    }

    const payload = {
        editingId: $('#editingStoreId').val() || null,
        storeName: $('#storeName').val(),
        courierType: courierType,
        credentials: credentials
    };
    try {
        await apiCall('add_or_update_store', payload);
        const data = await apiCall('load_user_data');
        userCourierStores = data.stores; loadUserStores();
        $('#storeName, #newApiKey, #newSecretKey, #pathaoClientId, #pathaoClientSecret, #pathaoUsername, #pathaoPassword, #pathaoStoreId, #redxToken').val('');
        $('#editingStoreId').val(''); $(this).text('Add Store');
        showMessage(document.getElementById('store-message'), 'Store saved.', 'success');
    } catch (e) { showMessage(document.getElementById('store-message'), e.message, 'error'); }
}).on('click', '.edit-store-btn', function () {
    const store = userCourierStores[$(this).data('id')];
    if (!store) return;

    const id = $(this).data('id');
    const storeName = store.storeName || store.store_name || '';
    const courierType = store.courierType || store.courier_type || '';

    $('#editingStoreId').val(id);
    $('#storeName').val(storeName);
    $('#courierTypeSelector').val(courierType).trigger('change');

    if (courierType === 'pathao') {
        $('#pathaoClientId').val(store.clientId || store.client_id || '');
        $('#pathaoClientSecret').val(store.clientSecret || store.client_secret || '');
        $('#pathaoUsername').val(store.username || '');
        $('#pathaoPassword').val(store.password || '');
        $('#pathaoStoreId').val(store.storeId || store.store_id || '');
    } else if (courierType === 'redx') {
        $('#redxToken').val(store.token || '');
    } else {
        $('#newApiKey').val(store.apiKey || store.api_key || '');
        $('#newSecretKey').val(store.secretKey || store.secret_key || '');
    }
    $('#addStoreBtn').text('Update Store');
}).on('click', '.delete-store-btn', async function () {
    if (confirm('Are you sure?')) {
        await apiCall('delete_store', { id: $(this).data('id') });
        const data = await apiCall('load_user_data');
        userCourierStores = data.stores; loadUserStores();
    }
}).on('change', '#courierTypeSelector', function () {
    const type = $(this).val();
    $('#pathao-fields').toggle(type === 'pathao');
    $('#redx-fields').toggle(type === 'redx');
    $('#steadfast-fields').toggle(type === 'steadfast');
});

// Profile Actions
$('#profile-modal').on('click', '#updateNameBtn', async () => {
    const newName = $('#updateNameInput').val().trim();
    if (newName) {
        try {
            await apiCall('update_profile', { displayName: newName });
            userInfo.textContent = newName; currentUser.displayName = newName;
            showMessage(document.getElementById('profile-message'), 'Name updated!', 'success');
        } catch (e) { showMessage(document.getElementById('profile-message'), e.message, 'error'); }
    }
}).on('click', '#updatePasswordBtn', async () => {
    const newPassword = $('#updatePasswordInput').val();
    if (newPassword.length >= 6) {
        try {
            await apiCall('update_profile', { password: newPassword });
            $('#updatePasswordInput').val('');
            showMessage(document.getElementById('profile-message'), 'Password updated!', 'success');
        } catch (e) { showMessage(document.getElementById('profile-message'), e.message, 'error'); }
    } else { showMessage(document.getElementById('profile-message'), 'Password must be at least 6 characters.', 'error'); }
});

// History Actions
$('#history-modal').on('click', '.history-tabs button', function () {
    $(this).addClass('active').siblings().removeClass('active');
    const target = $(this).attr('id').replace('TabBtn', 'Content');
    $(`#${target}`).show().siblings('.history-content').hide();
    if (target === 'parseHistoryContent') loadHistory('parses', '#parseHistoryContent');
    else loadHistory('orders', '#orderHistoryContent');
});
async function loadHistory(type, container) {
    $(container).html('Loading...');
    try {
        const history = await apiCall('get_history', { type });
        if (!history || history.length === 0) { $(container).html("No history found."); return; }
        $(container).empty();
        history.forEach(item => {
            const date = new Date(item.timestamp.replace(' ', 'T') + '+06:00').toLocaleString('en-GB', { timeZone: 'Asia/Dhaka' });
            let title = '';
            if (type === 'parses') title = `Method: ${item.method} | ${safeParse(item.data).length || 0} items`;
            else title = `Store: ${userCourierStores[item.store_id]?.storeName || 'N/A'}`;

            // Escape single quotes not needed if using the new renderer which accepts raw JSON string
            // But we pass the whole object for orders, or data for parses
            let detailsBtn = '';

            if (type === 'parses') {
                try {
                    const parsed = JSON.parse(item.data);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        // Pass ID and Data to new renderer
                        detailsBtn = renderItemTableBtn(item.id, item.data);
                    } else {
                        detailsBtn = '<span style="color:#aaa;">No Items</span>';
                    }
                } catch (e) {
                    console.error("Parse Error for item " + item.id, e, item.data);
                    // DEBUG: Alert the error so user can tell us
                    detailsBtn = `<button class="btn-danger btn-sm" onclick="alert('JSON Error: ${e.message}')">Error</button>`;
                }
            } else {
                // For Orders, keeping old Detail logic or upgrading?
                // The user specifically asked for "parsed data should show in a table view".
                // Order requests are different. Let's keep orders as "Details" button for now but make it standard.
                const safeItemStr = JSON.stringify(item).replace(/'/g, "&apos;");
                detailsBtn = `<button class="details-btn" data-type="${type}" data-item='${safeItemStr}'>Details</button>`;
            }

            $(container).append(`<div class="history-item"><div><p>${date}</p><p><strong>${title}</strong></p></div>${detailsBtn}</div>`);
        });
    } catch (e) { $(container).html(`<p class="error">Could not load history.</p>`); }
}

// Helper needed for title length check above
const safeParse = (str) => { try { return JSON.parse(str); } catch (e) { return []; } };
$('#history-modal').on('click', '.details-btn', function () {
    try {
        const item = JSON.parse($(this).attr('data-item'));
        let content;
        const safeParse = (str) => { try { return JSON.parse(str); } catch (e) { return str; } };

        if ($(this).data('type') === 'parses') {
            content = safeParse(item.data);
        } else {
            content = {
                Request: safeParse(item.request_payload),
                Response: safeParse(item.api_response)
            };
        }
        $('#details-title').text('Details');
        $('#details-content').text(JSON.stringify(content, null, 2));
        $('#details-content').css({
            'white-space': 'pre-wrap',
            'word-break': 'break-all',
            'max-height': '70vh',
            'overflow-y': 'auto'
        });
        $('#details-modal').show();
    } catch (e) {
        console.error("Details Error:", e);
        alert("Could not load details.");
    }
});

// Check Risks and Remove Card
$('#parsedDataContainer').on('click', '.remove-btn', function () {
    $(this).closest('.parcel-card').remove();
    updateSummary();
    validateAllParcels();
}).on('click', '.check-risk-btn', function () {
    checkFraudRisk(this);
}).on('click', '.correct-address-btn', function () {
    correctSingleAddress(this);
});

checkAllRiskBtn.addEventListener('click', async () => {
    const allCheckButtons = parsedDataContainer.querySelectorAll('.check-risk-btn:not(:disabled)');
    if (allCheckButtons.length === 0) { alert('No parcels to check or all risks have been checked already.'); return; }

    // Disable button during progress
    checkAllRiskBtn.disabled = true;
    checkAllRiskBtn.textContent = 'Checking...';
    checkAllRiskBtn.style.opacity = '0.6';
    checkAllRiskBtn.style.cursor = 'not-allowed';

    try {
        for (const button of allCheckButtons) {
            checkFraudRisk(button);
            await new Promise(res => setTimeout(res, 500));
        }
    } finally {
        // Re-enable button after all checks complete
        checkAllRiskBtn.disabled = false;
        checkAllRiskBtn.textContent = 'Check All Risks';
        checkAllRiskBtn.style.opacity = '1';
        checkAllRiskBtn.style.cursor = 'pointer';
    }
});

// Parsing Buttons
document.getElementById('smartParseToggle').addEventListener('change', async function () {
    updateRawTextPlaceholder();
    await saveParserSettings();
});

parseLocallyBtn.addEventListener('click', async () => {
    const rawText = rawTextInput.value.trim();
    if (!rawText) return alert("Please paste parsable text."); // Simple alert since we don't have authMessage

    const parcelBlocks = rawText.split(/\n\s*\n/).filter(b => b.trim());
    if (parcelBlocks.length === 0) return alert("No valid parcels found.");

    parsedDataContainer.innerHTML = '';
    duplicatePhoneData = {}; // Reset on new parse
    let allParsedData = [];
    const useAutoParsing = document.getElementById('smartParseToggle').checked;

    parcelBlocks.forEach(block => {
        let parcelData;
        let isValid = true;
        if (useAutoParsing) {
            parcelData = identifyAndParseOrder(block);
            if (Object.values(parcelData).every(v => v === null)) isValid = false;
        } else {
            const lines = block.split('\n').map(l => l.trim());
            parcelData = {};
            currentParserFields.forEach((field, index) => {
                if (lines[index]) {
                    if (field.id === 'phone') parcelData[field.id] = normalizePhoneNumber(lines[index]);
                    else parcelData[field.id] = lines[index];
                } else if (field.required) isValid = false;
            });
        }
        if (isValid) {
            allParsedData.push(parcelData);
            createParcelCard(parcelData);
        }
    });
    updateSummary();
    apiCall('save_parse', { method: useAutoParsing ? 'Auto-Local' : 'Local-Settings', data: allParsedData });

    // Check for duplicates after rendering
    await checkAndMarkDuplicates();
});

parseWithAIBtn.addEventListener('click', async () => {
    const rawText = rawTextInput.value.trim();
    if (!rawText) return;

    // --- Client-Side Validation ---
    // Count blocks separated by empty lines (regex: /\n\s*\n/)
    // Filter out empty blocks AND blocks that are just separators (e.g. "=====")
    const blocks = rawText.split(/\n\s*\n/).filter(b => {
        const trimmed = b.trim();
        return trimmed.length > 0 && !/^=+$/.test(trimmed);
    });

    // Anti-Spam Check: Max 2000 chars per block
    const spamBlock = blocks.find(b => b.length > 2000);
    if (spamBlock) {
        alert("Spam Detected: One or more blocks contain excessively large text (>2000 characters). Please shorten or clean your input.");
        return;
    }

    if (blocks.length > aiBulkParseLimit) {
        alert(`Input too large! Your current plan allows max ${aiBulkParseLimit} parcels per request. You provided ~${blocks.length}.\nPlease upgrade your plan or split your input.`);
        return;
    }
    // -----------------------------

    // --- Estimated Wait Time Logic ---
    const estimatedSeconds = Math.max(5, Math.ceil(blocks.length * 1.5)); // Min 5s, ~1.5s per block
    let remainingSeconds = estimatedSeconds;

    $('.parsing-buttons button').prop('disabled', true);
    parsedDataContainer.innerHTML = '';
    duplicatePhoneData = {}; // Reset on new parse

    // Create Progress UI
    const progressHtml = `
        <div id="parsing-progress" style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; margin-top: 20px;">
            <div class="loader" style="display: inline-block; animation: spin 2s linear infinite;"></div>
            <h3 style="margin: 10px 0 5px; color: #333;">Analyzing ${blocks.length} Parcels with AI...</h3>
            <p style="color: #666; font-size: 14px;">Estimated time remaining: <strong id="countdown-timer" style="color: var(--primary-color);">${remainingSeconds}s</strong></p>
            <div style="width: 100%; background-color: #e9ecef; border-radius: 4px; height: 6px; margin-top: 10px; overflow:hidden;">
                <div id="countdown-bar" style="width: 0%; height: 100%; background-color: var(--primary-color); transition: width 1s linear;"></div>
            </div>
            <p style="font-size: 12px; color: #999; margin-top: 5px;">Please do not close this window.</p>
        </div>
    `;
    parsedDataContainer.innerHTML = progressHtml;

    // Start Timer
    const timerInterval = setInterval(() => {
        remainingSeconds--;
        if (remainingSeconds < 1) remainingSeconds = 1; // Stuck at 1s if taking longer
        const percentage = Math.min(100, ((estimatedSeconds - remainingSeconds) / estimatedSeconds) * 100);

        $('#countdown-timer').text(remainingSeconds + 's');
        $('#countdown-bar').css('width', percentage + '%');
    }, 1000);


    // ... existing timer code ...
    try {
        const results = await apiCall('parse_with_ai', { rawText });
        clearInterval(timerInterval); // Stop timer
        console.log("AI Parse Results:", results); // Debugging

        if (results && results.parses && results.parses.length > 0) {
            parsedDataContainer.innerHTML = ''; // Clear progress UI
            results.parses.forEach(p => createParcelCard(p));
            updateSummary();

            // Check for duplicates after rendering
            await checkAndMarkDuplicates();

            // Auto-Apply Custom Note if enabled
            if ($('#autoApplyNote').is(':checked')) {
                applyCustomNoteToAll(true); // silent mode
            }

            // Refresh Plan Usage Stats
            await renderPlanStatus();
        } else {
            showRetryModal('AI returned no data. Please try again.');
        }
    } catch (e) {
        // Show Retry Modal instead of Alert
        showRetryModal(e.message || "An error occurred during AI parsing. Please check your connection and try again.");
    } finally {
        clearInterval(timerInterval);
        loader.style.display = 'none';
        $('.parsing-buttons button').prop('disabled', false); // Ensure all buttons are re-enabled
    }
});

// --- Retry Modal Logic ---
let currentRawTextForRetry = ''; // Global var to store text for retry

function showRetryModal(msg) {
    const modal = document.getElementById('retry-modal');
    const msgElem = document.getElementById('retry-error-msg');

    // Store current input for retry reference
    currentRawTextForRetry = document.getElementById('rawText').value;

    if (msgElem) msgElem.innerText = msg;
    if (modal) {
        modal.style.display = 'block';
        setTimeout(() => modal.classList.add('show'), 10); // Check if css has transition
    }
}

// Bind Retry Modal Buttons (Run once on load)
$(document).ready(function () {
    $('#cancel-retry-btn').on('click', function () {
        $('#retry-modal').removeClass('show').hide();
        // Clear progress container to reset state
        document.getElementById('parsedDataContainer').innerHTML = '';
    });

    $('#confirm-retry-btn').on('click', function () {
        $('#retry-modal').removeClass('show').hide();
        // Trigger the parsing button again
        // Ensure rawText matches what we failed on, just in case user changed it (unlikely but safe)
        $('#rawText').val(currentRawTextForRetry);
        $('#parseWithAIBtn').click();
    });
});


// Check for duplicate phone numbers and mark parcel cards
async function checkAndMarkDuplicates() {
    const phones = [];
    const localCounts = {};

    $('.parcel-card').each(function () {
        const data = JSON.parse($(this).attr('data-order-data'));
        const p = data.recipient_phone;
        if (p && p !== 'N/A') {
            phones.push(p);
            localCounts[p] = (localCounts[p] || 0) + 1;
        }
    });

    if (phones.length === 0) return;

    duplicatePhoneData = {}; // Reset

    // 1. Check API (DB Duplicates)
    try {
        const dbDuplicates = await apiCall('check_duplicate_phones', { phones });
        duplicatePhoneData = { ...dbDuplicates };
    } catch (e) {
        console.error("Duplicate check failed:", e);
    }

    // 2. Check Local Duplicates
    let hasLocalDuplicates = false;
    for (const [phone, count] of Object.entries(localCounts)) {
        if (count > 1) {
            hasLocalDuplicates = true;
            if (!duplicatePhoneData[phone]) {
                duplicatePhoneData[phone] = {};
            }
            duplicatePhoneData[phone].is_local_duplicate = true;
            duplicatePhoneData[phone].local_count = count;
        }
    }

    // Re-render cards if duplicates found (DB or Local)
    if (Object.keys(duplicatePhoneData).length > 0) {
        const allData = [];
        $('.parcel-card').each(function () {
            allData.push(JSON.parse($(this).attr('data-order-data')));
        });
        parsedDataContainer.innerHTML = '';
        allData.forEach(p => createParcelCard(p));
        updateSummary();
    }
}

// Create Order
createOrderBtn.addEventListener('click', async () => {
    const storeId = storeSelector.value;
    if (!storeId || !userCourierStores[storeId]) return alert('Please select a valid store.');

    const orders = $('.parcel-card').map((i, el) => {
        const parcelData = JSON.parse($(el).data('orderData'));
        const cleanOrder = {
            customerName: parcelData.customerName || parcelData.recipient_name,
            phone: parcelData.phone || parcelData.customerPhone || parcelData.recipient_phone,
            address: parcelData.address || parcelData.customerAddress || parcelData.recipient_address,
            amount: parcelData.amount || parcelData.cod_amount,
            productName: parcelData.productName || parcelData.item_description,
            note: parcelData.note,
            orderId: parcelData.orderId || parcelData.order_id
        };
        Object.keys(cleanOrder).forEach(key => {
            if (cleanOrder[key] === null || typeof cleanOrder[key] === 'undefined') delete cleanOrder[key];
        });
        return cleanOrder;
    }).get();

    if (orders.length === 0) return alert('No parcels to create.');

    loader.style.display = 'block'; createOrderBtn.disabled = true;
    try {
        const responseData = await apiCall('create_order', { storeId, orders });
        displayApiResponse(responseData);
        await renderPlanStatus();
    } catch (error) {
        displayApiResponse({ error: error.message, status: 'error' });
    } finally {
        loader.style.display = 'none';
        createOrderBtn.disabled = false;
        updateCreateOrderButtonText();
    }
});

storeSelector.addEventListener('change', updateCreateOrderButtonText);

// Parser Settings Logic
async function saveParserSettings() {
    try {
        const toggle = document.getElementById('smartParseToggle');
        const settingsPayload = {
            fields: currentParserFields,
            smart_parsing: toggle ? toggle.checked : true,
            custom_note: {
                template: $('#customNoteTemplate').val(),
                auto_apply: $('#autoApplyNote').is(':checked')
            }
        };
        await apiCall('save_parser_settings', { settings: settingsPayload });
    } catch (error) { console.error("Failed to save parser settings:", error); }
}

function renderParserFields() {
    const parserFieldsContainer = document.getElementById('parserFields');
    const availableFieldsContainer = document.getElementById('availableFields');
    if (!parserFieldsContainer || !availableFieldsContainer) return;

    parserFieldsContainer.innerHTML = '';
    availableFieldsContainer.innerHTML = '';

    currentParserFields.forEach(field => {
        const li = document.createElement('li');
        li.dataset.id = field.id;
        li.draggable = true;
        li.innerHTML = `<span>${field.label}</span><div class="field-controls"><label><input type="checkbox" ${field.required ? 'checked' : ''}> Required</label><button class="delete-field-btn">&times;</button></div>`;
        li.querySelector('input[type="checkbox"]').addEventListener('change', (e) => {
            const fieldId = e.target.closest('li').dataset.id;
            const field = currentParserFields.find(f => f.id === fieldId);
            if (field) field.required = e.target.checked;
            saveParserSettings();
        });
        li.querySelector('.delete-field-btn').addEventListener('click', () => {
            currentParserFields = currentParserFields.filter(f => f.id !== field.id);
            saveParserSettings(); renderParserFields();
        });
        parserFieldsContainer.appendChild(li);
    });

    const activeFieldIds = new Set(currentParserFields.map(f => f.id));
    const availableFields = DEFAULT_PARSER_FIELDS.filter(df => !activeFieldIds.has(df.id));
    availableFields.forEach(field => {
        const tile = document.createElement('button');
        tile.className = 'available-field-tile';
        tile.textContent = field.label;
        tile.dataset.id = field.id;
        tile.addEventListener('click', () => {
            const fieldToAdd = DEFAULT_PARSER_FIELDS.find(f => f.id === field.id);
            if (fieldToAdd) {
                currentParserFields.push(fieldToAdd);
                saveParserSettings(); renderParserFields();
            }
        });
        availableFieldsContainer.appendChild(tile);
    });
    setupDragAndDrop();
    updateRawTextPlaceholder();
}

function updateRawTextPlaceholder() {
    const rawTextInput = document.getElementById('rawText');
    if (!rawTextInput) return;

    const useAutoParsing = document.getElementById('smartParseToggle').checked;
    let placeholderText = "";

    // Force Bengali Placeholder for ALL modes as per user request
    placeholderText = `AI পার্সিং দিয়ে যেকোনো এলোমেলো ডাটা এক ক্লিকেই সাজান এবং সরাসরি কুরিয়ারে এন্ট্রি দিন।

📌 উদাহরণ:

মেহনাজ 01301989309
৫৬ পিস দোয়া স্টিকার সেট  ৪৯০
দক্ষিণ খেজুরবাগ, সাত পাখি নাহার ভবনের গলি,
আব্দুল কাইয়ুম মাদ্রাসা, দক্ষিণ কেরানীগঞ্জ, ঢাকা–১৩১০`;
    placeholderText += "\n\n(একাধিক পার্সেল আলাদা করতে প্রতিটি পার্সেলের মাঝে **একটি ফাঁকা লাইন (খালি লাইন)** দিন।)";
    const planName = currentUser?.plan_name || 'Plan';
    placeholderText += `\n\n[${planName}] আপনার লিমিট: একসাথে সর্বোচ্চ ${aiBulkParseLimit || 30} টি পার্সেল।`;
    rawTextInput.placeholder = placeholderText;
}

function setupDragAndDrop() {
    const parserFieldsContainer = document.getElementById('parserFields');
    if (!parserFieldsContainer) return;
    const fields = parserFieldsContainer.querySelectorAll('li');
    fields.forEach(field => {
        field.addEventListener('dragstart', () => field.classList.add('dragging'));
        field.addEventListener('dragend', () => {
            field.classList.remove('dragging');
            const container = document.getElementById('parserFields');
            const newFields = Array.from(container.querySelectorAll('li')).map(li => {
                const fieldId = li.dataset.id;
                const isRequired = li.querySelector('input[type="checkbox"]').checked;
                const originalField = currentParserFields.find(f => f.id === fieldId) || DEFAULT_PARSER_FIELDS.find(f => f.id === fieldId);
                return { ...originalField, id: fieldId, required: isRequired };
            });
            currentParserFields = newFields;
            saveParserSettings();
        });
    });
    parserFieldsContainer.addEventListener('dragover', e => {
        e.preventDefault();
        const afterElement = getDragAfterElement(parserFieldsContainer, e.clientY);
        const dragging = document.querySelector('.dragging');
        if (dragging) {
            if (afterElement == null) parserFieldsContainer.appendChild(dragging);
            else parserFieldsContainer.insertBefore(dragging, afterElement);
        }
    });
}
function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

// Subscription History and Upgrades logic
openSubscriptionHistoryModalBtn.addEventListener('click', async () => {
    $('#subscription-history-modal').show();
    if ($.fn.DataTable.isDataTable('#subscription-history-table')) { $('#subscription-history-table').DataTable().ajax.reload(); return; }
    $('#subscription-history-table').DataTable({
        destroy: true, processing: true,
        ajax: (data, callback, settings) => {
            apiCall('get_my_subscriptions').then(res => callback({ data: res })).catch(err => { console.error(err); callback({ data: [] }); });
        },
        columns: [
            { title: "Date", data: "created_at", render: d => new Date(d.replace(' ', 'T') + '+06:00').toLocaleString('en-US', { timeZone: 'Asia/Dhaka' }) },
            { title: "Plan", data: "plan_name" },
            { title: "Amount", data: "amount_paid" },
            { title: "Payment Method", data: "payment_method_name" },
            {
                title: "Status", data: "status", render: function (data) {
                    let color = 'grey'; if (data === 'approved') color = 'green'; if (data === 'rejected') color = 'red';
                    return `<span style="color: ${color}; font-weight: bold; text-transform: capitalize;">${data}</span>`;
                }
            }
        ],
        order: [[0, 'asc']]
    });
    $('#subscription-history-modal .close-btn').on('click', function () { $('#subscription-history-modal').hide(); });
});

// Upgrade Logic (Simplified for brevity, assuming existing logic from monolith)
let selectedPlan = null; let availablePlans = []; let selectedMethod = null; let availableMethods = []; let selectedPlanId = null; let selectedMethodId = null;
function showUpgradeStep(step) { $('#upgrade-modal .upgrade-step').hide(); $(`#upgrade-step-${step}`).show(); }

openUpgradeModalBtn.addEventListener('click', async () => {
    $('#upgrade-modal').show(); showUpgradeStep(1); $('#upgrade-message').hide(); $('#sender-number, #transaction-id').val('');
    const $plansContainer = $('#plans-container').html('Loading plans...');
    try {
        availablePlans = await apiCall('get_available_plans');
        $plansContainer.empty();
        if (availablePlans.length === 0) { $plansContainer.html('<p>No plans available.</p>'); return; }
        availablePlans.forEach(plan => {
            const details = [];
            if (plan.validity_days > 0) details.push(`Validity: ${plan.validity_days} Days`);
            if (plan.order_limit_monthly > 0) details.push(`Orders: ${plan.order_limit_monthly}/mo`);
            if (plan.ai_parsing_limit > 0) details.push(`AI Parsing: ${plan.ai_parsing_limit}/mo`);
            if (plan.bulk_parse_limit > 0) details.push(`Bulk Limit: ${plan.bulk_parse_limit}/req`);

            const detailHtml = details.length > 0 ? `<div style="font-size: 0.85em; color: #555; margin-top: 5px; background: #eef; padding: 4px; border-radius: 4px;">${details.join(' &bull; ')}</div>` : '';

            $plansContainer.append(`<div class="plan-option" data-plan-id="${plan.id}">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h4 style="margin:0;">${plan.name}</h4>
                    <span style="font-weight:bold; color:var(--primary-color);">${plan.price} BDT</span>
                </div>
                <p style="margin: 5px 0; font-size: 0.9em;">${plan.description}</p>
                ${detailHtml}
            </div>`);
        });
    } catch (e) { $plansContainer.html(`<p class="error">Could not load plans.</p>`); }
});
$('#plans-container').on('click', '.plan-option', async function () {
    selectedPlanId = $(this).data('plan-id');
    selectedPlan = availablePlans.find(p => p.id == selectedPlanId);
    showUpgradeStep(2);
    const $paymentContainer = $('#payment-methods-container').html('Loading...');
    try {
        availableMethods = await apiCall('get_active_payment_methods');
        $paymentContainer.empty();
        if (availableMethods.length === 0) { $paymentContainer.html('<p>No methods available.</p>'); return; }
        availableMethods.forEach(method => $paymentContainer.append(`<div class="plan-option" data-method-id="${method.id}" data-instructions="${method.instructions}"><h4 style="margin:0;">${method.name}</h4><pre>${method.account_details}</pre></div>`));
    } catch (e) { $paymentContainer.html(`<p class="error">Could not load methods.</p>`); }
});
$('#payment-methods-container').on('click', '.plan-option', function () {
    selectedMethodId = $(this).data('method-id');
    selectedMethod = availableMethods.find(m => m.id == selectedMethodId);
    if (selectedPlan) { $('#summary-plan-name').text(selectedPlan.name); $('#summary-plan-price').text(selectedPlan.price); }
    $('#summary-payment-details').text(selectedMethod.account_details);
    $('#payment-instructions').html($(this).data('instructions').replace(/\n/g, '<br>') || 'Enter details.');
    showUpgradeStep(3);
});
$('#upgrade-modal').on('click', '.btn-back', function () { showUpgradeStep($(this).data('target-step')); });
$('#submit-payment-btn').on('click', async function () {
    const senderNumber = $('#sender-number').val().trim(); const transactionId = $('#transaction-id').val().trim();
    if (!selectedPlanId || !selectedMethodId || !senderNumber || !transactionId) return showMessage(document.getElementById('upgrade-message'), 'Please fill all fields.', 'error');
    const $button = $(this); const $loader = $button.find('.loader');
    $button.prop('disabled', true); $loader.show();
    try {
        const result = await apiCall('submit_purchase_request', { planId: selectedPlanId, methodId: selectedMethodId, senderNumber, transactionId });

        // Pixel Tracking
        try {
            if (typeof fbq !== 'undefined' && selectedPlan) {
                fbq('track', 'Purchase', {
                    value: selectedPlan.price,
                    currency: 'BDT',
                    content_name: selectedPlan.name,
                    content_type: 'product',
                    content_ids: [selectedPlan.id]
                }, { eventID: result.eventId }); // Deduplication
            }
        } catch (e) { console.error('Pixel Error:', e); }

        showMessage(document.getElementById('upgrade-message'), result.message, 'success', 8000);
        setTimeout(() => $('#upgrade-modal').hide(), 4000);
    } catch (e) { showMessage(document.getElementById('upgrade-message'), e.message, 'error'); }
    finally { $button.prop('disabled', false); $loader.hide(); }
});
// Custom Note Builder Logic & Functions
function applyCustomNoteToAll(silent = false) {
    const template = $('#customNoteTemplate').val();
    if (!template) {
        if (!silent) alert('Please enter a note template first.');
        return;
    }

    const $cards = $('.parcel-card');
    if ($cards.length === 0) {
        if (!silent) alert('No parsed parcels to update.');
        return;
    }

    let updatedCount = 0;

    $cards.each(function (index) {
        const $card = $(this);
        // data() pulls from cache, attr() pulls from DOM. Best to keep both in sync
        let rawAttr = $card.attr('data-order-data');
        let data = {};
        try { data = JSON.parse(rawAttr); } catch (e) { }

        // Re-construct note
        let note = template;

        // Use original_note if available, otherwise fallback to current note (first run)
        // This prevents recursive appending (e.g. "Gift Gift Gift")
        const baseNote = (typeof data.original_note !== 'undefined') ? data.original_note : (data.note || '');

        note = note.replace(/{order_id}/g, data.order_id || data.orderId || '');
        note = note.replace(/{name}/g, data.recipient_name || data.customerName || '');
        note = note.replace(/{phone}/g, data.recipient_phone || data.phone || '');
        note = note.replace(/{address}/g, data.recipient_address || data.address || '');
        note = note.replace(/{product}/g, data.item_description || data.productName || '');
        note = note.replace(/{note}/g, baseNote);

        note = note.trim();

        // Update Data Object
        data.note = note;
        // Ensure original_note is preserved/set
        if (typeof data.original_note === 'undefined') {
            data.original_note = baseNote;
        }

        // Update DOM attribute
        $card.attr('data-order-data', JSON.stringify(data));
        $card.data('orderData', JSON.stringify(data));

        // Update Input Field in Card
        $card.find('.input-note').val(note);

        updatedCount++;
    });

    if (!silent) alert(`Updated notes for ${updatedCount} parcels.`);
}

$(document).ready(function () {
    $('#customNoteVariable').on('change', function () {
        const val = $(this).val();
        if (val) {
            const $input = $('#customNoteTemplate');
            $input.val($input.val() + val);
            $(this).val(''); // Reset dropdown
            $(this).blur(); // Remove focus from dropdown
            $input.focus();
            saveParserSettings(); // Auto-save
        }
    });

    // Auto-save template text after typing stops
    let noteTypingTimer;
    $('#customNoteTemplate').on('input', function () {
        clearTimeout(noteTypingTimer);
        noteTypingTimer = setTimeout(saveParserSettings, 1000);
    });

    // Auto-save checkbox
    $('#autoApplyNote').on('change', function () {
        saveParserSettings();
    });

    $('#applyCustomNoteBtn').on('click', () => applyCustomNoteToAll(false));
});
// --- Shared Dynamic Table Renderer (User Dashboard) ---
window.parsedDataCache = {};

function renderItemTableBtn(id, jsonString) {
    if (!jsonString) return '-';
    try {
        window.parsedDataCache[id] = jsonString;
        return `<button class="btn-primary btn-sm btn-view-items" onclick="openItemTableFromCache(${id})">View Items</button>`;
    } catch (e) { return 'Error'; }
}

window.openItemTableFromCache = function (id) {
    try {
        const jsonString = window.parsedDataCache[id];
        if (!jsonString) return alert("Data lost. Please refresh.");

        const data = JSON.parse(jsonString);
        if (!Array.isArray(data) || data.length === 0) {
            alert("No item data found.");
            return;
        }

        // 1. Generate Columns
        const keys = Object.keys(data[0]);
        const columns = keys.map(k => {
            return {
                title: k.replace(/_/g, ' ').toUpperCase(),
                data: k,
                render: function (d) {
                    return (d === null || d === undefined) ? '' : String(d);
                }
            };
        });

        // 2. Init DataTable
        if ($.fn.DataTable.isDataTable('#item-details-table')) {
            $('#item-details-table').DataTable().destroy();
            $('#item-details-table').empty();
        }

        $('#item-details-table').DataTable({
            data: data,
            columns: columns,
            pageLength: 10,
            scrollX: true,
            autoWidth: false,
            destroy: true
        });

        // 3. Show Modal
        $('#item-details-modal').show();

    } catch (e) {
        console.error("Table Render Error", e);
        alert("Failed to render table: " + e.message);
    }
};
