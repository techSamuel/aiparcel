// --- GLOBAL STATE & CONSTANTS ---
let userCourierStores = {};
let geminiApiKey = null;
let isPremiumUser = false;
let currentUser = null;
let userPermissions = {};
let currentParserFields = [];
let helpContent = '';
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
        const session = await apiCall('check_session');
        if (session.loggedIn && session.user) {
            currentUser = session.user;
            isPremiumUser = session.user.plan_id > 1;
            await renderAppView();
        } else {
            // If PHP routing works, we shouldn't be here if not logged in, but reload just in case
            window.location.reload();
        }
    } catch (e) {
        console.error('Init Error:', e);
        window.location.reload();
    }
});

// --- CORE APP FUNCTIONS ---
async function renderAppView() {
    userInfo.textContent = currentUser.displayName || currentUser.email;

    const data = await apiCall('load_user_data');
    userCourierStores = data.stores || {};
    geminiApiKey = data.geminiApiKey;
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
        }
    }

    currentParserFields = fields;
    renderParserFields();

    const toggle = document.getElementById('smartParseToggle');
    if (toggle) {
        toggle.checked = smartParsingEnabled;
        updateRawTextPlaceholder();
    }
}

async function renderPlanStatus() {
    try {
        const status = await apiCall('get_subscription_data');
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
        planStatusView.innerHTML = `<h3>Current Plan: <strong>${status.plan_name}</strong></h3>${usageHTML}<p>Expires on: <strong>${status.plan_expiry_date ? new Date(status.plan_expiry_date).toLocaleDateString() : 'N/A'}</strong></p>`;
        planStatusView.style.display = 'block';
    } catch (e) {
        planStatusView.innerHTML = `<p class="error">${e.message}</p>`;
        planStatusView.style.display = 'block';
    }
}

function updateFeatureVisibilityBasedOnPlan() {
    const canParseAI = userPermissions.can_parse_ai && geminiApiKey;
    $('#parseWithAIBtn').toggle(canParseAI);
    $('#parseAndAutocompleteBtn').toggle(userPermissions.can_autocomplete);
    $('#checkAllRiskBtn').toggle(userPermissions.can_check_risk);
}

function loadUserStores() {
    $('#storeList, #storeSelector').empty();
    if (Object.keys(userCourierStores).length === 0) {
        $('#storeList').html('<li>No stores found.</li>');
        $('#storeSelector').html(`<option value="">Please add a store first</option>`);
        return;
    }
    for (const id in userCourierStores) {
        const store = userCourierStores[id];
        $('#storeList').append(`<li><span>${store.storeName} <span class="courier-badge ${store.courierType}">${store.courierType}</span></span><div class="store-actions"><button class="edit-store-btn" data-id="${id}">Edit</button><button class="delete-store-btn" data-id="${id}">&times;</button></div></li>`);
        $('#storeSelector').append(`<option value="${id}">${store.storeName}</option>`);
    }
    if (currentUser.lastSelectedStoreId && userCourierStores[currentUser.lastSelectedStoreId]) {
        storeSelector.value = currentUser.lastSelectedStoreId;
    }
    updateCreateOrderButtonText();
}

