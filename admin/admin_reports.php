<?php
// Admin Reports Page
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

// Get report data
$reportType = $_GET['type'] ?? 'daily';
$reportDate = $_GET['date'] ?? date('Y-m-d');
$reportWeek = $_GET['week'] ?? date('Y-\WW');
$reportMonth = $_GET['month'] ?? date('Y-m');
$reportYear = $_GET['year'] ?? date('Y');

$reportData = [];
$summary = ['total' => 0, 'labs' => []];

// Initialize all laboratory counts to 0 to ensure uniform grid representation
$labsList = ['524', '526', '528', '530', 'MAC'];
foreach ($labsList as $l) {
    $summary['labs']['Lab ' . $l] = 0;
}

if ($reportType === 'daily' && !empty($reportDate)) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE date(r.time_in) = ?
            GROUP BY r.lab_number
        ");
        $stmt->execute([$reportDate]);
        $reportData = $stmt->fetchAll();

        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'weekly') {
    try {
        // Fetch logs over past 7 days
        $stmt = $pdo->query("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE r.time_in >= datetime('now', '-7 days')
            GROUP BY r.lab_number
        ");
        $reportData = $stmt->fetchAll();

        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'monthly' && !empty($reportMonth)) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE strftime('%Y-%m', r.time_in) = ?
            GROUP BY r.lab_number
        ");
        $stmt->execute([$reportMonth]);
        $reportData = $stmt->fetchAll();

        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'yearly' && !empty($reportYear)) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE strftime('%Y', r.time_in) = ?
            GROUP BY r.lab_number
        ");
        $stmt->execute([$reportYear]);
        $reportData = $stmt->fetchAll();

        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
}

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<?php
if (isset($_GET['format']) && in_array($_GET['format'], ['pdf', 'csv'])) {
    // Determine report type and build detailed query
    $title = "Sit-In Report";
    $filename = "CCS_Report";
    $query = "SELECT r.id, u.id_number, u.firstname, u.lastname, u.course, u.level,
        r.lab_number, r.pc_number, r.time_in, r.time_out, r.purpose
        FROM sitin_records r
        JOIN users u ON r.user_id = u.id
        WHERE 1=1";
    $params = [];
    if ($reportType === 'daily' && !empty($reportDate)) {
        $query .= " AND date(r.time_in) = ?";
        $params[] = $reportDate;
        $title = "Daily Sit-In Report - " . date('F j, Y', strtotime($reportDate));
        $filename = "CCS_Daily_Report_" . $reportDate;
    } elseif ($reportType === 'weekly' && !empty($reportWeek)) {
        $yearPart = substr($reportWeek, 0, 4);
        $weekPart = substr($reportWeek, 6);
        $query .= " AND strftime('%Y', r.time_in) = ? AND CAST(strftime('%W', r.time_in) AS INTEGER) = ?";
        $params[] = $yearPart;
        $params[] = (int)$weekPart;
        $title = "Weekly Sit-In Report - Week $weekPart, $yearPart";
        $filename = "CCS_Weekly_Report_" . $reportWeek;
    } elseif ($reportType === 'monthly' && !empty($reportMonth)) {
        $query .= " AND strftime('%Y-%m', r.time_in) = ?";
        $params[] = $reportMonth;
        $title = "Monthly Sit-In Report - " . date('F Y', strtotime($reportMonth . '-01'));
        $filename = "CCS_Monthly_Report_" . $reportMonth;
    } elseif ($reportType === 'yearly' && !empty($reportYear)) {
        $query .= " AND strftime('%Y', r.time_in) = ?";
        $params[] = $reportYear;
        $title = "Yearly Sit-In Report - " . $reportYear;
        $filename = "CCS_Yearly_Report_" . $reportYear;
    } else {
        $query .= " AND date(r.time_in) = date('now')";
        $title = "Sit-In Report";
        $filename = "CCS_SitIn_Report_" . date('Ymd');
    }
    $query .= " ORDER BY r.time_in DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    if ($_GET['format'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student ID', 'Name', 'Course/Level', 'Lab', 'PC #', 'Time In', 'Time Out', 'Purpose']);
        
        foreach ($records as $row) {
            fputcsv($output, [
                $row['id_number'],
                $row['lastname'] . ', ' . $row['firstname'],
                $row['course'] . '-' . $row['level'],
                $row['lab_number'],
                $row['pc_number'] ?: 'N/A',
                date('M d, h:i A', strtotime($row['time_in'])),
                $row['time_out'] ? date('M d, h:i A', strtotime($row['time_out'])) : 'Ongoing',
                ucfirst($row['purpose'] ?: 'N/A')
            ]);
        }
        fclose($output);
        exit;
    }

    // Output PDF HTML
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f8fafc;
                color: #334155;
                margin: 0;
                padding: 20px;
            }

            .report-container {
                max-width: 1000px;
                margin: 0 auto;
                background: #fff;
                padding: 40px;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            }

            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 20px;
            }

            .header img {
                height: 80px;
                margin-bottom: 15px;
            }

            .header h1 {
                color: #1e293b;
                margin: 0 0 10px;
                font-size: 24px;
            }

            .header p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-size: 13px;
            }

            th,
            td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #e2e8f0;
            }

            th {
                background: #f1f5f9;
                color: #475569;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 11px;
                letter-spacing: 0.5px;
            }

            tbody tr:nth-child(even) {
                background: #f8fafc;
            }

            .badge-ongoing {
                background: #fef3c7;
                color: #d97706;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
            }

            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 12px;
                color: #94a3b8;
                border-top: 1px solid #e2e8f0;
                padding-top: 20px;
            }

            #downloading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            }

            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3b82f6;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin-bottom: 20px;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }

            @media print {
                #downloading-overlay {
                    display: none !important;
                }

                .report-container {
                    box-shadow: none;
                    padding: 0;
                }

                body {
                    background: #fff;
                }
            }
        </style>
    </head>

    <body>
        <div id="downloading-overlay">
            <div class="spinner"></div>
            <h2>Generating PDF...</h2>
            <p>Please wait while we prepare your download.</p>
        </div>
        <div class="report-container" id="report-content">
            <div class="header">
                <img src="../imgs/ccslogo.png" alt="CCS Logo" onerror="this.style.display='none'">
                <h1><?= htmlspecialchars($title) ?></h1>
                <p>Generated on <?= date('F j, Y, g:i a') ?></p>
                <p>University of Cebu - College of Computer Studies</p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course/Level</th>
                        <th>Lab</th>
                        <th>PC #</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">No records found for this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id_number']) ?></td>
                                <td><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname']) ?></td>
                                <td><?= htmlspecialchars($row['course'] . '-' . $row['level']) ?></td>
                                <td><?= htmlspecialchars($row['lab_number']) ?></td>
                                <td><?= htmlspecialchars($row['pc_number'] ?: 'N/A') ?></td>
                                <td><?= date('M d, h:i A', strtotime($row['time_in'])) ?></td>
                                <td><?php if ($row['time_out']): ?><?= date('M d, h:i A', strtotime($row['time_out'])) ?><?php else: ?><span class="badge-ongoing">Ongoing</span><?php endif; ?></td>
                                <td><?= htmlspecialchars(ucfirst($row['purpose'] ?: 'N/A')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="footer">
                <p>End of Report — CCS Sit-In Monitoring System</p>
            </div>
        </div>
        <script>
            window.onload = function() {
                const element = document.getElementById('report-content');
                const opt = {
                    margin: 10,
                    filename: `<?= htmlspecialchars($filename) ?>.pdf`,
                    image: {
                        type: 'jpeg',
                        quality: 0.98
                    },
                    html2canvas: {
                        scale: 2,
                        useCORS: true
                    },
                    jsPDF: {
                        unit: 'mm',
                        format: 'a4',
                        orientation: 'landscape'
                    }
                };
                html2pdf().set(opt).from(element).save().then(() => {
                    document.getElementById('downloading-overlay').innerHTML = '<div style="font-size:40px;margin-bottom:20px;">✅</div><h2>PDF Downloaded Successfully</h2><p>You can close this tab now.</p>';
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage('pdf-done', '*');
                    }
                }).catch(err => {
                    console.error('Error generating PDF:', err);
                    document.getElementById('downloading-overlay').innerHTML = '<div style="font-size:40px;margin-bottom:20px;color:red;">❌</div><h2>Failed to generate PDF</h2><p>' + (err.message || 'An unknown error occurred.') + '</p>';
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage('pdf-error', '*');
                    }
                });
            };
        </script>
    </body>

    </html>
