<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Check if teacher data has valid English section data
 * @param array $teacherData The teacher data to evaluate
 * @return bool True if valid English data exists, false otherwise
 */
function hasValidEnglishData($teacherData) {
    return isset($teacherData['scores']['english']) && 
           isset($teacherData['scores']['english']['total']) && 
           is_numeric($teacherData['scores']['english']['total']) &&
           $teacherData['scores']['english']['total'] > 0 &&
           isset($teacherData['scores']['english']['items']) &&
           !empty($teacherData['scores']['english']['items']);
}

/**
 * Calculate overall score based on available sections
 * @param array $teacherData The teacher data to evaluate
 * @return float The calculated overall score
 */
function calculateOverallScore($teacherData) {
    $avgA = $teacherData['scores']['professionalism']['total'];
    $avgB = $teacherData['scores']['teaching']['total'];
    
    if (hasValidEnglishData($teacherData)) {
        $avgC = $teacherData['scores']['english']['total'];
        return ($avgA + $avgB + $avgC) / 3;
    } else {
        return ($avgA + $avgB) / 2;
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
 * Get color for achievement level
 * @param string $achievement The achievement level
 * @return string The color code
 */
function getAchievementColor($achievement) {
    switch ($achievement) {
        case 'CEMERLANG': return '#28a745'; // Green
        case 'BAIK': return '#17a2b8'; // Teal
        case 'SEDERHANA': return '#ffc107'; // Yellow
        case 'LEMAH': return '#fd7e14'; // Orange
        case 'SANGAT LEMAH': return '#dc3545'; // Red
        default: return '#6c757d'; // Gray
    }
}

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
    
    // Get unit mappings if provided
    $unitMappings = [];
    if (isset($_POST['unitMappings'])) {
        $unitMappings = json_decode($_POST['unitMappings'], true) ?: [];
    }
    
    // Create reports output directory
    $outputDir = 'unit_reports_' . date('Y-m-d_H-i-s');
    if (!file_exists($outputDir)) {
        if (!mkdir($outputDir, 0777, true)) {
            die("Failed to create directory for report files.");
        }
    }
    
    // Group teachers by unit
    $unitTeachers = [];
    foreach ($teacherData as $name => $data) {
        $unit = isset($data['unit']) ? $data['unit'] : 'Umum';
        if (!isset($unitTeachers[$unit])) {
            $unitTeachers[$unit] = [];
        }
        $unitTeachers[$unit][$name] = $data;
    }
    
    // Track successes and failures
    $successCount = 0;
    $unitCount = count($unitTeachers);
    $errors = [];
    
    // Create index file for unit reports
    $indexHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Reports Index - Semester ' . $semester . ' Sesi ' . $session . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .card {
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .unit-link {
            text-decoration: none;
            color: inherit;
        }
        .report-header {
            background-color: #f8f9fa;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
            border-left: 5px solid #0d6efd;
        }
        .unit-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
            background-color: #e9ecef;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="report-header">
            <h1>Unit Evaluation Reports</h1>
            <p class="lead">Semester ' . $semester . ' Sesi ' . $session . '</p>
            <p>Click on a unit to view its detailed evaluation report.</p>
            <a href="index.php" class="btn btn-secondary"><i class="bi bi-house"></i> Back to Home</a>
        </div>
        
        <div class="row">
';
    
    // Create a report for each unit
    foreach ($unitTeachers as $unit => $teachers) {
        // Generate safe filename for unit
        $safeUnitName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $unit);
        $reportFilename = $safeUnitName . '_report.html';
        $reportPath = $outputDir . '/' . $reportFilename;
        
        // Add unit to index
        $teacherCount = count($teachers);
        $indexHtml .= '
            <div class="col-md-4">
			<a href="' . $reportFilename . '" class="unit-link">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">' . htmlspecialchars($unit) . '</h5>
                            <p class="card-text">
                                <i class="bi bi-people"></i> ' . $teacherCount . ' Teachers<br>
                                <small class="text-muted">Click to view full report</small>
                            </p>
                        </div>
                    </div>
                </a>
            </div>';
        
        try {
            // Calculate unit averages
            $unitAvgA = 0;
            $unitAvgB = 0;
            $unitAvgC = 0;
            $unitOverall = 0;
            $totalTeachers = count($teachers);
            $totalStudents = 0;
            $teachersWithEnglish = 0; // Count teachers with English section
            
            foreach ($teachers as $name => $data) {
                $avgA = round($data['scores']['professionalism']['total'], 1);
                $avgB = round($data['scores']['teaching']['total'], 1);
                
                // Use helper function to check for valid English data
                $hasEnglish = hasValidEnglishData($data);
                
                if ($hasEnglish) {
                    $avgC = round($data['scores']['english']['total'], 1);
                    $overall = round(($avgA + $avgB + $avgC) / 3, 1);
                    $unitAvgC += $avgC;
                    $teachersWithEnglish++;
                } else {
                    // If no English section, calculate average from just Professionalism and Teaching
                    $overall = round(($avgA + $avgB) / 2, 1);
                }
                
                $unitAvgA += $avgA;
                $unitAvgB += $avgB;
                $unitOverall += $overall;
                $totalStudents += $data['responses'];
            }
            
            $unitAvgA = round($unitAvgA / $totalTeachers, 1);
            $unitAvgB = round($unitAvgB / $totalTeachers, 1);
            
            // Calculate English average only if there are teachers with English data
            $unitAvgC = $teachersWithEnglish > 0 ? round($unitAvgC / $teachersWithEnglish, 1) : 0;
            
            $unitOverall = round($unitOverall / $totalTeachers, 1);
            $unitAchievement = getAchievementLevel($unitOverall);
            
            // Generate unit report HTML
            $unitHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($unit) . ' - Unit Evaluation Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .header-section {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 5px solid #0d6efd;
        }
        .unit-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .stat-box {
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
            width: 23%;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .table-responsive {
            margin-bottom: 20px;
        }
        .chart-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .table-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .achievement-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            color: white;
            background-color: ' . getAchievementColor($unitAchievement) . ';
            margin-left: 10px;
        }
        .teacher-row-excellent {
            background-color: rgba(40, 167, 69, 0.1) !important;
        }
        .teacher-row-good {
            background-color: rgba(23, 162, 184, 0.1) !important;
        }
        .print-button {
            margin-bottom: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                background-color: white;
            }
            .header-section, .chart-container, .table-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row no-print print-button">
            <div class="col-12">
                <button onclick="window.print();" class="btn btn-primary"><i class="bi bi-printer"></i> Print Report</button>
                <a href="index.html" class="btn btn-secondary ms-2"><i class="bi bi-arrow-left"></i> Back to Index</a>
                <a href="index.php" class="btn btn-secondary ms-2"><i class="bi bi-house"></i> Back to Home</a>
            </div>
        </div>
        
        <div class="header-section">
            <h1>' . htmlspecialchars($unit) . ' Unit Evaluation Report</h1>
            <p class="lead">Semester ' . $semester . ' Sesi ' . $session . '</p>
            <p><strong>Total Teachers:</strong> ' . $totalTeachers . ' | <strong>Total Students:</strong> ' . $totalStudents . '</p>
            
            <div class="unit-stats">
                <div class="stat-box">
                    <div class="stat-label">OVERALL SCORE</div>
                    <div class="stat-value" style="color: ' . getAchievementColor($unitAchievement) . ';">' . $unitOverall . '</div>
                    <div><span class="achievement-badge">' . $unitAchievement . '</span></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">PROFESSIONALISM</div>
                    <div class="stat-value" style="color: #fd7e14;">' . $unitAvgA . '</div>
                    <div>Section A</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">TEACHING</div>
                    <div class="stat-value" style="color: #17a2b8;">' . $unitAvgB . '</div>
                    <div>Section B</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">ENGLISH</div>';
            
            // Only show English average if there are teachers with English data
            if ($teachersWithEnglish > 0) {
                $unitHtml .= '
                    <div class="stat-value" style="color: #28a745;">' . $unitAvgC . '</div>
                    <div>Section C</div>';
            } else {
                $unitHtml .= '
                    <div class="stat-value text-muted">N/A</div>
                    <div>Not Applicable</div>';
            }
            
            $unitHtml .= '
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <h4>Unit Performance by Category</h4>
                    <canvas id="unitCategoryChart" height="120"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <h4>Achievement Distribution</h4>
                    <canvas id="achievementChart" height="260"></canvas>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <h4>Teacher Performance Summary</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Teacher Name</th>
                            <th>Course</th>
                            <th>Students</th>
                            <th>Prof. (A)</th>
                            <th>Teaching (B)</th>
                            <th>English (C)</th>
                            <th>Overall</th>
                            <th>Achievement</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            // Sort teachers by overall score (descending)
            uasort($teachers, function($a, $b) {
                $overall_a = calculateOverallScore($a);
                $overall_b = calculateOverallScore($b);
                return $overall_b <=> $overall_a; // Descending order
            });
            
            // Achievement distribution for chart
            $achievementCounts = [
                'CEMERLANG' => 0,
                'BAIK' => 0,
                'SEDERHANA' => 0,
                'LEMAH' => 0,
                'SANGAT LEMAH' => 0
            ];
            
            // Add each teacher row
            foreach ($teachers as $name => $data) {
                $avgA = round($data['scores']['professionalism']['total'], 1);
                $avgB = round($data['scores']['teaching']['total'], 1);
                
                // Use the helper function to check if English section exists and has valid data
                $hasEnglish = hasValidEnglishData($data);
                
                if ($hasEnglish) {
                    $avgC = round($data['scores']['english']['total'], 1);
                    $overall = round(($avgA + $avgB + $avgC) / 3, 1);
                } else {
                    $avgC = 'N/A'; // Not applicable
                    $overall = round(($avgA + $avgB) / 2, 1);
                }
                
                $achievement = getAchievementLevel($overall);
                
                // Count for achievement chart
                if (isset($achievementCounts[$achievement])) {
                    $achievementCounts[$achievement]++;
                }
                
                // Row class based on achievement
                $rowClass = '';
                if ($achievement == 'CEMERLANG') {
                    $rowClass = 'teacher-row-excellent';
                } elseif ($achievement == 'BAIK') {
                    $rowClass = 'teacher-row-good';
                }
                
                $unitHtml .= '
                        <tr class="' . $rowClass . '">
                            <td>' . htmlspecialchars($name) . '</td>
                            <td>' . htmlspecialchars($data['course']) . '</td>
                            <td>' . $data['responses'] . '</td>
                            <td>' . $avgA . '</td>
                            <td>' . $avgB . '</td>
                            <td>' . $avgC . '</td>
                            <td><strong>' . $overall . '</strong></td>
                            <td><span class="badge" style="background-color: ' . getAchievementColor($achievement) . ';">' . $achievement . '</span></td>
                        </tr>';
            }
            
            $unitHtml .= '
                    </tbody>
                    <tfoot>
                        <tr class="table-secondary">
                            <td colspan="3"><strong>Unit Average</strong></td>
                            <td><strong>' . $unitAvgA . '</strong></td>
                            <td><strong>' . $unitAvgB . '</strong></td>';
            
            // Only show English average if applicable
            if ($teachersWithEnglish > 0) {
                $unitHtml .= '
                            <td><strong>' . $unitAvgC . '</strong></td>';
            } else {
                $unitHtml .= '
                            <td>N/A</td>';
            }
            
            $unitHtml .= '
                            <td><strong>' . $unitOverall . '</strong></td>
                            <td><span class="badge" style="background-color: ' . getAchievementColor($unitAchievement) . ';">' . $unitAchievement . '</span></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <div class="table-container">
            <h4>Detailed Scores Breakdown</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th rowspan="2">Teacher Name</th>
                            <th colspan="3">Professionalism</th>
                            <th colspan="7">Teaching & Learning</th>';
            
            // Only include English columns if there are teachers with English data
            if ($teachersWithEnglish > 0) {
                $unitHtml .= '
                            <th colspan="3">English</th>';
            }
            
            $unitHtml .= '
                        </tr>
                        <tr>
                            <th title="Penampilan pensyarah menarik minat">A1</th>
                            <th title="Pensyarah menepati waktu">A2</th>
                            <th title="Pensyarah mengambil berat">A3</th>
                            <th title="Pensyarah berpengetahuan luas">B4</th>
                            <th title="Pelajar faham isi kandungan">B5</th>
                            <th title="Bahan bantu mengajar efektif">B6</th>
                            <th title="Kaedah pengajaran membantu">B7</th>
                            <th title="Latihan/tugasan/kuiz menguji">B8</th>
                            <th title="Pembelajaran dalam talian">B9</th>
                            <th title="Bahan diakses mudah">B10</th>';
            
            // Only include English headers if there are teachers with English data
            if ($teachersWithEnglish > 0) {
                $unitHtml .= '
                            <th title="Mengajar dalam B. Inggeris">C11</th>
                            <th title="Bahan dalam B. Inggeris">C12</th>
                            <th title="Perbincangan B. Inggeris">C13</th>';
            }
            
            $unitHtml .= '
                        </tr>
                    </thead>
                    <tbody>';
            
            // Add detailed scores for each teacher
            foreach ($teachers as $name => $data) {
                $unitHtml .= '
                        <tr>
                            <td>' . htmlspecialchars($name) . '</td>
                            <td>' . $data['scores']['professionalism']['items']['appearance'] . '</td>
                            <td>' . $data['scores']['professionalism']['items']['punctuality'] . '</td>
                            <td>' . $data['scores']['professionalism']['items']['care'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['knowledge'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['understanding'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['materials'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['methods'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['exercises'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['online_learning'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['online_materials'] . '</td>';
                
                // Use helper function to check for valid English data
                $hasEnglish = hasValidEnglishData($data);
                
                // Only include English scores if there are teachers with English data in the unit
                if ($teachersWithEnglish > 0) {
                    if ($hasEnglish) {
                        $unitHtml .= '
                            <td>' . $data['scores']['english']['items']['teaching'] . '</td>
                            <td>' . $data['scores']['english']['items']['materials'] . '</td>
                            <td>' . $data['scores']['english']['items']['discussion'] . '</td>';
                    } else {
                        $unitHtml .= '
                            <td>N/A</td>
                            <td>N/A</td>
                            <td>N/A</td>';
                    }
                }
                
                $unitHtml .= '
                        </tr>';
            }
            
            $unitHtml .= '
                    </tbody>
                </table>
                
                <div class="mt-3">
                    <h6>Column Key:</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <p><strong>A1:</strong> Penampilan pensyarah menarik minat</p>
                            <p><strong>A2:</strong> Pensyarah menepati waktu</p>
                            <p><strong>A3:</strong> Pensyarah mengambil berat</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>B4:</strong> Pengetahuan luas</p>
                            <p><strong>B5:</strong> Kefahaman isi kandungan</p>
                            <p><strong>B6:</strong> Bahan bantu mengajar</p>
                            <p><strong>B7:</strong> Kaedah pengajaran</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>B8:</strong> Latihan/tugasan</p>
                            <p><strong>B9:</strong> Pembelajaran dalam talian</p>
                            <p><strong>B10:</strong> Bahan mudah diakses</p>
                        </div>';
            
            // Only include English legend if applicable
            if ($teachersWithEnglish > 0) {
                $unitHtml .= '
                        <div class="col-md-3">
                            <p><strong>C11:</strong> Mengajar dalam BI</p>
                            <p><strong>C12:</strong> Bahan dalam BI</p>
                            <p><strong>C13:</strong> Perbincangan BI</p>
                        </div>';
            }
            
            $unitHtml .= '
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12 text-center mt-4 mb-4">
                <p><strong>Julat Skor:</strong> 
                    1.00–2.99 (Sangat Lemah) | 
                    3.00–4.99 (Lemah) | 
                    5.00–6.99 (Sederhana) | 
                    7.00–8.99 (Baik) | 
                    9.00–10.00 (Cemerlang)
                </p>
                <div class="text-muted small">
                    <p>Laporan ini dijana oleh Sistem Penilaian ePP Pensyarah KMPP</p>
					<p>| Developed by OneExa EduTech Solutions for Kolej Matrikulasi Pulau Pinang</p>
                    <p>Maklumat ini dijana pada ' . date('d/m/Y H:i:s') . '</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>';
            
            // Prepare chart data array based on whether English data exists - FIXED VERSION WITHOUT SPACES
            if ($teachersWithEnglish > 0) {
                $chartDataLabels = '["Professionalism (A)","Teaching & Learning (B)","English (C)","Overall","College Average"]';
                $chartDataValues = '[' . $unitAvgA . ',' . $unitAvgB . ',' . $unitAvgC . ',' . $unitOverall . ',' . $collegeAverage . ']';
                $chartColors = '["rgba(253,126,20,0.7)","rgba(23,162,184,0.7)","rgba(40,167,69,0.7)","rgba(32,201,151,0.7)","rgba(108,117,125,0.7)"]';
                $chartBorders = '["rgba(253,126,20,1)","rgba(23,162,184,1)","rgba(40,167,69,1)","rgba(32,201,151,1)","rgba(108,117,125,1)"]';
            } else {
                // Exclude English from chart when not applicable
                $chartDataLabels = '["Professionalism (A)","Teaching & Learning (B)","Overall","College Average"]';
                $chartDataValues = '[' . $unitAvgA . ',' . $unitAvgB . ',' . $unitOverall . ',' . $collegeAverage . ']';
                $chartColors = '["rgba(253,126,20,0.7)","rgba(23,162,184,0.7)","rgba(32,201,151,0.7)","rgba(108,117,125,0.7)"]';
                $chartBorders = '["rgba(253,126,20,1)","rgba(23,162,184,1)","rgba(32,201,151,1)","rgba(108,117,125,1)"]';
            }
            
            $unitHtml .= '
        // Chart for unit categories
        var unitCtx = document.getElementById("unitCategoryChart").getContext("2d");
        var unitChart = new Chart(unitCtx, {
            type: "bar",
            data: {
                labels: ' . $chartDataLabels . ',
                datasets: [{
                    label: "Average Score",
                    data: ' . $chartDataValues . ',
                    backgroundColor: ' . $chartColors . ',
                    borderColor: ' . $chartBorders . ',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10
                    }
                }
            }
        });
        
        // Chart for achievement distribution
        var achievementCtx = document.getElementById("achievementChart").getContext("2d");
        var achievementChart = new Chart(achievementCtx, {
            type: "pie",
            data: {
                labels: ["Cemerlang","Baik","Sederhana","Lemah","Sangat Lemah"],
                datasets: [{
                    data: [
                        ' . $achievementCounts['CEMERLANG'] . ',
                        ' . $achievementCounts['BAIK'] . ',
                        ' . $achievementCounts['SEDERHANA'] . ',
                        ' . $achievementCounts['LEMAH'] . ',
                        ' . $achievementCounts['SANGAT LEMAH'] . '
                    ],
                    backgroundColor: [
                        "#28a745",
                        "#17a2b8",
                        "#ffc107",
                        "#fd7e14",
                        "#dc3545"
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "right"
                    }
                }
            }
        });
    </script>
</body>
</html>';
            
            // Save unit report to file
            file_put_contents($reportPath, $unitHtml);
            $successCount++;
            
        } catch (Exception $e) {
            $errors[] = "Error generating report for $unit: " . $e->getMessage();
        }
    }
    
    // Complete the index HTML
    $indexHtml .= '
        </div>
        
        <div class="text-center mt-5 mb-4">
            <hr>
            <p class="text-muted">
                Generated on ' . date('d/m/Y H:i:s') . ' | 
                Laporan ini dijana oleh EPP Anlitik KMPP | Develop by OneExa EduTech Solutions for KMPP
            </p>
        </div>
    </div>
</body>
</html>';

    // Save index file
    file_put_contents($outputDir . '/index.html', $indexHtml);
    
    // Show results page
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit Reports Generation Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Unit Reports Generation Results</h1>
        
        <div class="alert alert-info">
            <h4>Summary:</h4>
            <p>Successfully generated reports for ' . $successCount . ' units.</p>
            <p>' . count($errors) . ' errors occurred during generation.</p>
        </div>';
        
    if ($successCount > 0) {
        echo '<div class="card mb-4">
                <div class="card-header">
                    Access Unit Reports
                </div>
                <div class="card-body">
                    <p>Unit reports have been generated and organized by department/unit.</p>
                    <a href="' . $outputDir . '/index.html" class="btn btn-primary me-2" target="_blank">View Unit Reports</a>
                </div>
            </div>';
    }
    
    if (!empty($errors)) {
        echo '<div class="card mb-4">
                <div class="card-header text-white bg-danger">
                    Errors
                </div>
                <div class="card-body">
                    <ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>
                </div>
            </div>';
    }
    
    echo '<a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>';
    
    exit;
} else {
    // If accessed directly without POST data
    header("Location: index.php");
    exit;
}