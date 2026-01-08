<?php include 'includes/admin_header.php'; ?>
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>Visitor Tracking</h3>
        <div>
            <span id="total-visitors" style="font-size: 14px; color: #666;"></span>
            <button id="refresh-visitors-btn" class="btn-sm btn-primary" style="margin-left: 10px;">🔄 Refresh</button>
        </div>
    </div>
    <div class="table-container">
        <table class="data-table display" id="visitors-table" style="width:100%"></table>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    function loadVisitors() {
        $('#visitors-table').DataTable({
            destroy: true,
            ajax: (d, cb) => apiCall('get_visitors').then(res => {
                $('#total-visitors').text(`Total: ${res.length} visitors`);
                cb({ data: res });
            }),
            columns: [
                { title: "ID", data: "id", width: "50px" },
                {
                    title: "Last Seen",
                    data: "updated_at",
                    render: d => {
                        const date = new Date(d);
                        const now = new Date();
                        const diffMs = now - date;
                        const diffMins = Math.floor(diffMs / 60000);
                        const diffHours = Math.floor(diffMins / 60);
                        const diffDays = Math.floor(diffHours / 24);

                        let timeAgo = '';
                        if (diffMins < 1) timeAgo = '<span style="color: green; font-weight: bold;">Just now</span>';
                        else if (diffMins < 60) timeAgo = `<span style="color: green;">${diffMins}m ago</span>`;
                        else if (diffHours < 24) timeAgo = `${diffHours}h ago`;
                        else timeAgo = `${diffDays}d ago`;

                        return `${timeAgo}<br><small style="color:#888;">${date.toLocaleString('en-US', { timeZone: 'Asia/Dhaka' })}</small>`;
                    }
                },
                {
                    title: "User",
                    data: null,
                    render: row => {
                        if (row.email) {
                            return `<span style="color: #27ae60; font-weight: bold;">✓ ${row.email}</span>`;
                        }
                        return `<span style="color: #999;">Guest</span>`;
                    }
                },
                { title: "IP Address", data: "ip_address" },
                { title: "Location", data: "location" },
                {
                    title: "Visits",
                    data: "visit_count",
                    render: d => `<span style="background: #3498db; color: white; padding: 2px 8px; border-radius: 10px;">${d}</span>`
                },
                {
                    title: "Duration",
                    data: "duration_millis",
                    render: d => {
                        if (!d || d == 0) return '-';
                        const mins = Math.floor(d / 60000);
                        const secs = Math.floor((d % 60000) / 1000);
                        if (mins > 0) return `${mins}m ${secs}s`;
                        return `${secs}s`;
                    }
                },
                {
                    title: "Device",
                    data: "user_agent",
                    render: d => {
                        if (!d) return '-';
                        // Parse user agent for easy reading
                        let device = 'Unknown';
                        if (d.includes('Mobile')) device = '📱 Mobile';
                        else if (d.includes('Tablet')) device = '📱 Tablet';
                        else device = '💻 Desktop';

                        let browser = 'Unknown';
                        if (d.includes('Chrome')) browser = 'Chrome';
                        else if (d.includes('Firefox')) browser = 'Firefox';
                        else if (d.includes('Safari')) browser = 'Safari';
                        else if (d.includes('Edge')) browser = 'Edge';

                        return `${device}<br><small style="color:#888;">${browser}</small>`;
                    }
                }
            ],
            order: [[1, 'desc']],
            pageLength: 25,
            language: {
                emptyTable: "No visitors tracked yet. Visitor tracking starts when users visit the dashboard."
            }
        });
    }

    $(document).ready(function () {
        loadVisitors();

        $('#refresh-visitors-btn').on('click', function () {
            loadVisitors();
        });
    });
</script>