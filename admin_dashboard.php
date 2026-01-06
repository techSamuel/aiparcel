<?php include 'includes/admin_header.php'; ?>
<div class="stats-grid">
    <div class="stat-card users">
        <h3>Total Users</h3>
        <p id="total-users-stat">0</p>
    </div>
    <div class="stat-card parses">
        <h3>Total Parses</h3>
        <p id="total-parses-stat">0</p>
    </div>
    <div class="stat-card orders">
        <h3>Total Orders</h3>
        <p id="total-orders-stat">0</p>
    </div>
</div>
<?php include 'includes/admin_footer.php'; ?>
<script>
    async function loadDashboardStats() {
        try {
            const stats = await apiCall('get_stats');
            $('#total-users-stat').text(stats.userCount);
            $('#total-parses-stat').text(stats.parseCount);
            $('#total-orders-stat').text(stats.orderCount);
        } catch (e) { console.error('Stats error', e); }
    }
    $(document).ready(loadDashboardStats);
</script>