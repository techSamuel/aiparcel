<?php
// Ensure this file is only accessed within the application
if (!defined('APP_URL')) {
    exit('Direct access not allowed');
}
?>
<style>
    /* --- Critical Validation Styles --- */
    .parcel-card.invalid-parcel {
        border: 2px solid #dc3545 !important;
        background-color: #fff5f5 !important;
        box-shadow: 0 0 5px rgba(220, 53, 69, 0.3);
    }

    .parcel-card.invalid-parcel::before {
        content: '⚠️ Missing Mandatory Fields';
        display: block;
        color: #dc3545;
        font-weight: bold;
        font-size: 12px;
        margin-bottom: 5px;
    }

    .courier-badge.redx {
        background-color: #ff3333;
        /* Redx Color */
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
        margin-left: 5px;
        text-transform: capitalize;
    }

    .courier-badge.steadfast {
        background-color: #1abc9c;
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
        margin-left: 5px;
        text-transform: capitalize;
    }

    .courier-badge.pathao {
        background-color: #e74c3c;
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
        margin-left: 5px;
        text-transform: capitalize;
    }
</style>
<div id="app-view"
    style="display: block; width: 100%; max-width: 900px; margin: 0 auto; padding: 20px; background: var(--white); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
    <header class="app-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <img id="dashboardLogo" src="<?php echo $appLogoUrl; ?>" alt="Logo"
                style="<?php echo $appLogoUrl ? 'display: block;' : 'display: none;'; ?> max-height: 50px;">
            <h1 id="dashboardTitle" style="margin:0;">Welcome, <span id="userInfo">User</span></h1>
        </div>
        <button id="logoutBtn">Logout</button>
    </header>
    <nav class="app-nav">
        <button id="openProfileModalBtn">Profile Settings</button>
        <button id="openStoreModalBtn">Manage Stores</button>
        <button id="openSettingsModalBtn">Parser Settings</button>
        <button id="openHistoryModalBtn">History</button>
        <button id="openUpgradeModalBtn"
            style="background-color: var(--success-color); color: white; border-color: var(--success-color);">Upgrade
            Plan</button>
        <button id="openSubscriptionHistoryModalBtn">Subscription History</button>
        <button id="openHelpModalBtn">Help & Guide</button>
    </nav>

    <div class="plan-status" id="plan-status-view" style="display:none;"></div>

    <div class="section">
        <h2>Create New Parcel(s)</h2>
        <div>
            <label for="storeSelector">Select Store for this Batch</label>
            <select id="storeSelector">
                <option>Please add a store first</option>
            </select>
        </div>
        <div class="toggle-switch-container" style="display:none;">
            <label for="smartParseToggle">Enable Smart Auto-Parsing:</label>
            <label class="toggle-switch">
                <input type="checkbox" id="smartParseToggle" checked>
                <span class="slider"></span>
            </label>
        </div>
        <div id="parsing-input-area" style="margin-top:15px; display:none;">
            <label for="rawText">Paste All Parcel Info Here</label>
            <textarea id="rawText" rows="12" placeholder="Paste single or multiple parcel details here..."></textarea>
            <div class="parsing-buttons">
                <button id="parseWithAIBtn">Parse with AI </button>
                <button id="parseLocallyBtn">Parse Locally</button>
                <button id="parseAndAutocompleteBtn">Parse & Autocomplete</button>
                <button id="checkAllRiskBtn" style="background-color: #e67e22;">Check All Risks</button>
            </div>
        </div>
        <label style="margin-top: 15px;">Parsing Summary</label>
        <div class="summary-results">
            <div class="summary-item">Parcels Parsed: <span id="parcelCount">0</span></div>
            <div class="summary-item">Total COD: <span id="totalCod">0 BDT</span></div>
        </div>
        <label style="margin-top: 15px;">Review Parsed Parcels</label>

        <!-- Custom Note Builder (New Feature) -->
        <div class="custom-note-builder"
            style="background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; margin-bottom: 15px; border-radius: 6px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <strong style="font-size: 14px; white-space: nowrap;">Bulk Note:</strong>
                <input type="text" id="customNoteTemplate"
                    placeholder="Type text or select variable (e.g. ID: {order_id})" style="flex: 2; min-width: 200px;">
                <select id="customNoteVariable" style="flex: 1; min-width: 150px;">
                    <option value="">+ Insert Variable</option>
                    <option value="{order_id}">Order ID</option>
                    <option value="{name}">Recipient Name</option>
                    <option value="{phone}">Phone Number</option>
                    <option value="{address}">Address</option>
                    <option value="{product}">Product Details</option>
                    <option value="{note}">Existing Note</option>
                </select>
                <button id="applyCustomNoteBtn" class="btn-sm btn-primary">Apply to All</button>
            </div>
            <div
                style="font-size: 11px; color: #666; margin-top: 4px; display: flex; align-items: center; justify-content: space-between;">
                <span>Use variables to dynamically insert data. Appends/Replaces note.</span>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 5px;">
                    <input type="checkbox" id="autoApplyNote"> Auto-apply to new parcels
                </label>
            </div>
        </div>

        <div id="parsedDataContainer"></div>
        <button id="createOrderBtn">Create Order(s)</button>
        <div class="loader" id="loader"></div>
        <label style="margin-top: 15px;">API Response</label>
        <pre id="apiResponse"
            style="background: var(--light-gray); padding: 10px; border-radius: 6px; min-height: 50px; white-space: pre-wrap; word-break: break-all;">API response will appear here.</pre>
    </div>
