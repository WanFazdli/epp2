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
    
    // Get unit mappings if provided
    $unitMappings = [];
    if (isset($_POST['unitMappings'])) {
        $unitMappings = json_decode($_POST['unitMappings'], true) ?: [];
    }
    
    // Get save to file option
    $saveToFile = isset($_POST['saveToFile']) && $_POST['saveToFile'] == '1';
    
    // Group teachers by unit
    $unitTeachers = [];
    foreach ($teacherData as $name => $data) {
        $unit = isset($data['unit']) ? $data['unit'] : 'Umum';
        if (!isset($unitTeachers[$unit])) {
            $unitTeachers[$unit] = [];
        }
        $unitTeachers[$unit][$name] = $data;
    }
    
    // Create output directory for unit reports
    $outputDir = 'unit_reports_' . date('Y-m-d_H-i-s');
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    
    // Create index.html to list all unit reports
    $indexHtml = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unit Reports Index</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <h1>Unit Evaluation Reports</h1>
            <p class="lead">SEMESTER ' . $semester . ' SESI ' . $session . '</p>
            <div class="list-group mt-4">';
    
    $generatedFiles = [];
    
    // Generate a report for each unit
    foreach ($unitTeachers as $unit => $unitTeacherData) {
        // Sort teachers within this unit by overall score
        uasort($unitTeacherData, function($a, $b) {
            $avgA = calculateOverallScore($a);
            $avgB = calculateOverallScore($b);
            return $avgB <=> $avgA; // Descending order
        });
        
        // Generate the report for this unit
        $reportTitle = "LAPORAN RINGKASAN PENILAIAN ePP PENSYARAH KMPP UNIT: " . htmlspecialchars($unit) . " SEMESTER $semester SESI $session";
        $htmlContent = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $reportTitle . '</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    padding: 20px;
                    font-size: 14px;
                }
                .report-title {
                    text-align: center;
                    margin-bottom: 20px;
                    font-weight: bold;
                }
                .table {
                    font-size: 12px;
                }
                .table th {
                    background-color: #f2f2f2;
                    text-align: center;
                    vertical-align: middle;
                }
                .table td {
                    text-align: center;
                    vertical-align: middle;
                }
                .name-column {
                    text-align: left !important;
                }
                .excellent {
                    background-color: #e6ffe6 !important;
                }
                .good {
                    background-color: #ffffcc !important;
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
                    .table {
                        font-size: 10px;
                    }
                    body {
                        padding: 0;
                        margin: 0;
                    }
                }
                .score-section {
                    font-weight: bold;
                }
                .table-responsive {
                    overflow-x: auto;
                }
                .column-legend {
                    font-size: 12px;
                    margin-top: 20px;
                }
                .print-button {
                    margin-bottom: 20px;
                }
                .table-striped>tbody>tr.excellent:nth-child(odd)>* {
                    background-color: #d9f2d9 !important;
                }
                .table-striped>tbody>tr.good:nth-child(odd)>* {
                    background-color: #ffffbb !important;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    font-size: 12px;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="container-fluid">
                <div class="row no-print print-button">
                    <div class="col-12">
                        <button onclick="window.print();" class="btn btn-primary">Print Report</button>
                        <a href="../index.php" class="btn btn-secondary ms-2">Back to Home</a>
                        <a href="index.html" class="btn btn-info ms-2">Back to Unit List</a>
                    </div>
                </div>
                
                <h2 class="report-title">' . $reportTitle . '</h2>
                <div class="print-only">
                    <p class="text-center">Generated on: ' . date('d-m-Y H:i:s') . '</p>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th rowspan="2">Nama Pensyarah</th>
                                <th rowspan="2">Kod Subjek</th>
                                <th rowspan="2">Bilangan<br>Pelajar</th>
                                <th colspan="3">Profesionalisme</th>
                                <th colspan="7">Pengajaran dan Pembelajaran</th>
                                <th colspan="3">Pelaksanaan PdP dalam BI</th>
                                <th rowspan="2">Purata<br>A</th>
                                <th rowspan="2">Purata<br>B</th>
                                <th rowspan="2">Purata<br>C</th>
                                <th rowspan="2">Purata<br>Keseluruhan</th>
                                <th rowspan="2">Tahap<br>Pencapaian</th>
                            </tr>
                            <tr>
                                <th>A1</th>
                                <th>A2</th>
                                <th>A3</th>
                                <th>B4</th>
                                <th>B5</th>
                                <th>B6</th>
                                <th>B7</th>
                                <th>B8</th>
                                <th>B9</th>
                                <th>B10</th>
                                <th>C11</th>
                                <th>C12</th>
                                <th>C13</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        // Add data rows for this unit
        foreach ($unitTeacherData as $name => $data) {
            // Calculate averages
            $avgA = round($data['scores']['professionalism']['total'], 1);
            $avgB = round($data['scores']['teaching']['total'], 1);
            $avgC = round($data['scores']['english']['total'], 1);
            $avgAll = calculateOverallScore($data);
            
            // Get achievement level
            $achievementLevel = getAchievementLevel($avgAll);
            
            // Row class based on achievement level
            $rowClass = '';
            if ($achievementLevel == 'CEMERLANG') {
                $rowClass = 'excellent';
            } elseif ($achievementLevel == 'BAIK') {
                $rowClass = 'good';
            }
            
            $htmlContent .= '
                        <tr class="' . $rowClass . '">
                            <td class="name-column">' . htmlspecialchars($name) . '</td>
                            <td>' . htmlspecialchars($data['course']) . '</td>
                            <td>' . $data['responses'] . '</td>
                            <td>' . $data['scores']['professionalism']['items']['appearance'] . '</td>
                            <td>' . $data['scores']['professionalism']['items']['punctuality'] . '</td>
                            <td>' . $data['scores']['professionalism']['items']['care'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['knowledge'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['understanding'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['materials'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['methods'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['exercises'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['online_learning'] . '</td>
                            <td>' . $data['scores']['teaching']['items']['online_materials'] . '</td>
                            <td align="center">' . (isset($data['scores']['english']['items']['teaching']) ? $data['scores']['english']['items']['teaching'] : '-') . '</td>
                            <td align="center">' . (isset($data['scores']['english']['items']['materials']) ? $data['scores']['english']['items']['materials'] : '-') . '</td>
                            <td align="center">' . (isset($data['scores']['english']['items']['discussion']) ? $data['scores']['english']['items']['discussion'] : '-') . '</td>
                            <td class="score-section">' . $avgA . '</td>
                            <td class="score-section">' . $avgB . '</td>
                            <td class="score-section">' . $avgC . '</td>
                            <td class="score-section">' . $avgAll . '</td>
                            <td class="score-section">' . $achievementLevel . '</td>
                        </tr>';
        }
        
        // Calculate overall unit averages for the footer
        $unitAvgA = 0;
        $unitAvgB = 0;
        $unitAvgC = 0;
        $unitAvgAll = 0;
        $totalTeachers = count($unitTeacherData);
        
        foreach ($unitTeacherData as $data) {
            $unitAvgA += $data['scores']['professionalism']['total'];
            $unitAvgB += $data['scores']['teaching']['total'];
            $unitAvgC += $data['scores']['english']['total'];
            $unitAvgAll += calculateOverallScore($data);
        }
        
        $unitAvgA = round($unitAvgA / $totalTeachers, 1);
        $unitAvgB = round($unitAvgB / $totalTeachers, 1);
        $unitAvgC = round($unitAvgC / $totalTeachers, 1);
        $unitAvgAll = round($unitAvgAll / $totalTeachers, 1);
        
        $achievementLevelAvg = getAchievementLevel($unitAvgAll);
        
        // Add average row at the bottom
        $htmlContent .= '
                        <tr class="table-secondary">
                            <td colspan="3" class="text-end fw-bold">PURATA KESELURUHAN</td>
                            <td colspan="3"></td>
                            <td colspan="7"></td>
                            <td colspan="3"></td>
                            <td class="score-section fw-bold">' . $unitAvgA . '</td>
                            <td class="score-section fw-bold">' . $unitAvgB . '</td>
                            <td class="score-section fw-bold">' . $unitAvgC . '</td>
                            <td class="score-section fw-bold">' . $unitAvgAll . '</td>
                            <td class="score-section fw-bold">' . $achievementLevelAvg . '</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-4">
                        <h5>Julat Skor dan Tahap Pencapaian</h5>
                        <table class="table table-sm table-bordered">
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
                    
                    <div class="col-md-8">
                        <h5>Keterangan Kolum</h5>
                        <div class="row column-legend">
                            <div class="col-md-6">
                                <p><strong>A1:</strong> Penampilan pensyarah menarik minat</p>
                                <p><strong>A2:</strong> Pensyarah menepati waktu</p>
                                <p><strong>A3:</strong> Pensyarah mengambil berat</p>
                                <p><strong>B4:</strong> Pensyarah berpengetahuan luas</p>
                                <p><strong>B5:</strong> Saya faham isi kandungan kursus</p>
                                <p><strong>B6:</strong> Bahan bantu mengajar membantu</p>
                                <p><strong>B7:</strong> Kaedah pengajaran membantu</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>B8:</strong> Latihan/tugasan/kuiz menguji</p>
                                <p><strong>B9:</strong> Pembelajaran dalam talian membantu</p>
                                <p><strong>B10:</strong> Bahan PdP mudah diakses</p>
                                <p><strong>C11:</strong> Mengajar dalam B. Inggeris</p>
                                <p><strong>C12:</strong> Bahan pengajaran dalam B. Inggeris</p>
                                <p><strong>C13:</strong> Mendorong perbincangan B. Inggeris</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="footer">
                    <p>Laporan ini dijana oleh Sistem Penilaian ePP Pensyarah KMPP</p>
					<p>EPP Analytics © | Developed by OneExa EduTech Solutions for Kolej Matrikulasi Pulau Pinang</p>
                    <p>Tarikh: ' . date('d/m/Y') . '</p>
                </div>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>';
        
        // Save this unit's report to a file
        $safeUnitName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $unit);
        $fileName = $safeUnitName . '_report.html';
        $filePath = $outputDir . '/' . $fileName;
        file_put_contents($filePath, $htmlContent);
        
        // Add to index
        $generatedFiles[] = [
            'unit' => $unit,
            'file' => $fileName,
            'teacherCount' => count($unitTeacherData)
        ];
        
        $indexHtml .= '<a href="' . $fileName . '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span>' . htmlspecialchars($unit) . '</span>
                <span class="badge bg-primary rounded-pill">' . count($unitTeacherData) . ' teachers</span>
            </a>';
    }
    
    // Complete the index.html file
    $indexHtml .= '
            </div>
            <div class="mt-4">
                <a href="../index.php" class="btn btn-secondary">Back to Home</a>
            </div>
            <div class="mt-5 text-center">
                <p class="text-muted">Generated on: ' . date('d-m-Y H:i:s') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Save the index file
    file_put_contents($outputDir . '/index.html', $indexHtml);
    
    // Redirect to the index file
    echo '<script>window.location.href = "' . $outputDir . '/index.html";</script>';
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
    // This is the key part - we only count sections with non-zero scores
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
 * Overriding the generatePdfContent function to handle variable question counts
 * Use this in your generate_pdf.php file
 */
