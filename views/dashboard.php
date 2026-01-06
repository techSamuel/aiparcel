<?php
// Ensure this file is only accessed within the application
if (!defined('APP_URL')) {
    exit('Direct access not allowed');
}
?>
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
        <div class="toggle-switch-container">
            <label for="smartParseToggle">Enable Smart Auto-Parsing:</label>
            <label class="toggle-switch">
                <input type="checkbox" id="smartParseToggle" checked>
                <span class="slider"></span>
            </label>
        </div>
        <div style="margin-top:15px;">
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
<div id="history-modal" class="modal"></div>
<div id="profile-modal" class="modal"></div>
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
    <div class="chat-widget-container">
    <a href="https://wa.me/8801886626868" class="chat-btn whatsapp-btn" target="_blank" title="Chat on WhatsApp">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M16.001 2C8.27 2 2 8.27 2 16.001s6.27 14.001 14.001 14.001 14.001-6.27 14.001-14.001S23.732 2 16.001 2zm6.602 20.29c-.313.913-1.468 1.63-2.138 1.693-.521.05-1.139.063-3.235-.742-2.387-.913-4.223-2.67-5.89-4.708-1.782-2.178-3.004-4.83-3.045-4.947-.042-.117-.92-1.229-.92-2.234 0-.962.519-1.475.694-1.67.175-.194.389-.25.563-.25.175 0 .35.013.5.025.263.025.426.038.65.413.262.45.875 2.112.95 2.274.075.163.15.35.038.563-.112.213-.175.325-.312.475-.138.15-.275.313-.388.425-.112.113-.237.25-.112.488.125.237.563.95 1.125 1.575.763.85 1.626 1.488 2.59 1.963.775.375 1.2.412 1.525.35.325-.063.875-.413 1.113-.7.237-.288.45-.6.712-.8.3-.213.563-.113.888.062.325.175 2.113 1 2.475 1.175.363.175.613.263.7.4.088.137.025.824-.288 1.737z" />
        </svg>
    </a>
    <a href="https://m.me/quantumtechsoft" class="chat-btn messenger-btn" target="_blank" title="Chat on Messenger">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path d="M16 2.001c-7.72 0-14 5.46-14 12.19C2 20.3 6.02 24.8 11.23 26.83c.3.11.57.17.84.17.6 0 1.1-.3 1.38-.8l.4-1.2A11.9 11.9 0 0 1 16 23.4c5.52 0 10-3.9 10-8.62s-4.48-8.78-10-8.78zm.88 12.86l-2.45 2.1-5.5-4.8 10.6-4.2c.4-.16.7.3.4.6l-3.05 6.3zM21.1 19.3s.4.5.1.8c-.3.3-.9.3-1.2.1l-3.2-2.1-2.1 1.8c-.4.4-1.1.4-1.4.1-.3-.3-.2-.9.1-1.2l6.1-5.3c.4-.3 1 .1 1 .6l-2.6 5.3 2.1 1.5.1-.1z" />
        </svg>
    </a>
</div>
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

<div class="chat-widget-container">
    <a href="https://wa.me/8801886626868" class="chat-btn whatsapp-btn" target="_blank" title="Chat on WhatsApp">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M16.001 2C8.27 2 2 8.27 2 16.001s6.27 14.001 14.001 14.001 14.001-6.27 14.001-14.001S23.732 2 16.001 2zm6.602 20.29c-.313.913-1.468 1.63-2.138 1.693-.521.05-1.139.063-3.235-.742-2.387-.913-4.223-2.67-5.89-4.708-1.782-2.178-3.004-4.83-3.045-4.947-.042-.117-.92-1.229-.92-2.234 0-.962.519-1.475.694-1.67.175-.194.389-.25.563-.25.175 0 .35.013.5.025.263.025.426.038.65.413.262.45.875 2.112.95 2.274.075.163.15.35.038.563-.112.213-.175.325-.312.475-.138.15-.275.313-.388.425-.112.113-.237.25-.112.488.125.237.563.95 1.125 1.575.763.85 1.626 1.488 2.59 1.963.775.375 1.2.412 1.525.35.325-.063.875-.413 1.113-.7.237-.288.45-.6.712-.8.3-.213.563-.113.888.062.325.175 2.113 1 2.475 1.175.363.175.613.263.7.4.088.137.025.824-.288 1.737z" />
        </svg>
    </a>
    <a href="https://m.me/quantumtechsoft" class="chat-btn messenger-btn" target="_blank" title="Chat on Messenger">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M16 2.001c-7.72 0-14 5.46-14 12.19C2 20.3 6.02 24.8 11.23 26.83c.3.11.57.17.84.17.6 0 1.1-.3 1.38-.8l.4-1.2A11.9 11.9 0 0 1 16 23.4c5.52 0 10-3.9 10-8.62s-4.48-8.78-10-8.78zm.88 12.86l-2.45 2.1-5.5-4.8 10.6-4.2c.4-.16.7.3.4.6l-3.05 6.3zM21.1 19.3s.4.5.1.8c-.3.3-.9.3-1.2.1l-3.2-2.1-2.1 1.8c-.4.4-1.1.4-1.4.1-.3-.3-.2-.9.1-1.2l6.1-5.3c.4-.3 1 .1 1 .6l-2.6 5.3 2.1 1.5.1-.1z" />
        </svg>
    </a>
</div>

<script src="assets/js/utils.js"></script>
<script src="assets/js/dashboard.js"></script>