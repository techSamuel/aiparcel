// --- Visitor Tracking Module ---
(function () {
    let visitorDocId = null;
    let visitStartTime = null;

    const IPDATA_API_KEY = "cc45fc38076fbd77036417aae96d8231c5bb3c8fdbdbb4ccc7d5bf3e";

    async function trackVisitor() {
        try {
            // Fetch IP and location data
            let ip = "Unknown";
            let location = "Unknown";

            try {
                const response = await fetch(`https://api.ipdata.co?api-key=${IPDATA_API_KEY}`);
                if (response.ok) {
                    const data = await response.json();
                    ip = data.ip || "Unknown";
                    location = `${data.city || 'Unknown'}, ${data.country_name || 'Unknown'}`;
                }
            } catch (ipError) {
                console.log("IP lookup failed (may be blocked by adblocker):", ipError.message);
            }

            const userAgent = navigator.userAgent;
            visitStartTime = new Date();

            // Call backend API to track visitor
            const result = await fetch('api/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'track_visitor',
                    ip: ip,
                    location: location,
                    userAgent: userAgent,
                    durationMillis: 0
                }),
                credentials: 'same-origin'
            });

            const json = await result.json();
            if (json.success) {
                visitorDocId = json.visitorId;
                console.log(`Visitor tracked: ${json.isNew ? 'New' : 'Returning'} visitor (ID: ${visitorDocId})`);
            }

        } catch (error) {
            console.error("Error tracking visitor:", error);
        }
    }

    async function updateVisitorDuration() {
        if (!visitorDocId || !visitStartTime) return;

        try {
            const durationMillis = new Date() - visitStartTime;

            await fetch('api/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'track_visitor',
                    ip: 'existing',
                    location: 'existing',
                    userAgent: navigator.userAgent,
                    durationMillis: durationMillis
                }),
                credentials: 'same-origin'
            });

        } catch (error) {
            // Silent fail - don't bother user
        }
    }

    // Track on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackVisitor);
    } else {
        trackVisitor();
    }

    // Update duration when leaving page
    window.addEventListener('beforeunload', updateVisitorDuration);

    // Update duration every 30 seconds
    setInterval(updateVisitorDuration, 30000);

})();
