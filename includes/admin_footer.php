</div>
</main>
</div>

<!-- SHARED MODALS -->
<div id="user-details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="user-details-title">User Details</h2><span class="close-btn">&times;</span>
        </div>
        <div id="user-details-content"></div>
    </div>
</div>

<div id="admin-profile-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Admin Profile Settings</h2>
            <span class="close-btn">&times;</span>
        </div>
        <div class="form-group">
            <label for="adminDisplayNameInput">Display Name</label>
            <input type="text" id="adminDisplayNameInput" placeholder="Enter your display name">
            <button id="updateAdminNameBtn" class="btn-primary" style="width:100%; margin-top:10px;">Update
                Name</button>
        </div>
        <hr style="margin: 20px 0;">
        <div class="form-group">
            <label for="adminPasswordInput">New Password (leave blank to keep current)</label>
            <input type="password" id="adminPasswordInput" placeholder="Enter a new password">
            <button id="updateAdminPasswordBtn" class="btn-primary" style="width:100%; margin-top:10px;">Update
                Password</button>
        </div>
        <div id="admin-profile-message" class="message" style="display:none; margin-top: 15px;"></div>
    </div>
</div>
</div>

<!-- Item Details Modal (Dynamic Table) -->
<div id="item-details-modal" class="modal">
    <div class="modal-content" style="max-width: 90%; width: 1200px;">
        <div class="modal-header">
            <h2>Item Details</h2><span class="close-btn">&times;</span>
        </div>
        <div class="table-container">
            <table id="item-details-table" class="display data-table" style="width:100%"></table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script type="text/javascript" charset="utf8"
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    let currentUser = null;
    const $adminDisplayName = $('#adminDisplayName');

    function showMessage(element, message, type) {
        $(element).removeClass('success error').addClass(type).text(message).show();
    }

    async function apiCall(action, body = {}, url = 'api/admin.php') {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...body })
            });

            if (response.status === 401 || response.status === 403) {
                handleLogout();
                throw new Error('Permission Denied or Session Expired.');
            }

            const responseText = await response.text();
            if (!responseText) throw new Error('Server returned an empty response.');

            try {
                const data = JSON.parse(responseText);
                if (!response.ok) throw new Error(data.error || `API error with status ${response.status}`);
                return data;
            } catch (jsonError) {
                console.error("Failed to parse JSON:", responseText);
                throw new Error('Server returned invalid JSON.');
            }
        } catch (error) {
            console.error('API Call Error:', action, error);
            alert(`Error: ${error.message}`);
            throw error;
        }
    }

    const handleLogout = () => apiCall('logout', {}, 'api/index.php').then(() => window.location.href = 'admin_login.php');
    $('#logoutBtn').on('click', handleLogout);

    // Profile Logic
    $('#profileBtn').on('click', function () {
        $('#adminDisplayNameInput').val(currentUser ? (currentUser.displayName || '') : '');
        $('#adminPasswordInput').val('');
        $('#admin-profile-message').hide();
        $('#admin-profile-modal').show();
    });

    $('#updateAdminNameBtn').on('click', async function () {
        const newName = $('#adminDisplayNameInput').val();
        try {
            await apiCall('update_profile', { displayName: newName });
            if (currentUser) currentUser.displayName = newName;
            $('#adminDisplayName').text(newName);
            showMessage('#admin-profile-message', 'Display name updated!', 'success');
        } catch (e) {
            showMessage('#admin-profile-message', 'Error: ' + e.message, 'error');
        }
    });

    $('#updateAdminPasswordBtn').on('click', async function () {
        const newPassword = $('#adminPasswordInput').val();
        if (newPassword.length > 0 && newPassword.length < 6) return showMessage('#admin-profile-message', 'Password too short.', 'error');
        try {
            await apiCall('update_profile', { password: newPassword });
            $('#adminPasswordInput').val('');
            showMessage('#admin-profile-message', 'Password updated!', 'success');
        } catch (e) {
            showMessage('#admin-profile-message', 'Error: ' + e.message, 'error');
        }
    });

    // Shared Dynamic Table Renderer (Cached Version)
    window.parsedDataCache = {};

    window.renderItemTableBtn = function (id, jsonString) {
        if (!jsonString) return '-';
        try {
            // Cache the data instead of embedding it
            window.parsedDataCache[id] = jsonString;
            return `<button class="btn-primary btn-sm btn-view-items" onclick="openItemTableFromCache(${id})">View Items</button>`;
        } catch (e) { return 'Error'; }
    };

    window.openItemTableFromCache = function (id) {
        const jsonString = window.parsedDataCache[id];
        if (!jsonString) {
            alert("Data not found in cache. Please reload.");
            return;
        }
        // Use the existing logic or the same function body
        window.openItemTableEncoded(jsonString); // Delegate to keep logic clean if needed, or just inline here.
    };

    // Renamed for internal use, though we decode differently now.
    // Let's just inline the logic to be safe and simple
    window.openItemTableFromCache = function (id) {
        try {
            const jsonString = window.parsedDataCache[id];
             if (!jsonString) {
                alert("Data lost. Please refresh the page.");
                return;
            }
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

    $('.modal .close-btn').on('click', function () { $(this).closest('.modal').hide(); });
    $('.modal').on('click', function (e) { if (e.target === this) $(this).hide(); });

    // Initialize Session
    $(document).ready(function () {
        // Simple check to get user details if not present, primarily for display name
        fetch('api/index.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'check_session' }) })
            .then(res => res.json()).then(session => {
                if (session.loggedIn && session.user) {
                    currentUser = session.user;
                    $('#adminDisplayName').text(currentUser.displayName || currentUser.email);
                }
            });
    });
</script>
</body>

</html>