function generatePdfContent($teacherData, $semester = '2', $session = '2023/2024', $collegeAverage = '9.9') {
    // Calculate overall average score using the corrected function
    $overallScore = calculateOverallScore($teacherData);
    
    $achievementLevel = getAchievementLevel($overallScore);
    
    // Start building the HTML
    $html = '
    <style>
        /* Your existing CSS styles */
    </style>
    
    <h1>LAPORAN INDIVIDU PENILAIAN ePP PENSYARAH KMPP<br>SEMESTER ' . $semester . ' SESI ' . $session . '</h1>
    
    <p>TUAN/PUAN,</p>
    <p>Berikut adalah keputusan e-Penilaian Pensyarah (ePP)'.$department.' oleh pelajar yang telah dilaksanakan pada Semester ' . $semester . ' sesi ' . $session . ' di Kolej Matrikulasi Pulau Pinang.</p>
    
    <table class="info-table">
        <tr>
            <td width="30%">Nama Pensyarah</td>
            <td width="70%">: ' . htmlspecialchars($teacherData['name']) . '</td>
        </tr>
        <tr>
            <td>Kursus</td>
            <td>: ' . htmlspecialchars($teacherData['course']) . '</td>
        </tr>
        <tr>
            <td>Skor Purata Penilaian Pertama Pensyarah</td>
            <td>: ' . $overallScore . '</td>
        </tr>
        <tr>
            <td>Skor Purata Kolej</td>
            <td>: ' . $collegeAverage . '</td>
        </tr>
        <tr>
            <td>Bilangan Respon Pelajar</td>
            <td>: ' . $teacherData['responses'] . '</td>
        </tr>
        <tr>
            <td>Peratus Pelajar Menilai</td>
            <td>: 100%</td>
        </tr>
    </table>
    
    <table class="score-table">
        <tr>
            <th width="10%">BIL</th>
            <th width="70%">ITEM</th>
            <th width="20%">SKOR</th>
        </tr>
        <tr class="section-header">
            <td>A</td>
            <td class="category-header">Profesionalisme</td>
            <td>' . $teacherData['scores']['professionalism']['total'] . '</td>
        </tr>';
    
    // Add professionalism items
    foreach ($teacherData['scores']['professionalism']['items'] as $key => $value) {
        $itemLabel = '';
        switch ($key) {
            case 'appearance':
                $itemLabel = 'Penampilan pensyarah menarik minat saya untuk belajar.';
                break;
            case 'punctuality':
                $itemLabel = 'Pensyarah menepati waktu yang ditetapkan.';
                break;
            case 'care':
                $itemLabel = 'Pensyarah mengambil berat terhadap pembelajaran saya.';
                break;
        }
        
        $html .= '
        <tr>
            <td></td>
            <td>' . $itemLabel . '</td>
            <td>' . $value . '</td>
        </tr>';
    }
    
    $html .= '
        <tr class="section-footer">
            <td></td>
            <td><strong>Skor Purata Bahagian A</strong></td>
            <td><strong>' . $teacherData['scores']['professionalism']['total'] . '</strong></td>
        </tr>
        <tr class="section-header">
            <td>B</td>
            <td class="category-header">Pengajaran Dan Pembelajaran</td>
            <td>' . $teacherData['scores']['teaching']['total'] . '</td>
        </tr>';
    
    // Add teaching items
    foreach ($teacherData['scores']['teaching']['items'] as $key => $value) {
        $itemLabel = '';
        switch ($key) {
            case 'knowledge':
                $itemLabel = 'Pensyarah mempunyai pengetahuan yang luas tentang kursus yang diajar.';
                break;
            case 'understanding':
                $itemLabel = 'Saya faham isi kandungan kursus yang disampaikan oleh pensyarah.';
                break;
            case 'materials':
                $itemLabel = 'Bahan bantu mengajar pensyarah seperti nota / bahan aktiviti meningkatkan pemahaman saya.';
                break;
            case 'methods':
                $itemLabel = 'Kaedah pengajaran pensyarah saya membantu saya memahami isi kandungan kursus.';
                break;
            case 'exercises':
                $itemLabel = 'Latihan/tugasan/kuiz yang dilaksanakan di dalam kelas oleh pensyarah menguji pemahaman saya.';
                break;
            case 'online_learning':
                $itemLabel = 'Pembelajaran dalam talian (online) yang dilaksanakan oleh pensyarah membantu proses pembelajaran saya.';
                break;
            case 'online_materials':
                $itemLabel = 'Bahan PdP dalam talian dapat diakses dengan mudah.';
                break;
        }
        
        $html .= '
        <tr>
            <td></td>
            <td>' . $itemLabel . '</td>
            <td>' . $value . '</td>
        </tr>';
    }
    
    $html .= '
        <tr class="section-footer">
            <td></td>
            <td><strong>Skor Purata Bahagian B</strong></td>
            <td><strong>' . $teacherData['scores']['teaching']['total'] . '</strong></td>
        </tr>';
    
    // Only add English section if it exists
    if (isset($teacherData['scores']['has_english']) && $teacherData['scores']['has_english']) {
        $html .= '
        <tr class="section-header">
            <td>C</td>
            <td class="category-header">Pelaksanaan PdP dalam Bahasa Inggeris</td>
            <td>' . $teacherData['scores']['english']['total'] . '</td>
        </tr>';
        
        // Add English items
        foreach ($teacherData['scores']['english']['items'] as $key => $value) {
            $itemLabel = '';
            switch ($key) {
                case 'teaching':
                    $itemLabel = 'Pensyarah saya mengajar dalam bahasa Inggeris sepenuhnya.';
                    break;
                case 'materials':
                    $itemLabel = 'Pensyarah saya menyediakan bahan pengajaran dalam bahasa Inggeris.';
                    break;
                case 'discussion':
                    $itemLabel = 'Pensyarah mendorong saya membuat perbincangan dalam Bahasa Inggeris.';
                    break;
            }
            
            $html .= '
            <tr>
                <td></td>
                <td>' . $itemLabel . '</td>
                <td>' . $value . '</td>
            </tr>';
        }
        
        $html .= '
            <tr class="section-footer">
                <td></td>
                <td><strong>Skor Purata Bahagian C</strong></td>
                <td><strong>' . $teacherData['scores']['english']['total'] . '</strong></td>
            </tr>';
    }
    
    // Final overall score
    $html .= '
        <tr class="final-row">
            <td colspan="2" class="category-header">Skor Purata</td>
            <td>' . $overallScore . '</td>
        </tr>
        <tr class="final-row">
            <td colspan="2" class="category-header">Tahap Pencapaian Skor</td>
            <td>' . $achievementLevel . '</td>
        </tr>
    </table>';
    
    // Rest of your HTML template...
    $html .= '
    <div>
        <h3>Ulasan Keseluruhan/Cadangan Penambahbaikan</h3>
        <div class="comment-section">';
    
    if (count($teacherData['comments']) > 0) {
        // Pick one random comment
        $randomIndex = array_rand($teacherData['comments']);
        $randomComment = $teacherData['comments'][$randomIndex];
        $html .= '<p>Pelajar: ' . htmlspecialchars($randomComment) . '</p>';
    } else {
        $html .= '<p>Tiada ulasan.</p>';
    }
    
    $html .= '
        </div>
    </div>';
    
    // Add the rest of your template here...
    
    return $html;
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
?>