</div>

<!-- Modals -->
<div id="store-modal" class="modal"></div>
<div id="settings-modal" class="modal"></div>
<div id="profile-modal" class="modal"></div>

<!-- History Modal (Content restored) -->
<div id="history-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Activity History</h2>
            <span class="close-btn">&times;</span>
        </div>
        <div class="history-tabs" style="margin-bottom: 20px; display: flex; gap: 10px;">
            <button id="parseHistoryTabBtn" class="active" style="flex:1; padding:10px; cursor:pointer;">Past
                Parses</button>
            <button id="orderHistoryTabBtn" style="flex:1; padding:10px; cursor:pointer;">Order Requests</button>
        </div>
        <div id="parseHistoryContent" class="history-content"></div>
        <div id="orderHistoryContent" class="history-content" style="display:none;"></div>
    </div>
</div>

<div id="details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="details-title">Details</h2><span class="close-btn">&times;</span>
        </div>
        <pre id="details-content"></pre>
    </div>
</div>

<div id="upgrade-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="upgrade-modal-title">Upgrade Your Plan</h2>
            <span class="close-btn">&times;</span>
        </div>

        <div id="upgrade-step-1" class="upgrade-step">
            <h4>1. Select a Plan</h4>
            <div id="plans-container" class="plans-container">Loading plans...</div>
        </div>

        <div id="upgrade-step-2" class="upgrade-step">
            <h4>2. Select Payment Method</h4>
            <div id="payment-methods-container" class="plans-container">Loading...</div>
            <button class="btn-back" data-target-step="1">&larr; Back to Plans</button>
        </div>

        <div id="upgrade-step-3" class="upgrade-step">
            <h4>3. Enter Payment Details</h4>
            <div id="payment-summary"
                style="margin-bottom: 15px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
                <h5 style="margin-top: 0; margin-bottom: 10px; font-size: 16px; color: #333;">Order Summary</h5>
                <div style="font-size: 14px; line-height: 1.6;">
                    <p style="margin: 0;"><strong>Plan:</strong> <span id="summary-plan-name"></span></p>
                    <p style="margin: 0;"><strong>Amount to Pay:</strong> <span id="summary-plan-price"></span>
                        BDT
                    </p>
                    <p style="margin: 5px 0 0 0;"><strong>Pay to Number:</strong></p>
                    <pre id="summary-payment-details"
                        style="background-color: #fff; padding: 10px; border-radius: 4px; border: 1px solid #ced4da; margin-top: 5px; white-space: pre-wrap;"></pre>
                </div>
            </div>
            <p id="payment-instructions"></p>
            <div class="form-group" style="gap: 5px; margin-top: 10px;">
                <label for="sender-number">Your Sender Number</label>
                <input type="text" id="sender-number" placeholder="The number you paid from">
            </div>
            <div class="form-group" style="gap: 5px;">
                <label for="transaction-id">Transaction ID (TrxID)</label>
                <input type="text" id="transaction-id" placeholder="Payment Transaction ID">
            </div>
            <button id="submit-payment-btn"
                style="width: 100%; padding: 12px; font-size: 16px; background-color: var(--success-color); color: white; border: none; cursor: pointer; border-radius: 6px;">
                Submit for Verification <span class="loader"></span>
            </button>
            <button class="btn-back" data-target-step="2">&larr; Back to Payment Methods</button>
        </div>

        <div id="upgrade-message" class="message" style="display:none;"></div>
    </div>
</div>

<div id="help-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>How to Use This Site</h2>
            <span class="close-btn">&times;</span>
        </div>
        <div id="help-content-container" style="max-height: 70vh; overflow-y: auto; padding-right: 15px;">
        </div>
    </div>
</div>

<div id="subscription-history-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>My Subscription History</h2>
            <span class="close-btn">&times;</span>
        </div>
        <div class="table-container">
            <table id="subscription-history-table" class="display" style="width:100%"></table>
        </div>
    </div>
</div>

<!-- Edit Parcel Modal -->
<div id="edit-parcel-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Parcel Details</h2>
            <span class="close-btn">&times;</span>
        </div>
        <div class="form-group">
            <label>Customer Name</label>
            <input type="text" id="edit-name">
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" id="edit-phone">
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea id="edit-address" rows="3"></textarea>
        </div>
        <div class="form-group">
            <label>COD Amount (BDT)</label>
            <input type="number" id="edit-amount">
        </div>
        <div class="form-group">
            <label>Product Name</label>
            <input type="text" id="edit-product">
        </div>
        <div class="form-group">
            <label>Note</label>
            <input type="text" id="edit-note">
        </div>
        <button id="save-parcel-btn" class="primary-btn" style="width:100%; margin-top:15px;">Save Changes</button>
    </div>
</div>



<script src="assets/js/utils.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/dashboard.js?v=<?php echo time(); ?>"></script>