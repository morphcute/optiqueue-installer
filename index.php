<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiQueue Project Installer</title>
    <link rel="stylesheet" href="installer.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <div class="glow-1"></div>
    <div class="glow-2"></div>

    <div class="installer-card">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
            </div>
            <h1>OptiQueue Web Installer</h1>
            <p>Setup & Deploy OptiQueue Queuing System</p>
        </div>

        <!-- Stepper Header -->
        <div class="stepper">
            <div class="step-item active" id="step-nav-1">
                <div class="step-number">1</div>
                <div class="step-label">System</div>
            </div>
            <div class="step-item" id="step-nav-2">
                <div class="step-number">2</div>
                <div class="step-label">Database</div>
            </div>
            <div class="step-item" id="step-nav-3">
                <div class="step-number">3</div>
                <div class="step-label">Package</div>
            </div>
            <div class="step-item" id="step-nav-4">
                <div class="step-number">4</div>
                <div class="step-label">Admin</div>
            </div>
            <div class="step-item" id="step-nav-5">
                <div class="step-number">5</div>
                <div class="step-label">Finish</div>
            </div>
        </div>

        <div id="alert-box" class="alert"></div>

        <!-- STEP 1: System Check -->
        <div class="step-content active" id="step-1">
            <h3 style="margin-bottom: 12px; font-size: 16px;">1. System Requirements & Readiness</h3>
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">
                Checking PHP extensions, version compatibility, and file permissions.
            </p>

            <div class="check-list" id="sys-check-list">
                <div class="check-item">Loading environment checks...</div>
            </div>

            <div class="btn-row">
                <div></div>
                <button class="btn btn-primary" id="btn-step-1-next" disabled onclick="goToStep(2)">
                    Continue to Database &rarr;
                </button>
            </div>
        </div>

        <!-- STEP 2: Database Configuration -->
        <div class="step-content" id="step-2">
            <h3 style="margin-bottom: 12px; font-size: 16px;">2. Database & Environment Configuration</h3>
            
            <div class="form-group">
                <label>Database Host</label>
                <input type="text" id="db_host" class="form-control" value="127.0.0.1">
            </div>

            <div style="display: flex; gap: 12px;">
                <div class="form-group" style="flex: 2;">
                    <label>Database Name</label>
                    <input type="text" id="db_name" class="form-control" value="optiqueue">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Port</label>
                    <input type="text" id="db_port" class="form-control" value="3306">
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <div class="form-group" style="flex: 1;">
                    <label>DB Username</label>
                    <input type="text" id="db_user" class="form-control" value="root">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>DB Password</label>
                    <input type="password" id="db_pass" class="form-control" placeholder="Leave empty if none">
                </div>
            </div>

            <div class="form-group">
                <label>App URL</label>
                <input type="text" id="app_url" class="form-control" value="http://localhost:8000">
            </div>

            <div class="btn-row">
                <button class="btn btn-secondary" onclick="goToStep(1)">&larr; Back</button>
                <button class="btn btn-primary" id="btn-test-db" onclick="testDatabase()">
                    Test Connection & Save .env
                </button>
            </div>
        </div>

        <!-- STEP 3: Upload Project ZIP -->
        <div class="step-content" id="step-3">
            <h3 style="margin-bottom: 12px; font-size: 16px;">3. Select & Extract OptiQueue Project ZIP</h3>
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">
                Select the <code style="color: var(--primary);">optiqueue-laravel.zip</code> release file to extract code files into the application directory.
            </p>

            <div class="form-group">
                <label>Upload Project ZIP File</label>
                <input type="file" id="zip_file" accept=".zip" class="form-control">
            </div>

            <div class="log-box" id="extract-log">
                Ready to extract package...
            </div>

            <div class="btn-row">
                <button class="btn btn-secondary" onclick="goToStep(2)">&larr; Back</button>
                <button class="btn btn-primary" id="btn-extract-zip" onclick="extractZip()">
                    Extract & Process Package &rarr;
                </button>
            </div>
        </div>

        <!-- STEP 4: Admin Account Setup -->
        <div class="step-content" id="step-4">
            <h3 style="margin-bottom: 12px; font-size: 16px;">4. Create Initial Administrator Account</h3>
            
            <div class="form-group">
                <label>Administrator Full Name</label>
                <input type="text" id="admin_name" class="form-control" value="System Administrator">
            </div>

            <div class="form-group">
                <label>Admin Email Address</label>
                <input type="email" id="admin_email" class="form-control" value="admin@optiqueue.online">
            </div>

            <div class="form-group">
                <label>Admin Password</label>
                <input type="password" id="admin_password" class="form-control" value="admin123">
            </div>

            <div class="btn-row">
                <button class="btn btn-secondary" onclick="goToStep(3)">&larr; Back</button>
                <button class="btn btn-primary" onclick="createAdmin()">
                    Create Admin & Run Migrations &rarr;
                </button>
            </div>
        </div>

        <!-- STEP 5: Installation Finish -->
        <div class="step-content" id="step-5">
            <div style="text-align: center; padding: 20px 0;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: var(--success); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <h2 style="font-size: 22px; font-weight: 700; margin-bottom: 8px;">Installation Complete!</h2>
                <p style="color: var(--text-muted); font-size: 14px; max-width: 440px; margin: 0 auto 24px;">
                    OptiQueue has been successfully installed and configured with your database and administrator credentials.
                </p>

                <a id="launch-btn" href="http://localhost:8000" class="btn btn-primary" style="padding: 14px 32px; font-size: 15px; text-decoration: none;">
                    Launch OptiQueue System &rarr;
                </a>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;

        function showAlert(msg, isSuccess = false) {
            const box = document.getElementById('alert-box');
            box.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
            box.innerHTML = msg;
            box.style.display = 'block';
        }

        function clearAlert() {
            const box = document.getElementById('alert-box');
            box.style.display = 'none';
        }

        function goToStep(step) {
            clearAlert();
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach(el => el.classList.remove('active'));

            document.getElementById('step-' + step).classList.add('active');
            
            for (let i = 1; i <= step; i++) {
                const nav = document.getElementById('step-nav-' + i);
                if (i === step) nav.classList.add('active');
                if (i < step) nav.classList.add('completed');
            }

            currentStep = step;
        }

        // Run System Checks on Load
        async function runSystemChecks() {
            try {
                const res = await fetch('installer-backend.php?action=check_env');
                const data = await res.json();
                
                const list = document.getElementById('sys-check-list');
                list.innerHTML = '';

                if (data.checks) {
                    data.checks.forEach(c => {
                        const div = document.createElement('div');
                        div.className = 'check-item';
                        div.innerHTML = `
                            <span>${c.name}</span>
                            <span class="check-status ${c.pass ? 'pass' : 'fail'}">
                                ${c.value}
                            </span>
                        `;
                        list.appendChild(div);
                    });
                }

                if (data.canProceed) {
                    document.getElementById('btn-step-1-next').disabled = false;
                } else {
                    showAlert('Some system requirements are missing. Please fix them before proceeding.');
                }
            } catch (e) {
                showAlert('Failed to connect to installer backend script.');
            }
        }

        async function testDatabase() {
            clearAlert();
            const btn = document.getElementById('btn-test-db');
            btn.disabled = true;
            btn.innerText = 'Connecting...';

            const payload = new FormData();
            payload.append('action', 'test_db');
            payload.append('db_host', document.getElementById('db_host').value);
            payload.append('db_port', document.getElementById('db_port').value);
            payload.append('db_name', document.getElementById('db_name').value);
            payload.append('db_user', document.getElementById('db_user').value);
            payload.append('db_pass', document.getElementById('db_pass').value);

            try {
                const res = await fetch('installer-backend.php', { method: 'POST', body: payload });
                const data = await res.json();

                if (data.status === 'success') {
                    // Write .env
                    payload.set('action', 'write_env');
                    payload.append('app_url', document.getElementById('app_url').value);

                    const envRes = await fetch('installer-backend.php', { method: 'POST', body: payload });
                    const envData = await envRes.json();

                    showAlert(envData.message || 'Database configured successfully!', true);
                    setTimeout(() => goToStep(3), 1000);
                } else {
                    showAlert(data.message || 'Database connection failed.');
                }
            } catch (e) {
                showAlert('Network error during database test.');
            } finally {
                btn.disabled = false;
                btn.innerText = 'Test Connection & Save .env';
            }
        }

        async function extractZip() {
            clearAlert();
            const fileInput = document.getElementById('zip_file');
            const logBox = document.getElementById('extract-log');

            if (!fileInput.files.length) {
                showAlert('Please select a project .ZIP file first.');
                return;
            }

            const btn = document.getElementById('btn-extract-zip');
            btn.disabled = true;
            logBox.innerText = 'Extracting project files... Please wait...';

            const payload = new FormData();
            payload.append('action', 'extract_zip');
            payload.append('zip_file', fileInput.files[0]);

            try {
                const res = await fetch('installer-backend.php', { method: 'POST', body: payload });
                const data = await res.json();

                if (data.status === 'success') {
                    logBox.innerText += '\nExtracted successfully! Proceeding to Admin Setup...';
                    setTimeout(() => goToStep(4), 1000);
                } else {
                    showAlert(data.message || 'ZIP Extraction failed.');
                }
            } catch (e) {
                showAlert('Failed to extract ZIP package.');
            } finally {
                btn.disabled = false;
            }
        }

        async function createAdmin() {
            clearAlert();
            const payload = new FormData();
            payload.append('action', 'create_admin');
            payload.append('admin_name', document.getElementById('admin_name').value);
            payload.append('admin_email', document.getElementById('admin_email').value);
            payload.append('admin_password', document.getElementById('admin_password').value);

            payload.append('db_host', document.getElementById('db_host').value);
            payload.append('db_port', document.getElementById('db_port').value);
            payload.append('db_name', document.getElementById('db_name').value);
            payload.append('db_user', document.getElementById('db_user').value);
            payload.append('db_pass', document.getElementById('db_pass').value);

            try {
                // Run migrations first
                const setupPayload = new FormData();
                setupPayload.append('action', 'run_setup');
                await fetch('installer-backend.php', { method: 'POST', body: setupPayload });

                // Create admin
                const res = await fetch('installer-backend.php', { method: 'POST', body: payload });
                const data = await res.json();

                if (data.status === 'success') {
                    const launchBtn = document.getElementById('launch-btn');
                    launchBtn.href = document.getElementById('app_url').value || 'http://localhost:8000';
                    goToStep(5);
                } else {
                    showAlert(data.message || 'Failed to create admin user.');
                }
            } catch (e) {
                showAlert('Error creating administrator user.');
            }
        }

        document.addEventListener('DOMContentLoaded', runSystemChecks);
    </script>
</body>
</html>