function updateCreateOrderButtonText() {
    const selectedStoreId = storeSelector.value;
    if (selectedStoreId && userCourierStores[selectedStoreId]) {
        const courierType = userCourierStores[selectedStoreId].courierType;
        createOrderBtn.textContent = `Create ${courierType.charAt(0).toUpperCase() + courierType.slice(1)} Order(s)`;
    } else {
        createOrderBtn.textContent = 'Create Order(s)';
    }
}

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
    const customerName = parcelData.customerName || 'N/A';
    const phone = parcelData.phone || parcelData.customerPhone || 'N/A';
    const address = parcelData.address || parcelData.customerAddress || 'N/A';
    const orderId = parcelData.orderId || 'N/A';
    const amount = parcelData.amount || 0;
    const productName = parcelData.productName || parcelData.item_description || 'N/A';
    const note = parcelData.note || 'N/A';

    const card = $(`<div class="parcel-card"></div>`).data('orderData', JSON.stringify(parcelData));
    const phoneForCheck = (phone || '').replace(/\s+/g, '');
    const isPhoneValid = /^01[3-9]\d{8}$/.test(phoneForCheck);

    const checkRiskDisabled = !isPhoneValid || !userPermissions.can_check_risk;
    const checkRiskTitle = !userPermissions.can_check_risk ? 'This is a premium feature.' : 'Check customer risk';
    const correctAddressDisabled = !userPermissions.can_correct_address;
    const correctAddressTitle = !userPermissions.can_correct_address ? 'This is a premium feature.' : 'Correct Address with AI';

    card.html(`
        <div class="details">
            <strong>${customerName}</strong> (${phone})<br>
            Address: <span class="address-text">${address}</span><br>
            OrderID: ${orderId} | COD: <strong>${amount} BDT</strong> | Item: ${productName}
        </div>
        <div class="parcel-actions">
            <button class="check-risk-btn" data-phone="${phoneForCheck}" ${checkRiskDisabled ? 'disabled' : ''} title="${checkRiskTitle}">Check Risk</button>
            <button class="correct-address-btn" ${correctAddressDisabled ? 'disabled' : ''} title="${correctAddressTitle}">Correct Address 🤖 AI</button>
            <button class="remove-btn">&times;</button>
        </div>
        <div class="fraud-results-container" style="display: none;"></div>
    `);
    parsedDataContainer.appendChild(card[0]);
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
            $card.data('orderData', JSON.stringify(parcelData));
            $addressTextSpan.text(result.corrected_address);
            $button.text('Corrected ✔️');
        } else { throw new Error("AI did not return a corrected address."); }
    } catch (error) {
        alert(`Error correcting address: ${error.message}`);
        $button.text('Correction Failed');
    } finally {
        setTimeout(() => { $button.prop('disabled', false).html('Correct Address 🤖'); }, 3000);
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

        const uniqueId = `details-${phoneNumber}-${Date.now()}`;
        const finalHTML = `
            <div style="padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                    <span style="font-weight: 600; font-size: 13px;">
                        Delivery Success Ratio: 
                        <strong style="color: ${ratioColor}; font-size: 15px;">${successRatio}%</strong>
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

    let lines = orderText.split('\n').map(line => line.trim()).filter(line => line.length > 0);
    if (lines.length === 0) return parsedData;

    const assignedLines = new Set();

    // --- PASS 1: Extract Phone Number (Highest Priority) ---
    // BD phone: 11 digits starting with 01[3-9], optionally prefixed with +880 or 880
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const originalLine = lines[i];
        // Step 1: Convert Bengali digits to English
        let normalized = '';
        for (const char of originalLine) {
            if (char >= '০' && char <= '৯') {
                normalized += String.fromCharCode(char.charCodeAt(0) - '০'.charCodeAt(0) + '0'.charCodeAt(0));
            } else {
                normalized += char;
            }
        }
        // Step 2: Remove spaces, dashes, and other separators
        normalized = normalized.replace(/[\s\-\(\)\.]/g, '');

        // Step 3: Check for BD phone patterns
        // After normalization, valid patterns are:
        // - 01XXXXXXXXX (11 digits)
        // - 8801XXXXXXXXX (13 digits)
        // - +8801XXXXXXXXX (14 chars)
        let phoneDigits = null;
        if (/^\+?880?0?1[3-9]\d{8}$/.test(normalized)) {
            // Extract last 10 digits and prepend 0
            const match = normalized.match(/1[3-9]\d{8}$/);
            if (match) {
                phoneDigits = '0' + match[0];
            }
        } else if (/^01[3-9]\d{8}$/.test(normalized)) {
            phoneDigits = normalized;
        }

        if (phoneDigits) {
            parsedData.customerPhone = phoneDigits;
            assignedLines.add(i);
            break;
        }
    }

    // --- PASS 2: Extract Order ID (BEFORE Amount) ---
    // Catch long digit strings (7+ digits that are NOT phones) and alphanumeric IDs
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const line = lines[i].trim();

        // Convert Bengali digits for checking
        let normalized = '';
        for (const char of line) {
            if (char >= '০' && char <= '৯') {
                normalized += String.fromCharCode(char.charCodeAt(0) - '০'.charCodeAt(0) + '0'.charCodeAt(0));
            } else {
                normalized += char;
            }
        }
        normalized = normalized.replace(/[\s\-]/g, '');

        // Skip if line has common address/name patterns
        if (/[,।]/.test(line) || /road|house|village|গ্রাম|রোড/i.test(line)) continue;

        // Pattern 1: Pure digit string with 7+ digits (but NOT a phone pattern)
        if (/^\d{7,}$/.test(normalized)) {
            // Exclude phone patterns
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
    // Max 6 digits, must have currency indicator OR be a standalone small number
    for (let i = 0; i < lines.length; i++) {
        if (assignedLines.has(i)) continue;
        const line = lines[i].trim();

        // Convert Bengali digits
        let normalized = '';
        for (const char of line) {
            if (char >= '০' && char <= '৯') {
                normalized += String.fromCharCode(char.charCodeAt(0) - '০'.charCodeAt(0) + '0'.charCodeAt(0));
            } else {
                normalized += char;
            }
        }

        // Match amount patterns
        const amountMatch = normalized.match(/^(BDT|৳|Tk\.?|Cash|টাকা|taka)?[\s]*([\d]{1,6})([\.,]\d{1,2})?[\s]*(BDT|৳|Tk|টাকা|taka|\/-)?$/i);
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
    $('#store-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Store Management</h2><span class="close-btn">&times;</span></div><div class="store-management"><div class="add-store-container form-group"><h3>Add / Edit Store</h3><input type="hidden" id="editingStoreId"><select id="courierTypeSelector"><option value="steadfast">Steadfast</option><option value="pathao">Pathao</option></select><input type="text" id="storeName" placeholder="Store Name"><div id="steadfast-fields"><input type="password" id="newApiKey" placeholder="Steadfast API Key"><input type="password" id="newSecretKey" placeholder="Steadfast Secret Key"></div><div id="pathao-fields" style="display:none; flex-direction:column; gap:10px;"><input type="text" id="pathaoClientId" placeholder="Pathao Client ID"><input type="text" id="pathaoClientSecret" placeholder="Pathao Client Secret"><input type="text" id="pathaoUsername" placeholder="Pathao Username (Email)"><input type="password" id="pathaoPassword" placeholder="Pathao Password"><input type="number" id="pathaoStoreId" placeholder="Pathao Store ID"></div><button id="addStoreBtn" style="margin-top:10px;">Add Store</button></div><div class="store-list-container"><h3>Your Saved Stores</h3><ul id="storeList"></ul></div></div><div id="store-message" class="message" style="display:none;"></div></div>`);
    $('#settings-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Local Parser Settings</h2><span class="close-btn">&times;</span></div><div id="parserSettings"><h4>Active Fields (Drag to reorder)</h4><ul id="parserFields"></ul><div id="availableFieldsWrapper"><h4>Available Fields</h4><div class="available-fields-container" id="availableFields"></div></div><div class="instructions-bn" style="margin-top:20px; font-size: 14px; line-height: 1.6;"><h4>How to use Parser Settings</h4><ul><li>Arrange the fields above by dragging them into the same order as your pasted text lines.</li><li>Check 'Required' if a line must exist for the parcel to be valid.</li><li>When pasting multiple parcels, separate each one with a blank line.</li></ul></div></div></div>`);
    // History modal structure is already in HTML, just needs dynamic content logic
    // Profile modal structure is in JS in Monolith, let's keep it here
    $('#profile-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Profile Settings</h2><span class="close-btn">&times;</span></div><div class="profile-form"><h3>Update Your Profile</h3><div class="form-group" style="gap:5px;"><label>Display Name</label><input type="text" id="updateNameInput" placeholder="Enter your name"><button id="updateNameBtn">Update Name</button></div><hr style="margin: 20px 0;"><div class="form-group" style="gap:5px;"><label>New Password</label><input type="password" id="updatePasswordInput" placeholder="Enter a new password"><button id="updatePasswordBtn">Update Password</button></div></div><div id="profile-message" class="message" style="display:none;"></div></div>`);

    $('.modal .close-btn').on('click', function () { $(this).closest('.modal').hide(); });
    $('.modal').on('click', function (e) { if (e.target === this) $(this).hide(); });
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
$('#store-modal').on('click', '#addStoreBtn', async function () {
    const payload = {
        editingId: $('#editingStoreId').val() || null,
        storeName: $('#storeName').val(),
        courierType: $('#courierTypeSelector').val(),
        credentials: $('#courierTypeSelector').val() === 'pathao' ? {
            clientId: $('#pathaoClientId').val(), clientSecret: $('#pathaoClientSecret').val(),
            username: $('#pathaoUsername').val(), password: $('#pathaoPassword').val(), storeId: $('#pathaoStoreId').val()
        } : { apiKey: $('#newApiKey').val(), secretKey: $('#newSecretKey').val() }
    };
    try {
        await apiCall('add_or_update_store', payload);
        const data = await apiCall('load_user_data');
        userCourierStores = data.stores; loadUserStores();
        $('#storeName, #newApiKey, #newSecretKey, #pathaoClientId, #pathaoClientSecret, #pathaoUsername, #pathaoPassword, #pathaoStoreId').val('');
        $('#editingStoreId').val(''); $(this).text('Add Store');
        showMessage(document.getElementById('store-message'), 'Store saved.', 'success');
    } catch (e) { showMessage(document.getElementById('store-message'), e.message, 'error'); }
}).on('click', '.edit-store-btn', function () {
    const store = userCourierStores[$(this).data('id')];
    $('#editingStoreId').val($(this).data('id'));
    $('#storeName').val(store.storeName);
    $('#courierTypeSelector').val(store.courierType).trigger('change');
    if (store.courierType === 'pathao') {
        $('#pathaoClientId').val(store.clientId); $('#pathaoClientSecret').val(store.clientSecret);
        $('#pathaoUsername').val(store.username); $('#pathaoPassword').val(store.password); $('#pathaoStoreId').val(store.storeId);
    } else {
        $('#newApiKey').val(store.apiKey); $('#newSecretKey').val(store.secretKey);
    }
    $('#addStoreBtn').text('Update Store');
}).on('click', '.delete-store-btn', async function () {
    if (confirm('Are you sure?')) {
        await apiCall('delete_store', { id: $(this).data('id') });
        const data = await apiCall('load_user_data');
        userCourierStores = data.stores; loadUserStores();
    }
}).on('change', '#courierTypeSelector', function () {
    $('#pathao-fields').toggle($(this).val() === 'pathao');
    $('#steadfast-fields').toggle($(this).val() !== 'pathao');
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
            const date = new Date(item.timestamp).toLocaleString();
            let title = '';
            if (type === 'parses') title = `Method: ${item.method} | ${JSON.parse(item.data).length} items`;
            else title = `Store: ${userCourierStores[item.store_id]?.storeName || 'N/A'}`;
            $(container).append(`<div class="history-item"><div><p>${date}</p><p><strong>${title}</strong></p></div><button class="details-btn" data-type="${type}" data-item='${JSON.stringify(item)}'>Details</button></div>`);
        });
    } catch (e) { $(container).html(`<p class="error">Could not load history.</p>`); }
}
$('#history-modal').on('click', '.details-btn', function () {
    const item = JSON.parse($(this).attr('data-item'));
    const content = $(this).data('type') === 'parses' ? JSON.parse(item.data) : { Request: JSON.parse(item.request_payload), Response: JSON.parse(item.api_response) };
    $('#details-title').text('Details');
    $('#details-content').text(JSON.stringify(content, null, 2));
    $('#details-modal').show();
});

