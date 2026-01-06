<?php
/**
 * Gemini API Key Test Page
 * Use this to test if your API key is working correctly.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini API Key Tester</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #4ecdc4;
        }

        .card {
            background: #16213e;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #aaa;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #0f3460;
            border-radius: 8px;
            background: #0f3460;
            color: #fff;
            font-size: 14px;
            margin-bottom: 16px;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #4ecdc4;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4ecdc4, #44bd56);
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .result {
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            white-space: pre-wrap;
            word-break: break-all;
            font-family: monospace;
            font-size: 13px;
        }

        .success {
            background: #1b4332;
            border: 1px solid #40916c;
        }

        .error {
            background: #4a1c1c;
            border: 1px solid #c0392b;
        }

        .info {
            background: #1e3a5f;
            border: 1px solid #3498db;
        }

        .warning {
            background: #5c4813;
            border: 1px solid #f39c12;
            color: #ffeaa7;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔑 Gemini API Key Tester</h1>

        <div class="card warning">
            ⚠️ <strong>Security Warning:</strong> This page is for testing only. Delete it after use or restrict access.
        </div>

        <div class="card">
            <form id="testForm">
                <label for="apiKey">Enter your Gemini API Key:</label>
                <input type="text" id="apiKey" name="apiKey" placeholder="AIzaSy..." required>

                <label for="testPrompt">Test Prompt (optional):</label>
                <input type="text" id="testPrompt" name="testPrompt" value="Say hello in one word"
                    placeholder="Enter a test prompt">

                <button type="submit" id="testBtn">Test API Key</button>
            </form>

            <div id="result"></div>
        </div>

        <div class="card">
            <label>Currently saved key in database (first 15 chars):</label>
            <div class="result info">
                <?php
                require_once 'api/config.php';
                try {
                    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
                    $stmt->execute();
                    $key = $stmt->fetchColumn();
                    if ($key) {
                        echo "Key: " . substr($key, 0, 15) . "...\n";
                        echo "Length: " . strlen($key) . " characters";
                    } else {
                        echo "No API key found in database!";
                    }
                } catch (Exception $e) {
                    echo "Error reading from database: " . $e->getMessage();
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('testForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('testBtn');
            const result = document.getElementById('result');
            const apiKey = document.getElementById('apiKey').value.trim();
            const prompt = document.getElementById('testPrompt').value.trim() || 'Say hello';

            btn.disabled = true;
            btn.textContent = 'Testing...';
            result.innerHTML = '<div class="result info">Sending request to Gemini API...</div>';

            try {
                const response = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=${apiKey}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        contents: [{ parts: [{ text: prompt }] }]
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    const text = data.candidates?.[0]?.content?.parts?.[0]?.text || 'No response text';
                    result.innerHTML = `<div class="result success">✅ API Key is WORKING!\n\nResponse: ${text}\n\nHTTP Status: ${response.status}</div>`;
                } else {
                    const errorMsg = data.error?.message || JSON.stringify(data);
                    result.innerHTML = `<div class="result error">❌ API Error!\n\nStatus: ${response.status}\nMessage: ${errorMsg}</div>`;
                }
            } catch (err) {
                result.innerHTML = `<div class="result error">❌ Network Error!\n\n${err.message}</div>`;
            }

            btn.disabled = false;
            btn.textContent = 'Test API Key';
        });
    </script>
</body>

</html>