<?php
    exit;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Reports - CCS Sit-In System</title>
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
            <a href="admin_software.php">Software Manager</a>
            <a href="admin_pcs.php">PC Management</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php" class="active">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content premium-admin-dashboard">
        <div class="dashboard-header-flex">
            <div>
                <span class="welcome-badge">LOG REPORTS & AUDITS</span>
                <h1>Sit-In Reports</h1>
            </div>
            <div class="admin-profile-badge">
                <span class="avatar">📊</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>

        <div class="reports-tabs">
            <a href="?type=daily" class="<?php echo $reportType === 'daily' ? 'active' : ''; ?>">Daily Report</a>
            <a href="?type=weekly" class="<?php echo $reportType === 'weekly' ? 'active' : ''; ?>">Weekly Report</a>
            <a href="?type=monthly" class="<?php echo $reportType === 'monthly' ? 'active' : ''; ?>">Monthly Report</a>
            <a href="?type=yearly" class="<?php echo $reportType === 'yearly' ? 'active' : ''; ?>">Yearly Report</a>
        </div>

        <div class="chart-container-card report-criteria-card">
            <form method="GET" class="report-form-premium">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">

                <div class="report-inputs-row">
                    <?php if ($reportType === 'daily'): ?>
                        <div class="form-item">
                            <label>Select Audit Date:</label>
                            <input type="date" name="date" value="<?php echo htmlspecialchars($reportDate); ?>" class="search-box">
                        </div>
                    <?php elseif ($reportType === 'weekly'): ?>
                        <div class="form-item">
                            <label>Select Audit Week:</label>
                            <input type="week" name="week" value="<?php echo htmlspecialchars($reportWeek); ?>" class="search-box">
                        </div>
                    <?php elseif ($reportType === 'monthly'): ?>
                        <div class="form-item">
                            <label>Select Audit Month:</label>
                            <input type="month" name="month" value="<?php echo htmlspecialchars($reportMonth); ?>" class="search-box">
                        </div>
                    <?php elseif ($reportType === 'yearly'): ?>
                        <div class="form-item">
                            <label>Select Audit Year:</label>
                            <select name="year" class="search-box select-year-input">
                                <?php
                                $curr = date('Y');
                                for ($y = $curr; $y >= $curr - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $reportYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="report-buttons-row">
                    <button type="submit" class="btn-submit">⚡ Generate Report</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">🖨️ Print Form</button>
                    <button type="button" class="btn-ai-generate" style="background: var(--accent-gradient); width: auto;" onclick="downloadCSVReport()">📥 Download CSV Report</button>
                    <button type="button" class="btn-ai-generate" style="background: var(--accent-gradient); width: auto; margin-left: 8px;" onclick="downloadPDFReport()">📄 Download PDF Report</button>
                </div>
            </form>
        </div>

        <div class="chart-container-card report-summary-card">
            <div class="report-summary-header">
                <h2>Laboratory Logs Summary</h2>
                <span class="report-range-badge">
                    <?php
                    if ($reportType === 'daily') echo 'Date: ' . date('M d, Y', strtotime($reportDate));
                    elseif ($reportType === 'weekly') echo 'Current Weekly Cycle';
                    elseif ($reportType === 'monthly') echo 'Period: ' . date('F Y', strtotime($reportMonth . '-01'));
                    elseif ($reportType === 'yearly') echo 'Annual Cycle: ' . $reportYear;
                    ?>
                </span>
            </div>

            <div class="summary-stats-grid">
                <div class="stat-box-large">
                    <span class="stat-box-label">Aggregated Check-Ins</span>
                    <h3 class="stat-box-value"><?php echo $summary['total']; ?></h3>
                    <span class="stat-box-sub">Logs logged under selected timeframe</span>
                </div>
            </div>

            <h3 style="margin-top: 30px; font-family: 'Outfit'; font-size: 20px;">Check-ins Distribution by Room</h3>
            <div class="table-wrapper-outer">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Laboratory Room</th>
                            <th>Total Logged Check-Ins</th>
                            <th style="text-align: right;">Percentage Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['labs'] as $lab => $count): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($lab); ?></strong></td>
                                <td><code><?php echo $count; ?> check-ins</code></td>
                                <td style="text-align: right; font-weight: bold; color: var(--primary-color);">
                                    <?php echo $summary['total'] > 0 ? round(($count / $summary['total']) * 100, 1) : 0; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function downloadCSVReport() {
            const type = '<?php echo $reportType; ?>';
            let params = `type=${type}`;

            if (type === 'daily') {
                params += `&date=<?php echo $reportDate; ?>`;
            } else if (type === 'weekly') {
                params += `&week=<?php echo $reportWeek; ?>`;
            } else if (type === 'monthly') {
                params += `&month=<?php echo $reportMonth; ?>`;
            } else if (type === 'yearly') {
                params += `&year=<?php echo $reportYear; ?>`;
            }

            window.location.href = `admin_reports.php?format=csv&${params}`;
        }

        function downloadPDFReport() {
            const type = '<?php echo $reportType; ?>';
            let params = `type=${type}`;

            if (type === 'daily') {
                params += `&date=<?php echo $reportDate; ?>`;
            } else if (type === 'weekly') {
                params += `&week=<?php echo $reportWeek; ?>`;
            } else if (type === 'monthly') {
                params += `&month=<?php echo $reportMonth; ?>`;
            } else if (type === 'yearly') {
                params += `&year=<?php echo $reportYear; ?>`;
            }

            const url = `admin_reports.php?format=pdf&${params}`;
            
            // Show loading state
            const buttons = document.querySelectorAll('.btn-ai-generate');
            let btn = null;
            buttons.forEach(b => {
                if(b.textContent.includes('Download PDF Report')) {
                    btn = b;
                }
            });
            
            let originalText = '📄 Download PDF Report';
            if (btn) {
                originalText = btn.innerHTML;
                btn.innerHTML = '⏳ Generating PDF...';
                btn.disabled = true;
                btn.style.opacity = '0.7';
                btn.style.cursor = 'wait';
            }

            let iframe = document.getElementById('pdf-iframe');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'pdf-iframe';
                // Position off-screen but maintain dimensions for proper rendering
                iframe.style.position = 'absolute';
                iframe.style.top = '-10000px';
                iframe.style.left = '-10000px';
                iframe.style.width = '1200px';
                iframe.style.height = '1200px';
                iframe.style.border = 'none';
                document.body.appendChild(iframe);
            }
            
            // Listen for completion
            const handleMessage = (e) => {
                if (e.data === 'pdf-done' || e.data === 'pdf-error') {
                    if (btn) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    }
                    window.removeEventListener('message', handleMessage);
                }
            };
            window.addEventListener('message', handleMessage);
            
            iframe.src = url;
        }
    </script>

    <?php require_once 'search_modal.php'; ?>
</body>

</html>