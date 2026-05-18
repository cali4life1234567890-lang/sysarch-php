<?php
// Admin Software Catalog & Import Page
require_once '../database/db.php';
startSession();

// Check if admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ? AND id_number = '2664388'");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    header('Location: ../index.php');
    exit;
}

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Manager - CCS Sit-In System</title>
    <link rel="icon" href="../imgs/ccslogo.png" type="image/png" />
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar admin-navbar">
        <div class="nav-brand"> 
            <a href="admin_home.php" class="logo-group"> 
                <img src="../imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
                <h1 class="system-title">CCS Sit-In Monitoring System</h1>
            </a>
        </div>
        <div class="nav-links admin-links">
            <a href="admin_home.php">Home</a>
            <a href="#" onclick="openSearchModal(); return false;">Search</a>
            <a href="admin_leaderboard.php">Leaderboard</a>
            <a href="admin_students.php">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_software.php" class="active">Software Manager</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content premium-admin-dashboard">
        <div class="dashboard-header-flex">
            <div>
                <span class="welcome-badge">CATALOG MANAGEMENT</span>
                <h1>Software Manager</h1>
            </div>
            <div class="admin-profile-badge">
                <span class="avatar">💻</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>

        <div class="software-actions-grid">
            <!-- Drag & Drop Uploader Card -->
            <div class="chart-container-card uploader-card">
                <div class="chart-card-header">
                    <h3>📥 CSV Catalog Import</h3>
                    <p>Bulk import software catalogs for any computer lab using a CSV file.</p>
                </div>
                
                <div class="csv-format-tip">
                    <strong>Expected Columns:</strong> 
                    <code>lab_number, software_name, version, status</code>
                    <br>
                    <span class="sample-link" onclick="downloadSampleCSV()">Download Sample CSV Format</span>
                </div>

                <div class="drag-drop-zone" id="csv-drop-zone">
                    <span class="upload-cloud-icon">📂</span>
                    <h4>Drag & Drop CSV File here</h4>
                    <p>or click to browse local files</p>
                    <input type="file" id="software-csv-input" accept=".csv" style="display: none;">
                </div>
                
                <div id="upload-status" class="upload-status-bar" style="display: none;">
                    <div class="spinner-mini"></div>
                    <span id="upload-status-text">Uploading catalog...</span>
                </div>
            </div>

            <!-- Manual Add Form Card -->
            <div class="chart-container-card manual-add-card">
                <div class="chart-card-header">
                    <h3>➕ Add Software Manually</h3>
                    <p>Insert a single software item directly into a laboratory registry.</p>
                </div>
                
                <form id="manual-software-form" onsubmit="addSoftwareManually(event)" class="modern-inline-form">
                    <div class="form-group-grid">
                        <div class="form-item">
                            <label for="sw-lab">Laboratory Room</label>
                            <select id="sw-lab" required>
                                <option value="524">Lab 524</option>
                                <option value="526">Lab 526</option>
                                <option value="528">Lab 528</option>
                                <option value="530">Lab 530</option>
                                <option value="MAC">MAC Lab</option>
                            </select>
                        </div>
                        <div class="form-item">
                            <label for="sw-name">Software Application Name</label>
                            <input type="text" id="sw-name" placeholder="e.g. Visual Studio Code" required>
                        </div>
                        <div class="form-item">
                            <label for="sw-version">Version</label>
                            <input type="text" id="sw-version" placeholder="e.g. 1.85.0" required>
                        </div>
                        <div class="form-item">
                            <label for="sw-status">Availability Status</label>
                            <select id="sw-status">
                                <option value="available">Available (Operational)</option>
                                <option value="maintenance">Under Maintenance</option>
                                <option value="restricted">Restricted / Instructor Only</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">Add to Catalog</button>
                </form>
            </div>
        </div>

        <!-- Catalog Viewer Table Section -->
        <div class="chart-container-card catalog-viewer-card">
            <div class="catalog-viewer-header">
                <h2>Software Inventories & Catalogues</h2>
                <div class="search-filter-box">
                    <input type="text" id="softwareSearch" placeholder="Search applications..." onkeyup="filterSoftwareList()" class="search-box">
                </div>
            </div>

            <!-- Sleek Lab Filtering Pills -->
            <div class="lab-filter-pills">
                <button class="pill-btn active" onclick="setLabFilter('all')">All Rooms</button>
                <button class="pill-btn" onclick="setLabFilter('524')">Lab 524</button>
                <button class="pill-btn" onclick="setLabFilter('526')">Lab 526</button>
                <button class="pill-btn" onclick="setLabFilter('528')">Lab 528</button>
                <button class="pill-btn" onclick="setLabFilter('530')">Lab 530</button>
                <button class="pill-btn" onclick="setLabFilter('MAC')">MAC Lab</button>
            </div>

            <div class="table-wrapper-outer">
                <table class="data-table" id="softwareTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Laboratory Room</th>
                            <th>Software Name</th>
                            <th>Installed Version</th>
                            <th>Status Badge</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="software-list-body">
                        <!-- Loaded dynamically -->
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px;">
                                <div class="spinner-mini" style="display: inline-block;"></div>
                                <span style="margin-left: 10px; color: #888;">Fetching software registers...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let allSoftware = [];
        let currentLabFilter = 'all';

        document.addEventListener('DOMContentLoaded', () => {
            loadSoftwareCatalog();
            setupDragAndDrop();
        });

        async function loadSoftwareCatalog() {
            try {
                const response = await fetch('admin_dashboard.php?action=get_software');
                const data = await response.json();
                if (data.success) {
                    allSoftware = data.software;
                    renderSoftwareList();
                } else {
                    document.getElementById('software-list-body').innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--danger-color); padding: 20px;">
                                Failed to load catalog: ${data.message}
                            </td>
                        </tr>
                    `;
                }
            } catch (err) {
                console.error(err);
                document.getElementById('software-list-body').innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--danger-color); padding: 20px;">
                            Error connecting to backend database.
                        </td>
                    </tr>
                `;
            }
        }

        function renderSoftwareList() {
            const body = document.getElementById('software-list-body');
            const searchVal = document.getElementById('softwareSearch').value.toLowerCase();
            
            // Filter by room & search query
            const filtered = allSoftware.filter(item => {
                const matchLab = currentLabFilter === 'all' || item.lab_number === currentLabFilter;
                const matchSearch = item.software_name.toLowerCase().includes(searchVal) || 
                                    (item.version && item.version.toLowerCase().includes(searchVal)) || 
                                    item.lab_number.toLowerCase().includes(searchVal);
                return matchLab && matchSearch;
            });

            if (filtered.length === 0) {
                body.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; color: #888; padding: 30px;">
                            No matching software applications registered.
                        </td>
                    </tr>
                `;
                return;
            }

            body.innerHTML = filtered.map(item => {
                let badgeClass = 'status-badge status-active';
                let statusLabel = 'Available';
                
                if (item.status === 'maintenance') {
                    badgeClass = 'status-badge status-denied';
                    statusLabel = 'Maintenance';
                } else if (item.status === 'restricted') {
                    badgeClass = 'status-badge status-pending';
                    statusLabel = 'Restricted';
                }

                return `
                    <tr>
                        <td><strong>#${item.id}</strong></td>
                        <td><span class="lab-badge">Lab ${item.lab_number}</span></td>
                        <td><strong>${escapeHtml(item.software_name)}</strong></td>
                        <td><code>${escapeHtml(item.version || '1.0')}</code></td>
                        <td><span class="${badgeClass}">${statusLabel}</span></td>
                        <td style="text-align: center;">
                            <button class="btn-small btn-danger" onclick="deleteSoftwareItem(${item.id})">Delete</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function setLabFilter(lab) {
            currentLabFilter = lab;
            
            // Toggle active pill button
            document.querySelectorAll('.lab-filter-pills .pill-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            renderSoftwareList();
        }

        function filterSoftwareList() {
            renderSoftwareList();
        }

        async function addSoftwareManually(e) {
            e.preventDefault();
            const lab = document.getElementById('sw-lab').value;
            const name = document.getElementById('sw-name').value.trim();
            const version = document.getElementById('sw-version').value.trim();
            const status = document.getElementById('sw-status').value;

            try {
                const response = await fetch('admin_dashboard.php?action=add_software', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        lab_number: lab,
                        software_name: name,
                        version: version,
                        status: status
                    })
                });
                const data = await response.json();
                
                alert(data.message);
                if (data.success) {
                    document.getElementById('sw-name').value = '';
                    document.getElementById('sw-version').value = '';
                    loadSoftwareCatalog();
                }
            } catch (err) {
                console.error(err);
                alert('Connection failure adding software');
            }
        }

        async function deleteSoftwareItem(id) {
            if (!confirm('Are you sure you want to remove this software from the laboratory inventory?')) {
                return;
            }

            try {
                const response = await fetch(`admin_dashboard.php?action=delete_software&id=${id}`);
                const data = await response.json();
                alert(data.message);
                if (data.success) {
                    loadSoftwareCatalog();
                }
            } catch (err) {
                console.error(err);
                alert('Connection failure deleting item');
            }
        }

        function setupDragAndDrop() {
            const zone = document.getElementById('csv-drop-zone');
            const fileInput = document.getElementById('software-csv-input');

            zone.addEventListener('click', () => fileInput.click());

            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drag-active');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('drag-active');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('drag-active');
                if (e.dataTransfer.files.length > 0) {
                    handleCSVFile(e.dataTransfer.files[0]);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    handleCSVFile(fileInput.files[0]);
                }
            });
        }

        async function handleCSVFile(file) {
            if (!file.name.endsWith('.csv')) {
                alert('Please upload a valid CSV formatted file.');
                return;
            }

            const statusDiv = document.getElementById('upload-status');
            const statusText = document.getElementById('upload-status-text');
            
            statusDiv.style.display = 'flex';
            statusText.textContent = `Uploading ${file.name}...`;

            const formData = new FormData();
            formData.append('software_csv', file);

            try {
                const response = await fetch('admin_dashboard.php?action=import_software', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                statusDiv.style.display = 'none';
                
                if (data.success) {
                    alert(data.message);
                    if (data.errors && data.errors.length > 0) {
                        alert("Note: Some rows had warnings:\n" + data.errors.join("\n"));
                    }
                    loadSoftwareCatalog();
                } else {
                    alert('Import Failed: ' + data.message);
                }
            } catch (err) {
                console.error(err);
                statusDiv.style.display = 'none';
                alert('Network upload failure.');
            }
        }

        function downloadSampleCSV() {
            const csvContent = "data:text/csv;charset=utf-8," 
                + "lab_number,software_name,version,status\n"
                + "524,Python Runtime Compiler,3.11.2,available\n"
                + "526,IntelliJ IDEA Community,2023.2.1,available\n"
                + "528,Cisco Packet Tracer,8.2.1,maintenance\n"
                + "530,Visual Studio Enterprise,2022,available\n"
                + "MAC,Xcode IDE Pro,15.0,restricted";
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "ccs_software_sample.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
