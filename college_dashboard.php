<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['data'])) {
    $teacherData = json_decode($_POST['data'], true);
    
    if (!$teacherData || empty($teacherData)) {
        die("No teacher data found. Please go back and try again.");
    }
    
    // Get session, semester, and college average
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '2';
    $session = isset($_POST['session']) ? $_POST['session'] : '2023/2024';
    $collegeAverage = isset($_POST['collegeAverage']) ? $_POST['collegeAverage'] : '9.9';
    
    // Group teachers by unit
    $unitTeachers = [];
    foreach ($teacherData as $name => $data) {
        $unit = isset($data['unit']) ? $data['unit'] : 'Umum';
        if (!isset($unitTeachers[$unit])) {
            $unitTeachers[$unit] = [];
        }
        $unitTeachers[$unit][$name] = $data;
    }
    
    // Calculate unit statistics
    $unitStats = [];
    $totalTeachers = 0;
    $totalStudents = 0;
    $collegeScores = [
        'professionalism' => 0,
        'teaching' => 0,
        'english' => 0,
        'overall' => 0
    ];
    $unitsWithEnglish = 0;
    $teachersWithEnglish = 0;
    
    foreach ($unitTeachers as $unit => $teachers) {
        $unitProfScore = 0;
        $unitTeachScore = 0;
        $unitEnglishScore = 0;
        $unitOverallScore = 0;
        $unitStudents = 0;
        $teacherCount = count($teachers);
        $unitTeachersWithEnglish = 0;
        $totalTeachers += $teacherCount;
        $hasEnglish = false;
        
        foreach ($teachers as $name => $data) {
            $unitProfScore += $data['scores']['professionalism']['total'];
            $unitTeachScore += $data['scores']['teaching']['total'];
            $unitStudents += $data['responses'];
            
            // Check if this teacher has English scores
            if (isset($data['scores']['english']['total']) && $data['scores']['english']['total'] > 0) {
                $unitEnglishScore += $data['scores']['english']['total'];
                $unitTeachersWithEnglish++;
                $hasEnglish = true;
            }
            
            // Calculate individual overall score
            $teacherOverall = calculateOverallScore($data);
            $unitOverallScore += $teacherOverall;
        }
        
        $totalStudents += $unitStudents;
        $teachersWithEnglish += $unitTeachersWithEnglish;
        
        // Calculate unit averages
        $unitStats[$unit] = [
            'teacherCount' => $teacherCount,
            'studentCount' => $unitStudents,
            'scores' => [
                'professionalism' => round($unitProfScore / $teacherCount, 1),
                'teaching' => round($unitTeachScore / $teacherCount, 1),
                'english' => $hasEnglish && $unitTeachersWithEnglish > 0 ? round($unitEnglishScore / $unitTeachersWithEnglish, 1) : 0,
                'overall' => round($unitOverallScore / $teacherCount, 1)
            ],
            'hasEnglish' => $hasEnglish,
            'teachersWithEnglish' => $unitTeachersWithEnglish
        ];
        
        // Add to college totals
        $collegeScores['professionalism'] += $unitProfScore;
        $collegeScores['teaching'] += $unitTeachScore;
        if ($hasEnglish) {
            $collegeScores['english'] += $unitEnglishScore;
            $unitsWithEnglish++;
        }
        $collegeScores['overall'] += $unitOverallScore;
    }
    
    // Calculate college-wide averages
    if ($totalTeachers > 0) {
        $collegeScores['professionalism'] = round($collegeScores['professionalism'] / $totalTeachers, 1);
        $collegeScores['teaching'] = round($collegeScores['teaching'] / $totalTeachers, 1);
        
        // Fix for English average - only divide by teachers who have English scores
        $collegeScores['english'] = $teachersWithEnglish > 0 ? round($collegeScores['english'] / $teachersWithEnglish, 1) : 0;
        
        $collegeScores['overall'] = round($collegeScores['overall'] / $totalTeachers, 1);
    }
    
    // Sort units by overall score
    uasort($unitStats, function($a, $b) {
        return $b['scores']['overall'] <=> $a['scores']['overall'];
    });
    
    // Generate the dashboard HTML
    $dashboardTitle = "PAPAN PEMUKA PRESTASI KOLEJ MATRIKULASI PULAU PINANG SEMESTER $semester SESI $session";
    $htmlContent = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $dashboardTitle . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding: 20px;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .dashboard-header {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .card-title {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .card-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .chart-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: 400px;
        }
        .table-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .table th {
            background-color: #f8f9fa;
        }
        .excellent {
            color: #28a745;
        }
        .good {
            color: #17a2b8;
        }
        .moderate {
            color: #ffc107;
        }
        .bg-excellent {
            background-color: rgba(40, 167, 69, 0.1) !important;
        }
        .bg-good {
            background-color: rgba(23, 162, 184, 0.1) !important;
        }
        .section-title {
            margin-bottom: 20px;
            font-weight: bold;
            color: #0d6efd;
        }
        .print-only {
            display: none;
        }
        @media print {
            .print-only {
                display: block;
            }
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .chart-container, .stat-card, .table-container, .dashboard-header {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="row no-print mb-3">
            <div class="col-12">
                <button onclick="window.print();" class="btn btn-primary"><i class="bi bi-printer"></i> Print Dashboard</button>
                <a href="index.php" class="btn btn-secondary ms-2"><i class="bi bi-house"></i> Back to Home</a>
            </div>
        </div>
        
        <div class="dashboard-header">
            <h1>' . $dashboardTitle . '</h1>
            <p class="lead print-only">Generated on: ' . date('d-m-Y H:i:s') . '</p>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="card-title">JUMLAH UNIT</div>
                    <div class="card-value">' . count($unitStats) . '</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="card-title">JUMLAH PENSYARAH</div>
                    <div class="card-value">' . $totalTeachers . '</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="card-title">JUMLAH PELAJAR</div>
                    <div class="card-value">' . $totalStudents . '</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="card-title">PURATA KESELURUHAN KOLEJ</div>
                    <div class="card-value ' . getScoreClass($collegeScores['overall']) . '">' . $collegeScores['overall'] . '</div>
                    <div>' . getAchievementLevel($collegeScores['overall']) . '</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h4 class="section-title">Prestasi Mengikut Kategori</h4>
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h4 class="section-title">Prestasi Mengikut Unit</h4>
                    <canvas id="unitChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <h4 class="section-title">Statistik Mengikut Unit</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Bilangan Pensyarah</th>
                            <th>Bilangan Pelajar</th>
                            <th>Purata Profesionalisme (A)</th>
                            <th>Purata Pengajaran (B)</th>
                            <th>Purata Bahasa Inggeris (C)</th>
                            <th>Purata Keseluruhan</th>
                            <th>Tahap Pencapaian</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($unitStats as $unit => $stats) {
        $achievementLevel = getAchievementLevel($stats['scores']['overall']);
        $rowClass = '';
        if ($achievementLevel == 'CEMERLANG') {
            $rowClass = 'bg-excellent';
        } elseif ($achievementLevel == 'BAIK') {
            $rowClass = 'bg-good';
        }
        
        $htmlContent .= '
                        <tr class="' . $rowClass . '">
                            <td>' . htmlspecialchars($unit) . '</td>
                            <td>' . $stats['teacherCount'] . '</td>
                            <td>' . $stats['studentCount'] . '</td>
                            <td>' . $stats['scores']['professionalism'] . '</td>
                            <td>' . $stats['scores']['teaching'] . '</td>
                            <td>' . ($stats['hasEnglish'] ? $stats['scores']['english'] : '-') . '</td>
                            <td class="' . getScoreClass($stats['scores']['overall']) . '"><strong>' . $stats['scores']['overall'] . '</strong></td>
                            <td><strong>' . $achievementLevel . '</strong></td>
                        </tr>';
    }
    
    // Add college average row
    $htmlContent .= '
                        <tr class="table-secondary">
                            <td><strong>PURATA KOLEJ</strong></td>
                            <td>' . $totalTeachers . '</td>
                            <td>' . $totalStudents . '</td>
                            <td><strong>' . $collegeScores['professionalism'] . '</strong></td>
                            <td><strong>' . $collegeScores['teaching'] . '</strong></td>
                            <td><strong>' . ($collegeScores['english'] > 0 ? $collegeScores['english'] : '-') . '</strong></td>
                            <td class="' . getScoreClass($collegeScores['overall']) . '"><strong>' . $collegeScores['overall'] . '</strong></td>
                            <td><strong>' . getAchievementLevel($collegeScores['overall']) . '</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="table-container">
                    <h4 class="section-title">Julat Skor dan Tahap Pencapaian</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Julat Skor</th>
                                <th>Tahap Pencapaian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1.00 – 2.99</td>
                                <td>Sangat Lemah</td>
                            </tr>
                            <tr>
                                <td>3.00 – 4.99</td>
                                <td>Lemah</td>
                            </tr>
                            <tr>
                                <td>5.00 – 6.99</td>
                                <td>Sederhana</td>
                            </tr>
                            <tr>
                                <td>7.00 – 8.99</td>
                                <td>Baik</td>
                            </tr>
                            <tr>
                                <td>9.00 – 10.00</td>
                                <td>Cemerlang</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Laporan ini dijana oleh Sistem Penilaian ePP Pensyarah KMPP</p>
			<p>EPP Analitik © | Developed by OneExa EduTech Solutions</p>
            <p>Tarikh: ' . date('d/m/Y') . '</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Category performance chart
        var ctxCategory = document.getElementById("categoryChart").getContext("2d");
        var categoryChart = new Chart(ctxCategory, {
            type: "bar",
            data: {
                labels: ["Profesionalisme (A)", "Pengajaran (B)", "Bahasa Inggeris (C)", "Keseluruhan"],
                datasets: [{
                    label: "Purata Skor",
                    data: [' . 
                        $collegeScores['professionalism'] . ', ' . 
                        $collegeScores['teaching'] . ', ' . 
                        $collegeScores['english'] . ', ' . 
                        $collegeScores['overall'] . '
                    ],
                    backgroundColor: [
                        "rgba(255, 99, 132, 0.7)",
                        "rgba(54, 162, 235, 0.7)",
                        "rgba(255, 206, 86, 0.7)",
                        "rgba(75, 192, 192, 0.7)"
                    ],
                    borderColor: [
                        "rgba(255, 99, 132, 1)",
                        "rgba(54, 162, 235, 1)",
                        "rgba(255, 206, 86, 1)",
                        "rgba(75, 192, 192, 1)"
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10
                    }
                }
            }
        });
        
        // Unit performance chart
        var ctxUnit = document.getElementById("unitChart").getContext("2d");
        var unitChart = new Chart(ctxUnit, {
            type: "bar",
            data: {
                labels: [';
    
    // Add unit names for chart labels
    $unitNames = array_keys($unitStats);
    foreach ($unitNames as $index => $unit) {
        $htmlContent .= '"' . $unit . '"';
        if ($index < count($unitNames) - 1) {
            $htmlContent .= ', ';
        }
    }
    
    $htmlContent .= '],
                datasets: [{
                    label: "Purata Keseluruhan",
                    data: [';
    
    // Add unit overall scores for chart data
    foreach ($unitStats as $index => $stats) {
        $htmlContent .= $stats['scores']['overall'];
        if ($index !== array_key_last($unitStats)) {
            $htmlContent .= ', ';
        }
    }
    
    $htmlContent .= '],
                    backgroundColor: "rgba(75, 192, 192, 0.7)",
                    borderColor: "rgba(75, 192, 192, 1)",
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10
                    }
                }
            }
        });
    </script>
</body>
</html>';

    // Output the HTML
    echo $htmlContent;
    exit;
} else {
    // If accessed directly without POST data
    header("Location: index.php");
    exit;
}

/**
 * Calculate overall average score correctly based on available sections
 * @param array $data Teacher data
 * @return float Overall average score
 */
function calculateOverallScore($data) {
    $sections = 0;
    $totalScore = 0;
    
    // Add professionalism score if it exists and is not zero
    if (isset($data['scores']['professionalism']['total']) && $data['scores']['professionalism']['total'] > 0) {
        $totalScore += $data['scores']['professionalism']['total'];
        $sections++;
    }
    
    // Add teaching score if it exists and is not zero
    if (isset($data['scores']['teaching']['total']) && $data['scores']['teaching']['total'] > 0) {
        $totalScore += $data['scores']['teaching']['total'];
        $sections++;
    }
    
    // Add English score ONLY if it exists AND is not zero
    if (isset($data['scores']['english']['total']) && $data['scores']['english']['total'] > 0) {
        $totalScore += $data['scores']['english']['total'];
        $sections++;
    }
    
    // Calculate average of available sections
    if ($sections > 0) {
        return round($totalScore / $sections, 1);
    } else {
        return 0;
    }
}

/**
 * Get achievement level based on score
 * @param float $score The score to evaluate
 * @return string Achievement level
 */
function getAchievementLevel($score) {
    if ($score >= 9.0) return "CEMERLANG";
    if ($score >= 7.0) return "BAIK";
    if ($score >= 5.0) return "SEDERHANA";
    if ($score >= 3.0) return "LEMAH";
    return "SANGAT LEMAH";
}

/**
 * Get CSS class for score
 * @param float $score The score to evaluate
 * @return string CSS class
 */
function getScoreClass($score) {
    if ($score >= 9.0) return "excellent";
    if ($score >= 7.0) return "good";
    if ($score >= 5.0) return "moderate";
    return "";
}
?>