// Check Risks and Remove Card
$('#parsedDataContainer').on('click', '.remove-btn', function () {
    $(this).closest('.parcel-card').remove();
    updateSummary();
}).on('click', '.check-risk-btn', function () {
    checkFraudRisk(this);
}).on('click', '.correct-address-btn', function () {
    correctSingleAddress(this);
});

checkAllRiskBtn.addEventListener('click', async () => {
    const allCheckButtons = parsedDataContainer.querySelectorAll('.check-risk-btn:not(:disabled)');
    if (allCheckButtons.length === 0) { alert('No parcels to check or all risks have been checked already.'); return; }
    for (const button of allCheckButtons) {
        checkFraudRisk(button);
        await new Promise(res => setTimeout(res, 500));
    }
});

// Parsing Buttons
document.getElementById('smartParseToggle').addEventListener('change', async function () {
    updateRawTextPlaceholder();
    await saveParserSettings();
});

parseLocallyBtn.addEventListener('click', () => {
    const rawText = rawTextInput.value.trim();
    if (!rawText) return alert("Please paste parsable text."); // Simple alert since we don't have authMessage

    const parcelBlocks = rawText.split(/\n\s*\n/).filter(b => b.trim());
    if (parcelBlocks.length === 0) return alert("No valid parcels found.");

    parsedDataContainer.innerHTML = '';
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
});

