<?php
session_start();
require_once '../database/db.php';

// Auth Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    die("Not authenticated");
}

$stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ? AND id_number = '2664388'");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    die("Access denied");
}

$type = $_GET['type'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');
$week = $_GET['week'] ?? date('Y-\WW');
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');

$title = "Sit-In Report";
$filename = "report";

try {
    $query = "
        SELECT r.id, u.id_number, u.firstname, u.lastname, u.course, u.level, 
               r.lab_number, r.pc_number, r.time_in, r.time_out, r.purpose
        FROM sitin_records r
        JOIN users u ON r.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($type === 'daily' && !empty($date)) {
        $query .= " AND date(r.time_in) = ?";
        $params[] = $date;
        $title = "Daily Sit-In Report - " . date('F j, Y', strtotime($date));
        $filename = "CCS_Daily_Report_" . $date;
    } elseif ($type === 'weekly' && !empty($week)) {
        // SQLite doesn't easily support week numbers, so fallback to last 7 days from selected week's rough date, or just simple substring
        // For simplicity, assuming week is YYYY-Www format from input type="week"
        $yearPart = substr($week, 0, 4);
        $weekPart = substr($week, 6);
        $query .= " AND strftime('%Y', r.time_in) = ? AND CAST(strftime('%W', r.time_in) AS INTEGER) = ?";
        $params[] = $yearPart;
        $params[] = (int)$weekPart;
        $title = "Weekly Sit-In Report - Week $weekPart, $yearPart";
        $filename = "CCS_Weekly_Report_" . $week;
    } elseif ($type === 'monthly' && !empty($month)) {
        $query .= " AND strftime('%Y-%m', r.time_in) = ?";
        $params[] = $month;
        $title = "Monthly Sit-In Report - " . date('F Y', strtotime($month . '-01'));
        $filename = "CCS_Monthly_Report_" . $month;
    } else {
        $query .= " AND date(r.time_in) = date('now')";
        $title = "Sit-In Report";
        $filename = "CCS_SitIn_Report_" . date('Ymd');
    }
    
    $query .= " ORDER BY r.time_in DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <!-- Include html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            margin: 0;
            padding: 20px;
        }
        
        .report-container {
            width: 100%;
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
            margin: 0 0 10px 0;
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

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        tbody tr:nth-child(even) {
            background-color: #f8fafc;
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
            top: 0; left: 0; right: 0; bottom: 0;
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Hide UI elements from PDF */
        @media print {
            #downloading-overlay { display: none !important; }
            .report-container { box-shadow: none; padding: 0; }
            body { background: #fff; }
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
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p>Generated on <?php echo date('F j, Y, g:i a'); ?></p>
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
                        <td colspan="8" style="text-align: center; py-4">No records found for this period.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['lastname'] . ', ' . $row['firstname']); ?></td>
                            <td><?php echo htmlspecialchars($row['course'] . '-' . $row['level']); ?></td>
                            <td><?php echo htmlspecialchars($row['lab_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['pc_number'] ?: 'N/A'); ?></td>
                            <td><?php echo date('M d, h:i A', strtotime($row['time_in'])); ?></td>
                            <td>
                                <?php if ($row['time_out']): ?>
                                    <?php echo date('M d, h:i A', strtotime($row['time_out'])); ?>
                                <?php else: ?>
                                    <span class="badge-ongoing">Ongoing</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst($row['purpose'] ?: 'N/A')); ?></td>
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
            
            // html2pdf options
            const opt = {
                margin:       10,
                filename:     '<?php echo $filename; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };

            // Generate and download PDF
            html2pdf().set(opt).from(element).save().then(() => {
                document.getElementById('downloading-overlay').innerHTML = `
                    <div style="font-size: 40px; margin-bottom: 20px;">✅</div>
                    <h2>PDF Downloaded Successfully</h2>
                    <p>You can close this tab now.</p>
                `;
            }).catch(err => {
                console.error('Error generating PDF:', err);
                document.getElementById('downloading-overlay').innerHTML = `
                    <div style="font-size: 40px; margin-bottom: 20px; color: red;">❌</div>
                    <h2>Failed to generate PDF</h2>
                    <p>${err.message || 'An unknown error occurred.'}</p>
                `;
            });
        };
    </script>

</html>
