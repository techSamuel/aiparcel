<?php include 'includes/admin_header.php'; ?>
<div class="card settings-form">
    <h2>Application Settings</h2>
    <form id="settings-form">
        <div class="form-group"><label for="appName">Application Name</label><input type="text" id="appName"></div>
        <div class="form-group">
            <label for="appLogoFile">Application Logo</label>
            <img id="logoPreview" src="" alt="Logo Preview"
                style="max-height: 100px; margin-bottom: 10px; border: 1px solid var(--border-color); padding: 5px; border-radius: 5px; display: block;">
            <input type="file" id="appLogoFile" accept="image/*">
        </div>
        <hr style="margin: 20px 0;">
        <div class="form-group"><label for="geminiApiKey">Gemini API Key</label><textarea type="password"
                id="geminiApiKey"></textarea></div>
        <div class="form-group"><label for="aiBulkParseLimit">AI Bulk Parse Limit</label><input type="number"
                id="aiBulkParseLimit" placeholder="50" min="1" max="500">
            <small style="color: #666; font-size: 0.85em; display:block; margin-top:5px;">Maximum number of
                customers/parcels allowed in a single "Parse with AI" request.</small>
        </div>
        <div class="form-group"><label for="barikoiApiKey">Barikoi API Key</label><textarea type="password"
                id="barikoiApiKey"></textarea></div>
        <div class="form-group"><label for="googleMapsApiKey">Google Maps API Key</label><input type="password"
                id="googleMapsApiKey"></div>
        <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
            <label>Google OAuth Configuration (for Login)</label>
            <div class="form-group"><label for="googleClientId">Google Client ID</label><input type="text"
                    id="googleClientId"></div>
            <div class="form-group"><label for="googleClientSecret">Google Client Secret</label><input type="password"
                    id="googleClientSecret"></div>
        </div>
        <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
            <label for="ezoicPlaceholderId">Ezoic Placeholder ID</label>
            <input type="text" id="ezoicPlaceholderId" placeholder="e.g., 101">
        </div>
        <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
            <label for="autocompleteService">Address Autocomplete Service</label>
            <select id="autocompleteService">
                <option value="barikoi">Barikoi (Recommended for Bangladesh)</option>
                <option value="google">Google Maps</option>
            </select>
        </div>
        <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
            <label>Button Visibility</label>
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                <label style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                        type="checkbox" id="showAiParseButton"> Show "Parse with AI" Button</label>
                <label style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                        type="checkbox" id="showAutocompleteButton"> Show "Parse & Autocomplete" Button</label>
            </div>
        </div>
        <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
            <label>Social Media Integration</label>
            <div style="margin-bottom: 15px;">
                <label style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                        type="checkbox" id="enableSocialPlugins"> Enable Social Chat Plugins (FB & WhatsApp)</label>
            </div>
            <div class="form-group"><label for="facebookPageId">Facebook Page ID (For Messenger Chat)</label><input
                    type="text" id="facebookPageId" placeholder="e.g. 104033333333333">
                <small>Find your Page ID in Page Settings > About.</small>
                <div style="margin-top:5px;">
                    <label
                        style="cursor:pointer; font-weight: normal; font-size: 13px; display:flex; align-items:center; gap: 8px;">
                        <input type="checkbox" id="useSimpleFbBtn"> Force Simple Link (Use this if Plugin fails to
                        load)
                    </label>
                </div>
            </div>
            <div class="form-group"><label for="whatsappNumber">WhatsApp Number (For Floating Button)</label><input
                    type="text" id="whatsappNumber" placeholder="e.g. +8801700000000"></div>

            <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                <label>SEO Settings (Open Graph)</label>
                <div class="form-group"><label for="seoTitle">Default Meta Title</label><input type="text" id="seoTitle"
                        placeholder="e.g. AiParcel - Automate Your Deliveries"></div>
                <div class="form-group"><label for="seoDescription">Default Meta Description</label><textarea
                        id="seoDescription"
                        placeholder="e.g. The best tool for managing RedX, Pathao, and Steadfast parcels..."></textarea>
                </div>
                <div class="form-group">
                    <label for="seoImageFile">Default OG Image (Upload/URL)</label>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="file" id="seoImageFile" accept="image/*" style="flex:1;">
                        <div style="font-size:12px; color:#666;">OR</div>
                        <input type="text" id="seoImageUrl" placeholder="https://example.com/banner.jpg"
                            style="flex:1;">
                    </div>
                    <div style="margin-top:10px;">
                        <img id="seoImagePreview" src="" alt="Preview"
                            style="max-width: 200px; display:none; border-radius:8px; border: 1px solid #ddd;">
                    </div>
                </div>
            </div>

            <div class="form-group" style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                <label for="helpContent">Help Modal Content (HTML/CSS Allowed)</label>
                <textarea id="helpContent" rows="15"
                    placeholder="Enter the HTML and CSS for your help guide here..."></textarea>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 10px;">Save Settings</button>
    </form>