parseWithAIBtn.addEventListener('click', async () => {
    const rawText = rawTextInput.value.trim();
    if (!rawText) return;
    loader.style.display = 'block';
    $('.parsing-buttons button').prop('disabled', true);
    parsedDataContainer.innerHTML = '';
    try {
        const results = await apiCall('parse_with_ai', { rawText });
        if (results && results.parses && results.parses.length > 0) {
            results.parses.forEach(p => createParcelCard(p)); // AI returns updated keys, createParcelCard handles them
            updateSummary();
        } else {
            alert('AI could not parse the text.');
        }
    } catch (e) { alert(e.message); }
    finally {
        loader.style.display = 'none';
        $('.parsing-buttons button').prop('disabled', false);
    }
});

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
    }
});

storeSelector.addEventListener('change', updateCreateOrderButtonText);

// Parser Settings Logic
async function saveParserSettings() {
    try {
        const toggle = document.getElementById('smartParseToggle');
        const settingsPayload = {
            fields: currentParserFields,
            smart_parsing: toggle ? toggle.checked : true
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

    if (useAutoParsing) {
        placeholderText = [
            "Smart Auto-Parsing চালু আছে। তুমি যেকোনো ক্রমে ফিল্ড পেস্ট করতে পারো।",
            "Example:",
            "Customer Name",
            "01xxxxxxxxx",
            "Product Name",
            "500",
            "Full Address",
            "Note (Optional)"
        ].join('\n');
    } else {
        let placeholderLines = currentParserFields.map(field => {
            let line = field.label;
            if (!field.required) line += " (Optional)";
            return line;
        });
        placeholderText = placeholderLines.join('\n');
        placeholderText += "\n\n(Parser Settings চালু আছে। ফিল্ডগুলো অবশ্যই এই নির্দিষ্ট ক্রমে থাকতে হবে।)";
    }
    placeholderText += "\n\n(একাধিক পার্সেল আলাদা করতে প্রতিটি পার্সেলের মাঝে **একটি ফাঁকা লাইন (খালি লাইন)** দিন।)";
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
            { title: "Date", data: "created_at", render: d => new Date(d).toLocaleString() },
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
        order: [[0, 'desc']]
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
            $plansContainer.append(`<div class="plan-option" data-plan-id="${plan.id}"><h4>${plan.name} - ${plan.price} BDT</h4><p>${plan.description}</p></div>`);
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
        showMessage(document.getElementById('upgrade-message'), result.message, 'success', 8000);
        setTimeout(() => $('#upgrade-modal').hide(), 4000);
    } catch (e) { showMessage(document.getElementById('upgrade-message'), e.message, 'error'); }
    finally { $button.prop('disabled', false); $loader.hide(); }
});
