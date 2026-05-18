<?php
require_once 'database/db.php';

echo "Running Analytics Verification...\n";

try {
    // 1. Setup mock records
    $pdo->exec("DELETE FROM sitin_records WHERE purpose LIKE 'MOCK_%'");
    
    // Insert Mock Data
    $mockData = [
        ['purpose' => 'Java'],
        ['purpose' => 'Java'],
        ['purpose' => 'Java'],
        ['purpose' => 'Python'],
        ['purpose' => 'Python'],
        ['purpose' => 'Others: self-study'],
        ['purpose' => 'Others: Rust'], // Rust is in recognized list, should extract Rust!
        ['purpose' => 'Others: exam'],
        ['purpose' => 'browsing'],
        ['purpose' => 'MOCK_programming'], // Programming should count!
    ];
    
    $stmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, purpose, time_in) VALUES (1, '524', ?, datetime('now'))");
    foreach ($mockData as $data) {
        $stmt->execute([$data['purpose']]);
    }
    
    echo "Inserted mock sit-in records.\n";
    
    // 2. Fetch all purposes
    $stmt = $pdo->query("
        SELECT purpose, COUNT(*) as count 
        FROM sitin_records 
        WHERE purpose IS NOT NULL AND purpose != ''
        GROUP BY purpose
    ");
    $purposes = $stmt->fetchAll();
    
    $grouped = [];
    $othersCount = 0;
    
    $programmingLanguages = [
        'java', 'python', 'c++', 'c#', 'c', 'php', 'javascript', 'html/css', 'sql', 'asp.net', 'ruby', 'swift', 'kotlin', 'go', 'typescript', 'rust', 'perl', 'scala', 'haskell', 'r', 'dart', 'assembly', 'cobol', 'fortran', 'matlab', 'vb.net', 'visual basic', 'bash', 'powershell', 'objective-c', 'html', 'css', 'programming'
    ];
    
    foreach ($purposes as $row) {
        $rawPurpose = trim($row['purpose']);
        $count = intval($row['count']);
        
        // Clean up "Others: " prefix if present
        $cleanedPurpose = $rawPurpose;
        if (stripos($rawPurpose, 'others:') === 0) {
            $cleanedPurpose = trim(substr($rawPurpose, 7));
        }
        
        $lower = strtolower($cleanedPurpose);
        
        // Check if it matches a known programming language
        if (in_array($lower, $programmingLanguages)) {
            // Normalize names
            $display = $cleanedPurpose;
            if ($lower === 'java') $display = 'Java';
            elseif ($lower === 'python') $display = 'Python';
            elseif ($lower === 'c++') $display = 'C++';
            elseif ($lower === 'c#') $display = 'C#';
            elseif ($lower === 'c') $display = 'C';
            elseif ($lower === 'php') $display = 'PHP';
            elseif ($lower === 'javascript') $display = 'JavaScript';
            elseif ($lower === 'html/css' || $lower === 'html' || $lower === 'css') $display = 'HTML/CSS';
            elseif ($lower === 'sql') $display = 'SQL';
            elseif ($lower === 'asp.net') $display = 'ASP.NET';
            elseif ($lower === 'ruby') $display = 'Ruby';
            elseif ($lower === 'swift') $display = 'Swift';
            elseif ($lower === 'kotlin') $display = 'Kotlin';
            elseif ($lower === 'go') $display = 'Go';
            elseif ($lower === 'typescript') $display = 'TypeScript';
            else $display = ucfirst($cleanedPurpose);
            
            if (!isset($grouped[$display])) {
                $grouped[$display] = 0;
                }
            $grouped[$display] += $count;
        } else {
            $othersCount += $count;
        }
    }
    
    // Sort programming languages by count descending
    arsort($grouped);
    
    $purposeData = [];
    foreach ($grouped as $purpose => $count) {
        $purposeData[] = [
            'purpose' => $purpose,
            'count' => $count
        ];
    }
    
    if ($othersCount > 0) {
        $purposeData[] = [
            'purpose' => 'Others',
            'count' => $othersCount
        ];
    }
    
    usort($purposeData, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    $purposeData = array_slice($purposeData, 0, 6);
    
    echo "\nCategorized Purpose Distribution:\n";
    print_r($purposeData);
    
    // 3. Clean up
    $pdo->exec("DELETE FROM sitin_records WHERE purpose LIKE 'MOCK_%' OR purpose IN ('Java', 'Python', 'Others: self-study', 'Others: Rust', 'Others: exam', 'browsing')");
    echo "\nCleaned up mock records successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
