<?php
// generate_college_report.php

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['data'])) {
    $teacherData = json_decode($_POST['data'], true);
    $semester = $_POST['semester'] ?? '2';
    $session = $_POST['session'] ?? '2023/2024';
    $unitMappings = isset($_POST['unitMappings']) ? json_decode($_POST['unitMappings'], true) : [];
    
    // Generate the HTML content - now we'll calculate college average within the function
    $htmlContent = generateCollegeReportHTML($teacherData, $semester, $session, $unitMappings);
    
    // Save the HTML to a file
    $filename = 'college_report.html';
    file_put_contents($filename, $htmlContent);
    
    // Redirect to the generated report
    header('Location: ' . $filename);
    exit;
}

function generateCollegeReportHTML($teacherData, $semester, $session, $unitMappings) {
    // Calculate statistics
    $totalTeachers = count($teacherData);
    $totalResponses = 0;
    $unitStats = [];
    $achievementCounts = ['CEMERLANG' => 0, 'BAIK' => 0, 'SEDERHANA' => 0, 'LEMAH' => 0, 'SANGAT LEMAH' => 0];
    $topPerformers = [];
    $totalCollegeScore = 0; // New variable to track total score for college average
    
    // Calculate unit-wise detailed statistics
    foreach ($teacherData as $teacher) {
        $totalResponses += $teacher['responses'];
        $unit = $teacher['unit'];
        
        if (!isset($unitStats[$unit])) {
            $unitStats[$unit] = [
                'teacherCount' => 0,
                'totalResponses' => 0,
                'teachersWithEnglish' => 0,
                'scores' => [
                    'professionalism' => [
                        'total' => 0,
                        'appearance' => 0,
                        'punctuality' => 0,
                        'care' => 0
                    ],
                    'teaching' => [
                        'total' => 0,
                        'knowledge' => 0,
                        'understanding' => 0,
                        'materials' => 0,
                        'methods' => 0,
                        'exercises' => 0,
                        'online_learning' => 0,
                        'online_materials' => 0
                    ],
                    'english' => [
                        'total' => 0,
                        'teaching' => 0,
                        'materials' => 0,
                        'discussion' => 0
                    ],
                    'overall' => 0
                ]
            ];
        }
        
        $unitStats[$unit]['teacherCount']++;
        $unitStats[$unit]['totalResponses'] += $teacher['responses'];
        
        // Calculate overall score
        $overallScore = calculateOverallScore($teacher);
        
        // Add to college total for average calculation
        $totalCollegeScore += $overallScore;
        
        // Track achievement distribution
        $achievementLevel = getAchievementLevel($overallScore);
        $achievementCounts[$achievementLevel]++;
        
        // Add to top performers list
        $topPerformers[] = [
            'name' => $teacher['name'],
            'unit' => $unit,
            'course' => $teacher['course'],
            'score' => $overallScore,
            'responses' => $teacher['responses']
        ];
        
        // Accumulate scores
        $unitStats[$unit]['scores']['professionalism']['total'] += $teacher['scores']['professionalism']['total'];
        $unitStats[$unit]['scores']['professionalism']['appearance'] += $teacher['scores']['professionalism']['items']['appearance'];
        $unitStats[$unit]['scores']['professionalism']['punctuality'] += $teacher['scores']['professionalism']['items']['punctuality'];
        $unitStats[$unit]['scores']['professionalism']['care'] += $teacher['scores']['professionalism']['items']['care'];
        
        $unitStats[$unit]['scores']['teaching']['total'] += $teacher['scores']['teaching']['total'];
        $unitStats[$unit]['scores']['teaching']['knowledge'] += $teacher['scores']['teaching']['items']['knowledge'];
        $unitStats[$unit]['scores']['teaching']['understanding'] += $teacher['scores']['teaching']['items']['understanding'];
        $unitStats[$unit]['scores']['teaching']['materials'] += $teacher['scores']['teaching']['items']['materials'];
        $unitStats[$unit]['scores']['teaching']['methods'] += $teacher['scores']['teaching']['items']['methods'];
        $unitStats[$unit]['scores']['teaching']['exercises'] += $teacher['scores']['teaching']['items']['exercises'];
        $unitStats[$unit]['scores']['teaching']['online_learning'] += $teacher['scores']['teaching']['items']['online_learning'];
        $unitStats[$unit]['scores']['teaching']['online_materials'] += $teacher['scores']['teaching']['items']['online_materials'];
        
        // Check if teacher has English scores
        $hasEnglishScores = isset($teacher['scores']['english']) && 
                           (($teacher['scores']['english']['total'] ?? 0) > 0 || 
                            ($teacher['scores']['english']['items']['teaching'] ?? 0) > 0 || 
                            ($teacher['scores']['english']['items']['materials'] ?? 0) > 0 || 
                            ($teacher['scores']['english']['items']['discussion'] ?? 0) > 0);
        
        if ($hasEnglishScores) {
            $unitStats[$unit]['teachersWithEnglish']++;
            $unitStats[$unit]['scores']['english']['total'] += $teacher['scores']['english']['total'];
            $unitStats[$unit]['scores']['english']['teaching'] += $teacher['scores']['english']['items']['teaching'];
            $unitStats[$unit]['scores']['english']['materials'] += $teacher['scores']['english']['items']['materials'];
            $unitStats[$unit]['scores']['english']['discussion'] += $teacher['scores']['english']['items']['discussion'];
        }
        
        $unitStats[$unit]['scores']['overall'] += $overallScore;
    }
    
    // Calculate college average
    $collegeAverage = $totalTeachers > 0 ? round($totalCollegeScore / $totalTeachers, 1) : 0;
    
    // Calculate averages for each unit
    foreach ($unitStats as $unit => &$stats) {
        $teacherCount = $stats['teacherCount'];
        $teachersWithEnglish = $stats['teachersWithEnglish'];
        
        // Professionalism averages
        $stats['scores']['professionalism']['total'] = round($stats['scores']['professionalism']['total'] / $teacherCount, 1);
        $stats['scores']['professionalism']['appearance'] = round($stats['scores']['professionalism']['appearance'] / $teacherCount, 1);
        $stats['scores']['professionalism']['punctuality'] = round($stats['scores']['professionalism']['punctuality'] / $teacherCount, 1);
        $stats['scores']['professionalism']['care'] = round($stats['scores']['professionalism']['care'] / $teacherCount, 1);
        
        // Teaching averages
        $stats['scores']['teaching']['total'] = round($stats['scores']['teaching']['total'] / $teacherCount, 1);
        $stats['scores']['teaching']['knowledge'] = round($stats['scores']['teaching']['knowledge'] / $teacherCount, 1);
        $stats['scores']['teaching']['understanding'] = round($stats['scores']['teaching']['understanding'] / $teacherCount, 1);
        $stats['scores']['teaching']['materials'] = round($stats['scores']['teaching']['materials'] / $teacherCount, 1);
        $stats['scores']['teaching']['methods'] = round($stats['scores']['teaching']['methods'] / $teacherCount, 1);
        $stats['scores']['teaching']['exercises'] = round($stats['scores']['teaching']['exercises'] / $teacherCount, 1);
        $stats['scores']['teaching']['online_learning'] = round($stats['scores']['teaching']['online_learning'] / $teacherCount, 1);
        $stats['scores']['teaching']['online_materials'] = round($stats['scores']['teaching']['online_materials'] / $teacherCount, 1);
        
        // English averages (only for teachers with English scores)
        if ($teachersWithEnglish > 0) {
            $stats['scores']['english']['total'] = round($stats['scores']['english']['total'] / $teachersWithEnglish, 1);
            $stats['scores']['english']['teaching'] = round($stats['scores']['english']['teaching'] / $teachersWithEnglish, 1);
            $stats['scores']['english']['materials'] = round($stats['scores']['english']['materials'] / $teachersWithEnglish, 1);
            $stats['scores']['english']['discussion'] = round($stats['scores']['english']['discussion'] / $teachersWithEnglish, 1);
        } else {
            $stats['scores']['english']['total'] = '-';
            $stats['scores']['english']['teaching'] = '-';
            $stats['scores']['english']['materials'] = '-';
            $stats['scores']['english']['discussion'] = '-';
        }
        
        // Overall average
        $stats['scores']['overall'] = round($stats['scores']['overall'] / $teacherCount, 1);
    }
    
    // Sort top performers
    usort($topPerformers, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    $topPerformers = array_slice($topPerformers, 0, 10);
    
    // Generate HTML
    $html = generateHTMLContent($totalTeachers, $totalResponses, $unitStats, $achievementCounts, $topPerformers, $semester, $session, $collegeAverage);
    
    return $html;
}

// Rest of the code remains the same...

function generateHTMLContent($totalTeachers, $totalResponses, $unitStats, $achievementCounts, $topPerformers, $semester, $session, $collegeAverage) {
    // Reuse the HTML template from the college-report artifact, but with dynamic data
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Evaluation Dashboard - Semester ' . $semester . ' Session ' . $session . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .dashboard-header {
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .stats-card:hover { transform: translateY(-3px); }
        .stat-value { font-size: 2rem; font-weight: bold; color: #0d6efd; }
        .stat-label { color: #6c757d; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .achievement-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        .achievement-cemerlang { background-color: #198754; color: white; }
        .achievement-baik { background-color: #0d6efd; color: white; }
        .achievement-sederhana { background-color: #ffc107; color: #000; }
        .achievement-lemah { background-color: #fd7e14; color: white; }
        .achievement-sangatlemah { background-color: #dc3545; color: white; }
        .unit-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .unit-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            font-weight: 600;
        }
        .chart-container { position: relative; height: 400px; width: 100%; }
        .table th { background-color: #f8f9fa; }
        .compare-badge { font-size: 0.8rem; margin-left: 0.5rem; }
        .above-average { color: #198754; }
        .below-average { color: #dc3545; }
        .table-bordered > :not(caption) > * > * {
            border-width: 1px;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .score-cell {
            text-align: center;
            font-weight: bold;
        }
        .category-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .summary-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="dashboard-header text-center">
            <h1 class="mb-3">LAPORAN PENILAIAN ePP PENSYARAH</h1>
            <h2>Kolej Matrikulasi Pulau Pinang</h2>
            <h3>Semester ' . $semester . ' Sesi ' . $session . '</h3>
        </div>
        
        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value">' . $totalTeachers . '</div>
                    <div class="stat-label">Total Pensyarah</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value">' . $collegeAverage . '</div>
                    <div class="stat-label">Purata Kolej</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value">' . $totalResponses . '</div>
                    <div class="stat-label">Jumlah Penilaian</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stat-value">' . count($unitStats) . '</div>
                    <div class="stat-label">Jumlah Unit</div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Unit Scores Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Skor Terperinci bagi Setiap Unit</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th rowspan="2" class="align-middle">Unit</th>
                                <th rowspan="2" class="align-middle text-center">Bil. Pensyarah</th>
                                <th rowspan="2" class="align-middle text-center">Bil. Respons</th>
                                <th colspan="3" class="text-center category-header">Profesionalisme</th>
                                <th colspan="7" class="text-center category-header">Pengajaran & Pembelajaran</th>
                                <th colspan="3" class="text-center category-header">Pelaksanaan PdP dalam BI</th>
                                <th rowspan="2" class="align-middle text-center">Skor Keseluruhan</th>
                            </tr>
                            <tr>
                                <th class="score-cell">Penampilan</th>
                                <th class="score-cell">Masa</th>
                                <th class="score-cell">Prihatin</th>
                                <th class="score-cell">Pengetahuan</th>
                                <th class="score-cell">Pemahaman</th>
                                <th class="score-cell">Bahan</th>
                                <th class="score-cell">Kaedah</th>
                                <th class="score-cell">Latihan</th>
                                <th class="score-cell">e-Pembelajaran</th>
                                <th class="score-cell">Bahan Online</th>
                                <th class="score-cell">Pengajaran</th>
                                <th class="score-cell">Bahan</th>
                                <th class="score-cell">Perbincangan</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    $summaryStats = [
        'appearance' => 0,
        'punctuality' => 0,
        'care' => 0,
        'knowledge' => 0,
        'understanding' => 0,
        'materials' => 0,
        'methods' => 0,
        'exercises' => 0,
        'online_learning' => 0,
        'online_materials' => 0,
        'english_teaching' => 0,
        'english_materials' => 0,
        'english_discussion' => 0,
        'overall' => 0
    ];
    
    $unitCount = count($unitStats);
    $unitsWithEnglish = 0;
    
    foreach ($unitStats as $unit => $stats) {
        $hasEnglishScores = ($stats['scores']['english']['teaching'] !== '-');
        
        $html .= '<tr>
            <td>' . htmlspecialchars($unit) . '</td>
            <td class="text-center">' . $stats['teacherCount'] . '</td>
            <td class="text-center">' . $stats['totalResponses'] . '</td>
            <td class="score-cell">' . $stats['scores']['professionalism']['appearance'] . '</td>
            <td class="score-cell">' . $stats['scores']['professionalism']['punctuality'] . '</td>
            <td class="score-cell">' . $stats['scores']['professionalism']['care'] . '</td>
            <td class="score-cell">' . $stats['scores']['teaching']['knowledge'] . '</td>
            <td class="score-cell">' . $stats['scores']['teaching']['understanding'] . '</td>
            <td class="score-cell">' . $stats['scores']['teaching']['materials'] . '</td>
            <td class="score-cell">' . $stats['scores']['teaching']['methods'] . '</td>
            <td class="score-cell">' . $stats['scores']['teaching']['exercises'] . '</td>
            <td class="score-cell">' . $stats['scores']['teaching']['online_learning'] . '</td>
            <td class="score-cell">' . $stats['scores']['teaching']['online_materials'] . '</td>
            <td class="score-cell">' . $stats['scores']['english']['teaching'] . '</td>
            <td class="score-cell">' . $stats['scores']['english']['materials'] . '</td>
            <td class="score-cell">' . $stats['scores']['english']['discussion'] . '</td>
            <td class="score-cell">' . $stats['scores']['overall'] . '</td>
        </tr>';
        
        // Add to summary
        $summaryStats['appearance'] += $stats['scores']['professionalism']['appearance'];
        $summaryStats['punctuality'] += $stats['scores']['professionalism']['punctuality'];
        $summaryStats['care'] += $stats['scores']['professionalism']['care'];
        $summaryStats['knowledge'] += $stats['scores']['teaching']['knowledge'];
        $summaryStats['understanding'] += $stats['scores']['teaching']['understanding'];
        $summaryStats['materials'] += $stats['scores']['teaching']['materials'];
        $summaryStats['methods'] += $stats['scores']['teaching']['methods'];
        $summaryStats['exercises'] += $stats['scores']['teaching']['exercises'];
        $summaryStats['online_learning'] += $stats['scores']['teaching']['online_learning'];
        $summaryStats['online_materials'] += $stats['scores']['teaching']['online_materials'];
        
        if ($hasEnglishScores) {
            $unitsWithEnglish++;
            $summaryStats['english_teaching'] += (float)$stats['scores']['english']['teaching'];
            $summaryStats['english_materials'] += (float)$stats['scores']['english']['materials'];
            $summaryStats['english_discussion'] += (float)$stats['scores']['english']['discussion'];
        }
        
        $summaryStats['overall'] += $stats['scores']['overall'];
    }
    
    // Calculate average for summary
    if ($unitCount > 0) {
        $summaryStats['appearance'] = round($summaryStats['appearance'] / $unitCount, 1);
        $summaryStats['punctuality'] = round($summaryStats['punctuality'] / $unitCount, 1);
        $summaryStats['care'] = round($summaryStats['care'] / $unitCount, 1);
        $summaryStats['knowledge'] = round($summaryStats['knowledge'] / $unitCount, 1);
        $summaryStats['understanding'] = round($summaryStats['understanding'] / $unitCount, 1);
        $summaryStats['materials'] = round($summaryStats['materials'] / $unitCount, 1);
        $summaryStats['methods'] = round($summaryStats['methods'] / $unitCount, 1);
        $summaryStats['exercises'] = round($summaryStats['exercises'] / $unitCount, 1);
        $summaryStats['online_learning'] = round($summaryStats['online_learning'] / $unitCount, 1);
        $summaryStats['online_materials'] = round($summaryStats['online_materials'] / $unitCount, 1);
        $summaryStats['overall'] = round($summaryStats['overall'] / $unitCount, 1);
        
        if ($unitsWithEnglish > 0) {
            $summaryStats['english_teaching'] = round($summaryStats['english_teaching'] / $unitsWithEnglish, 1);
            $summaryStats['english_materials'] = round($summaryStats['english_materials'] / $unitsWithEnglish, 1);
            $summaryStats['english_discussion'] = round($summaryStats['english_discussion'] / $unitsWithEnglish, 1);
        } else {
            $summaryStats['english_teaching'] = '-';
            $summaryStats['english_materials'] = '-';
            $summaryStats['english_discussion'] = '-';
        }
    }
    
    // Add summary row
    $html .= '<tr class="summary-row">
            <td colspan="3">PURATA KESELURUHAN</td>
            <td class="score-cell">' . $summaryStats['appearance'] . '</td>
            <td class="score-cell">' . $summaryStats['punctuality'] . '</td>
            <td class="score-cell">' . $summaryStats['care'] . '</td>
            <td class="score-cell">' . $summaryStats['knowledge'] . '</td>
            <td class="score-cell">' . $summaryStats['understanding'] . '</td>
            <td class="score-cell">' . $summaryStats['materials'] . '</td>
            <td class="score-cell">' . $summaryStats['methods'] . '</td>
            <td class="score-cell">' . $summaryStats['exercises'] . '</td>
            <td class="score-cell">' . $summaryStats['online_learning'] . '</td>
            <td class="score-cell">' . $summaryStats['online_materials'] . '</td>
            <td class="score-cell">' . $summaryStats['english_teaching'] . '</td>
            <td class="score-cell">' . $summaryStats['english_materials'] . '</td>
            <td class="score-cell">' . $summaryStats['english_discussion'] . '</td>
            <td class="score-cell">' . $summaryStats['overall'] . '</td>
        </tr>';
    
    $html .= '</tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Achievement Distribution Table -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Taburan Pencapaian</h4>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tahap Pencapaian</th>
                            <th>Julat Skor</th>
                            <th>Bilangan Pensyarah</th>
                            <th>Peratus</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($achievementCounts as $level => $count) {
        $percentage = round(($count / $totalTeachers) * 100, 1);
        $scoreRange = getScoreRange($level);
        $badgeClass = 'achievement-' . strtolower(str_replace(' ', '', $level));
        
        $html .= '<tr>
            <td><span class="achievement-badge ' . $badgeClass . '">' . $level . '</span></td>
            <td>' . $scoreRange . '</td>
            <td>' . $count . '</td>
            <td>' . $percentage . '%</td>
        </tr>';
    }
    
    $html .= '</tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Performers -->
        <div class="card mt-4">
            <div class="card-header">
                <h4 class="mb-0">10 Pensyarah Terbaik</h4>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kedudukan</th>
                            <th>Nama Pensyarah</th>
                            <th>Unit</th>
                            <th>Kursus</th>
                            <th>Skor</th>
                            <th>Respons</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($topPerformers as $i => $teacher) {
        $html .= '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . htmlspecialchars($teacher['name']) . '</td>
            <td>' . htmlspecialchars($teacher['unit']) . '</td>
            <td>' . htmlspecialchars($teacher['course']) . '</td>
            <td>' . $teacher['score'] . '</td>
            <td>' . $teacher['responses'] . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
    
    return $html;
}

function getScoreRange($level) {
    switch ($level) {
        case 'CEMERLANG': return '9.00 - 10.00';
        case 'BAIK': return '7.00 - 8.99';
        case 'SEDERHANA': return '5.00 - 6.99';
        case 'LEMAH': return '3.00 - 4.99';
        case 'SANGAT LEMAH': return '1.00 - 2.99';
        default: return '';
    }
}

function calculateOverallScore($data) {
    $sections = 0;
    $totalScore = 0;
    
    if (isset($data['scores']['professionalism']['total']) && $data['scores']['professionalism']['total'] > 0) {
        $totalScore += $data['scores']['professionalism']['total'];
        $sections++;
    }
    
    if (isset($data['scores']['teaching']['total']) && $data['scores']['teaching']['total'] > 0) {
        $totalScore += $data['scores']['teaching']['total'];
        $sections++;
    }
    
    if (isset($data['scores']['english']['total']) && $data['scores']['english']['total'] > 0) {
        $totalScore += $data['scores']['english']['total'];
        $sections++;
    }
    
    if ($sections > 0) {
        return round($totalScore / $sections, 1);
    } else {
        return 0;
    }
}

function getAchievementLevel($score) {
    if ($score >= 9.0) return "CEMERLANG";
    if ($score >= 7.0) return "BAIK";
    if ($score >= 5.0) return "SEDERHANA";
    if ($score >= 3.0) return "LEMAH";
    return "SANGAT LEMAH";
}
?>