</div>
<div class="card settings-form" style="margin-top: 20px;">
    <h2>System Testing & Maintenance</h2>

    <div style="margin-bottom: 20px;">
        <h3>Run Daily Maintenance (Cron)</h3>
        <p style="color: #666; font-size: 0.9em; margin-bottom: 10px;">Manually trigger the daily cron job. This will
            check for expired plans (demote users), send expiration warning emails, and <strong>send 75%/90% usage
                warnings</strong> for Order/AI limits.</p>
        <button type="button" id="btnRunCron" class="btn-primary" style="background-color: #6c757d;">Run Daily
            Maintenance Now</button>
        <div id="cronOutput"
            style="margin-top: 10px; display: none; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; font-size: 0.9em;">
        </div>
    </div>

    <hr style="margin: 20px 0;">

    <div>
        <h3>Send Test Email</h3>
        <p style="color: #666; font-size: 0.9em; margin-bottom: 10px;">Send a test email to verify your SMTP
            configuration.</p>
        <div style="display: flex; gap: 10px; max-width: 500px;">
            <input type="email" id="testEmailInput" placeholder="Enter recipient email"
                style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <button type="button" id="btnSendTestEmail" class="btn-primary">Send Test Email</button>
        </div>
    </div>

    <hr style="margin: 20px 0;">

    <div style="padding-bottom: 40px;">
        <h3>Cron Job Setup Instructions</h3>
        <p style="color: #666; font-size: 0.9em; margin-bottom: 10px;">To automate daily maintenance (expiry checks,
            alerts), set up a Cron Job in your hosting panel.</p>

        <div style="margin-bottom:15px;">
            <label style="font-weight:600; font-size:14px; display:block; margin-bottom:5px;">Cron URL:</label>
            <div
                style="background: #f8f9fa; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; word-break: break-all; user-select: all;">
                <?php echo defined('APP_URL') ? APP_URL : 'https://courier.aiparcel.site'; ?>/api/cron.php
            </div>
        </div>

        <div>
            <label style="font-weight:600; font-size:14px; display:block; margin-bottom:5px;">Example Command (cPanel /
                Hostinger):</label>
            <div
                style="background: #2d2d2d; color: #00ff00; padding: 10px; border-radius: 4px; font-family: monospace; word-break: break-all; user-select: all;">
                curl -s "<?php echo defined('APP_URL') ? APP_URL : 'https://courier.aiparcel.site'; ?>/api/cron.php"
                >/dev/null 2>&1
            </div>
            <p style="color:#666; font-size: 0.85em; margin-top:5px;">Set this to run <strong>Once Per Day (e.g., at
                    00:00)</strong>.</p>
        </div>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    $(document).ready(function () {
        async function loadSettings() {
            try {
                const result = await apiCall('get_settings');
                $('#appName').val(result.appName || '');
                $('#logoPreview').attr('src', result.appLogoUrl ? `../${result.appLogoUrl}` : '').toggle(!!result.appLogoUrl);
                $('#geminiApiKey').val(result.geminiApiKey || '');
                $('#aiBulkParseLimit').val(result.aiBulkParseLimit || '50'); // Add this
                $('#barikoiApiKey').val(result.barikoiApiKey || '');
                $('#googleMapsApiKey').val(result.googleMapsApiKey || ''); // Fixed duplicate
                $('#googleClientId').val(result.googleClientId || '');
                $('#googleClientSecret').val(result.googleClientSecret || '');
                $('#autocompleteService').val(result.autocompleteService || 'barikoi');
                $('#showAiParseButton').prop('checked', result.showAiParseButton == '1');
                $('#showAutocompleteButton').prop('checked', result.showAutocompleteButton == '1');
                $('#ezoicPlaceholderId').val(result.ezoicPlaceholderId || '');
                $('#helpContent').val(result.helpContent || '');
                $('#facebookPageId').val(result.facebookPageId || '');
                $('#useSimpleFbBtn').prop('checked', result.useSimpleFbBtn == '1' || result.useSimpleFbBtn === 'true');
                $('#whatsappNumber').val(result.whatsappNumber || '');
                $('#enableSocialPlugins').prop('checked', result.enableSocialPlugins == '1' || result.enableSocialPlugins === 'true');
                $('#seoTitle').val(result.seoTitle || '');
                $('#seoDescription').val(result.seoDescription || '');
                $('#seoImageUrl').val(result.seoImageUrl || '');
                if (result.seoImageUrl) {
                    $('#seoImagePreview').attr('src', result.seoImageUrl).show();
                }
            } catch (e) { console.error(e); }
        }
        loadSettings();

        // Preview Image on Select
        $('#seoImageFile').on('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    $('#seoImagePreview').attr('src', e.target.result).show();
                }
                reader.readAsDataURL(file);
            }
        });

        $('#settings-form').on('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'save_settings');
            formData.append('appName', $('#appName').val());
            formData.append('geminiApiKey', $('#geminiApiKey').val());
            formData.append('aiBulkParseLimit', $('#aiBulkParseLimit').val()); // Add this
            formData.append('barikoiApiKey', $('#barikoiApiKey').val());
            formData.append('googleMapsApiKey', $('#googleMapsApiKey').val());
            formData.append('googleClientId', $('#googleClientId').val());
            formData.append('googleClientSecret', $('#googleClientSecret').val());
            formData.append('autocompleteService', $('#autocompleteService').val());
            formData.append('showAiParseButton', $('#showAiParseButton').is(':checked') ? '1' : '0');
            formData.append('showAutocompleteButton', $('#showAutocompleteButton').is(':checked') ? '1' : '0');
            formData.append('ezoicPlaceholderId', $('#ezoicPlaceholderId').val());
            formData.append('helpContent', $('#helpContent').val());
            formData.append('facebookPageId', $('#facebookPageId').val());
            formData.append('useSimpleFbBtn', $('#useSimpleFbBtn').is(':checked') ? '1' : '0');
            formData.append('whatsappNumber', $('#whatsappNumber').val());
            formData.append('enableSocialPlugins', $('#enableSocialPlugins').is(':checked') ? '1' : '0');
            formData.append('seoTitle', $('#seoTitle').val());
            formData.append('seoDescription', $('#seoDescription').val());
            formData.append('seoImageUrl', $('#seoImageUrl').val());

            const seoImageFile = $('#seoImageFile')[0].files[0];
            if (seoImageFile) formData.append('seoImageFile', seoImageFile);

            const logoFile = $('#appLogoFile')[0].files[0];
            if (logoFile) formData.append('appLogoFile', logoFile);

            try {
                await fetch('api/admin.php', { method: 'POST', body: formData });
                alert('Settings saved!');
                loadSettings();
            } catch (e) { alert('Error saving settings: ' + e.message); }
        });


        // Run Cron
        $('#btnRunCron').click(async function () {
            if (!confirm('Are you sure? This will execute real database changes (Demotion/Emails).')) return;
            const btn = $(this);
            btn.prop('disabled', true).text('Running...');
            $('#cronOutput').hide().html('');

            try {
                // Determine API URL (handle potential subdirectory issues)
                const res = await fetch('api/cron.php');
                const data = await res.json();

                let html = '<strong>Result:</strong> ' + (data.success ? '<span style="color:green">Success</span>' : '<span style="color:red">Failed</span>') + '<br>';
                if (data.log && data.log.length) {
                    html += '<ul style="padding-left: 20px; margin: 5px 0;">' + data.log.map(l => `<li>${l}</li>`).join('') + '</ul>';
                } else if (data.error) {
                    html += '<div style="color:red; margin:5px 0;"><strong>Error:</strong> ' + data.error + '</div>';
                } else {
                    html += '<em>No actions taken (no expired users or warnings).</em>';
                }
                $('#cronOutput').html(html).show();
            } catch (e) {
                $('#cronOutput').html('<span style="color:red">Error: ' + e.message + '</span>').show();
            } finally {
                btn.prop('disabled', false).text('Run Daily Maintenance Now');
            }
        });

        // Test Email
        $('#btnSendTestEmail').click(async function () {
            const email = $('#testEmailInput').val();
            if (!email) return alert('Please enter an email address.');

            const btn = $(this);
            const originalText = btn.text();
            btn.prop('disabled', true).text('Sending...');

            try {
                await apiCall('send_test_email', { email: email });
                alert('Test email sent successfully!');
            } catch (e) {
                alert('Failed: ' + e.message);
            } finally {
                btn.prop('disabled', false).text(originalText);
            }
        });
    });
</script>