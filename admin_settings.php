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
            <label for="helpContent">Help Modal Content (HTML/CSS Allowed)</label>
            <textarea id="helpContent" rows="15"
                placeholder="Enter the HTML and CSS for your help guide here..."></textarea>
        </div>
        <button type="submit" class="btn-primary" style="margin-top: 10px;">Save Settings</button>
    </form>
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
                $('#barikoiApiKey').val(result.barikoiApiKey || '');
                $('#googleMapsApiKey').val(result.googleMapsApiKey || ''); // Fixed duplicate
                $('#googleClientId').val(result.googleClientId || '');
                $('#googleClientSecret').val(result.googleClientSecret || '');
                $('#autocompleteService').val(result.autocompleteService || 'barikoi');
                $('#showAiParseButton').prop('checked', result.showAiParseButton == '1');
                $('#showAutocompleteButton').prop('checked', result.showAutocompleteButton == '1');
                $('#ezoicPlaceholderId').val(result.ezoicPlaceholderId || '');
                $('#helpContent').val(result.helpContent || '');
            } catch (e) { console.error(e); }
        }
        loadSettings();

        $('#settings-form').on('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'save_settings');
            formData.append('appName', $('#appName').val());
            formData.append('geminiApiKey', $('#geminiApiKey').val());
            formData.append('barikoiApiKey', $('#barikoiApiKey').val());
            formData.append('googleMapsApiKey', $('#googleMapsApiKey').val());
            formData.append('googleClientId', $('#googleClientId').val());
            formData.append('googleClientSecret', $('#googleClientSecret').val());
            formData.append('autocompleteService', $('#autocompleteService').val());
            formData.append('showAiParseButton', $('#showAiParseButton').is(':checked') ? '1' : '0');
            formData.append('showAutocompleteButton', $('#showAutocompleteButton').is(':checked') ? '1' : '0');
            formData.append('ezoicPlaceholderId', $('#ezoicPlaceholderId').val());
            formData.append('helpContent', $('#helpContent').val());

            const logoFile = $('#appLogoFile')[0].files[0];
            if (logoFile) formData.append('appLogoFile', logoFile);

            try {
                await fetch('api/admin.php', { method: 'POST', body: formData });
                alert('Settings saved!');
                loadSettings();
            } catch (e) { alert('Error saving settings: ' + e.message); }
        });
    });
</script>